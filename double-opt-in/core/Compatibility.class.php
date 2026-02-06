<?php

namespace forge12\contactform7\CF7DoubleOptIn {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Compatibility
	 */
	class Compatibility {
		/**
		 * @var array<string, string>
		 */
		private $components = array();

		/**
		 * @var CF7DoubleOptIn $Controller
		 */
		private $Controller;

		/**
		 * UI constructor.
		 *
		 * @param $slug
		 */
		public function __construct( $Controller ) {
			$this->Controller = $Controller;

			$this->get_logger()->debug( 'Loading components for the UI.', [
				'plugin' => 'double-opt-in',
			] );
			$this->load_components();

			$this->get_logger()->debug( 'Registering a callback for the after_setup_theme action.', [
				'plugin' => 'double-opt-in',
				'hook'   => 'after_setup_theme',
			] );
			add_action( 'after_setup_theme', function () {
				$this->get_logger()->debug( 'after_setup_theme hook triggered. Registering components.', [
					'plugin' => 'double-opt-in',
				] );

				add_action( 'f12_cf7_doubleoptin_ui_after_load_compatibilities', array(
					$this,
					'registerComponents'
				), 10, 1 );

				$this->get_logger()->info( 'Triggering UI after load compatibilities action.', [
					'plugin' => 'double-opt-in',
					'hook'   => 'f12_cf7_doubleoptin_ui_after_load_compatibilities',
				] );
				do_action( 'f12_cf7_doubleoptin_ui_after_load_compatibilities', $this );

				$this->get_logger()->notice( 'UI initialization actions are complete.', [
					'plugin' => 'double-opt-in',
				] );
			} );
		}

		public function get_logger() {
			return $this->Controller->get_logger();
		}

		/**
		 * Loads the components for the current application.
		 *
		 * This method is responsible for loading the components for the current application.
		 * It retrieves the directories where the components are located using the `get_component_directories()`
		 * method and then iterates through each directory to load the components using the `load()` method.
		 *
		 * @return void
		 * @since 3.0.0
		 * @see   get_component_directories()
		 * @see   load()
		 */
		private function load_components() {
			$this->get_logger()->info( 'Starting to load UI components from directories.', [
				'plugin' => 'double-opt-in',
			] );

			$directories = $this->get_component_directories();

			if ( empty( $directories ) ) {
				$this->get_logger()->warning( 'No component directories found to load.', [
					'plugin' => 'double-opt-in',
				] );
				return;
			}

			$this->get_logger()->debug( 'Found ' . count( $directories ) . ' component directories.', [
				'plugin' => 'double-opt-in',
				'directories' => $directories,
			] );

			foreach ( $directories as $directory ) {
				$this->get_logger()->info( 'Loading components from directory: ' . $directory, [
					'plugin'    => 'double-opt-in',
					'directory' => $directory,
				] );
				$this->load( $directory, 0 );
			}

			$this->get_logger()->notice( 'All UI components have been loaded.', [
				'plugin' => 'double-opt-in',
			] );
		}

		/**
		 * Retrieves the component directories.
		 *
		 * This method allows developers to retrieve an array of component directories. These directories are used for
		 * displaying UI pages.
		 *
		 * @return array An array of component directories.
		 *
		 * @since 3.0.0
		 */
		private function get_component_directories() {
			$this->get_logger()->info( 'Retrieving component directories from filter.', [
				'plugin' => 'double-opt-in',
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
			$directories = apply_filters( 'f12_cf7_doubleoption_compatbility_get_component_directories', [ dirname( dirname( __FILE__ ) ) . '/compatibility' ] );

			// Log the result of the filter application.
			if ( empty( $directories ) ) {
				$this->get_logger()->warning( 'Component directories filter returned an empty array.', [
					'plugin' => 'double-opt-in',
				] );
			} else {
				$this->get_logger()->debug( 'Found ' . count( $directories ) . ' component directories.', [
					'plugin'    => 'double-opt-in',
					'directories' => $directories,
				] );
			}

			return $directories;
		}

		public function registerComponents( $Compatibility ) {
			$this->get_logger()->info( 'Starting component registration.', [
				'plugin' => 'double-opt-in',
			] );

			if ( empty( $this->components ) ) {
				$this->get_logger()->warning( 'No components found to register.', [
					'plugin' => 'double-opt-in',
				] );
				return;
			}

			$this->get_logger()->debug( 'Found ' . count($this->components) . ' components to process.', [
				'plugin' => 'double-opt-in',
				'components' => $this->components,
			] );

			foreach ( $this->components as $component ) {
				if ( isset( $component['name'] ) && isset( $component['path'] ) ) {
					$this->get_logger()->info( 'Registering component: ' . $component['name'], [
						'plugin' => 'double-opt-in',
						'component_name' => $component['name'],
						'component_path' => $component['path'],
					] );

					require_once( $component['path'] );

					if ( class_exists($component['name']) ) {
						new $component['name']( $this->Controller );
						$this->get_logger()->notice( 'Component ' . $component['name'] . ' successfully registered.', [
							'plugin' => 'double-opt-in',
						] );
					} else {
						$this->get_logger()->error( 'Failed to register component. Class ' . $component['name'] . ' not found after requiring file.', [
							'plugin' => 'double-opt-in',
							'component_path' => $component['path'],
						] );
					}
				} else {
					$this->get_logger()->warning( 'Skipped a component due to missing "name" or "path" keys.', [
						'plugin' => 'double-opt-in',
						'component_data' => $component,
					] );
				}
			}

			$this->get_logger()->info( 'All available components have been processed.', [
				'plugin' => 'double-opt-in',
			] );
		}

		private function addComponent( $name, $path ) {
			$this->get_logger()->info( 'Adding new component to the list.', [
				'plugin' => 'double-opt-in',
				'component_name' => $name,
				'component_path' => $path,
			] );

			$this->components[] = array(
				'name' => $name,
				'path' => $path
			);

			$this->get_logger()->debug( 'Component "' . $name . '" successfully added.', [
				'plugin' => 'double-opt-in',
				'total_components' => count($this->components),
			] );
		}

		private function load( $directory, $lvl ) {
			$this->get_logger()->info( 'Starting component loading process from directory.', [
				'plugin' => 'double-opt-in',
				'directory' => $directory,
				'level'     => $lvl,
			] );

			if ( ! is_dir( $directory ) ) {
				$this->get_logger()->warning( 'Specified directory does not exist or is not a directory.', [
					'plugin'    => 'double-opt-in',
					'directory' => $directory,
				] );
				return;
			}

			$handle = opendir( $directory );

			if ( ! $handle ) {
				$this->get_logger()->error( 'Failed to open directory handle.', [
					'plugin'    => 'double-opt-in',
					'directory' => $directory,
				] );
				return;
			}

			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( $entry != '.' && $entry != '..' ) {
					$full_path = $directory . '/' . $entry;

					if ( is_dir( $full_path ) && $lvl == 0 ) {
						$this->get_logger()->debug( 'Found a subdirectory. Recursively loading.', [
							'plugin'      => 'double-opt-in',
							'subdirectory'  => $entry,
							'current_level' => $lvl,
						] );
						$this->load( $full_path, $lvl + 1 );
					} else {
						if ( preg_match( '!Controller([a-zA-Z_0-9]+)\.class\.php!', $entry, $matches ) ) {
							$this->get_logger()->info( 'Found a potential controller file.', [
								'plugin'  => 'double-opt-in',
								'filename'  => $entry,
							] );

							if ( isset( $matches[1] ) ) {
								$component_name = '\\' . __NAMESPACE__ . '\Controller' . $matches[1];
								$this->get_logger()->debug( 'Adding component to the list.', [
									'plugin'   => 'double-opt-in',
									'component_name' => $component_name,
									'file_path'    => $full_path,
								] );
								$this->addComponent( $component_name, $full_path );
							} else {
								$this->get_logger()->warning( 'Controller filename matched, but could not extract component name.', [
									'plugin'  => 'double-opt-in',
									'filename'  => $entry,
								] );
							}
						}
					}
				}
			}

			closedir( $handle );

			$this->get_logger()->notice( 'Finished loading components from directory: ' . $directory, [
				'plugin' => 'double-opt-in',
			] );
		}
	}
}