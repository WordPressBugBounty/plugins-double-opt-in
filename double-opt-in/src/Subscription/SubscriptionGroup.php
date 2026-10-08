<?php
/**
 * A subscription group: several forms presented as one subscription.
 *
 * @package Forge12\DoubleOptIn\Subscription
 * @since   5.12.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable value object. A group only changes how records are presented
 * and selected; the opt-in records themselves are never merged.
 *
 * A member names a form (`form_id`) and optionally one instance of it
 * (`form_ref`, e.g. an Elementor widget id). Without a reference the member
 * covers every record of that form, including records stored before
 * `form_ref` existed.
 */
final class SubscriptionGroup {

	/** @var string */
	private $key;

	/** @var string */
	private $label;

	/** @var array<int, array{form_id:int, form_ref:string}> */
	private $members;

	/** @var bool */
	private $mergeInFrontend;

	/** @var bool */
	private $showDetails;

	/**
	 * @param string             $key             Stable identifier.
	 * @param string             $label           Name shown to visitors and admins.
	 * @param array<int, mixed>  $members         Members, each with `form_id` and optional `form_ref`.
	 * @param bool               $mergeInFrontend One row per address in the consent center.
	 * @param bool               $showDetails     Let visitors expand the single sign-ups.
	 */
	public function __construct( string $key, string $label, array $members, bool $mergeInFrontend, bool $showDetails ) {
		$this->key             = $key;
		$this->label           = $label;
		$this->members         = self::normaliseMembers( $members );
		$this->mergeInFrontend = $mergeInFrontend;
		$this->showDetails     = $showDetails;
	}

	public function getKey(): string {
		return $this->key;
	}

	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * @return array<int, array{form_id:int, form_ref:string}>
	 */
	public function getMembers(): array {
		return $this->members;
	}

	public function mergesInFrontend(): bool {
		return $this->mergeInFrontend;
	}

	public function showsDetails(): bool {
		return $this->showDetails;
	}

	/**
	 * Whether a record belongs to this group.
	 *
	 * @param int    $formId  The record's form id.
	 * @param string $formRef The record's form instance reference, if any.
	 */
	public function matches( int $formId, string $formRef = '' ): bool {
		foreach ( $this->members as $member ) {
			if ( $member['form_id'] !== $formId ) {
				continue;
			}
			if ( $member['form_ref'] === '' || $member['form_ref'] === $formRef ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A WHERE fragment selecting this group's records, with `%d` / `%s`
	 * placeholders for `$wpdb->prepare()`.
	 *
	 * @param string $alias Optional table alias including the dot, e.g. `o.`.
	 *
	 * @return array{0:string, 1:array<int, int|string>} Fragment and its parameters.
	 */
	public function toSqlCondition( string $alias = '' ): array {
		if ( array() === $this->members ) {
			return array( '1 = 0', array() );
		}

		$parts  = array();
		$params = array();
		foreach ( $this->members as $member ) {
			if ( $member['form_ref'] === '' ) {
				$parts[]  = $alias . 'cf_form_id = %d';
				$params[] = $member['form_id'];
				continue;
			}
			$parts[]  = '(' . $alias . 'cf_form_id = %d AND ' . $alias . 'form_ref = %s)';
			$params[] = $member['form_id'];
			$params[] = $member['form_ref'];
		}

		return array( '(' . implode( ' OR ', $parts ) . ')', $params );
	}

	/**
	 * @param array<int, mixed> $members Raw members.
	 *
	 * @return array<int, array{form_id:int, form_ref:string}>
	 */
	private static function normaliseMembers( array $members ): array {
		$clean = array();
		foreach ( $members as $member ) {
			if ( ! is_array( $member ) ) {
				continue;
			}
			$formId = (int) ( $member['form_id'] ?? 0 );
			if ( $formId <= 0 ) {
				continue;
			}
			$clean[] = array(
				'form_id'  => $formId,
				'form_ref' => (string) ( $member['form_ref'] ?? '' ),
			);
		}

		return $clean;
	}
}
