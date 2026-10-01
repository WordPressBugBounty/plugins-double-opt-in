<?php
/**
 * Can the sender domain of the confirmation mails be trusted by inboxes?
 *
 * The most common "the plugin is broken" report is a confirmation mail in
 * spam or never delivered, because the sender domain has no SPF or DMARC
 * record, or because the sender is a free-mail address the server may not
 * send for. This looks the records up over DNS — no external API — and says
 * what is missing.
 *
 * DKIM needs a selector only the mail provider knows, so it is not checked
 * and not claimed.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

use Forge12\DoubleOptIn\Frontend\SubmitNotice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SenderDomainCheck implements HealthCheckInterface {

	public const CACHE_KEY = 'f12_doi_sender_domain_dns';

	private const CACHE_TTL = 12 * 3600;

	/** @var callable(): string[] */
	private $senders;

	/** @var callable(string): (string[]|null) */
	private $txtLookup;

	/** @var callable(): bool */
	private $smtpActive;

	/**
	 * @param callable      $senders    Returns the sender addresses in use.
	 * @param callable|null $txtLookup  TXT records of a host, null when DNS is unavailable.
	 * @param callable|null $smtpActive Whether something routes wp_mail() elsewhere.
	 */
	public function __construct( callable $senders, ?callable $txtLookup = null, ?callable $smtpActive = null ) {
		$this->senders    = $senders;
		$this->txtLookup  = $txtLookup ?? array( self::class, 'dnsTxt' );
		$this->smtpActive = $smtpActive ?? array( self::class, 'smtpPluginActive' );
	}

	public function getId(): string {
		return 'f12_doi_sender_domain';
	}

	public function getLabel(): string {
		return __( 'Sender domain of the confirmation mail', 'double-opt-in' );
	}

	public function getPackage(): string {
		return 'core';
	}

	public function run(): HealthCheckResult {
		$domains = $this->senderDomains();
		if ( $domains === array() ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'No sender domain to check', 'double-opt-in' ),
				__( 'No active form has a sender address yet.', 'double-opt-in' ),
				'none'
			);
		}

		$smtp     = (bool) call_user_func( $this->smtpActive );
		$freeMail = array_values( array_intersect( $domains, array_keys( SubmitNotice::providers() ) ) );

		if ( $freeMail !== array() ) {
			return new HealthCheckResult(
				$smtp ? HealthCheckResult::STATUS_RECOMMENDED : HealthCheckResult::STATUS_CRITICAL,
				__( 'Confirmation mails are sent from a free-mail address', 'double-opt-in' ),
				sprintf(
					/* translators: %s: domain(s), e.g. gmail.com */
					__( 'The sender address uses %s. Your web server is not allowed to send mail for that domain, so Gmail, Outlook and others reject these mails or file them as spam. Use an address of your own domain as the sender in the form settings.', 'double-opt-in' ),
					implode( ', ', $freeMail )
				),
				'freemail:' . implode( ',', $freeMail ),
				__( 'Open the forms', 'double-opt-in' ),
				admin_url( 'admin.php?page=f12-doi-admin#/forms' )
			);
		}

		$records  = $this->records( $domains );
		$problems = array();
		$debug    = array();
		foreach ( $records as $domain => $found ) {
			if ( $found === null ) {
				$debug[] = $domain . ':unchecked';
				continue;
			}
			$missing = array();
			if ( ! $found['spf'] ) {
				$missing[] = 'SPF';
			}
			if ( ! $found['dmarc'] ) {
				$missing[] = 'DMARC';
			}
			$debug[] = $domain . ':' . ( $missing === array() ? 'ok' : 'no-' . strtolower( implode( '-', $missing ) ) );
			if ( $missing !== array() ) {
				$problems[] = sprintf(
					/* translators: 1: domain, 2: missing record types, e.g. "SPF, DMARC" */
					__( '%1$s has no %2$s record.', 'double-opt-in' ),
					$domain,
					implode( ', ', $missing )
				);
			}
		}

		if ( $problems === array() ) {
			$checked = count( array_filter( $records ) ) > 0;
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				$checked
					? __( 'The sender domain has SPF and DMARC records', 'double-opt-in' )
					: __( 'The sender domain could not be checked', 'double-opt-in' ),
				$checked
					? __( 'Inboxes can verify that your server may send for the sender domain. DKIM is set up at your mail provider and is not checked here.', 'double-opt-in' )
					: __( 'DNS lookups are not available on this server.', 'double-opt-in' ),
				implode( ' ', $debug )
			);
		}

		$description = implode( ' ', $problems ) . ' '
			. __( 'Without these records Gmail, Outlook and others cannot tell your confirmation mails from forged ones and file them as spam or reject them. Your host or domain provider can add them in the DNS settings.', 'double-opt-in' );
		if ( ! $smtp ) {
			$description .= ' ' . __( 'No SMTP plugin is active, so the mails leave through the web server. An SMTP plugin that sends through your mail provider usually fixes delivery as well.', 'double-opt-in' );
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_RECOMMENDED,
			__( 'The sender domain is missing records that inboxes check', 'double-opt-in' ),
			$description,
			implode( ' ', $debug )
		);
	}

	/**
	 * Lower-case domains of the valid sender addresses, unique.
	 *
	 * @return string[]
	 */
	private function senderDomains(): array {
		$domains = array();
		foreach ( (array) call_user_func( $this->senders ) as $sender ) {
			$sender = str_replace( '[_site_admin_email]', (string) get_bloginfo( 'admin_email' ), (string) $sender );
			$sender = SubmitNotice::senderAddress( $sender );
			if ( $sender === '' ) {
				continue;
			}
			$domains[] = strtolower( substr( $sender, (int) strrpos( $sender, '@' ) + 1 ) );
		}

		return array_values( array_unique( $domains ) );
	}

	/**
	 * SPF/DMARC presence per domain; null where DNS gave no answer.
	 *
	 * @param string[] $domains
	 *
	 * @return array<string, array{spf: bool, dmarc: bool}|null>
	 */
	private function records( array $domains ): array {
		sort( $domains );
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ( $cached['domains'] ?? null ) === $domains && isset( $cached['records'] ) ) {
			return $cached['records'];
		}

		$records = array();
		foreach ( $domains as $domain ) {
			$txt = call_user_func( $this->txtLookup, $domain );
			if ( ! is_array( $txt ) ) {
				$records[ $domain ] = null;
				continue;
			}
			$records[ $domain ] = array(
				'spf'   => self::has( $txt, 'v=spf1' ),
				'dmarc' => $this->hasDmarc( $domain ),
			);
		}

		set_transient(
			self::CACHE_KEY,
			array(
				'domains' => $domains,
				'records' => $records,
			),
			self::CACHE_TTL
		);

		return $records;
	}

	/**
	 * DMARC on the domain, or on its organisational domain (a sub-domain
	 * inherits the parent's policy).
	 */
	private function hasDmarc( string $domain ): bool {
		$hosts  = array( '_dmarc.' . $domain );
		$labels = explode( '.', $domain );
		if ( count( $labels ) > 2 ) {
			$hosts[] = '_dmarc.' . implode( '.', array_slice( $labels, -2 ) );
		}

		foreach ( $hosts as $host ) {
			$txt = call_user_func( $this->txtLookup, $host );
			if ( is_array( $txt ) && self::has( $txt, 'v=DMARC1' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $txt
	 */
	private static function has( array $txt, string $prefix ): bool {
		foreach ( $txt as $record ) {
			if ( stripos( ltrim( (string) $record, "\" \t" ), $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * TXT records of a host; an empty list when there are none, null when
	 * DNS is not available.
	 *
	 * @return string[]|null
	 */
	public static function dnsTxt( string $host ): ?array {
		if ( ! function_exists( 'dns_get_record' ) ) {
			return null;
		}

		// dns_get_record() warns on a failed lookup instead of returning false only.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$records = @dns_get_record( $host, DNS_TXT );
		if ( ! is_array( $records ) ) {
			return null;
		}

		$txt = array();
		foreach ( $records as $record ) {
			if ( isset( $record['entries'] ) && is_array( $record['entries'] ) ) {
				$txt[] = implode( '', $record['entries'] );
			} elseif ( isset( $record['txt'] ) ) {
				$txt[] = (string) $record['txt'];
			}
		}

		return $txt;
	}

	/**
	 * Something reconfigures PHPMailer or replaces wp_mail() — an SMTP or
	 * mail-API plugin.
	 */
	public static function smtpPluginActive(): bool {
		return (bool) has_action( 'phpmailer_init' ) || (bool) has_filter( 'pre_wp_mail' );
	}
}
