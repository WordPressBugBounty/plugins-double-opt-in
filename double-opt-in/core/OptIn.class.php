<?php
/**
 * OptIn Facade
 *
 * Backward-compatible facade for the legacy OptIn API.
 * Internally uses the new Repository pattern.
 *
 * @package forge12\contactform7\CF7DoubleOptIn
 * @since   4.0.0
 */

namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Entity\OptIn as OptInEntity;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\Shared\Logger;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptIn
 *
 * This class provides backward compatibility with the legacy API
 * while using the new Repository pattern internally.
 */
class OptIn {

	private LoggerInterface $logger;
	private OptInEntity $entity;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger     The logger instance.
	 * @param array           $properties Optional properties to initialize.
	 */
	public function __construct( LoggerInterface $logger, array $properties = [] ) {
		$this->logger = $logger;

		if ( ! empty( $properties ) ) {
			$this->entity = OptInEntity::fromArray( $this->mapToEntityArray( $properties ) );
		} else {
			$this->entity = OptInEntity::create();
		}

		$this->logger->debug( 'OptIn facade initialized', [
			'plugin' => 'double-opt-in',
			'id'     => $this->entity->getId(),
		] );
	}

	/**
	 * Get the logger instance.
	 *
	 * @return LoggerInterface
	 */
	public function get_logger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * Get the underlying entity.
	 *
	 * @return OptInEntity
	 */
	public function getEntity(): OptInEntity {
		return $this->entity;
	}

	// =========================================================================
	// STATIC FACTORY METHODS
	// =========================================================================

	/**
	 * Get OptIn by hash.
	 *
	 * @param string $hash The hash.
	 *
	 * @return OptIn|null
	 */
	public static function get_by_hash( string $hash ): ?OptIn {
		$logger = Logger::getInstance();
		$logger->debug( 'get_by_hash called', [
			'plugin' => 'double-opt-in',
			'hash'   => $hash,
		] );

		try {
			$repository = self::getRepository();
			$entity     = $repository->findByHash( $hash );

			if ( ! $entity ) {
				return null;
			}

			$optIn         = new self( $logger );
			$optIn->entity = $entity;

			return $optIn;
		} catch ( \Exception $e ) {
			$logger->error( 'Failed to get OptIn by hash', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
				'error'  => $e->getMessage(),
			] );
			return null;
		}
	}

	/**
	 * Get count by form ID.
	 *
	 * @param int $formId The form ID.
	 *
	 * @return int
	 */
	public static function get_count( int $formId ): int {
		try {
			return self::getRepository()->countByFormId( $formId );
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Get list of OptIns with pagination.
	 *
	 * @param array    $atts          Query attributes.
	 * @param int|null $numberOfPages Reference to store page count.
	 *
	 * @return array<OptIn>
	 */
	public static function get_list( array $atts = [], ?int &$numberOfPages = null ): array {
		$logger = Logger::getInstance();

		try {
			$repository = self::getRepository();
			$entities   = $repository->findAll( $atts, $numberOfPages );

			return array_map( function ( OptInEntity $entity ) use ( $logger ) {
				$optIn         = new self( $logger );
				$optIn->entity = $entity;
				return $optIn;
			}, $entities );
		} catch ( \Exception $e ) {
			$logger->error( 'Failed to get OptIn list', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get list by category ID.
	 *
	 * @param int      $categoryId    The category ID.
	 * @param array    $atts          Query attributes.
	 * @param int|null $numberOfPages Reference to store page count.
	 *
	 * @return array<OptIn>
	 */
	public static function get_list_by_category_id( int $categoryId, array $atts = [], ?int &$numberOfPages = null ): array {
		$logger = Logger::getInstance();

		try {
			$repository = self::getRepository();

			// Get total count for pagination
			if ( $numberOfPages !== null ) {
				$keyword       = $atts['keyword'] ?? '';
				$perPage       = max( 1, (int) ( $atts['perPage'] ?? 10 ) );
				$total         = $repository->countByCategory( $categoryId, $keyword );
				$numberOfPages = $total > 0 ? (int) ceil( $total / $perPage ) : 0;

				if ( $numberOfPages === 0 ) {
					return [];
				}
			}

			$entities = $repository->findByCategory( $categoryId, $atts );

			return array_map( function ( OptInEntity $entity ) use ( $logger ) {
				$optIn         = new self( $logger );
				$optIn->entity = $entity;
				return $optIn;
			}, $entities );
		} catch ( \Exception $e ) {
			$logger->error( 'Failed to get OptIn list by category', [
				'plugin'   => 'double-opt-in',
				'category' => $categoryId,
				'error'    => $e->getMessage(),
			] );
			return [];
		}
	}

	/**
	 * Get list by email.
	 *
	 * @param string $email The email address.
	 *
	 * @return array<OptIn>
	 */
	public static function get_list_by_email( string $email ): array {
		$logger = Logger::getInstance();

		try {
			$repository = self::getRepository();
			$entities   = $repository->findByEmail( $email );

			return array_map( function ( OptInEntity $entity ) use ( $logger ) {
				$optIn         = new self( $logger );
				$optIn->entity = $entity;
				return $optIn;
			}, $entities );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Get confirmed list by email.
	 *
	 * @param string $email The email address.
	 *
	 * @return array<OptIn>
	 */
	public static function get_list_by_email_confirmed( string $email ): array {
		$logger = Logger::getInstance();

		try {
			$repository = self::getRepository();
			$entities   = $repository->findConfirmedByEmail( $email );

			return array_map( function ( OptInEntity $entity ) use ( $logger ) {
				$optIn         = new self( $logger );
				$optIn->entity = $entity;
				return $optIn;
			}, $entities );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Get unconfirmed list by email.
	 *
	 * @param string $email The email address.
	 *
	 * @return array<OptIn>
	 */
	public static function get_list_by_email_unconfirmed( string $email ): array {
		$logger = Logger::getInstance();

		try {
			$repository = self::getRepository();
			$entities   = $repository->findUnconfirmedByEmail( $email );

			return array_map( function ( OptInEntity $entity ) use ( $logger ) {
				$optIn         = new self( $logger );
				$optIn->entity = $entity;
				return $optIn;
			}, $entities );
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Bulk update category.
	 *
	 * @param int $fromId The source category ID.
	 * @param int $toId   The target category ID.
	 *
	 * @return int
	 */
	public static function bulk_update_category( int $fromId, int $toId ): int {
		try {
			return self::getRepository()->bulkUpdateCategory( $fromId, $toId );
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Update category by OptIn ID.
	 *
	 * @param int $optInId    The OptIn ID.
	 * @param int $categoryId The new category ID.
	 *
	 * @return bool
	 */
	public static function update_category_by_id( int $optInId, int $categoryId ): bool {
		try {
			return self::getRepository()->updateCategoryById( $optInId, $categoryId );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	// =========================================================================
	// GETTERS
	// =========================================================================

	public function get_id(): int {
		return $this->entity->getId();
	}

	public function get_hash(): string {
		return $this->entity->getHash();
	}

	public function get_cf_form_id(): int {
		return $this->entity->getFormId();
	}

	public function is_confirmed(): bool {
		return $this->entity->isConfirmed();
	}

	public function get_doubleoptin(): int {
		return $this->entity->isConfirmed() ? 1 : 0;
	}

	public function is_optout(): bool {
		return $this->entity->isOptedOut();
	}

	public function get_content(): string {
		return $this->entity->getContent();
	}

	public function get_createtime( string $view = 'raw' ): string {
		if ( $view !== 'raw' ) {
			return $this->entity->getCreateTimeFormatted( 'd.m.Y / H:i:s' );
		}
		return (string) $this->entity->getCreateTime();
	}

	public function get_updatetime( string $view = 'raw' ): string {
		if ( $view !== 'raw' ) {
			return $this->entity->getUpdateTimeFormatted( 'd.m.Y / H:i:s' );
		}
		return (string) $this->entity->getUpdateTime();
	}

	public function get_optouttime( string $view = 'raw' ): string {
		$time = $this->entity->getOptOutTime();
		if ( $view !== 'raw' && $time > 0 ) {
			return wp_date( 'd.m.Y / H:i:s', $time );
		}
		return (string) $time;
	}

	/**
	 * Get create time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function get_createtime_iso(): string {
		return $this->entity->getCreateTimeISO();
	}

	/**
	 * Get update time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function get_updatetime_iso(): string {
		return $this->entity->getUpdateTimeISO();
	}

	/**
	 * Get opt-out time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function get_optouttime_iso(): string {
		return $this->entity->getOptOutTimeISO();
	}

	public function get_ipaddr_register(): string {
		return $this->entity->getIpRegister();
	}

	public function get_ipaddr_confirmation(): string {
		return $this->entity->getIpConfirmation();
	}

	public function get_ipaddr_optout(): string {
		return $this->entity->getIpOptOut();
	}

	public function get_files(): string {
		return $this->entity->getFiles();
	}

	public function get_category(): int {
		return $this->entity->getCategory();
	}

	public function get_email(): string {
		return $this->entity->getEmail();
	}

	public function get_mail_optin(): string {
		return $this->entity->getMailOptIn();
	}

	public function get_consent_text(): string {
		return $this->entity->getConsentText();
	}

	public function get_form( bool $encoded = false ): string {
		$form = $this->entity->getForm();
		if ( $encoded ) {
			return base64_encode( $form );
		}
		return $form;
	}

	/**
	 * Get the validity end date.
	 *
	 * @return string
	 */
	public function get_valid_until(): string {
		$settings = CF7DoubleOptIn::getInstance()->getSettings();
		$dt       = new \DateTime();
		$dt->setTimestamp( $this->entity->getCreateTime() );

		if ( $this->is_confirmed() ) {
			$amount = (int) ( $settings['delete'] ?? 0 );
			$period = $settings['delete_period'] ?? 'months';
		} else {
			$amount = (int) ( $settings['delete_unconfirmed'] ?? 0 );
			$period = $settings['delete_unconfirmed_period'] ?? 'months';
		}

		$dt->modify( '+' . $amount . ' ' . $period );
		$dt->modify( '+1 day' );

		return $dt->format( 'd.m.Y' );
	}

	// =========================================================================
	// SETTERS
	// =========================================================================

	public function set_cf_form_id( int $formId ): void {
		$this->entity = $this->entity->withFormId( $formId );
	}

	public function set_doubleoptin( int $confirmed ): void {
		$this->entity->setConfirmed( (bool) $confirmed );
	}

	public function set_createtime( $timestamp ): void {
		$this->entity = $this->entity->withCreateTime( (int) $timestamp );
	}

	public function set_updatetime( $timestamp ): void {
		$this->entity->setUpdateTime( (int) $timestamp );
	}

	public function set_optouttime( $timestamp ): void {
		$this->entity = $this->entity->withOptOutTime( (int) $timestamp );
	}

	public function set_ipaddr_register( string $ip ): void {
		$this->entity = $this->entity->withIpRegister( $ip );
	}

	public function set_ipaddr_confirmation( string $ip ): void {
		$this->entity->setIpConfirmation( $ip );
	}

	public function set_ipaddr_optout( string $ip ): void {
		$this->entity = $this->entity->withIpOptOut( $ip );
	}

	public function set_content( string $content ): void {
		$this->entity = $this->entity->withContent( $content );
	}

	public function set_files( string $files ): void {
		$this->entity = $this->entity->withFiles( $files );
	}

	public function set_category( int $categoryId ): void {
		$this->entity = $this->entity->withCategory( $categoryId );
	}

	public function set_form( string $form ): void {
		$this->entity = $this->entity->withForm( $form );
	}

	public function set_email( string $email ): void {
		$this->entity = $this->entity->withEmail( $email );
	}

	public function set_mail_optin( string $mail ): void {
		$this->entity = $this->entity->withMailOptIn( $mail );
	}

	public function set_consent_text( string $text ): void {
		$this->entity = $this->entity->withConsentText( $text );
	}

	// =========================================================================
	// TYPE CHECKS
	// =========================================================================

	public function isTypeCF7(): bool {
		return $this->isType( 'cf7' );
	}

	public function isTypeAvada(): bool {
		return $this->isType( 'avada' );
	}

	public function isTypeElementor(): bool {
		return $this->isType( 'elementor' );
	}

	public function isTypeWPForms(): bool {
		return $this->isType( 'wpforms' );
	}

	public function isTypeGravityForms(): bool {
		return $this->isType( 'gravityforms' );
	}

	public function isType( string $type ): bool {
		$formId = $this->get_cf_form_id();

		switch ( $type ) {
			case 'cf7':
				return get_post_type( $formId ) === 'wpcf7_contact_form';
			case 'avada':
				return get_post_type( $formId ) === 'fusion_form';
			case 'elementor':
				// Elementor forms are embedded in pages/posts, not stored as separate post types.
				// Check if the post has Elementor data AND DOI settings configured.
				$hasElementorData = ! empty( get_post_meta( $formId, '_elementor_data', true ) );
				$hasDoiSettings = ! empty( get_post_meta( $formId, 'f12-cf7-doubleoptin', true ) );
				return $hasElementorData && $hasDoiSettings;
			case 'wpforms':
				return get_post_type( $formId ) === 'wpforms';
			case 'gravityforms':
				// Gravity Forms stores forms in a custom table, not as posts.
				// Check if the form ID exists in the GF forms table.
				if ( class_exists( 'GFAPI' ) ) {
					$form = \GFAPI::get_form( $formId );
					return $form !== false && ! is_wp_error( $form );
				}
				return false;
			default:
				return false;
		}
	}

	// =========================================================================
	// PERSISTENCE
	// =========================================================================

	/**
	 * Save the OptIn to the database.
	 *
	 * @return bool|int
	 */
	public function save() {
		$this->logger->info( 'Saving OptIn', [
			'plugin' => 'double-opt-in',
			'id'     => $this->entity->getId(),
			'hash'   => $this->entity->getHash(),
		] );

		try {
			$repository   = self::getRepository();
			$this->entity = $repository->save( $this->entity );

			$this->logger->info( 'OptIn saved successfully', [
				'plugin' => 'double-opt-in',
				'id'     => $this->entity->getId(),
				'hash'   => $this->entity->getHash(),
			] );

			return true;
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to save OptIn', [
				'plugin' => 'double-opt-in',
				'error'  => $e->getMessage(),
			] );
			return false;
		}
	}

	// =========================================================================
	// LINK GENERATION
	// =========================================================================

	/**
	 * Get the opt-in confirmation link.
	 *
	 * @param array $parameter Additional parameters.
	 * @param int   $formId    Optional form ID override.
	 *
	 * @return string
	 */
	public function get_link_optin( array $parameter = [], int $formId = 0 ): string {
		$formId = $formId > 0 ? $formId : $this->get_cf_form_id();

		$formParameter = CF7DoubleOptIn::getInstance()->getParameter( $formId );
		$pageId        = (int) ( $formParameter['page'] ?? 0 );

		if ( $pageId <= 0 ) {
			return home_url( '?optin=' . $this->get_hash() );
		}

		$pageUrl = get_permalink( $pageId );
		if ( ! $pageUrl ) {
			return home_url( '?optin=' . $this->get_hash() );
		}

		$separator = strpos( $pageUrl, '?' ) !== false ? '&' : '?';

		return $pageUrl . $separator . 'optin=' . $this->get_hash();
	}

	/**
	 * Get the opt-out link.
	 *
	 * @return string
	 */
	public function get_link_optout(): string {
		$settings = CF7DoubleOptIn::getInstance()->getSettings();
		$pageId   = (int) ( $settings['optout_page'] ?? 0 );

		if ( $pageId <= 0 ) {
			return home_url( '?optout=' . $this->get_hash() );
		}

		$pageUrl = get_permalink( $pageId );
		if ( ! $pageUrl ) {
			return home_url( '?optout=' . $this->get_hash() );
		}

		$separator = strpos( $pageUrl, '?' ) !== false ? '&' : '?';

		return $pageUrl . $separator . 'optout=' . $this->get_hash();
	}

	/**
	 * Get the UI edit link.
	 *
	 * @return string
	 */
	public function get_link_ui(): string {
		return admin_url( 'admin.php?page=' . FORGE12_OPTIN_SLUG . '&view=single&hash=' . $this->get_hash() );
	}

	/**
	 * Get the delete link.
	 *
	 * @return string
	 */
	public function get_link_delete(): string {
		$nonce = wp_create_nonce( 'delete_optin_' . $this->get_hash() );
		return admin_url( 'admin.php?page=' . FORGE12_OPTIN_SLUG . '&action=delete&hash=' . $this->get_hash() . '&_wpnonce=' . $nonce );
	}

	/**
	 * Get the form name.
	 *
	 * @return string
	 */
	public function get_form_name(): string {
		$formId = $this->get_cf_form_id();

		if ( $formId <= 0 ) {
			return __( 'Unknown', 'double-opt-in' );
		}

		$post = get_post( $formId );
		if ( ! $post ) {
			return __( 'Deleted Form', 'double-opt-in' ) . ' (ID: ' . $formId . ')';
		}

		return $post->post_title ?: __( 'Untitled Form', 'double-opt-in' );
	}

	/**
	 * Get the form edit link.
	 *
	 * @return string
	 */
	public function get_form_link(): string {
		$formId   = $this->get_cf_form_id();
		$postType = get_post_type( $formId );

		if ( $postType === 'wpcf7_contact_form' ) {
			return admin_url( 'admin.php?page=wpcf7&post=' . $formId . '&action=edit' );
		}

		if ( $postType === 'fusion_form' ) {
			return admin_url( 'post.php?post=' . $formId . '&action=edit' );
		}

		return admin_url( 'post.php?post=' . $formId . '&action=edit' );
	}

	/**
	 * Get form settings.
	 *
	 * @return array
	 */
	public function get_form_settings(): array {
		return CF7DoubleOptIn::getInstance()->getParameter( $this->get_cf_form_id() );
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Get the repository instance.
	 *
	 * @return OptInRepositoryInterface
	 */
	private static function getRepository(): OptInRepositoryInterface {
		$container = Container::getInstance();
		return $container->get( OptInRepositoryInterface::class );
	}

	/**
	 * Map legacy property names to entity array keys.
	 *
	 * @param array $properties Legacy properties.
	 *
	 * @return array
	 */
	private function mapToEntityArray( array $properties ): array {
		$mapping = [
			'cf_form_id'          => 'cf_form_id',
			'doubleoptin'         => 'doubleoptin',
			'content'             => 'content',
			'hash'                => 'hash',
			'createtime'          => 'createtime',
			'updatetime'          => 'updatetime',
			'optouttime'          => 'optouttime',
			'ipaddr_register'     => 'ipaddr_register',
			'ipaddr_confirmation' => 'ipaddr_confirmation',
			'ipaddr_optout'       => 'ipaddr_optout',
			'files'               => 'files',
			'category'            => 'category',
			'form'                => 'form',
			'mail_optin'          => 'mail_optin',
			'email'               => 'email',
			'id'                  => 'id',
			'consent_text'        => 'consent_text',
		];

		$result = [];
		foreach ( $mapping as $legacy => $entity ) {
			if ( isset( $properties[ $legacy ] ) ) {
				$result[ $entity ] = $properties[ $legacy ];
			}
		}

		return $result;
	}
}
