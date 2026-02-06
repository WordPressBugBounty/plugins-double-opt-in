<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Ajax
	 * Responsible to handle the admin settings for the double opt-in field
	 *
	 * @package forge12\contactform7\CF7OptIn
	 */
	class Ajax {
		private LoggerInterface $logger;

		/**
		 * Admin constructor.
		 */
		public function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;

			$this->get_logger()->debug( 'Initializing AJAX handlers', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );

			add_action( 'wp_ajax_f12_doi_details', [ $this, 'getDetails' ] );
			add_action( 'wp_ajax_f12_doi_templateloader', [ $this, 'getTemplate' ] );

			$this->get_logger()->info( 'AJAX handlers registered', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
			] );
		}


		public function get_logger() {
			return $this->logger;
		}

		/**
		 * Load and get the template we need.
		 */
		public function getTemplate() {
			$this->get_logger()->debug( 'getTemplate called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'post'   => $_POST,
			] );

			$content = '';
			if ( isset( $_POST['template'] ) && wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'f12_doi_templateloader' ) ) {
				// Use sanitize_file_name() to prevent path traversal attacks
				$template_name = sanitize_file_name( wp_unslash( $_POST['template'] ) );
				$mails_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'mails/';
				$template_path = $mails_dir . $template_name . '.html';

				// Verify the resolved path is within the allowed directory (prevent path traversal)
				$real_template_path = realpath( $template_path );
				$real_mails_dir = realpath( $mails_dir );

				if ( $real_template_path && $real_mails_dir && strpos( $real_template_path, $real_mails_dir ) === 0 && file_exists( $real_template_path ) ) {
					$this->get_logger()->debug( 'Template file found, loading content', [
						'plugin'        => 'double-opt-in',
						'class'         => __CLASS__,
						'method'        => __METHOD__,
						'template_path' => $template_path,
					] );
					$content = file_get_contents( $real_template_path );
				} else {
					$this->get_logger()->warning( 'Template file not found', [
						'plugin'        => 'double-opt-in',
						'class'         => __CLASS__,
						'method'        => __METHOD__,
						'template_path' => $template_path,
					] );
				}
			} else {
				$this->get_logger()->warning( 'Invalid or missing nonce/template parameter', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'post'   => $_POST,
				] );
			}

			echo wp_json_encode( [ 'status' => 200, 'content' => $content ] );
			wp_die();
		}


		/**
		 * Return the Popup for the HASH DOI
		 */
		public function getDetails() {
			$this->get_logger()->debug( 'getDetails called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'post'   => $_POST,
			] );

			if ( isset( $_POST['hash'] ) && wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'f12_doi_details' ) ) {
				global $wpdb;
				$tableName = $wpdb->prefix . 'f12_cf7_doubleoptin';
				$hash      = sanitize_text_field( $_POST['hash'] );

				$this->get_logger()->debug( 'Looking up OptIn by hash', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'hash'   => $hash,
				] );

				$OptIn = OptIn::get_by_hash( $hash );

				if ( null == $OptIn ) {
					$this->get_logger()->warning( 'OptIn not found for hash', [
						'plugin' => 'double-opt-in',
						'class'  => __CLASS__,
						'method' => __METHOD__,
						'hash'   => $hash,
					] );

					ob_start();
					?>
                    <h2><?php _e( 'Ooops!', 'double-opt-in' ); ?></h2>
                    <p>
						<?php _e( 'Something went wrong. The given DOI wasn\'t found. Maybe it got removed?', 'double-opt-in' ); ?>
                    </p>
					<?php
					$content = ob_get_contents();
					ob_end_clean();
					echo wp_json_encode( [ 'status' => 200, 'content' => $content ] );
					wp_die();
				}

				$this->get_logger()->info( 'OptIn found', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'optin'  => [
						'id'    => $OptIn->get_id(),
						'hash'  => $OptIn->get_hash(),
						'email' => $OptIn->get_email(),
					],
				] );

				$formfields = maybe_unserialize( $OptIn->get_content() );
				ob_start();
				?>
                <h2><?php echo esc_html( $OptIn->get_hash() ); ?></h2>
				<?php if ( current_user_can( 'manage_options' ) ): ?>
                    <div class="options">
						<?php
						do_action( 'f12_cf7_doubleoptin_ui_view_optin_options', $OptIn );
						?>
                        <a class="button"
                           href="<?php echo esc_url( $OptIn->get_link_delete() ); ?>"><?php _e( 'Delete DOI', 'double-opt-in' ); ?></a>
                        <a class="button"
                           href="<?php echo esc_url( $OptIn->get_link_ui() ); ?>"><?php _e( 'Details', 'double-opt-in' ); ?></a>
                    </div>
				<?php endif; ?>
                <table>
                    <tr>
                        <td><?php _e( 'Key', 'double-opt-in' ); ?></td>
                        <td><?php _e( 'Value', 'double-opt-in' ); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e( 'ID', 'double-opt-in' ); ?></td>
                        <td><?php echo esc_html( $OptIn->get_id() ); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e( 'CF7 Form ID', 'double-opt-in' ); ?></td>
                        <td><?php echo esc_html( $OptIn->get_cf_form_id() ); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e( 'Registration Date', 'double-opt-in' ); ?></td>
                        <td><?php echo esc_html( $OptIn->get_createtime( 'formatted' ) ); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e( 'Registration IP', 'double-opt-in' ); ?></td>
                        <td><?php echo esc_html( $OptIn->get_ipaddr_register() ); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e( 'Confirmation Date', 'double-opt-in' ); ?></td>
                        <td><?php if ( $OptIn->is_confirmed() ) {
								echo esc_html( $OptIn->get_updatetime( 'formatted' ) );
							} ?></td>
                    </tr>
                    <tr>
                        <td><?php _e( 'Confirmation IP', 'double-opt-in' ); ?></td>
                        <td><?php echo esc_html( $OptIn->get_ipaddr_confirmation() ); ?></td>
                    </tr>
                </table>

                <h3><?php _e( 'Form Fields', 'double-opt-in' ); ?></h3>
                <table>
                    <tr>
                        <td><?php _e( 'Key', 'double-opt-in' ); ?></td>
                        <td><?php _e( 'Value', 'double-opt-in' ); ?></td>
                    </tr>
					<?php if ( isset( $formfields['fields'] ) ): ?>
						<?php foreach ( $formfields['fields'] as $key => $value ): ?>
                            <tr>
                                <td><?php esc_attr_e( $key ); ?></td>
                                <td><?php echo is_array( $value ) ? esc_html( implode( ',', $value ) ) : esc_html( $value ); ?></td>
                            </tr>
						<?php endforeach; ?>
					<?php else: ?>
						<?php foreach ( $formfields as $key => $value ): ?>
                            <tr>
                                <td><?php esc_attr_e( $key ); ?></td>
                                <td><?php echo is_array( $value ) ? esc_html( implode( ',', $value ) ) : esc_html( $value ); ?></td>
                            </tr>
						<?php endforeach; ?>
					<?php endif; ?>
                </table>
				<?php
				$content = ob_get_contents();
				ob_end_clean();
				echo wp_json_encode( [ 'status' => 200, 'content' => $content ] );
				exit;
			}

			$this->get_logger()->warning( 'Invalid request for getDetails (missing or invalid nonce/hash)', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'post'   => $_POST,
			] );

			wp_die( 0 );
		}


		/**
		 * Add the styles for the form
		 */
		public function addStyles( $hook ) {
			$this->get_logger()->debug( 'addStyles called', [
				'plugin' => 'double-opt-in',
				'class'  => __CLASS__,
				'method' => __METHOD__,
				'hook'   => $hook,
			] );

			if ( $hook == 'tools_page_f12doubleoptin' ) {
				wp_enqueue_style( 'f12-cf7-doubleoptin-admin', plugins_url( 'assets/admin-style.css', __FILE__ ) );
				wp_enqueue_script( 'f12-cf7-doubleoptin-admin', plugins_url( 'assets/f12-cf7-popup.js', __FILE__ ), [ 'jquery' ] );

				$this->get_logger()->info( 'Admin styles and scripts enqueued for DOI tools page', [
					'plugin' => 'double-opt-in',
					'class'  => __CLASS__,
					'method' => __METHOD__,
					'hook'   => $hook,
				] );
			}
		}

	}

	new Ajax( Logger::getInstance() );
}