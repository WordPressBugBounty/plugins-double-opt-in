<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\Shared\Logger;
	use Forge12\Shared\LoggerInterface;

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * Class Category
	 * Handle the Categories
	 *
	 * @package forge12\contactform7\CF7DoubleOptIn
	 */
	class Category {
		/**
		 * Stores the DB ID for the entry
		 *
		 * @var int
		 */
		private $id = 0;

		/**
		 * The name of the category
		 *
		 * @var string
		 */
		private $name = '';
		/**
		 * Stores the timestamp the opt-in mail has been sent to the user.
		 *
		 * @var string
		 */
		private $createtime = "";
		/**
		 * Stores the timestamp the opt-in has been confirmed by the user.
		 */
		private $updatetime = "";
		private LoggerInterface $logger;

		/**
		 * The constructor
		 */
		public function __construct( LoggerInterface $logger, array $properties = array() ) {
			$this->logger = $logger;
			$this->get_logger()->info( 'Constructor started.', [ 'plugin' => 'double-opt-in' ] );

			foreach ( $properties as $name => $value ) {
				if ( isset( $this->{$name} ) ) {
					$this->{$name} = $value;
					$this->get_logger()->debug( 'Property ' . $name . ' set.', [
						'plugin'   => 'double-opt-in',
						'property' => $name,
						'value'    => $value,
					] );
				} else {
					$this->get_logger()->warning( 'Attempted to set a non-existent property.', [
						'plugin'   => 'double-opt-in',
						'property' => $name,
					] );
				}
			}

			$this->get_logger()->info( 'Constructor finished successfully.', [ 'plugin' => 'double-opt-in' ] );
		}

		public function get_logger() {
			return $this->logger;
		}

		/**
		 * Return the ID of the Optin
		 *
		 * @return int
		 */
		public function get_id() {
			return $this->id;
		}

		/**
		 * Return the name of the category
		 *
		 * @return string
		 */
		public function get_name() {
			return $this->name;
		}

		/**
		 * Set the name of the category
		 *
		 * @param string $name
		 *
		 * @return void
		 */
		public function set_name( $name ) {
			$this->name = $name;
		}

		/**
		 * @param string $timestamp
		 */
		public function set_createtime( $timestamp ) {
			$this->createtime = $timestamp;
		}

		/**
		 * Return a timestamp
		 *
		 * @param string $view Select how to return the content. raw is the stored db value. use formatted to return a
		 *                     formatted string.
		 *
		 * @return string
		 */
		public function get_createtime( $view = "raw" ) {
			$this->get_logger()->info( 'Getting creation time.', [
				'plugin' => 'double-opt-in',
				'view'   => $view
			] );

			if ( empty( $this->createtime ) ) {
				$this->get_logger()->warning( 'Creation time is empty. Setting to current time.', [
					'plugin' => 'double-opt-in'
				] );
				$this->set_createtime( time() );
			}

			if ( $view != "raw" ) {
				$this->get_logger()->debug( 'Processing creation time for formatted view.', [
					'plugin' => 'double-opt-in',
					'time_value' => $this->createtime
				] );

				if ( ! is_numeric( $this->createtime ) ) {
					$date = date( 'd.m.Y', strtotime( $this->createtime ) );
					$time = date( 'H:i:s', strtotime( $this->createtime ) );
					$this->get_logger()->debug( 'Creation time is not numeric. Converting from string.', [
						'plugin' => 'double-opt-in'
					] );
				} else {
					$date = date( 'd.m.Y', $this->createtime );
					$time = date( 'H:i:s', $this->createtime );
					$this->get_logger()->debug( 'Creation time is numeric. Converting from timestamp.', [
						'plugin' => 'double-opt-in'
					] );
				}

				$formatted_time = $date . ' ' . __( '/', 'double-opt-in' ) . ' ' . $time;
				$this->get_logger()->info( 'Returning formatted creation time.', [
					'plugin' => 'double-opt-in',
					'formatted_value' => $formatted_time
				] );
				return $formatted_time;
			} else {
				$this->get_logger()->info( 'Returning raw creation time.', [
					'plugin' => 'double-opt-in',
					'raw_value' => $this->createtime
				] );
				return $this->createtime;
			}
		}

		/**
		 * @param string $timestamp
		 */
		public function set_updatetime( $timestamp ) {
			$this->updatetime = $timestamp;
		}

		/**
		 * Return a timestamp
		 *
		 * @param string $view Select how to return the content. raw is the stored db value. use formatted to return a
		 *                     formatted string.
		 *
		 * @return string
		 */
		public function get_updatetime( $view = 'raw' ) {
			$this->get_logger()->info( 'Retrieving update time.', [
				'plugin' => 'double-opt-in',
				'view'   => $view
			] );

			if ( empty( $this->updatetime ) ) {
				$this->get_logger()->warning( 'Update time is empty. Setting to current time.', [
					'plugin' => 'double-opt-in'
				] );
				$this->set_updatetime( time() );
			}

			if ( $view != "raw" ) {
				$this->get_logger()->debug( 'Processing update time for formatted view.', [
					'plugin' => 'double-opt-in'
				] );

				if ( ! is_numeric( $this->updatetime ) ) {
					$date = date( 'd.m.Y', strtotime( $this->updatetime ) );
					$time = date( 'H:i:s', strtotime( $this->updatetime ) );
					$this->get_logger()->debug( 'Update time is not numeric; converting from string.', [
						'plugin' => 'double-opt-in'
					] );
				} else {
					$date = date( 'd.m.Y', $this->updatetime );
					$time = date( 'H:i:s', $this->updatetime );
					$this->get_logger()->debug( 'Update time is numeric; converting from timestamp.', [
						'plugin' => 'double-opt-in'
					] );
				}

				$formatted_time = $date . ' ' . __( '/', 'double-opt-in' ) . ' ' . $time;
				$this->get_logger()->info( 'Returning formatted update time.', [
					'plugin' => 'double-opt-in',
					'formatted_value' => $formatted_time
				] );
				return $formatted_time;
			} else {
				$this->get_logger()->info( 'Returning raw update time.', [
					'plugin' => 'double-opt-in',
					'raw_value' => $this->updatetime
				] );
				return $this->updatetime;
			}
		}

		/**
		 * Return the link to the category
		 *
		 * @return string
		 */
		public function get_link_ui() {
			$link = admin_url( 'admin.php?page=f12-cf7-doubleoptin_categories_view&id=' . $this->get_id() );

			$this->get_logger()->info( 'Generating UI link for admin page.', [
				'plugin' => 'double-opt-in',
				'link'   => $link,
				'id'     => $this->get_id(),
			] );

			return $link;
		}

		/**
		 * Return a list of opt ins stored in the database.
		 *
		 * @param      $atts            array (
		 *                              'perPage' => 10, // Posts per page
		 *                              'page' => 0, // Current page
		 *                              'order' => DESC, // Order (ASC/DESC)
		 *                              );
		 *
		 * @param null $numberOfPages   - Stores the number of pages found if limited
		 *
		 * @return array<Category>
		 */
		public static function get_list( $atts = array(), &$numberOfPages = null ) {
			global $wpdb;

			// Log the start of the get_list function.
			Logger::getInstance()->info( 'Fetching a list of categories.', [
				'plugin' => 'double-opt-in',
				'atts'   => $atts,
			] );

			$attr = array(
				'perPage' => 10,
				'page'    => 1,
				'order'   => 'DESC',
				'orderBy' => 'ID',
			);

			foreach ( $atts as $key => $value ) {
				if ( isset( $attr[ $key ] ) ) {
					$attr[ $key ] = $atts[ $key ];
				}
			}

			// Whitelist allowed columns for ORDER BY to prevent SQL injection
			$allowed_order_by = array( 'ID', 'id', 'name', 'createtime', 'updatetime' );

			// Validate orderBy against whitelist
			if ( ! in_array( $attr['orderBy'], $allowed_order_by, true ) ) {
				$attr['orderBy'] = 'ID';
			}

			// Validate order against whitelist
			if ( ! in_array( strtoupper( $attr['order'] ), array( 'ASC', 'DESC' ), true ) ) {
				$attr['order'] = 'DESC';
			}

			// Log the final attributes used for the query.
			Logger::getInstance()->debug( 'Using the following attributes for the query.', [
				'plugin' => 'double-opt-in',
				'attributes' => $attr,
			] );

			$tableName = $wpdb->prefix . 'f12_cf7_doubleoptin_categories';
			$pageNum   = (int) $attr['page'] - 1;

			if ( $numberOfPages !== null ) {
				// Log the start of counting items for pagination.
				Logger::getInstance()->info( 'Counting total items for pagination.', [
					'plugin' => 'double-opt-in',
				] );

				$result = $wpdb->get_row( 'SELECT count(*) AS counter FROM ' . $tableName );

				if ( $result ) {
					$itemCounter = $result->counter;

					$numberOfPages = 1;

					if ( $attr['perPage'] != - 1 ) {
						$numberOfPages = ceil( $itemCounter / (int) $attr['perPage'] );
					}
					// Log the calculated number of pages.
					Logger::getInstance()->info( 'Calculated number of pages.', [
						'plugin'        => 'double-opt-in',
						'total_items'   => $itemCounter,
						'items_per_page' => $attr['perPage'],
						'total_pages'   => $numberOfPages,
					] );
				} else {
					// Log a potential error if the count query fails.
					Logger::getInstance()->error( 'Failed to count total items for pagination.', [
						'plugin' => 'double-opt-in',
						'query'  => $wpdb->last_query,
						'error'  => $wpdb->last_error,
					] );
				}
			}

			$offset = $pageNum * (int) $attr['perPage'];

			$limit = '';

			if ( $attr['perPage'] != - 1 ) {
				$limit = ' LIMIT ' . (int) $attr['perPage'] . ' OFFSET ' . $offset;
			}

			$query = 'SELECT * FROM ' . $tableName . ' ORDER BY ' . $attr['orderBy'] . ' ' . $attr['order'] . $limit;
			// Log the final query before execution.
			Logger::getInstance()->debug( 'Executing database query to fetch categories.', [
				'plugin' => 'double-opt-in',
				'query'  => $query,
			] );

			$rows = $wpdb->get_results( $query, ARRAY_A );

			if ( $rows === null ) {
				// Log a potential error if the main query fails.
				Logger::getInstance()->error( 'Failed to fetch categories from the database.', [
					'plugin' => 'double-opt-in',
					'query'  => $wpdb->last_query,
					'error'  => $wpdb->last_error,
				] );
			}

			$list = array();
			foreach ( $rows as $row ) {
				$list[] = new Category( Logger::getInstance(), $row );
			}

			// Log the number of items retrieved.
			Logger::getInstance()->info( 'Successfully retrieved ' . count($list) . ' categories.', [
				'plugin' => 'double-opt-in',
				'count'  => count( $list ),
			] );

			return $list;
		}

		/**
		 * Return a Category by the given ID
		 *
		 * @param $id
		 *
		 * @return Category|null
		 */
		public static function get_by_id( $id ) {
			global $wpdb;

			// Log the start of the get_by_id function.
			Logger::getInstance()->info( 'Attempting to retrieve category by ID.', [
				'plugin' => 'double-opt-in',
				'id'     => $id,
			] );

			$table = $wpdb->prefix . 'f12_cf7_doubleoptin_categories';

			$query = $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id=%d', $id );

			// Log the prepared query before execution.
			Logger::getInstance()->debug( 'Executing database query to get category by ID.', [
				'plugin' => 'double-opt-in',
				'query'  => $query,
			] );

			$rows = $wpdb->get_results( $query, ARRAY_A );

			if ( is_array( $rows ) && ! empty( $rows ) ) {
				$rows = $rows[0];

				// Log the successful retrieval of the category.
				Logger::getInstance()->info( 'Category found and retrieved successfully.', [
					'plugin'    => 'double-opt-in',
					'id'        => $id,
					'category'  => $rows,
				] );

				return new Category( Logger::getInstance(), $rows );
			}

			// Log that no category was found with the given ID.
			Logger::getInstance()->warning( 'No category found with the given ID.', [
				'plugin' => 'double-opt-in',
				'id'     => $id,
			] );

			return null;
		}

		/**
		 * Delete a Category by the given id. All assigned Opt-Ins will be changed to be uncategorized.
		 *
		 * @param $id
		 *
		 * @return bool|int|\mysqli_result|resource|null
		 */
		public static function delete_by_id( $id ) {
			global $wpdb;

			// Log the start of the deletion process.
			Logger::getInstance()->info( 'Attempting to delete category by ID.', [
				'plugin' => 'double-opt-in',
				'id'     => $id,
			] );

			$table = $wpdb->prefix . 'f12_cf7_doubleoptin_categories';

			// Ensure that the opt in class exists otherwise do nothing.
			if ( class_exists( 'forge12\contactform7\CF7DoubleOptIn\OptIn' ) ) {
				// Log the action before updating the category in the OptIn class.
				Logger::getInstance()->info( 'Updating category for related opt-ins before deletion.', [
					'plugin' => 'double-opt-in',
					'old_id' => $id,
					'new_id' => 0,
				] );

				OptIn::bulk_update_category( $id, 0 );

				// Log the deletion from the database.
				$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

				if ( $result === false ) {
					// Log an error if the deletion failed.
					Logger::getInstance()->error( 'Failed to delete category from the database.', [
						'plugin' => 'double-opt-in',
						'id'     => $id,
						'error'  => $wpdb->last_error,
					] );
				} else {
					// Log a successful deletion.
					Logger::getInstance()->notice( 'Category deleted successfully.', [
						'plugin'       => 'double-opt-in',
						'id'           => $id,
						'rows_deleted' => $result,
					] );
				}

				return $result;
			}

			// Log that the required class does not exist.
			Logger::getInstance()->warning( 'Required OptIn class does not exist. Deletion process aborted.', [
				'plugin' => 'double-opt-in',
				'id'     => $id,
			] );

			return false;
		}

		/**
		 * Update or create the given Object
		 */
		public function save() {
			global $wpdb;

			$this->get_logger()->info( 'Attempting to save category data.', [ 'plugin' => 'double-opt-in' ] );

			$table = $wpdb->prefix . 'f12_cf7_doubleoptin_categories';

			if ( ! empty( $this->get_id() ) && $this->get_id() > 0 ) {
				$this->get_logger()->info( 'Updating existing category.', [
					'plugin' => 'double-opt-in',
					'id'     => $this->get_id(),
				] );

				$result = $wpdb->update( $table, array(
					'name'       => $this->get_name(),
					'updatetime' => $this->get_updatetime()
				), array(
					'id' => $this->get_id()
				) );

				if ( $result === false ) {
					$this->get_logger()->error( 'Failed to update category.', [
						'plugin' => 'double-opt-in',
						'id'     => $this->get_id(),
						'name'   => $this->get_name(),
						'error'  => $wpdb->last_error,
					] );
				} else {
					$this->get_logger()->notice( 'Category updated successfully.', [
						'plugin'        => 'double-opt-in',
						'id'            => $this->get_id(),
						'rows_affected' => $result,
					] );
				}

				return $result;
			} else {
				$this->get_logger()->info( 'Inserting new category.', [ 'plugin' => 'double-opt-in' ] );

				$result = $wpdb->insert( $table, array(
					'name'       => $this->get_name(),
					'createtime' => $this->get_createtime(),
					'updatetime' => $this->get_updatetime(),
				) );

				if ( $result > 0 ) {
					$this->id = $wpdb->insert_id;
					$this->get_logger()->notice( 'New category inserted successfully.', [
						'plugin' => 'double-opt-in',
						'id'     => $this->id,
						'name'   => $this->get_name(),
					] );
					return $this->id;
				} else {
					$this->get_logger()->error( 'Failed to insert new category.', [
						'plugin' => 'double-opt-in',
						'name'   => $this->get_name(),
						'error'  => $wpdb->last_error,
					] );
				}
			}

			$this->get_logger()->critical( 'Save operation failed for an unknown reason.', [
				'plugin' => 'double-opt-in',
			] );

			return false;
		}
	}
}