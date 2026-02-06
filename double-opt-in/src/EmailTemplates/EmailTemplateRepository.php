<?php
/**
 * Email Template Repository
 *
 * @package Forge12\DoubleOptIn\EmailTemplates
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\EmailTemplates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailTemplateRepository
 *
 * Repository for Email Template CRUD operations.
 */
class EmailTemplateRepository {

	/**
	 * Find all templates.
	 *
	 * @param array $args Optional. Query arguments.
	 * @return array Array of template data.
	 */
	public function findAll( array $args = [] ): array {
		$defaults = [
			'post_type'      => EmailTemplatePostType::POST_TYPE,
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$query_args = wp_parse_args( $args, $defaults );
		$posts = get_posts( $query_args );

		return array_map( [ $this, 'formatTemplate' ], $posts );
	}

	/**
	 * Find template by ID.
	 *
	 * @param int $id Template ID.
	 * @return array|null Template data or null if not found.
	 */
	public function findById( int $id ): ?array {
		$post = get_post( $id );

		if ( ! $post || $post->post_type !== EmailTemplatePostType::POST_TYPE ) {
			return null;
		}

		return $this->formatTemplate( $post );
	}

	/**
	 * Debug info from last create operation.
	 *
	 * @var array
	 */
	public array $lastCreateDebug = [];

	/**
	 * Create a new template.
	 *
	 * @param array $data Template data.
	 * @return int|false Template ID on success, false on failure.
	 */
	public function create( array $data ) {
		$this->lastCreateDebug = [
			'input_blocks_json_length' => strlen( $data['blocks_json'] ?? '' ),
			'input_blocks_json_preview' => substr( $data['blocks_json'] ?? '', 0, 200 ),
		];

		$post_data = [
			'post_type'    => EmailTemplatePostType::POST_TYPE,
			'post_title'   => sanitize_text_field( $data['title'] ?? __( 'Untitled Template', 'double-opt-in' ) ),
			'post_content' => wp_kses_post( $data['html'] ?? '' ),
			'post_status'  => sanitize_text_field( $data['status'] ?? 'draft' ),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			$this->lastCreateDebug['error'] = $post_id->get_error_message();
			return false;
		}

		$this->lastCreateDebug['post_id'] = $post_id;

		// Save meta data
		$this->saveMeta( $post_id, $data );

		// Verify what was saved
		$savedBlocks = get_post_meta( $post_id, EmailTemplatePostType::META_BLOCKS_JSON, true );
		$this->lastCreateDebug['saved_blocks_json_length'] = strlen( $savedBlocks );
		$this->lastCreateDebug['saved_blocks_json_preview'] = substr( $savedBlocks, 0, 200 );

		return $post_id;
	}

	/**
	 * Update an existing template.
	 *
	 * @param int   $id   Template ID.
	 * @param array $data Template data.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $id, array $data ): bool {
		$post = get_post( $id );

		if ( ! $post || $post->post_type !== EmailTemplatePostType::POST_TYPE ) {
			return false;
		}

		$post_data = [
			'ID' => $id,
		];

		if ( isset( $data['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $data['title'] );
		}

		if ( isset( $data['html'] ) ) {
			$post_data['post_content'] = wp_kses_post( $data['html'] );
		}

		if ( isset( $data['status'] ) ) {
			$post_data['post_status'] = sanitize_text_field( $data['status'] );
		}

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Save meta data
		$this->saveMeta( $id, $data );

		return true;
	}

	/**
	 * Delete a template.
	 *
	 * @param int  $id    Template ID.
	 * @param bool $force Optional. Whether to force delete (bypass trash). Default false.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id, bool $force = false ): bool {
		$post = get_post( $id );

		if ( ! $post || $post->post_type !== EmailTemplatePostType::POST_TYPE ) {
			return false;
		}

		$result = wp_delete_post( $id, $force );

		return $result !== false && $result !== null;
	}

	/**
	 * Duplicate a template.
	 *
	 * @param int $id Template ID to duplicate.
	 * @return int|false New template ID on success, false on failure.
	 */
	public function duplicate( int $id ) {
		$template = $this->findById( $id );

		if ( ! $template ) {
			return false;
		}

		$data = [
			'title'        => sprintf( __( '%s (Copy)', 'double-opt-in' ), $template['title'] ),
			'html'         => $template['html'],
			'status'       => 'draft',
			'blocks_json'  => $template['blocks_json'],
			'global_styles'=> $template['global_styles'],
		];

		return $this->create( $data );
	}

	/**
	 * Count published templates.
	 *
	 * @return int Number of published templates.
	 */
	public function countPublished(): int {
		$query = new \WP_Query( [
			'post_type'      => EmailTemplatePostType::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );

		return $query->found_posts;
	}

	/**
	 * Get templates for select dropdown.
	 *
	 * @return array Key-value pairs of template ID => title.
	 */
	public function getForSelect(): array {
		$templates = $this->findAll( [
			'post_status' => 'publish',
		] );

		$options = [];
		foreach ( $templates as $template ) {
			$options[ 'custom_' . $template['id'] ] = $template['title'];
		}

		return $options;
	}

	/**
	 * Save meta data for a template.
	 *
	 * @param int   $id   Template ID.
	 * @param array $data Template data.
	 * @return void
	 */
	private function saveMeta( int $id, array $data ): void {
		if ( isset( $data['blocks_json'] ) ) {
			$json = is_string( $data['blocks_json'] )
				? $data['blocks_json']
				: wp_json_encode( $data['blocks_json'] );

			// WordPress requires slashing for update_post_meta
			// See: https://developer.wordpress.org/reference/functions/update_post_meta/
			$slashed_json = wp_slash( $json );

			// Debug logging
			error_log( 'DOI saveMeta: blocks_json length=' . strlen( $json ) );

			$result = update_post_meta( $id, EmailTemplatePostType::META_BLOCKS_JSON, $slashed_json );
			error_log( 'DOI saveMeta: update_post_meta result=' . var_export( $result, true ) );

			// Verify save
			$saved = get_post_meta( $id, EmailTemplatePostType::META_BLOCKS_JSON, true );
			error_log( 'DOI saveMeta: verified saved length=' . strlen( $saved ) );
		}

		if ( isset( $data['global_styles'] ) ) {
			$json = is_string( $data['global_styles'] )
				? $data['global_styles']
				: wp_json_encode( $data['global_styles'] );
			update_post_meta( $id, EmailTemplatePostType::META_GLOBAL_STYLES, $json );
		}

		if ( isset( $data['thumbnail'] ) ) {
			update_post_meta( $id, EmailTemplatePostType::META_THUMBNAIL, sanitize_text_field( $data['thumbnail'] ) );
		}
	}

	/**
	 * Format a post object into template data array.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Formatted template data.
	 */
	private function formatTemplate( \WP_Post $post ): array {
		$blocks_json = get_post_meta( $post->ID, EmailTemplatePostType::META_BLOCKS_JSON, true );
		$global_styles = get_post_meta( $post->ID, EmailTemplatePostType::META_GLOBAL_STYLES, true );
		$thumbnail = get_post_meta( $post->ID, EmailTemplatePostType::META_THUMBNAIL, true );

		return [
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'html'          => $post->post_content,
			'status'        => $post->post_status,
			'blocks_json'   => $blocks_json ?: '[]',
			'global_styles' => $global_styles ?: '{}',
			'thumbnail'     => $thumbnail ?: '',
			'created_at'    => $post->post_date,
			'updated_at'    => $post->post_modified,
		];
	}
}
