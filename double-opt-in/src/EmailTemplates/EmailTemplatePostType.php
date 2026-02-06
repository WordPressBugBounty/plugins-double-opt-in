<?php
/**
 * Email Template Custom Post Type
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplatePostType
 *
 * Registers and manages the doi_email_template custom post type.
 */
class EmailTemplatePostType {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'doi_email_template';

	/**
	 * Meta key for blocks JSON.
	 */
	const META_BLOCKS_JSON = '_doi_blocks_json';

	/**
	 * Meta key for global styles.
	 */
	const META_GLOBAL_STYLES = '_doi_global_styles';

	/**
	 * Meta key for thumbnail.
	 */
	const META_THUMBNAIL = '_doi_thumbnail';

	/**
	 * Initialize the custom post type.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'init', [ $this, 'registerMeta' ] );
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public function register(): void {
		$labels = [
			'name'                  => _x( 'Email Templates', 'Post type general name', 'double-opt-in' ),
			'singular_name'         => _x( 'Email Template', 'Post type singular name', 'double-opt-in' ),
			'menu_name'             => _x( 'Email Templates', 'Admin Menu text', 'double-opt-in' ),
			'name_admin_bar'        => _x( 'Email Template', 'Add New on Toolbar', 'double-opt-in' ),
			'add_new'               => __( 'Add New', 'double-opt-in' ),
			'add_new_item'          => __( 'Add New Email Template', 'double-opt-in' ),
			'new_item'              => __( 'New Email Template', 'double-opt-in' ),
			'edit_item'             => __( 'Edit Email Template', 'double-opt-in' ),
			'view_item'             => __( 'View Email Template', 'double-opt-in' ),
			'all_items'             => __( 'All Email Templates', 'double-opt-in' ),
			'search_items'          => __( 'Search Email Templates', 'double-opt-in' ),
			'parent_item_colon'     => __( 'Parent Email Templates:', 'double-opt-in' ),
			'not_found'             => __( 'No email templates found.', 'double-opt-in' ),
			'not_found_in_trash'    => __( 'No email templates found in Trash.', 'double-opt-in' ),
			'featured_image'        => _x( 'Email Template Cover Image', 'Overrides the "Featured Image" phrase', 'double-opt-in' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'double-opt-in' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'double-opt-in' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'double-opt-in' ),
			'archives'              => _x( 'Email Template archives', 'The post type archive label', 'double-opt-in' ),
			'insert_into_item'      => _x( 'Insert into email template', 'Overrides the "Insert into post" phrase', 'double-opt-in' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this email template', 'Overrides the "Uploaded to this post" phrase', 'double-opt-in' ),
			'filter_items_list'     => _x( 'Filter email templates list', 'Screen reader text', 'double-opt-in' ),
			'items_list_navigation' => _x( 'Email templates list navigation', 'Screen reader text', 'double-opt-in' ),
			'items_list'            => _x( 'Email templates list', 'Screen reader text', 'double-opt-in' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false, // We use custom UI
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title' ],
			'show_in_rest'       => true,
			'rest_base'          => 'doi-email-templates',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register post meta fields.
	 *
	 * @return void
	 */
	public function registerMeta(): void {
		register_post_meta( self::POST_TYPE, self::META_BLOCKS_JSON, [
			'type'              => 'string',
			'description'       => 'Block structure as JSON',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => [ self::class, 'sanitizeJson' ],
			'auth_callback'     => function() {
				return current_user_can( 'manage_options' );
			},
		] );

		register_post_meta( self::POST_TYPE, self::META_GLOBAL_STYLES, [
			'type'              => 'string',
			'description'       => 'Global styles (fonts, colors)',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => [ self::class, 'sanitizeJson' ],
			'auth_callback'     => function() {
				return current_user_can( 'manage_options' );
			},
		] );

		register_post_meta( self::POST_TYPE, self::META_THUMBNAIL, [
			'type'              => 'string',
			'description'       => 'Preview thumbnail (Base64 or URL)',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function() {
				return current_user_can( 'manage_options' );
			},
		] );
	}

	/**
	 * Sanitize JSON input.
	 *
	 * @param string $value The value to sanitize.
	 * @return string Sanitized JSON string.
	 */
	public static function sanitizeJson( $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// If it's already a valid JSON string, return it as-is
		// We trust the input since it comes from our own code
		$decoded = json_decode( $value, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			// Valid JSON - return the original to preserve formatting
			return $value;
		}

		// Invalid JSON - log and return empty
		error_log( 'DOI sanitizeJson: Invalid JSON - ' . json_last_error_msg() );
		error_log( 'DOI sanitizeJson: First 200 chars: ' . substr( $value, 0, 200 ) );

		return '';
	}
}
