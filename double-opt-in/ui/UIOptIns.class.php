<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIOptIns
	 *
	 * Displays the paginated opt-in list with search, filter, and delete actions.
	 * This page was extracted from UIDashboard so the dashboard can show statistics.
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 * @since   3.3.0
	 */
	class UIOptIns extends UIPage {

		/**
		 * Constructor.
		 *
		 * @param LoggerInterface $logger          The logger instance.
		 * @param TemplateHandler $templateHandler The template handler.
		 * @param string          $domain          The text domain.
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $domain ) {
			parent::__construct(
				$logger,
				$templateHandler,
				$domain,
				'optins',
				__( 'Opt-Ins', 'double-opt-in' ),
				1
			);
		}

		/** @inheritDoc */
		public function getSettings( $settings ) {
			return $settings;
		}

		/** @inheritDoc */
		protected function onSave( $settings ) {
			return $settings;
		}

		/** @inheritDoc */
		public function theContent( $slug, $page, $settings ) {
			$this->maybeDelete();
			$this->renderOptInList( $slug, $page );
		}

		/** @inheritDoc */
		public function theSidebar( $slug, $page ) {
			?>
			<div class="box">
				<h2><?php _e( 'Hint:', 'double-opt-in' ); ?></h2>
				<p>
					<?php _e( "Click on a hash to open additional information about the form submission and user data. Use the search and filter options to find specific entries.", 'double-opt-in' ); ?>
				</p>
			</div>
			<div class="box">
				<h2><?php _e( 'Hooks:', 'double-opt-in' ); ?></h2>
				<p><?php _e( 'You can use the following hooks to adjust the Opt-In to your requirements.', 'double-opt-in' ); ?></p>

				<p><strong><?php _e( 'An Opt-in link has been called which is already confirmed or has been deleted.', 'double-opt-in' ); ?></strong></p>
				<div class="option">
					<div class="input">
						<p>add_action('f12_cf7_doubleoptin_already_confirmed', $hash, $OptIn)</p>
					</div>
				</div>

				<p><strong><?php _e( 'An Opt-in link has been called but not yet updated the database.', 'double-opt-in' ); ?></strong></p>
				<div class="option">
					<div class="input">
						<p>add_action('f12_cf7_doubleoptin_before_confirm', $hash, $OptIn)</p>
					</div>
				</div>

				<p><strong><?php _e( 'An Opt-in link has been called and the database has been updated already.', 'double-opt-in' ); ?></strong></p>
				<div class="option">
					<div class="input">
						<p>add_action('f12_cf7_doubleoptin_after_confirm', $hash, $OptIn)</p>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Handle opt-in deletion requests.
		 */
		private function maybeDelete(): void {
			if ( isset( $_GET['option'] ) && $_GET['option'] === 'delete'
				&& isset( $_GET['hash'] ) && ! empty( $_GET['hash'] )
				&& current_user_can( 'manage_options' )
			) {
				$hash = sanitize_text_field( $_GET['hash'] );

				if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'doi-delete-optin-' . $hash ) ) {
					wp_die(
						__( 'Security check failed. Please try again.', 'double-opt-in' ),
						__( 'Security Error', 'double-opt-in' ),
						[ 'response' => 403 ]
					);
				}

				$CleanUp = new CleanUp( $this->get_logger() );

				if ( $CleanUp->deleteByHash( $hash ) ) {
					wp_redirect( admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins&status=deleted' ) );
					wp_die();
				} else {
					wp_redirect( admin_url( 'admin.php?page=f12-cf7-doubleoptin_optins&status=error' ) );
					wp_die();
				}
			} elseif ( isset( $_GET['status'] ) ) {
				$status = sanitize_text_field( $_GET['status'] );
				if ( $status === 'deleted' ) {
					Messages::getInstance()->add( __( 'The given DOI has been removed.', 'double-opt-in' ), 'success' );
				}
				if ( $status === 'error' ) {
					Messages::getInstance()->add( __( "Couldn't delete the DOI, please try again later.", 'double-opt-in' ), 'error' );
				}
			}
		}

		/**
		 * Render the paginated opt-in list.
		 *
		 * @param string $slug The page slug.
		 * @param string $page The current page.
		 */
		private function renderOptInList( string $slug, string $page ): void {
			$atts = [
				'perPage' => 10,
				'page'    => 1,
				'order'   => 'DESC',
				'keyword' => '',
			];

			if ( isset( $_GET['perPage'] ) ) {
				$atts['perPage'] = (int) $_GET['perPage'];
			}
			if ( isset( $_GET['pageNum'] ) ) {
				$atts['page'] = (int) $_GET['pageNum'];
			}
			if ( isset( $_GET['keyword'] ) ) {
				$atts['keyword'] = sanitize_text_field( $_GET['keyword'] );
			}
			if ( isset( $_GET['cf_form_id'] ) ) {
				$atts['cf_form_id'] = (int) $_GET['cf_form_id'];
			}

			$numberOfPages = 0;
			$listOfOptIns  = OptIn::get_list( $atts, $numberOfPages );

			?>
			<h1><?php _e( 'Opt-Ins', 'double-opt-in' ); ?></h1>
			<?php

			$this->TemplateHandler->renderTemplate( 'list-optins', [
				'domain'        => $this->domain,
				'listOfOptIns'  => $listOfOptIns,
				'numberOfPages' => $numberOfPages,
				'currentPage'   => $atts['page'],
				'slug'          => $slug,
			] );
		}
	}
}
