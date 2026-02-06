<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}
	/**
	 * dependencies
	 */
	require_once( 'UIMenu.class.php' );

	/**
	 * Class UI
	 *
	 * @package forge12\ui
	 */
	class UI {
		/**
		 * @var UI
		 */
		private static $instance = null;

		/**
		 * @var string
		 */
		private $slug = '';
		/**
		 * @var string
		 */
		private $title = '';
		/**
		 * @var string
		 */
		private $capabilities;
		/**
		 * Store all Pages
		 *
		 * @var array<UIPage>
		 */
		private $pages;
		/**
		 * Components
		 */
		private $components = [];
		/**
		 * @var TemplateHandler
		 */
		private TemplateHandler $templateHandler;
		private LoggerInterface $logger;

		/**
		 * UI constructor.
		 *
		 * @param $slug
		 */
		public function __construct( LoggerInterface $logger, TemplateHandler $templateHandler, string $slug, string $title, string $capabilities ) {
			$this->logger = $logger;
			$this->templateHandler = $templateHandler;
			$this->slug = $slug;
			$this->title = $title;
			$this->capabilities = $capabilities;

			$this->get_logger()->info( 'UI class instance created.', [
				'plugin' => $this->slug,
				'slug' => $this->slug,
				'title' => $this->title,
				'capabilities' => $this->capabilities,
			] );

			$this->loadComponents();
			$this->get_logger()->debug( 'UI components loaded.', ['plugin' => $this->slug] );

			// Add Assets
			add_action( 'admin_enqueue_scripts', array( $this, 'addAssets' ) );
			$this->get_logger()->debug( 'Added "addAssets" action to "admin_enqueue_scripts".', ['plugin' => $this->slug] );

			// Load Components after the Pages have been initialized
			add_action( 'f12_cf7_doubleoptin_ui_after_load_pages', array( $this, 'registerComponents' ), 999999990, 2 );
			$this->get_logger()->debug( 'Added "registerComponents" action to "f12_cf7_doubleoptin_ui_after_load_pages".', ['plugin' => $this->slug] );

			// Sort Pages after intialize
			add_action( 'f12_cf7_doubleoptin_ui_after_load_pages', array( $this, 'sortComponents' ), 999999999, 2 );
			$this->get_logger()->debug( 'Added "sortComponents" action to "f12_cf7_doubleoptin_ui_after_load_pages".', ['plugin' => $this->slug] );

			add_action( 'init', function () {
				$this->get_logger()->debug( 'Triggering "f12_cf7_doubleoptin_ui_after_load_pages" action.', ['plugin' => $this->slug] );
				do_action( 'f12_cf7_doubleoptin_ui_after_load_pages', $this, $this->slug );
			} );

			// Create the Submenus
			add_action( 'admin_menu', array( $this, 'addSubmenuPagesToWordPress' ) );
			$this->get_logger()->debug( 'Added "addSubmenuPagesToWordPress" action to "admin_menu".', ['plugin' => $this->slug] );

			// Hotfix to hide the submenus that should be hidden but still callable in wordpress backend
			add_action( 'admin_head', array( $this, 'hideSubmenuPagesToWordpress' ) );
			$this->get_logger()->debug( 'Added "hideSubmenuPagesToWordpress" action to "admin_head".', ['plugin' => $this->slug] );

			$this->get_logger()->notice( 'UI initialization complete.', ['plugin' => $this->slug] );
		}

		public function get_logger() {
			return $this->logger;
		}


		/**
		 * Add a Page to the UI
		 *
		 * @param $UIPage
		 *
		 * @return void
		 */
		public function addPage( $UIPage ) {
			$this->get_logger()->info( 'Adding a new page to the UI.', [
				'plugin'   => $this->slug,
				'page_class' => get_class( $UIPage ),
			] );

			$this->pages[] = $UIPage;

			// Add the renderContent action
			add_action( 'forge12-plugin-content-' . $this->slug, array( $UIPage, 'renderContent' ), 10, 2 );
			$this->get_logger()->debug( 'Added "renderContent" action for page: ' . get_class( $UIPage ), [
				'plugin' => $this->slug,
				'action_hook' => 'forge12-plugin-content-' . $this->slug,
			] );

			// Add the renderSidebar action
			add_action( 'forge12-plugin-sidebar-' . $this->slug, array( $UIPage, 'renderSidebar' ), 10, 2 );
			$this->get_logger()->debug( 'Added "renderSidebar" action for page: ' . get_class( $UIPage ), [
				'plugin' => $this->slug,
				'action_hook' => 'forge12-plugin-sidebar-' . $this->slug,
			] );

			$this->get_logger()->notice( 'Page successfully added to the UI.', [
				'plugin' => $this->slug,
				'total_pages' => count($this->pages),
			] );
		}

		/**
		 * @return array<UIPage>
		 */
		private function getPages() {
			$this->get_logger()->info( 'Retrieving the list of registered UI pages.', [
				'plugin' => $this->slug,
			] );

			// Check if the pages array is empty before returning.
			if ( empty($this->pages) ) {
				$this->get_logger()->warning( 'The list of UI pages is empty.', [
					'plugin' => $this->slug,
				] );
			} else {
				$this->get_logger()->notice( 'Returning ' . count($this->pages) . ' registered UI pages.', [
					'plugin' => $this->slug,
					'page_count' => count($this->pages),
				] );
			}

			return $this->pages;
		}

		/**
		 * @param $slug
		 *
		 * @return UIPage|null
		 */
		private function get( $slug ) {
			$this->get_logger()->info( 'Attempting to retrieve a UI page by its slug.', [
				'plugin' => $this->slug,
				'requested_slug' => $slug,
			] );

			foreach ( $this->pages as $UIPage ) {
				if ( $UIPage->getSlug() == $slug ) {
					$this->get_logger()->notice( 'UI page with slug "' . $slug . '" found.', [
						'plugin' => $this->slug,
						'found_slug' => $slug,
						'page_class' => get_class($UIPage),
					] );
					return $UIPage;
				}
			}

			$this->get_logger()->warning( 'No UI page found for the requested slug "' . $slug . '".', [
				'plugin' => $this->slug,
				'requested_slug' => $slug,
			] );

			return null;
		}


		public function addAssets() {
			$this->get_logger()->info( 'Enqueuing admin assets.', [
				'plugin' => $this->slug,
			] );

			/**
			 * Stylesheets for the admin panel
			 */
			wp_enqueue_style( 'f12-cf7-doubleoptin-admin', plugins_url( 'assets/admin-style.css', __FILE__ ), array(), '1.3' );
			$this->get_logger()->debug( 'Enqueued stylesheet: f12-cf7-doubleoptin-admin', [
				'plugin' => $this->slug,
			] );

			/**
			 * Toggle Button
			 */
			wp_enqueue_script( 'f12-cf7-doubleoptin-toggle', plugins_url( 'assets/toggle.js', __FILE__ ), array( 'jquery' ), '1.0' );
			$this->get_logger()->debug( 'Enqueued script: f12-cf7-doubleoptin-toggle', [
				'plugin' => $this->slug,
			] );

			/**
			 * Copy to clipboard
			 */
			wp_enqueue_script( 'f12-cf7-doubleoptin-copy', plugins_url( 'assets/copy-to-clipboard.js', __FILE__ ), array( 'jquery' ), '1.0' );
			$this->get_logger()->debug( 'Enqueued script: f12-cf7-doubleoptin-copy', [
				'plugin' => $this->slug,
			] );

			/**
			 * External vendor scripts
			 */
			wp_enqueue_script( 'vendor-highlight', plugins_url( 'assets/vendor/highlight/highlight.min.js', __FILE__ ), array( 'jquery' ), '1.0' );
			$this->get_logger()->debug( 'Enqueued vendor script: vendor-highlight', [
				'plugin' => $this->slug,
			] );

			wp_enqueue_style( 'vendor-highlight', plugins_url( 'assets/vendor/highlight/default.min.css', __FILE__ ), array(), '1.0' );
			$this->get_logger()->debug( 'Enqueued vendor stylesheet: vendor-highlight', [
				'plugin' => $this->slug,
			] );

			$this->get_logger()->notice( 'All admin assets have been enqueued successfully.', [
				'plugin' => $this->slug,
			] );
		}

		public function sortComponents( $UI, $domain ) {
			$this->get_logger()->info( 'Sorting UI pages based on their position.', [
				'plugin' => $this->slug,
				'domain' => $domain,
			] );

			if ( ! empty( $this->pages ) ) {
				$this->get_logger()->debug( 'Pages array is not empty. Proceeding with sort.', [
					'plugin' => $this->slug,
					'initial_page_order' => array_map(function($page) {
						return ['slug' => $page->getSlug(), 'position' => $page->getPosition()];
					}, $this->pages),
				] );

				usort( $this->pages, function ( $a, $b ) {
					if ( $a->getPosition() < $b->getPosition() ) {
						return - 1;
					} else if ( $a->getPosition() > $b->getPosition() ) {
						return 1;
					} else {
						return 0;
					}
				} );

				$this->get_logger()->notice( 'UI pages have been successfully sorted.', [
					'plugin' => $this->slug,
					'sorted_page_order' => array_map(function($page) {
						return ['slug' => $page->getSlug(), 'position' => $page->getPosition()];
					}, $this->pages),
				] );
			} else {
				$this->get_logger()->warning( 'Pages array is empty. Nothing to sort.', [
					'plugin' => $this->slug,
				] );
			}
		}

		/**
		 * @param UI $UI
		 * @param    $domain
		 *
		 * @return void
		 */
		public function registerComponents( $UI, $domain ) {
			$this->get_logger()->info( 'Starting to register UI components.', [
				'plugin' => $this->slug,
				'domain' => $domain,
			] );

			/**
			 * Load all registered components
			 */
			foreach ( $this->components as $index => $component ) {
				$this->get_logger()->debug( 'Processing component at index ' . $index, [
					'plugin'    => $this->slug,
					'component' => $component,
				] );

				/**
				 * Check if the path has been defined
				 */
				if ( ! isset( $component['path'] ) ) {
					$this->get_logger()->error( 'Component path not defined. Skipping component at index ' . $index . '.', [
						'plugin' => $this->slug,
					] );
					continue;
				}

				/**
				 * Check if the name has been defined
				 */
				if ( ! isset( $component['name'] ) ) {
					$this->get_logger()->error( 'Component name not defined. Skipping component at index ' . $index . '.', [
						'plugin' => $this->slug,
					] );
					continue;
				}

				/**
				 * Load the file
				 */
				$component_path = $component['path'];
				if ( ! file_exists( $component_path ) ) {
					$this->get_logger()->error( 'Component file does not exist. Skipping.', [
						'plugin' => $this->slug,
						'path'   => $component_path,
					] );
					continue;
				}

				require_once( $component_path );
				$this->get_logger()->debug( 'Component file loaded successfully.', [
					'plugin' => $this->slug,
					'path'   => $component_path,
				] );

				/**
				 * Load the instance of the object
				 */
				$component_name = $component['name'];
				if ( ! class_exists( $component_name ) ) {
					$this->get_logger()->error( 'Component class "' . $component_name . '" not found after including the file. Skipping.', [
						'plugin' => $this->slug,
						'name'   => $component_name,
					] );
					continue;
				}

				$UIPage = new $component_name($this->get_logger(), $this->templateHandler, $domain );
				$this->get_logger()->debug( 'Component class instantiated.', [
					'plugin' => $this->slug,
					'class'  => $component_name,
				] );

				/**
				 * Add the page to the user interface in the backend of wordpress.
				 */
				$UI->addPage( $UIPage );
				$this->get_logger()->notice( 'Component "' . $component_name . '" successfully registered and added as a page.', [
					'plugin' => $this->slug,
				] );
			}

			$this->get_logger()->info( 'Finished registering all UI components.', [
				'plugin' => $this->slug,
			] );
		}

		private function addComponent( $name, $path ) {
			$this->get_logger()->info( 'Adding a new component to the registration list.', [
				'plugin' => $this->slug,
				'component_name' => $name,
				'component_path' => $path,
			] );

			$this->components[] = array(
				'name' => $name,
				'path' => $path
			);

			$this->get_logger()->notice( 'Component "' . $name . '" successfully added.', [
				'plugin' => $this->slug,
				'total_components' => count($this->components),
			] );
		}

		/**
		 * Retrieves the directories where the components are located.
		 *
		 * This method is responsible for retrieving the directories where the components
		 * for the current application are located. It uses the 'f12_cf7_doubleoption_ui_get_component_directories'
		 * filter to allow customization of the directories. By default, it returns an array
		 * containing the 'ui' directory located two levels above the current file's directory.
		 *
		 * @return array An array containing the directories where the components are located.
		 */
		private function get_component_directories() {
			$this->get_logger()->info( 'Retrieving component directories via filter.', [
				'plugin' => $this->slug,
			] );

			/**
			 * Component Directories
			 *
			 * This filter allows developers to add custom ui directories which will be used to display ui pages.
			 *
			 * @param array $directories
			 *
			 * @since 3.0.0
			 */
			$default_directory = dirname( __FILE__, 2 ) . '/ui';
			$this->get_logger()->debug( 'Default component directory: ' . $default_directory, [
				'plugin' => $this->slug,
			] );

			$directories = apply_filters( 'f12_cf7_doubleoption_ui_get_component_directories', [ $default_directory ] );

			$this->get_logger()->notice( 'Component directories retrieved successfully.', [
				'plugin'     => $this->slug,
				'directories' => $directories,
			] );

			return $directories;
		}

		/**
		 * Load all components from the 'ui' directory.
		 *
		 * @return void
		 */
		private function loadComponents() {
			$this->get_logger()->info( 'Starting to load UI components from registered directories.', [
				'plugin' => $this->slug,
			] );

			$directories = $this->get_component_directories();

			foreach ( $directories as $directory ) {
				$this->get_logger()->debug( 'Processing directory: ' . $directory, [
					'plugin' => $this->slug,
				] );

				if ( is_dir( $directory ) ) {
					$handle = opendir( $directory );

					if ( ! $handle ) {
						$this->get_logger()->error( 'Failed to open directory. Skipping.', [
							'plugin' => $this->slug,
							'directory' => $directory,
						] );
						continue; // Continue to the next directory instead of returning
					}

					while ( false !== ( $entry = readdir( $handle ) ) ) {
						if ( $entry != '.' && $entry != '..' ) {
							$this->get_logger()->debug( 'Found directory entry: ' . $entry, [
								'plugin' => $this->slug,
							] );

							if ( preg_match( '!UI([a-zA-Z_0-9]+)\.class\.php!', $entry, $matches ) ) {
								if ( isset( $matches[1] ) ) {
									$component_name = '\\' . __NAMESPACE__ . '\UI' . $matches[1];
									$component_path = $directory . '/' . $entry;

									$this->get_logger()->info( 'Found a valid UI component file.', [
										'plugin' => $this->slug,
										'file' => $entry,
										'component_name' => $component_name,
										'component_path' => $component_path,
									] );

									$this->addComponent( $component_name, $component_path );
								} else {
									$this->get_logger()->warning( 'Regex matched but group 1 was empty for file: ' . $entry, [
										'plugin' => $this->slug,
									] );
								}
							} else {
								$this->get_logger()->debug( 'File does not match component naming convention: ' . $entry, [
									'plugin' => $this->slug,
								] );
							}
						}
					}
					closedir( $handle );
					$this->get_logger()->notice( 'Finished processing directory: ' . $directory, [
						'plugin' => $this->slug,
					] );
				} else {
					$this->get_logger()->warning( 'Directory does not exist or is not a directory. Skipping: ' . $directory, [
						'plugin' => $this->slug,
					] );
				}
			}

			$this->get_logger()->notice( 'All component directories have been processed.', [
				'plugin' => $this->slug,
			] );
		}

		/**
		 * Add the WordPress Page for the Settings to the WordPress CMS
		 *
		 * @private WordPress Hook
		 */
		public function addSubmenuPagesToWordPress() {
			$this->get_logger()->info( 'Starting to add submenu pages to the WordPress admin menu.', [
				'plugin' => $this->slug,
			] );

			$icon_url = plugins_url( 'assets/icon-double-opt-in-20x20.png', dirname( __FILE__ ) );

			// Add the main menu page
			add_menu_page( $this->title, $this->title, $this->capabilities, $this->slug, '', $icon_url );
			$this->get_logger()->notice( 'Main menu page added.', [
				'plugin'     => $this->slug,
				'title'      => $this->title,
				'slug'       => $this->slug,
				'capability' => $this->capabilities,
			] );

			// Add submenu pages for each registered UI page
			foreach ( $this->getPages() as /** @var UIPage $Page */ $Page ) {
				$this->get_logger()->debug( 'Processing page for submenu: ' . $Page->getSlug(), [
					'plugin' => $this->slug,
					'page_slug' => $Page->getSlug(),
					'is_dashboard' => $Page->isDashboard(),
				] );

				if ( $Page->isDashboard() ) {
					$slug = $this->slug;
				} else {
					$slug = $this->slug . '_' . $Page->getSlug();
				}

				add_submenu_page(
					$this->slug,
					$Page->getTitle(),
					$Page->getTitle(),
					$this->capabilities,
					$slug,
					function () {
						$this->render();
					},
					$Page->getPosition()
				);

				$this->get_logger()->notice( 'Submenu page added.', [
					'plugin'     => $this->slug,
					'parent_slug' => $this->slug,
					'submenu_title' => $Page->getTitle(),
					'submenu_slug'  => $slug,
					'position'      => $Page->getPosition(),
				] );
			}

			$this->get_logger()->info( 'Finished adding all submenu pages.', [
				'plugin' => $this->slug,
			] );
		}

		/**
		 * This will ensure that the menu page will be removed before the rendering.
		 * This needs to be called from add_action('admin_head', ''); to be working.
		 *
		 * @return void
		 */
		public function hideSubmenuPagesToWordpress() {
			$this->get_logger()->info( 'Starting to hide submenu pages from the WordPress admin menu.', [
				'plugin' => $this->slug,
			] );

			foreach ( $this->getPages() as /** @var UIPage $Page */ $Page ) {
				$this->get_logger()->debug( 'Processing page: ' . $Page->getSlug(), [
					'plugin' => $this->slug,
					'page_slug' => $Page->getSlug(),
					'hide_in_menu' => $Page->hideInMenu(),
				] );

				if ( ! $Page->hideInMenu() ) {
					$this->get_logger()->debug( 'Page is not configured to be hidden. Skipping.', [
						'plugin' => $this->slug,
					] );
					continue;
				}

				if ( $Page->isDashboard() ) {
					$slug = $this->slug;
				} else {
					$slug = $this->slug . '_' . $Page->getSlug();
				}

				remove_submenu_page( $this->slug, $slug );

				$this->get_logger()->notice( 'Submenu page successfully hidden.', [
					'plugin'      => $this->slug,
					'parent_slug' => $this->slug,
					'submenu_slug'  => $slug,
				] );
			}

			$this->get_logger()->info( 'Finished hiding all designated submenu pages.', [
				'plugin' => $this->slug,
			] );
		}

		public function render() {
			$this->get_logger()->info( 'Rendering the main UI page.', [
				'plugin' => $this->slug,
			] );

			// Sanitize and determine the current page slug
			$page_query = sanitize_text_field( $_GET['page'] ?? '' );
			$page_parts = explode( $this->slug, $page_query );
			$page       = '';
			if (isset($page_parts[1])) {
				$page = ltrim($page_parts[1], '_');
			}

			if ( empty( $page ) ) {
				$page = $this->slug;
			}

			$this->get_logger()->debug( 'Determined current UI page slug: ' . $page, [
				'plugin'      => $this->slug,
				'query_page'  => $page_query,
			] );

			$pages     = $this->getPages();
			$menuPages = array();

			foreach ( $pages as $UIPage ) {
				if ( ! $UIPage->hideInMenu() ) {
					$menuPages[] = $UIPage;
				}
			}

			$this->get_logger()->info( 'Filtered ' . count($menuPages) . ' pages for the menu.', [
				'plugin' => $this->slug,
			] );

			// Start rendering the HTML
			?>
            <div class="forge12-plugin <?php esc_attr_e( $this->slug ); ?>">
                <div class="forge12-plugin-header">
                    <div class="forge12-plugin-header-inner">
                        <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>/assets/logo-forge12.png"
                             alt="Forge12 Interactvie GmbH" title="Forge12 Interactive GmbH"/>
                    </div>
                </div>
                <div class="forge12-plugin-menu">
					<?php
					$this->get_logger()->info( 'Triggering "f12_cf7_doubleoptin_admin_menu" action.', [
						'plugin' => $this->slug,
					] );
					do_action( 'f12_cf7_doubleoptin_admin_menu', $menuPages, $page, $this->slug );
					?>
                </div>
                <div class="forge12-plugin-content">
                    <div class="forge12-plugin-content-main">
						<?php
						$this->get_logger()->info( 'Triggering "forge12-plugin-content-' . $this->slug . '" action.', [
							'plugin' => $this->slug,
							'current_page_slug' => $page,
						] );
						do_action( 'forge12-plugin-content-' . $this->slug, $this->slug, $page );
						?>
                    </div>
                    <div class="forge12-plugin-content-sidebar">
						<?php
						$this->get_logger()->info( 'Triggering "forge12-plugin-sidebar-' . $this->slug . '" action.', [
							'plugin' => $this->slug,
							'current_page_slug' => $page,
						] );
						do_action( 'forge12-plugin-sidebar-' . $this->slug, $this->slug, $page );
						?>
                    </div>
                </div>
                <div class="forge12-plugin-footer">
                    <div class="forge12-plugin-footer-inner">
                        <img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ); ?>/assets/logo-forge12-dark.png"
                             alt="Forge12 Interactvie GmbH" title="Forge12 Interactive GmbH"/>
                    </div>
                </div>
            </div>
			<?php
			$this->get_logger()->notice( 'Main UI page rendering complete.', [
				'plugin' => $this->slug,
			] );
		}
	}
}