<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 *
	 */
	class UIMenu {
		private LoggerInterface $logger;

		/**
		 * UI constructor.
		 *
		 * @param $slug
		 */
		public function __construct( LoggerInterface $logger ) {
			$this->logger = $logger;
			$this->get_logger()->info( 'UIMenu class instance created. Registering menu renderer.', [
				'plugin' => 'double-opt-in',
			] );

			add_action( 'f12_cf7_doubleoptin_admin_menu', array( $this, 'render' ), 10, 3 );
			$this->get_logger()->debug( 'Added "render" method to "f12_cf7_doubleoptin_admin_menu" action.', [
				'plugin' => 'double-opt-in',
			] );
		}

		public function get_logger() {
			return $this->logger;
		}

		/**
		 *
		 * @param array<UIPage> $Pages
		 * @param string        $active_slug
		 *
		 * @return void
		 */
		public function render( $Pages, $active_slug, $plugin_slug ) {
			$this->get_logger()->info( 'Rendering the admin menu navigation.', [
				'plugin' => $plugin_slug,
				'active_slug' => $active_slug,
			] );

			if ( ! is_array( $Pages ) ) {
				$this->get_logger()->warning( 'Provided pages variable is not an array. Converting to an array.', [
					'plugin' => $plugin_slug,
				] );
				$Pages = array( $Pages );
			}

			// Begin HTML output
			?>
            <nav class="navbar">
                <ul class="navbar-nav">
					<?php
					$this->get_logger()->info( 'Triggering "before-forge12-plugin-menu-' . $plugin_slug . '" action.', [
						'plugin' => $plugin_slug,
					] );
					do_action( 'before-forge12-plugin-menu-' . $plugin_slug );
					?>
					<?php foreach ( $Pages as /** @var UIPage $Page */ $Page ): ?>
                        <li class="forge12-plugin-menu-item">
							<?php
							$this->get_logger()->debug( 'Processing menu item for page: ' . $Page->getSlug(), [
								'plugin' => $plugin_slug,
							] );

							$class = '';
							$slug = $plugin_slug . '_' . $Page->getSlug();

							if ( $Page->isDashboard() ) {
								$slug = $plugin_slug;
							}

							if ( $Page->getSlug() == $active_slug || ( $Page->isDashboard() && empty( $active_slug ) ) ) {
								$class = 'active';
								$this->get_logger()->notice( 'Setting menu item "' . $Page->getSlug() . '" as active.', [
									'plugin' => $plugin_slug,
								] );
							}
							?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>?page=<?php echo esc_attr( $slug ); ?>"
                               title="<?php echo esc_attr( $Page->getTitle() ); ?>"
                               class="<?php echo esc_attr( $class ) . ' ' . esc_attr( $Page->getClass() ); ?>">
								<?php echo esc_html( $Page->getTitle() ); ?>
                            </a>
                        </li>
					<?php endforeach; ?>
					<?php
					$this->get_logger()->info( 'Triggering "after-forge12-plugin-menu-' . $plugin_slug . '" action.', [
						'plugin' => $plugin_slug,
					] );
					do_action( 'after-forge12-plugin-menu-' . $plugin_slug );
					?>
                </ul>
            </nav>
			<?php
			$this->get_logger()->notice( 'Menu navigation rendering complete.', [
				'plugin' => $plugin_slug,
			] );
		}
	}

	new UIMenu( Logger::getInstance() );
}