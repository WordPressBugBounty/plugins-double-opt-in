<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class UIPage
	 */
	abstract class UIPage {
		/**
		 * @var string
		 */
		protected $domain;
		/**
		 * @var string
		 */
		protected $slug;
		/**
		 * @var string
		 */
		protected $title;
		/**
		 * @var string
		 */
		protected $class;
		/**
		 * @var int
		 */
		protected $position = 0;
		/**
		 * @var TemplateHandler
		 */
		protected TemplateHandler $TemplateHandler;
		private LoggerInterface $logger;

		/**
		 * __construct method initializes an instance of the class.
		 *
		 * @param TemplateHandler $templateHandler - The TemplateHandler object used for handling templates.
		 * @param string          $domain          - The domain of the current page.
		 * @param string          $slug            - The WordPress slug used for the current page.
		 * @param string          $title           - The title of the sidebar.
		 * @param int             $position        - (optional) The position of the sidebar. Default is 10.
		 * @param string          $class           - (optional) The class of the sidebar. Default is an empty string.
		 *
		 * @return void
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $domain, string $slug, string $title, int $position = 10, string $class = '' ) {
			$this->logger          = $logger;
			$this->TemplateHandler = $templateHandler;
			$this->domain          = $domain;
			$this->slug            = $slug;
			$this->title           = $title;
			$this->class           = $class;
			$this->position        = $position;

			$this->get_logger()->info( 'UIPage class instance created.', [
				'plugin'   => $this->domain,
				'slug'     => $this->slug,
				'title'    => $this->title,
				'position' => $this->position,
			] );

			add_filter( 'f12_cf7_doubleoptin_settings', array( $this, 'getSettings' ) );
			$this->get_logger()->debug( 'Added "getSettings" method to "f12_cf7_doubleoptin_settings" filter.', [
				'plugin' => $this->domain,
			] );
		}

		public function get_logger() {
			return $this->logger;
		}

		public function hideInMenu() {
			return false;
		}

		public function getPosition() {
			return $this->position;
		}

		public function isDashboard() {
			return $this->getPosition() == 0;
		}

		public function getDomain() {
			return $this->domain;
		}

		public function getSlug() {
			return $this->slug;
		}

		public function getTitle() {
			return $this->title;
		}

		public function getClass() {
			return $this->class;
		}

		/**
		 * @param $settings
		 *
		 * @return mixed
		 */
		public abstract function getSettings( $settings );

		/**
		 * @param string $slug - The WordPress Slug
		 * @param string $page - The Name of the current Page e.g.: license
		 *
		 * @return void
		 */
		protected abstract function theSidebar( $slug, $page );

		/**
		 * @param string $slug - The WordPress Slug
		 * @param string $page - The Name of the current Page e.g.: license
		 *
		 * @return void
		 */
		protected abstract function theContent( $slug, $page, $settings );

		/**
		 * @return void
		 * @private WordPress HOOK
		 */
		public function renderContent( $slug, $page ) {
			$this->get_logger()->info( 'Attempting to render content for page: ' . $page, [
				'plugin' => $this->domain,
				'handler_slug' => $this->slug,
			] );

			if ( $this->slug != $page ) {
				$this->get_logger()->debug( 'Skipping content rendering because the provided page slug does not match the handler\'s slug.', [
					'plugin' => $this->domain,
					'provided_slug' => $page,
					'handler_slug' => $this->slug,
				] );
				return;
			}

			$settings = CF7DoubleOptIn::getInstance()->getSettings();

			// Output all pending messages (e.g., success, error)
			$messages_html = Messages::getInstance()->getAll();
			if (!empty($messages_html)) {
				$this->get_logger()->notice( 'Rendering message box with content.', [
					'plugin' => $this->domain,
				] );
			}
			echo wp_kses_post( $messages_html );

			// Trigger actions before the main content box
			$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $page . '_before_box" action.', [
				'plugin' => $this->domain,
			] );
			do_action( 'f12_cf7_doubleoptin_ui_' . $page . '_before_box' );

			// Start of the main content box
			?>
            <div class="box">
				<?php
				// Trigger actions before the main content
				$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $page . '_before_content" action.', [
					'plugin' => $this->domain,
				] );
				do_action( 'f12_cf7_doubleoptin_ui_' . $page . '_before_content', $settings );

				// Render the actual page content
				$this->get_logger()->info( 'Calling theContent() method to render the page\'s main content.', [
					'plugin' => $this->domain,
				] );
				$this->theContent( $slug, $page, $settings );

				// Trigger actions after the main content
				$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $page . '_after_content" action.', [
					'plugin' => $this->domain,
				] );
				do_action( 'f12_cf7_doubleoptin_ui_' . $page . '_after_content', $settings );
				?>
            </div>
			<?php

			// Trigger actions after the main content box
			$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_ui_' . $page . '_after_box" action.', [
				'plugin' => $this->domain,
			] );
			do_action( 'f12_cf7_doubleoptin_ui_' . $page . '_after_box' );

			$this->get_logger()->notice( 'Content rendering for page ' . $page . ' completed successfully.', [
				'plugin' => $this->domain,
			] );
		}

		/**
		 * @param string $slug
		 * @param string $page
		 *
		 * @return void
		 * @private WordPress Hook
		 */
		public function renderSidebar( $slug, $page ) {
			$this->get_logger()->info( 'Attempting to render sidebar content for page: ' . $page, [
				'plugin' => $this->domain,
				'handler_slug' => $this->slug,
			] );

			// Check if the current page slug matches the one handled by this class
			if ( $this->slug != $page ) {
				$this->get_logger()->debug( 'Skipping sidebar rendering because the provided page slug does not match the handler\'s slug.', [
					'plugin' => $this->domain,
					'provided_slug' => $page,
					'handler_slug' => $this->slug,
				] );
				return;
			}

			$this->get_logger()->info( 'Calling theSidebar() method to render the page\'s sidebar content.', [
				'plugin' => $this->domain,
			] );

			// Call the method responsible for generating the sidebar's content
			$this->theSidebar( $slug, $page );

			$this->get_logger()->notice( 'Sidebar rendering for page ' . $page . ' completed successfully.', [
				'plugin' => $this->domain,
			] );
		}
	}
}