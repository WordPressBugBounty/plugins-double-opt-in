<?php
/**
 * What the setup wizard reads and writes.
 *
 * Four steps (sender, forms, mail, page), each saved as it is confirmed, and a
 * finish that turns the collected values into form settings. Forms that are
 * already configured are left alone: the wizard only switches on forms that
 * have no Double Opt-In yet.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

use Forge12\DoubleOptIn\EmailTemplates\PlaceholderMapper;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SetupService {

	public const STEP_SENDER = 'sender';
	public const STEP_FORMS  = 'forms';
	public const STEP_MAIL   = 'mail';
	public const STEP_PAGE   = 'page';

	/** Step name => zero-based position. */
	public const STEPS = array(
		self::STEP_SENDER => 0,
		self::STEP_FORMS  => 1,
		self::STEP_MAIL   => 2,
		self::STEP_PAGE   => 3,
	);

	public const TEST_MAIL_LIMIT  = 5;
	public const TEST_MAIL_WINDOW = 3600;

	/** @var SetupState */
	private $state;

	/** @var FormSettingsService */
	private $settings;

	/** @var FormPluginDetector */
	private $detector;

	/** @var SetupMailComposer */
	private $composer;

	/**
	 * Lists the forms of every available integration. Entries without an
	 * `integration` are Contact Form 7 forms.
	 *
	 * @var callable():array<int, array{id:int|string, title:string, enabled:bool, integration?:string, integrationName?:string}>
	 */
	private $formLister;

	/**
	 * Lists the fields of one form as key => label.
	 *
	 * @var callable(int|string, string):array<int|string, string>
	 */
	private $fieldLister;

	/** @var callable():array<int, array{id:int, title:string}> */
	private $pages;

	/** @var array<string, array<string, string>> Fields per form key, per request. */
	private $fieldCache = array();

	/**
	 * @param callable|null $forms  Lists the forms of all integrations (test seam).
	 * @param callable|null $fields Lists the fields of a form (test seam).
	 * @param callable|null $pages  Lists published pages (test seam).
	 */
	public function __construct(
		SetupState $state,
		FormSettingsService $settings,
		FormPluginDetector $detector,
		SetupMailComposer $composer,
		?callable $forms = null,
		?callable $fields = null,
		?callable $pages = null
	) {
		$this->state       = $state;
		$this->settings    = $settings;
		$this->detector    = $detector;
		$this->composer    = $composer;
		$this->formLister  = $forms ?? array( self::class, 'listForms' );
		$this->fieldLister = $fields ?? array( self::class, 'listFields' );
		$this->pages       = $pages ?? array( self::class, 'listPublishedPages' );
	}

	/**
	 * All forms, keyed "integration:id".
	 *
	 * @return array<string, array{key:string, integration:string, integrationName:string, id:int|string, title:string, enabled:bool}>
	 */
	private function forms(): array {
		$forms = array();
		foreach ( (array) call_user_func( $this->formLister ) as $form ) {
			if ( ! is_array( $form ) || ! isset( $form['id'] ) ) {
				continue;
			}
			$integration   = (string) ( $form['integration'] ?? 'cf7' );
			$id            = $form['id'];
			$key           = self::formKey( $integration, $id );
			$forms[ $key ] = array(
				'key'             => $key,
				'integration'     => $integration,
				'integrationName' => (string) ( $form['integrationName'] ?? ( $integration === 'cf7' ? 'Contact Form 7' : $integration ) ),
				'id'              => $id,
				'title'           => (string) ( $form['title'] ?? '' ),
				'enabled'         => ! empty( $form['enabled'] ),
			);
		}
		return $forms;
	}

	/**
	 * Fields of a form as key => label.
	 *
	 * @param array{key:string, integration:string, id:int|string} $form
	 *
	 * @return array<string, string>
	 */
	private function fieldsOf( array $form ): array {
		if ( isset( $this->fieldCache[ $form['key'] ] ) ) {
			return $this->fieldCache[ $form['key'] ];
		}
		$fields = array();
		foreach ( (array) call_user_func( $this->fieldLister, $form['id'], $form['integration'] ) as $key => $label ) {
			if ( ! is_scalar( $label ) ) {
				continue;
			}
			$name            = (string) $key;
			$fields[ $name ] = trim( (string) $label ) !== '' ? (string) $label : $name;
		}
		$this->fieldCache[ $form['key'] ] = $fields;
		return $fields;
	}

	public static function formKey( string $integration, $id ): string {
		return $integration . ':' . (string) $id;
	}

	/**
	 * Where a form's settings live: the post ID, also for Elementor's
	 * "page_widget" IDs — the same rule the Forms page uses.
	 *
	 * @param int|string $id
	 */
	private static function storageId( $id ): int {
		return (int) explode( '_', (string) $id )[0];
	}

	/**
	 * Draft forms keyed by form key. Drafts from 5.7 used bare CF7 IDs.
	 *
	 * @param array<int|string, mixed> $forms
	 *
	 * @return array<string, string>
	 */
	private static function normalizeDraftForms( array $forms ): array {
		$out = array();
		foreach ( $forms as $key => $field ) {
			$key         = is_int( $key ) || ctype_digit( (string) $key ) ? self::formKey( 'cf7', $key ) : (string) $key;
			$out[ $key ] = (string) $field;
		}
		return $out;
	}

	/**
	 * Everything the wizard needs to render, with prefilled values.
	 *
	 * @return array<string, mixed>
	 */
	public function overview(): array {
		$state    = $this->state->get();
		$draft    = $state['draft'];
		$defaults = FormDefaults::get();

		$forms        = array();
		$integrations = array();
		$draftForms   = isset( $draft['forms'] ) && is_array( $draft['forms'] ) ? self::normalizeDraftForms( $draft['forms'] ) : null;
		foreach ( $this->forms() as $key => $form ) {
			$fields   = $this->fieldsOf( $form );
			$detected = self::detectEmailFieldIn( $fields );
			$enabled  = $form['enabled'];

			if ( $draftForms !== null ) {
				$selected = array_key_exists( $key, $draftForms );
				$field    = $selected ? $draftForms[ $key ] : $detected;
			} else {
				$selected = ! $enabled && $detected !== '';
				$field    = $detected;
			}

			$fieldList = array();
			foreach ( $fields as $name => $label ) {
				$fieldList[] = array(
					'key'   => (string) $name,
					'label' => $label,
				);
			}

			$integrations[ $form['integration'] ] = $form['integrationName'];
			$forms[]                              = array(
				'key'             => $key,
				'id'              => $form['id'],
				'integration'     => $form['integration'],
				'integrationName' => $form['integrationName'],
				'title'           => $form['title'],
				'enabled'         => $enabled,
				'fields'          => $fieldList,
				'detectedField'   => $detected,
				'field'           => $field,
				'selected'        => ! $enabled && $selected,
			);
		}

		$plugins = array();
		foreach ( $this->detector->withoutAddon() as $plugin ) {
			$plugin['productUrl'] = self::productUrl( 'wizard-detected-' . $plugin['id'] );
			$plugins[]            = $plugin;
		}

		$pageId = (int) ( $draft['pageId'] ?? 0 );

		return array(
			'status'       => $state['status'],
			'step'         => $state['step'],
			'steps'        => SetupState::STEPS,
			'sender'       => array(
				'email'  => (string) ( $draft['sender'] ?? ( $defaults['sender'] !== '' ? $defaults['sender'] : get_bloginfo( 'admin_email' ) ) ),
				'name'   => (string) ( $draft['sender_name'] ?? ( $defaults['sender_name'] !== '' ? $defaults['sender_name'] : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) ) ),
				'domain' => self::siteDomain(),
			),
			'cf7'          => array(
				'installed'  => defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' ),
				'newFormUrl' => admin_url( 'admin.php?page=wpcf7-new' ),
				'installUrl' => admin_url( 'plugin-install.php?s=contact+form+7&tab=search&type=term' ),
			),
			// Every available integration, not only Contact Form 7 (5.9.0).
			'forms'        => $forms,
			'integrations' => $integrations,
			'mail'         => array(
				'subject' => (string) ( $draft['subject'] ?? $this->composer->subject() ),
				'design'  => SetupMailComposer::isDesign( (string) ( $draft['design'] ?? '' ) ) ? (string) $draft['design'] : SetupMailComposer::DEFAULT_DESIGN,
				'designs' => SetupMailComposer::DESIGNS,
			),
			'page'         => array(
				'mode'   => $pageId > 0 && empty( $draft['pageCreated'] ) ? 'existing' : 'new',
				'pageId' => $this->isPublishedPage( $pageId ) ? $pageId : 0,
				'pages'  => array_values( (array) call_user_func( $this->pages ) ),
			),
			'testMailTo'   => self::currentUserEmail(),
			'formPlugins'  => $plugins,
			'editorUrl'    => self::productUrl( 'wizard-editor' ),
		);
	}

	/**
	 * Save one step.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array{ok: bool, errors: array<string, string>, data?: array<string, mixed>}
	 */
	public function saveStep( string $step, array $data ): array {
		switch ( $step ) {
			case self::STEP_SENDER:
				return $this->saveSender( $data );
			case self::STEP_FORMS:
				return $this->saveForms( $data );
			case self::STEP_MAIL:
				return $this->saveMail( $data );
			case self::STEP_PAGE:
				return $this->savePage( $data );
		}
		return self::fail( 'step', __( 'Unknown setup step.', 'double-opt-in' ) );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function saveSender( array $data ): array {
		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$name  = sanitize_text_field( (string) ( $data['name'] ?? '' ) );

		if ( ! is_email( $email ) ) {
			return self::fail( 'email', __( 'Please enter a valid email address.', 'double-opt-in' ) );
		}

		FormDefaults::save( $email, $name );
		$this->state->saveStep(
			self::STEPS[ self::STEP_SENDER ],
			array(
				'sender'      => $email,
				'sender_name' => $name,
			)
		);
		return self::ok();
	}

	/**
	 * @param array<string, mixed> $data `forms`: list of {key, field}; a bare `id` means a CF7 form.
	 */
	private function saveForms( array $data ): array {
		$known = array_filter(
			$this->forms(),
			static function ( array $form ): bool {
				return ! $form['enabled'];
			}
		);

		$chosen = array();
		foreach ( (array) ( $data['forms'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$key   = isset( $entry['key'] ) ? sanitize_text_field( (string) $entry['key'] ) : self::formKey( 'cf7', (int) ( $entry['id'] ?? 0 ) );
			$field = sanitize_text_field( (string) ( $entry['field'] ?? '' ) );

			if ( ! isset( $known[ $key ] ) ) {
				continue;
			}
			if ( ! array_key_exists( $field, $this->fieldsOf( $known[ $key ] ) ) ) {
				return self::fail(
					'forms',
					__( 'Please choose the email field for every selected form.', 'double-opt-in' )
				);
			}
			$chosen[ $key ] = $field;
		}

		$this->state->saveStep( self::STEPS[ self::STEP_FORMS ], array( 'forms' => $chosen ) );
		return self::ok();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function saveMail( array $data ): array {
		$subject = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
		$design  = (string) ( $data['design'] ?? '' );

		if ( trim( $subject ) === '' ) {
			return self::fail( 'subject', __( 'Please enter a subject.', 'double-opt-in' ) );
		}
		if ( ! SetupMailComposer::isDesign( $design ) ) {
			$design = SetupMailComposer::DEFAULT_DESIGN;
		}

		$this->state->saveStep(
			self::STEPS[ self::STEP_MAIL ],
			array(
				'subject' => $subject,
				'design'  => $design,
			)
		);
		return self::ok();
	}

	/**
	 * @param array<string, mixed> $data `mode`: new|existing, `pageId` for existing.
	 */
	private function savePage( array $data ): array {
		$mode = (string) ( $data['mode'] ?? 'new' );

		if ( $mode === 'existing' ) {
			$pageId = (int) ( $data['pageId'] ?? 0 );
			if ( ! $this->isPublishedPage( $pageId ) ) {
				return self::fail( 'pageId', __( 'Please choose a published page.', 'double-opt-in' ) );
			}
			$this->state->saveStep(
				self::STEPS[ self::STEP_PAGE ],
				array(
					'pageId'      => $pageId,
					'pageCreated' => false,
				)
			);
			return self::ok( array( 'pageId' => $pageId ) );
		}

		$pageId = $this->ensureConfirmationPage();
		if ( $pageId <= 0 ) {
			return self::fail( 'page', __( 'The page could not be created. Please choose an existing page instead.', 'double-opt-in' ) );
		}
		$this->state->saveStep(
			self::STEPS[ self::STEP_PAGE ],
			array(
				'pageId'      => $pageId,
				'pageCreated' => true,
			)
		);
		return self::ok( array( 'pageId' => $pageId ) );
	}

	/**
	 * Create the confirmation page once, published. Reuses the page from an
	 * earlier run while it is still published.
	 */
	private function ensureConfirmationPage(): int {
		$draft    = $this->state->draft();
		$existing = (int) ( $draft['pageId'] ?? 0 );
		if ( ! empty( $draft['pageCreated'] ) && $this->isPublishedPage( $existing ) ) {
			return $existing;
		}

		if ( ! current_user_can( 'publish_pages' ) ) {
			return 0;
		}

		// The shortcode tells a confirmed link from an expired or invalid one
		// (5.8.0); a fixed "confirmed" text said so for every click.
		$content = '<!-- wp:shortcode -->[doi_confirmation_status]<!-- /wp:shortcode -->';

		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Email address confirmed', 'double-opt-in' ),
				'post_content'   => $content,
				'comment_status' => 'closed',
			),
			true
		);

		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Send the configured mail to the current user, with sample values.
	 *
	 * @return array{ok: bool, errors: array<string, string>, data?: array<string, mixed>}
	 */
	public function sendTestMail(): array {
		$to = self::currentUserEmail();
		if ( ! is_email( $to ) ) {
			return self::fail( 'testMail', __( 'Your user account has no valid email address.', 'double-opt-in' ) );
		}

		$key   = 'f12_doi_setup_test_mail_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= self::TEST_MAIL_LIMIT ) {
			return self::fail( 'testMail', __( 'You have sent several test emails in a short time. Please try again in an hour.', 'double-opt-in' ) );
		}
		set_transient( $key, $count + 1, self::TEST_MAIL_WINDOW );

		$overview = $this->overview();
		$draft    = $this->state->draft();
		$pageId   = (int) ( $draft['pageId'] ?? 0 );
		// Points at the confirmation page without an opt-in hash: the admin
		// sees where visitors land, and nothing gets confirmed.
		$link = $pageId > 0 ? (string) get_permalink( $pageId ) : home_url( '/' );

		$body = strtr(
			$this->composer->body( (string) $overview['mail']['design'] ),
			array(
				'[doubleoptinlink]'        => esc_url( $link ),
				'[doubleoptin_form_url]'   => esc_url( home_url( '/' ) ),
				'[doubleoptin_form_date]'  => (string) wp_date( (string) get_option( 'date_format', 'Y-m-d' ) ),
				'[doubleoptin_form_time]'  => (string) wp_date( (string) get_option( 'time_format', 'H:i' ) ),
				'[doubleoptin_form_email]' => $to,
			)
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sender  = (string) $overview['sender']['email'];
		$name    = (string) $overview['sender']['name'];
		if ( is_email( $sender ) ) {
			$headers[] = 'From: ' . ( $name !== '' ? self::headerName( $name ) . ' <' . $sender . '>' : $sender );
		}

		$sent = wp_mail(
			$to,
			sprintf(
				/* translators: %s: subject of the confirmation email */
				__( '[Test] %s', 'double-opt-in' ),
				(string) $overview['mail']['subject']
			),
			$body,
			$headers
		);

		if ( $sent === false ) {
			return self::fail( 'testMail', __( 'WordPress could not send the email. Please check the mail settings of your site, for example with an SMTP plugin.', 'double-opt-in' ) );
		}

		return self::ok( array( 'to' => $to ) );
	}

	/**
	 * Turn the collected values into form settings and switch the forms on.
	 *
	 * @return array{ok: bool, errors: array<string, string>, data?: array<string, mixed>}
	 */
	public function finish(): array {
		$draft   = $this->state->draft();
		$forms   = isset( $draft['forms'] ) && is_array( $draft['forms'] ) ? self::normalizeDraftForms( $draft['forms'] ) : array();
		$design  = (string) ( $draft['design'] ?? SetupMailComposer::DEFAULT_DESIGN );
		$subject = (string) ( $draft['subject'] ?? $this->composer->subject() );
		$pageId  = (int) ( $draft['pageId'] ?? 0 );
		$sender  = FormDefaults::get();
		$body    = $this->composer->body( $design );

		$known = $this->forms();

		$enabled    = array();
		$incomplete = array();
		$stored     = array();
		foreach ( $forms as $key => $field ) {
			if ( ! isset( $known[ $key ] ) || $known[ $key ]['enabled'] ) {
				continue;
			}
			$formId = self::storageId( $known[ $key ]['id'] );
			// Elementor forms on one page share their settings.
			if ( $formId <= 0 || isset( $stored[ $formId ] ) ) {
				continue;
			}
			$stored[ $formId ] = true;

			$dto            = $this->settings->getSettings( $formId );
			$dto->recipient = '[' . (string) $field . ']';
			$dto->subject   = $subject;
			$dto->body      = $body;
			$dto->template  = '';
			if ( $sender['sender'] !== '' ) {
				$dto->sender = $sender['sender'];
			}
			if ( $sender['sender_name'] !== '' ) {
				$dto->senderName = $sender['sender_name'];
			}
			if ( $this->isPublishedPage( $pageId ) ) {
				$dto->confirmationPage = $pageId;
			}

			$missing = $dto->getMissingRequiredFields();
			if ( $missing !== array() ) {
				$incomplete[] = array(
					'id'      => $key,
					'missing' => $missing,
				);
				continue;
			}

			$dto->enabled = true;
			$this->settings->saveSettings( $formId, $dto );
			$enabled[] = $key;
		}

		$this->state->complete();

		return self::ok(
			array(
				'enabled'    => $enabled,
				'incomplete' => $incomplete,
			)
		);
	}

	/**
	 * The field that most likely holds the visitor's address.
	 *
	 * @param string[] $fields
	 */
	public static function detectEmailField( array $fields ): string {
		if ( $fields === array() ) {
			return '';
		}
		$mapping = PlaceholderMapper::autoDetectMapping( $fields );
		return isset( $mapping['doi_email'] ) ? (string) $mapping['doi_email'] : '';
	}

	/**
	 * Same, for key => label fields. WPForms, Gravity Forms and Elementor
	 * key their fields by number or random ID, so the label decides there.
	 *
	 * @param array<string, string> $fields
	 */
	public static function detectEmailFieldIn( array $fields ): string {
		$byKey = self::detectEmailField( array_map( 'strval', array_keys( $fields ) ) );
		if ( $byKey !== '' ) {
			return $byKey;
		}
		$byLabel = self::detectEmailField( array_values( $fields ) );
		if ( $byLabel === '' ) {
			return '';
		}
		$key = array_search( $byLabel, $fields, true );
		return $key === false ? '' : (string) $key;
	}

	private function isPublishedPage( int $pageId ): bool {
		if ( $pageId <= 0 ) {
			return false;
		}
		foreach ( (array) call_user_func( $this->pages ) as $page ) {
			if ( (int) $page['id'] === $pageId ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Forms of every available integration (Contact Form 7 and the form
	 * add-ons that registered one).
	 *
	 * @return array<int, array{id: int|string, title: string, enabled: bool, integration: string, integrationName: string}>
	 */
	public static function listForms(): array {
		if ( ! class_exists( FormIntegrationRegistry::class ) ) {
			return array();
		}
		$forms = array();
		foreach ( FormIntegrationRegistry::getInstance()->getAvailable() as $identifier => $integration ) {
			foreach ( $integration->getForms() as $form ) {
				if ( ! is_array( $form ) || ! isset( $form['id'] ) ) {
					continue;
				}
				$forms[] = array(
					'id'              => is_int( $form['id'] ) ? $form['id'] : (string) $form['id'],
					'title'           => (string) ( $form['title'] ?? '' ),
					'enabled'         => ! empty( $form['enabled'] ),
					'integration'     => (string) $identifier,
					'integrationName' => $integration->getName(),
				);
			}
		}
		return $forms;
	}

	/**
	 * @param int|string $formId
	 *
	 * @return array<string, string> key => label
	 */
	public static function listFields( $formId, string $integration = 'cf7' ): array {
		if ( ! class_exists( FormIntegrationRegistry::class ) ) {
			return array();
		}
		$instance = FormIntegrationRegistry::getInstance()->get( $integration );
		if ( $instance === null || ! $instance->isAvailable() ) {
			return array();
		}
		$fields = array();
		foreach ( $instance->getFormFields( $formId ) as $key => $label ) {
			$fields[ (string) $key ] = is_scalar( $label ) ? (string) $label : (string) $key;
		}
		return $fields;
	}

	/**
	 * @return array<int, array{id: int, title: string}>
	 */
	public static function listPublishedPages(): array {
		$pages = array();
		foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $page ) {
			$pages[] = array(
				'id'    => (int) $page->ID,
				'title' => (string) $page->post_title,
			);
		}
		return $pages;
	}

	private static function currentUserEmail(): string {
		$user = get_userdata( get_current_user_id() );
		return ( $user && isset( $user->user_email ) ) ? (string) $user->user_email : '';
	}

	private static function siteDomain(): string {
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return strpos( $host, 'www.' ) === 0 ? substr( $host, 4 ) : $host;
	}

	private static function productUrl( string $from ): string {
		$fn = '\\forge12\\contactform7\\CF7DoubleOptIn\\get_product_url';
		return function_exists( $fn ) ? (string) $fn( $from ) : '';
	}

	/**
	 * A display name safe for a mail header.
	 */
	private static function headerName( string $name ): string {
		$name = str_replace( array( "\r", "\n", '"' ), '', $name );
		return '"' . $name . '"';
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function ok( array $data = array() ): array {
		return array(
			'ok'     => true,
			'errors' => array(),
			'data'   => $data,
		);
	}

	private static function fail( string $field, string $message ): array {
		return array(
			'ok'     => false,
			'errors' => array( $field => $message ),
		);
	}
}
