<?php
/**
 * REST routes for the in-plugin help.
 *
 * `GET f12-doi/v1/help`         table of contents
 * `GET f12-doi/v1/help/<id>`    one article rendered to HTML
 *
 * Both require `manage_options`; the REST cookie nonce is the CSRF protection.
 * Read-only, nothing is written.
 *
 * @package Forge12\DoubleOptIn\Help
 * @since   5.11.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Help;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpRestController {

	public const API_NAMESPACE = 'f12-doi/v1';

	/** @var HelpSourceLocator */
	private $locator;

	public function __construct( HelpSourceLocator $locator ) {
		$this->locator = $locator;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	public function checkPermission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function registerRoutes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/help',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'listArticles' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
		register_rest_route(
			self::API_NAMESPACE,
			'/help/(?P<id>[a-z0-9-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getArticle' ),
				'permission_callback' => array( $this, 'checkPermission' ),
			)
		);
	}

	public function listArticles(): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array( 'articles' => $this->repository()->listArticles() ),
			),
			200
		);
	}

	public function getArticle( \WP_REST_Request $request ): \WP_REST_Response {
		$article = $this->repository()->getArticle( (string) $request->get_param( 'id' ) );
		if ( null === $article ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'This help article does not exist.', 'double-opt-in' ),
					'code'    => 'HELP_NOT_FOUND',
				),
				404
			);
		}
		// The HTML is produced by MarkdownRenderer from files shipped with the
		// plugins; kses is a second wall in case a shipped file is ever wrong.
		$article['html'] = wp_kses_post( $article['html'] );
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $article,
			),
			200
		);
	}

	private function repository(): HelpRepository {
		return new HelpRepository( $this->locator->locate(), determine_locale() );
	}
}
