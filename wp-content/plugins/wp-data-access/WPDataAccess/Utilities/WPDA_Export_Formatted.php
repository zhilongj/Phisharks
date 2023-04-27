<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess\Utilities
 */

namespace WPDataAccess\Utilities {

	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Exist;
	use WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model;
	use WPDataAccess\Plugin_Table_Models\WPDP_Page_Model;
	use WPDataAccess\WPDA;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;

	/**
	 * Class WPDA_Export_Formatted
	 *
	 * This class should not be instantiated directly. Use it to built exports for specific types. Overwrite methods
	 * header, row and footer for your own type specific export.
	 *
	 * @author  Peter Schulz
	 * @since    2.0.13
	 */
	class WPDA_Export_Formatted {

		/**
		 * Remote or local database connection
		 *
		 * @var null|object
		 */
		protected $wpdadb = null;

		/**
		 * Query
		 *
		 * Select statement used to perform export.
		 *
		 * @var string
		 */
		protected $statement = '';

		/**
		 * Database schema name
		 *
		 * @var string
		 */
		protected $schema_name = '';

		/**
		 * Database table name
		 *
		 * @var string
		 */
		protected $table_names = '';

		/**
		 * Project page id
		 *
		 * Helper to hide schema and table name in export links on web pages
		 *
		 * @var null
		 */
		protected $pid = null;

		/**
		 * Array containing selected rows
		 *
		 * @var null
		 */
		protected $rows = null;

		/**
		 * Number of rows found
		 *
		 * @var int
		 */
		protected $row_count = 0;

		/**
		 * Select columns
		 *
		 * @see WPDA_List_Columns::get_table_columns()
		 *
		 * @var array|null
		 */
		protected $columns = null;

		/**
		 * Column data types
		 *
		 * @var array
		 */
		protected $data_types = array();

		/**
		 * Primary key columns
		 *
		 * @var array
		 */
		protected $table_primary_key = array();

		/**
		 * Where clause added to query
		 *
		 * @var string
		 */
		protected $where = '';

		/**
		 * Handle to table columns
		 *
		 * @var object|null
		 */
		protected $wpda_list_columns = null;

		/**
		 * WPDA_Export_Formatted constructor.
		 *
		 * @since    2.0.13
		 */
		public function __construct() {
			if ( defined( 'WP_MAX_MEMORY_LIMIT' ) ) {
				$wp_memory_limit      = WP_MAX_MEMORY_LIMIT;
				$current_memory_limit = @ini_get( 'memory_limit' );
				if ( false === $current_memory_limit ||
					 WPDA::convert_memory_to_decimal( $current_memory_limit ) < WPDA::convert_memory_to_decimal( $wp_memory_limit )
				) {
					@ini_set( 'memory_limit', $wp_memory_limit );
				}
			}
		}

		/**
		 * Main method to get arguments and start export.
		 *
		 * @since   2.0.13
		 */
		public function export() {
			$this->table_names = isset( $_REQUEST['table_names'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['table_names'] ) ) : ''; // input var okay.
			$this->pid         = isset( $_REQUEST['pid'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pid'] ) ) : ''; // input var okay.
			if ( '' !== $this->table_names ) {
				$wp_action = 'wpda-export-' . json_encode( $this->table_names );
				if ( isset( $_REQUEST['wpdaschema_name'] ) ) {
					$this->schema_name = sanitize_text_field( wp_unslash( $_REQUEST['wpdaschema_name'] ) ); // input var okay.
				}
			} else {
				$wp_action = "wpda-export-{$this->pid}";
			}

			// Check if export is allowed.
			$wp_nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '?'; // input var okay.
			if ( ! wp_verify_nonce( $wp_nonce, $wp_action ) ) {
				wp_die();
			}

			if ( '' !== $this->pid ) {
				$page = WPDP_Page_Model::get_page_from_page_id( $this->pid );
				if ( false === $page ) {
					wp_die();
				}
				$this->schema_name = $page[0]['page_schema_name'];
				$this->table_names = $page[0]['page_table_name'];

			} elseif ( isset( $_REQUEST['pid_default_where'] ) && is_numeric( $_REQUEST['pid_default_where'] ) ) {
				$pid_default_where = sanitize_text_field( wp_unslash( $_REQUEST['pid_default_where'] ) ); // input var okay.
				$page = WPDP_Page_Model::get_page_from_page_id( $pid_default_where );
				WPDA::wpda_log_wp_error($pid_default_where);
				if ( isset( $page[0]['page_where'] ) && '' !== trim( $page[0]['page_where'] ) ) {
					$this->where .= '' === $this->where ? ' where ' : ' and ';
					$this->where .= $page[0]['page_where'];
				}
				WPDA::wpda_log_wp_error($this->where);
			}

			// Check if table exists to prevent SQL injection.
			$wpda_dictionary_exists = new WPDA_Dictionary_Exist( $this->schema_name, $this->table_names );
			if ( ! $wpda_dictionary_exists->table_exists() ) {
				wp_die();
			}

			// Check if table exists and access is granted.
			$this->wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $this->schema_name, $this->table_names );
			$this->columns           = $this->wpda_list_columns->get_table_columns();
			foreach ( $this->columns as $column ) {
				$this->data_types[ $column['column_name'] ] = $column['data_type'];
			}

			// Get primary key columns.
			$this->table_primary_key = $this->wpda_list_columns->get_table_primary_key();

			// Check validity request. All primary key columns must be supplied. Return error if
			// primary key columns are missing.
			foreach ( $this->table_primary_key as $key ) {
				if ( ! isset( $key ) ) {
					wp_die();
				}
			}

			if ( isset( $this->table_primary_key[0] ) && isset( $_REQUEST[ $this->table_primary_key[0] ] ) ) {
				// Build where clause.
				global $wpdb;
				$count_pk = count( $_REQUEST[ $this->table_primary_key[0] ] );//phpcs:ignore - 8.1 proof
				for ( $i = 0; $i < $count_pk; $i ++ ) {
					$and = '';
					foreach ( $this->table_primary_key as $key ) {
						$and .= '' === $and ? '(' : ' and ';
						if ( $this->is_numeric( $this->data_types[ $key ] ) ) {
							$and .= $wpdb->prepare(
								'`%1s` = %d', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
								array(
									WPDA::remove_backticks( $key ),
									sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $i ] ) )
								)
							); // phpcs:ignore Standard.Category.SniffName.ErrorCode
						} else {
							$and .= $wpdb->prepare(
								'`%1s` = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
								array(
									WPDA::remove_backticks( $key ),
									sanitize_text_field( wp_unslash( $_REQUEST[ $key ][ $i ] ) )
								)
							); // phpcs:ignore Standard.Category.SniffName.ErrorCode
						}
					}

					$and .= '' === $and ? '' : ')';

					$this->where .= '' === $this->where ? ' where ' : ' or ';
					$this->where .= $and;
				}
			}

			$this->prepare_sql();

			$settings_db = WPDA_Table_Settings_Model::query( $this->table_names, $this->schema_name );
			if ( isset( $settings_db[0]['wpda_table_settings'] ) ) {
				$settings_db_custom = json_decode( $settings_db[0]['wpda_table_settings'] );
				if ( isset( $settings_db_custom->table_settings->query_buffer_size ) ) {
					$query_buffer_size = $settings_db_custom->table_settings->query_buffer_size;
				} else {
					$query_buffer_size = 0;
				}
			} else {
				$query_buffer_size = 0;
			}

			if ( is_numeric( $query_buffer_size ) && $query_buffer_size > 0 ) {
				set_time_limit(0);
				$this->send_export_file_large( $query_buffer_size );
			} else {
				$this->send_export_file();
			}
		}

		/**
		 * Prepare SQL query
		 *
		 * @since    2.0.13
		 */
		protected function prepare_sql() {
			$this->wpdadb = WPDADB::get_db_connection( $this->schema_name );
			if ( null === $this->wpdadb ) {
				if ( is_admin() ) {
					wp_die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), esc_attr( $this->schema_name ) ) );
				} else {
					die( sprintf( __( 'ERROR - Remote database %s not available', 'wp-data-access' ), esc_attr( $this->schema_name ) ) );
				}
			}

			if ( '' === $this->schema_name ) {
				$this->statement = "select * from `{$this->table_names}` {$this->where}";
			} else {
				$this->statement = "select * from `{$this->wpdadb->dbname}`.`{$this->table_names}` {$this->where}";
			}
		}

		protected function send_export_file_large( $query_buffer_size ) {
			$i   = 0;
			$sql = $this->statement . ' limit ' . $query_buffer_size;

			$this->rows      = $this->wpdadb->get_results( $sql, 'ARRAY_A' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
			$this->row_count = $this->wpdadb->num_rows;

			$this->header();

			while ( $this->wpdadb->num_rows > 0 ) {
				foreach ( $this->rows as $row ) {
					$this->row( $row );
				}

				$i++;

				$sql             = $this->statement . ' limit ' . $query_buffer_size . ' offset ' . ($i*$query_buffer_size);
				$this->rows      = $this->wpdadb->get_results( $sql, 'ARRAY_A' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
				$this->row_count += $this->wpdadb->num_rows;
			}

			$this->footer();

			if ( null !== $this->pid ) {
				die();
			}
		}

		/**
		 * Send export file to browser
		 *
		 * @since    2.0.13
		 */
		protected function send_export_file() {
			$this->rows      = $this->wpdadb->get_results( $this->statement, 'ARRAY_A' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
			$this->row_count = $this->wpdadb->num_rows;

			$this->header();
			foreach ( $this->rows as $row ) {
				$this->row( $row );
			}
			$this->footer();

			if ( null !== $this->pid ) {
				die();
			}
		}

		/**
		 * Implement file header here
		 */
		protected function header() { }

		/**
		 * Implement how to process a row here
		 *
		 * @param $row
		 */
		protected function row( $row ) { }

		/**
		 * Implement file footer here
		 */
		protected function footer() { }

		/**
		 * Check if data type is numeric
		 *
		 * @param string $data_type Column data type
		 *
		 * @return bool TRUE = numeric
		 */
		protected function is_numeric( $data_type ) {
			return ( 'number' === WPDA::get_type( $data_type ) );
		}

	}

}
