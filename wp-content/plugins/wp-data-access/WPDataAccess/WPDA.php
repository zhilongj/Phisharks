<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess
 */

namespace WPDataAccess {

	use WP_Data_Access_Admin;
	use WPDataAccess\Connection\WPDADB;
	use WPDataAccess\Data_Dictionary\WPDA_Dictionary_Lists;
	use WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache;
	use WPDataAccess\Utilities\WPDA_Message_Box;
	use WPDataProjects\WPDP;

	/**
	 * Class WPDA
	 *
	 * Plugin default values and settings are managed through this class. Every plugin option has a default value
	 * which is maintained in an array together with the option name. Options are only saved in $wpdb->options when
	 * they are changed. Otherwise the default values are used. After reading option values from $wpdb->options the
	 * values are cached as many of them are used in multiple
	 * values are cached as many of them are used in multiple
	 * places during the processing of a request.
	 *
	 * @author  Peter Schulz
	 * @since   1.0.0
	 */
	class WPDA {

		// SAVING SPACE - According to the plugin guidelines it is allowed to include external fonts:
		// https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems
		const CDN_FONTAWESOME = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/';
		const GOOGLE_CHARTS   = 'https://www.gstatic.com/charts/loader.js';

		/**
		 * Label for WordPress tables
		 */
		const TABLE_TYPE_WP = 'wordpress table';
		/**
		 * Label for WPDA plugin tables
		 */
		const TABLE_TYPE_WPDA = 'plugin table';

		// Options are stored in arrays (OPTION_ARRAYS):
		// [0] = option name (as saved in wp_options).
		// [1] = default value (used if option is not available in wp_options).
		// Application options.
		/**
		 * Option wpda_version and it's default value
		 */
		const OPTION_WPDA_VERSION  = array( 'wpda_version', '5.3.8' );
		const OPTION_WPDA_UPGRADED = array( 'wpda_upgraded', false );
		/**
		 * Option wpda_setup_error and it's default value
		 */
		const OPTION_WPDA_SETUP_ERROR = array(
			'wpda_setup_error',
			''
		); // Values: off = do not display setup errors, other = show.
		/**
		 * Option wpda_uninstall_tables and it's default value
		 */
		const OPTION_WPDA_UNINSTALL_TABLES = array( 'wpda_uninstall_tables', 'off' ); // On uninstall drop WPDA tables.
		/**
		 * Option wpda_uninstall_options and it's default value
		 */
		const OPTION_WPDA_UNINSTALL_OPTIONS = array( 'wpda_uninstall_options', 'off' ); // On uninstall delete WPDA options.
		/**
		 * Option wpda_datatables_version and it's default value
		 */
		const OPTION_WPDA_DATATABLES_VERSION = array( 'wpda_datatables_version', '1.11.3');
		/**
		 * Option wpda_datatables_responsive_version and it's default value
		 */
		const OPTION_WPDA_DATATABLES_RESPONSIVE_VERSION = array( 'wpda_datatables_responsive_version', '2.4.0');

		const OPTION_PLUGIN_HIDE_NOTICES = array( 'wpda_plugin_hide_notices', 'on');

		const OPTION_PLUGIN_HIDE_ADMIN_MENU = array( 'wpda_plugin_hide_admin_menu', 'off');

		const OPTION_PLUGIN_PANEL_COOKIES = array( 'wpda_plugin_panel_cookies', 'clear');

		const OPTION_PLUGIN_SECRET_KEY_DEFAULT = 'enter-your-secret-key-here';
		const OPTION_PLUGIN_SECRET_IV_DEFAULT  = 'enter-your-secret-iv-here';

		const OPTION_PLUGIN_SECRET_KEY = array( 'wpda_plugin_secret_key', self::OPTION_PLUGIN_SECRET_KEY_DEFAULT);
		const OPTION_PLUGIN_SECRET_IV  = array( 'wpda_plugin_secret_iv', self::OPTION_PLUGIN_SECRET_IV_DEFAULT);

		const OPTION_PLUGIN_SONCE_SEED = array( 'wpda_plugin_sonce_seed', 'ALSKDFHIUWEALDSKNCNKSDJAKDJHSFAKSDFGFKOJHORITIHGMRTGHMHL');

		const OPTION_PLUGIN_WPDATAACCESS_POST = array( 'wpda_plugin_wpdataaccess_post', 'on');
		const OPTION_PLUGIN_WPDATAACCESS_PAGE = array( 'wpda_plugin_wpdataaccess_page', 'on');
		const OPTION_PLUGIN_WPDADIEHARD_POST  = array( 'wpda_plugin_wpdadiehard_post', 'on');
		const OPTION_PLUGIN_WPDADIEHARD_PAGE  = array( 'wpda_plugin_wpdadiehard_page', 'on');
		const OPTION_PLUGIN_WPDADATAFORMS_POST = array( 'wpda_plugin_wpdadataforms_post', 'on');
		const OPTION_PLUGIN_WPDADATAFORMS_PAGE = array( 'wpda_plugin_wpdadataforms_page', 'on');
		const OPTION_PLUGIN_WPDADATAFORMS_ALLOW_ANONYMOUS_ACCESS = array( 'wpda_plugin_wpdadataforms_allow_anonymous_access', 'off');
		const OPTION_PLUGIN_WPDAREPORT_POST = array( 'wpda_plugin_wpdareport_post', 'on');
		const OPTION_PLUGIN_WPDAREPORT_PAGE = array( 'wpda_plugin_wpdareport_page', 'on');

		// MySQL date and time formats
		const DB_DATE_FORMAT     = 'Y-m-d';
		const DB_TIME_FORMAT     = 'H:i:s';
		const DB_DATETIME_FORMAT = 'Y-m-d H:i:s';

		// Plugin options. (date and time settings)
		const OPTION_PLUGIN_DATE_FORMAT      = array( 'wpda_plugin_date_format', 'Y-m-d');
		const OPTION_PLUGIN_DATE_PLACEHOLDER = array( 'wpda_plugin_date_placeholder', 'yyyy-mm-dd');
		const OPTION_PLUGIN_TIME_FORMAT      = array( 'wpda_plugin_time_format', 'H:i');
		const OPTION_PLUGIN_TIME_PLACEHOLDER = array( 'wpda_plugin_time_placeholder', 'hh:mi');
		const OPTION_PLUGIN_SET_FORMAT       = array( 'wpda_plugin_set_format', 'csv');

		// Plugin debug mode
		const OPTION_PLUGIN_DEBUG = array( 'wpda_plugin_debug', 'off');

		// Back-end options.
		/**
		 * Option wpda_be_load_datatables and it's default value
		 */
		const OPTION_BE_LOAD_DATATABLES = array( 'wpda_be_load_datatables', 'on');
		/**
		 * Option wpda_be_load_datatables_response and it's default value
		 */
		const OPTION_BE_LOAD_DATATABLES_RESPONSE = array( 'wpda_be_load_datatables_response', 'on');
		/**
		 * Option wpda_be_table_access and it's default value
		 */
		const OPTION_BE_TABLE_ACCESS = array( 'wpda_be_table_access', 'show'); // Values: show, hide and select.
		/**
		 * Option wpda_be_table_access_selected and it's default value
		 */
		const OPTION_BE_TABLE_ACCESS_SELECTED = array( 'wpda_be_table_access_selected', ''); // Tables authorized in WPDA.
		/**
		 * Option wpda_be_view_link and it's default value
		 */
		const OPTION_BE_VIEW_LINK = array( 'wpda_be_view_link', 'on'); // Show view link in list table.
		/**
		 * Option wpda_be_allow_insert and it's default value
		 */
		const OPTION_BE_ALLOW_INSERT = array(
			'wpda_be_allow_insert',
			'on'
		); // Show insert link in list table (simple form).
		/**
		 * Option wpda_be_allow_update and it's default value
		 */
		const OPTION_BE_ALLOW_UPDATE = array(
			'wpda_be_allow_update',
			'on'
		); // Show update link in list table (simple form).
		/**
		 * Option wpda_be_allow_delete and it's default value
		 */
		const OPTION_BE_ALLOW_DELETE = array( 'wpda_be_allow_delete', 'on'); // Show delete link in list table.
		/**
		 * Option wpda_be_wpda_export_rows and it's default value
		 */
		const OPTION_BE_EXPORT_ROWS = array(
			'wpda_be_wpda_export_rows',
			'on'
		); // Show export link in list table (row export).
		/**
		 * Option wpda_be_wpda_export_variable_prefix and it's default value
		 */
		const OPTION_BE_EXPORT_VARIABLE_PREFIX = array(
			'wpda_be_wpda_export_variable_prefix',
			'on'
		); // Allows to import into repository with different wpdb prefix.
		/**
		 * Option wpda_be_wpda_allow_imports and it's default value
		 */
		const OPTION_BE_ALLOW_IMPORTS = array( 'wpda_be_wpda_allow_imports', 'on'); // Allow to import data (main only).
		/**
		 * Option wpda_be_confirm_export and it's default value
		 */
		const OPTION_BE_CONFIRM_EXPORT = array( 'wpda_be_confirm_export', 'off'); // Ask for confirmation before exporting.
		/**
		 * Option wpda_be_confirm_view and it's default value
		 */
		const OPTION_BE_CONFIRM_VIEW = array( 'wpda_be_confirm_view', 'off'); // Ask for confirmation before viewing.
		/**
		 * Option wpda_be_pagination and it's default value
		 */
		const OPTION_BE_PAGINATION = array( 'wpda_be_pagination', '10');
		/**
		 * Option wpda_be_remember_search and it's default value
		 */
		const OPTION_BE_REMEMBER_SEARCH = array( 'wpda_be_remember_search', 'on');
		/**
		 * Option wpda_be_innodb_count and it's default value
		 */
		const OPTION_BE_INNODB_COUNT = array( 'wpda_be_innodb_count', 100000);
		/**
		 * Option wpda_be_design_mode and it's default value
		 */
		const OPTION_BE_DESIGN_MODE = array( 'wpda_be_design_mode', 'advanced'); // Default design mode (basic/advanced).
		/**
		 * Option wpda_be_text_wrap_switch and it's default value
		 */
		const OPTION_BE_TEXT_WRAP_SWITCH = array( 'wpda_be_text_wrap_switch', 'off');
		/**
		 * Option wpda_be_text_wrap and it's default value
		 */
		const OPTION_BE_TEXT_WRAP = array( 'wpda_be_text_wrap', 400);
		/**
		 * Option wpda_be_hide_button_icons and it's default value
		 */
		const OPTION_BE_HIDE_BUTTON_ICONS = array( 'wpda_be_hide_button_icons', 'off');

		// Front-end options.
		/**
		 * Option wpda_fe_load_datatables and it's default value
		 */
		const OPTION_FE_LOAD_DATATABLES = array( 'wpda_fe_load_datatables', 'on');
		/**
		 * Option wpda_fe_load_datatables_response and it's default value
		 */
		const OPTION_FE_LOAD_DATATABLES_RESPONSE = array( 'wpda_fe_load_datatables_response', 'on');
		/**
		 * Option wpda_fe_table_access and it's default value
		 */
		const OPTION_FE_TABLE_ACCESS = array( 'wpda_fe_table_access', 'select'); // Values: hide, show, select.
		/**
		 * Option wpda_fe_table_access_selected and it's default value
		 */
		const OPTION_FE_TABLE_ACCESS_SELECTED = array( 'wpda_fe_table_access_selected', ''); // Tables authorized in WPDA.
		/**
		 * Option wpda_fe_pagination and it's default value
		 */
		const OPTION_FE_PAGINATION = array( 'wpda_fe_pagination', '10');
		const OPTION_FE_ADD_PROJECTS_TO_TOOLBAR = array( 'wpda_fe_add_projects_to_toolbar', 'on');

		// Manage Repository options.
		/**
		 * Option wpda_mr_keep_backup_tables and it's default value
		 */
		const OPTION_MR_KEEP_BACKUP_TABLES = array( 'wpda_mr_keep_backup_tables', 'on');
		const OPTION_MR_BACKUP_TABLES_KEPT_DEFAULT = '3';
		const OPTION_MR_BACKUP_TABLES_KEPT = array( 'wpda_mr_backup_tables_kept', self::OPTION_MR_BACKUP_TABLES_KEPT_DEFAULT);

		// Data Backup options.
		/**
		 * Option wpda_db_local_path and it's default value
		 */
		const OPTION_DB_LOCAL_PATH = array( 'wpda_db_local_path', '');
		/**
		 * Option wpda_db_dropbox_path and it's default value
		 */
		const OPTION_DB_DROPBOX_PATH = array( 'wpda_db_dropbox_path', '');

		// Data Tables options.
		/**
		 * Option wpda_dp_publication_roles and it's default value
		 */
		const OPTION_DP_PUBLICATION_ROLES = array( 'wpda_dp_publication_roles', '');
		const OPTION_DP_LANGUAGE          = array( 'wpda_dp_language', 'English');
		const OPTION_DP_JSON_EDITING      = array( 'wpda_dp_json_editing', 'validate');
		const OPTION_DP_STYLE             = array( 'wpda_dp_style', 'default');

		// Role Management
		const OPTION_WPDA_ENABLE_ROLE_MANAGEMENT = array( 'wpda_rm_enable_role_management', 'off');
		const OPTION_WPDA_USE_ROLES_IN_SHORTCODE = array( 'wpda_rm_use_roles_in_shortcode', 'off');

		// Prefixes to store backend access for non WP databases
		const BACKEND_OPTIONNAME_DATABASE_ACCESS   = 'WPDA_BE_TABLE_ACCESS_';
		const BACKEND_OPTIONNAME_DATABASE_SELECTED = 'WPDA_BE_TABLE_SELECTED_';

		// Prefixes to store frontend access for non WP databases
		const FRONTEND_OPTIONNAME_DATABASE_ACCESS   = 'WPDA_FE_TABLE_ACCESS_';
		const FRONTEND_OPTIONNAME_DATABASE_SELECTED = 'WPDA_FE_TABLE_SELECTED_';

		const WPDA_DT_UI_THEME_DEFAULT   = array( 'wpda_dt_ui_theme_default', 'smoothness');

		/**
		 * List of plugin tables
		 */
		const WPDA_TABLES = array(
			'wpda_logging'        => true,
			'wpda_menus'          => true,
			'wpda_table_settings' => true,
			'wpda_table_design'   => true,
			'wpda_publisher'      => true, // Old plugin table name: do NOT change!
			'wpda_media'          => true,
			'wpda_project'        => true,
			'wpda_project_page'   => true,
			'wpda_project_table'  => true,
			'wpda_csv_uploads'    => true,
			'wpda_pro_reports'	  => true,
		);

		/**
		 * List containing all plugin tables
		 *
		 * @var array
		 */
		static protected $wpda_tables = array();

		// Option values once queried from wp_options are stored in an array for re-use during request to prevent
		// executing same query on wp_options multiple times.
		/**
		 * Options cache array
		 *
		 * @var array
		 */
		static protected $option_cache = array();

		/**
		 * List containing all WordPress tables
		 *
		 * @var array
		 */
		static protected $wp_tables = array();

		static protected $plugin_pages = array(
			WP_Data_Access_Admin::PAGE_MAIN,
			WP_Data_Access_Admin::PAGE_DASHBOARD,
			WP_Data_Access_Admin::PAGE_SETTINGS,
			WP_Data_Access_Admin::PAGE_EXPLORER,
			WP_Data_Access_Admin::PAGE_QUERY_BUILDER,
			WP_Data_Access_Admin::PAGE_PUBLISHER,
			WP_Data_Access_Admin::PAGE_DESIGNER,
			WP_Data_Access_Admin::PAGE_MY_TABLES,
			WP_Data_Access_Admin::PAGE_CHARTS,
			WPDP::PAGE_MAIN,
			WPDP::PAGE_TEMPLATES,
		);

		public static function is_plugin_page( $page ) {
			return (
				is_scalar( $page ) &&
				(
					'wpda_wpdp_' === substr( $page, 0, 10 ) ||
					WP_Data_Access_Admin::PAGE_EXPLORER === substr( $page, 0, 13 ) ||
					in_array( $page, self::$plugin_pages) //phpcs:ignore - 8.1 proof
				)
			);
		}

		/**
		 * Translated table label
		 *
		 * Returns a translated table label. The label depends on the type of table. Provided through a function to
		 * support internationalization.
		 *
		 * @param string $table_type Table type (use WPDA constants).
		 *
		 * @return string Translated table type.
		 * @since   1.0.0
		 *
		 */
		public static function get_table_type_text( $table_type ) {

			switch ( $table_type ) {

				case self::TABLE_TYPE_WP:
					return __( 'WordPress table', 'wp-data-access' );

				case self::TABLE_TYPE_WPDA:
					return __( 'plugin table', 'wp-data-access' );

				default:
					return $table_type;

			}

		}

		/**
		 * Get plugin option values
		 *
		 * Get value for pluginoption saved in wp_options. If option is not found in $wpdb->options the default value
		 * for that option is returned. Option values once taken from $wpdb->options are cached to prevent execution
		 * of the same query multiple times during teh processing of a request.
		 *
		 * @param array $option OPTION_ARRAY (use class constants).
		 *
		 * @return mixed Value for OPTION_ARRAY ($option): (1) cached value (2) wp_options value (3) default value.
		 * @since   1.0.0
		 *
		 */
		public static function get_option( $option ) {

			if ( isset( self::$option_cache[ $option[0] ] ) ) {
				return self::$option_cache[ $option[0] ]; // Re-use cached value.
			}

			$option_value = get_option( $option[0] );
			if ( ! $option_value ) {
				// Option not found in wp_options: save default value for re-use.
				self::$option_cache[ $option[0] ] = $option[1];
			} else {
				// Option found in wp_options: save for re-use.
				self::$option_cache[ $option[0] ] = $option_value;
			}

			return self::$option_cache[ $option[0] ]; // Return saved value.

		}

		/**
		 * Delete all plugin options from table wp_options
		 *
		 * @since   1.0.0
		 */
		public static function clear_all_options() {

			global $wpdb;

			$wpdb->query(
				"
				DELETE FROM wp_options
				WHERE option_name LIKE 'wpda_%'
			"
			); // db call ok; no-cache ok.

			self::$option_cache = array(); // Reset cache.

		}

		/**
		 * Load all WordPress tables
		 *
		 * @since   1.1.0
		 */
		public static function load_wp_tables() {

			if ( 0 === count( self::$wp_tables ) ) {//phpcs:ignore - 8.1 proof
				try {
					global $wpdb;

					if ( ! is_multisite() ) {
						foreach ( $wpdb->tables( 'all', true ) as $table ) {
							self::$wp_tables[ $table ] = $table;
						}
					} else {
						$query = "select blog_id from {$wpdb->blogs}";
						$blogs = $wpdb->get_results( $query, 'ARRAY_N' );
						foreach ( $blogs as $blog ) {
							foreach ( $wpdb->tables( $blog === reset( $blogs ) ? 'all' : 'blog', true, $blog[0] ) as $table ) {//phpcs:ignore - 8.1 proof
								self::$wp_tables[ $table ] = $table;
							}
						}
					}

					return true;
				} catch ( \Exception $e ) {
					wp_die( 'ERROR: ' . $e->getMessage() );
				}
			}

		}

		/**
		 * Checks if a table is a WordPress table
		 *
		 * @param string $table_name Table name.
		 *
		 * @return bool TRUE = WordPress table
		 * @since   1.1.0
		 *
		 */
		public static function is_wp_table( $table_name ) {

			self::load_wp_tables();

			if ( 0 === count( self::$wp_tables ) ) {//phpcs:ignore - 8.1 proof
				return false;
			}

			return isset( self::$wp_tables[ $table_name ] );

		}

		/**
		 * List containing all WordPress tables
		 *
		 * @return array
		 * @since   1.1.0
		 *
		 */
		public static function get_wp_tables() {

			self::load_wp_tables();

			if ( 0 === count( self::$wp_tables ) ) {//phpcs:ignore - 8.1 proof
				wp_die( __( 'ERROR: No WordPress table found', 'wp-data-access' ) );
			}

			return self::$wp_tables;

		}

		/**
		 * Returns a list of all plugin tables
		 *
		 * @return array List of plugin tables
		 * @since   1.0.0
		 *
		 */
		public static function get_wpda_tables() {

			global $wpdb;
			$wpda_tables = array();

			foreach ( self::WPDA_TABLES as $key => $value ) {
				$wpda_tables[] = $wpdb->prefix . $key;
			}

			return $wpda_tables;

		}

		/**
		 * Check if repository column represents a schema name
		 *
		 * @param string $table_name Table name
		 * @param string $column_name Column name
		 *
		 * @return bool
		 */
		public static function column_is_schema_name( $table_name, $column_name ) {
			if ( 0 === count( self::$wpda_tables ) ) {//phpcs:ignore - 8.1 proof
				// Cache schema names
				global $wpdb;
				self::$wpda_tables[$wpdb->prefix . 'wpda_media.media_schema_name' ] = true;
				self::$wpda_tables[$wpdb->prefix . 'wpda_project_page.page_schema_name' ] = true;
				self::$wpda_tables[$wpdb->prefix . 'wpda_menus.menu_schema_name' ] = true;
				self::$wpda_tables[$wpdb->prefix . 'wpda_project_table.wpda_schema_name' ] = true;
				self::$wpda_tables[$wpdb->prefix . 'wpda_publisher.pub_schema_name' ] = true;
				self::$wpda_tables[$wpdb->prefix . 'wpda_table_design.wpda_schema_name' ] = true;
				self::$wpda_tables[$wpdb->prefix . 'wpda_table_settings.wpda_schema_name' ] = true;
			}

			return isset( self::$wpda_tables[ "{$table_name}.{$column_name}" ] );
		}

		/**
		 * Return the default value for a plugin option
		 *
		 * @param array $option OPTION_ARRAY (use class constants).
		 *
		 * @return mixed Default value for OPTION_ARRAY ($option).
		 * @since   1.0.0
		 *
		 */
		public static function get_option_default( $option ) {

			return $option[1]; // Default option value.

		}

		/**
		 * Checks if table is plugin table
		 *
		 * NOTE
		 * Variable $phpdoc_supported_solution is a temporary variable that does not add any functionality to this
		 * function. It only serves the purpose to get class WPDA in the documentation!!! If the isset statement in
		 * the return is performed directly on self::WPDA_TABLES, class WPDA will not appear in the phpdoc generated
		 * documentation. To avoid class WPDA to be undocumented, we use $phpdoc_supported_solution.
		 *
		 * @param string $real_table_name Table name to be checked.
		 *
		 * @return bool TRUE = $table_name is a WPDA table, FALSE = $table_name is not a WPDA table.
		 * @since   1.0.0
		 *
		 */
		public static function is_wpda_table( $real_table_name ) {
			if ( null === $real_table_name ) {
				return false;
			}

			global $wpdb;

			$phpdoc_supported_solution = self::WPDA_TABLES; // DO NOT DELETE THIS TO MAKE THE CODE SIMPLER!!! (read).

			return
				(
					isset(
						$phpdoc_supported_solution[ substr( $real_table_name, strlen( $wpdb->prefix ) ) ]
					) && (
						$wpdb->prefix === substr( $real_table_name, 0, strlen( $wpdb->prefix ) )
					)
				);
		}

		/**
		 * Save plugin option
		 *
		 * Saves a plugin option in $wpdb->options.
		 *
		 * @param array $option OPTION_ARRAYS (use class constants).
		 * @param mixed $value Value to be saved for $option. If null set to default.
		 *
		 * @since   1.0.0
		 *
		 */
		public static function set_option( $option, $value = null ) {

			try {

				if ( is_null( $value ) ) {
					$option_value = $option[1]; // Set option to default.
				} else {
					$option_value = $value; // Set option value.
				}

				update_option( $option[0], $option_value ); // Save option value in wp_options.

				self::$option_cache[ $option[0] ] = $option_value; // Save for re-use.

			} catch ( \Exception $e ) {

				die( 'ERROR: ' . esc_html( $e->errorMessage() ) );

			}

		}

		/**
		 * Simplify data type for simple forms
		 *
		 * Data types used in plugin a re simplified for simple form usage.
		 *
		 * @param string $arg Data type as known to the MySQL database.
		 *
		 * @return string Simplified data type (mainly used to recognize when to use quotes).
		 * @since   1.0.0
		 *
		 * @see \WPDataAccess\Simple_Form\WPDA_Simple_Form
		 *
		 */
		public static function get_type( $arg ) {
			$type = trim( str_replace( 'unsigned', '', (string) $arg ) );
			if ( false !== ( $index = strpos( $type, '(' ) ) ) {
				$type = substr( $type, 0, $index );
			}
			switch ( $type ) {
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
				case 'float':
				case 'double':
				case 'decimal':
				case 'year':
					return 'number';
				case 'date':
				case 'datetime':
				case 'timestamp':
					return 'date';
				case 'time':
					return 'time';
				case 'enum':
					return 'enum';
				case 'set':
					return 'set';
				default:
					return 'string';
			}
		}

		public static function get_sb_type( $arg ) {
			$type = trim( str_replace( 'unsigned', '', (string) $arg ) );
			if ( false !== ( $index = strpos( $type, '(' ) ) ) {
				$type = substr( $type, 0, $index );
			}
			switch ( $type ) {
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
				case 'float':
				case 'double':
				case 'decimal':
				case 'year':
					return 'num';
				case 'date':
				case 'datetime':
				case 'timestamp':
					return 'date';
				default:
					return 'string';
			}
		}

		/**
		 * Insert a value or key/value pair after a specific key in an array.  If key doesn't exist, value is appended
		 * to the end of the array.
		 *
		 * Source: https://gist.github.com/wpscholar/0deadce1bbfa4adb4e4c
		 *
		 * @param array $array
		 * @param string $key
		 * @param array $new
		 *
		 * @return array
		 */
		public static function array_insert_after( $array, $key, $new ) {
			$keys = array_keys( (array) $array ); //phpcs:ignore - 8.1 proof
			$index = array_search( $key, $keys ); //phpcs:ignore - 8.1 proof
			$pos = false === $index ? count( $array ) : $index + 1; //phpcs:ignore - 8.1 proof

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) ); //phpcs:ignore - 8.1 proof
		}

		/**
		 * Log a message in the database
		 *
		 * Use this method to log messages to the database.
		 *
		 * NOTE Don't use $wpdb->insert! You'll miss a lot of information...
		 *
		 * @param $log_id   string Id to identify/find logged data.
		 * @param $log_type string Possible values: 'FATAL', 'ERROR', 'WARN', 'INFO', 'DEBUG', 'TRACE'
		 * @param $log_msg  string Any text (max length 4096kb).
		 *
		 * @since   2.0.7
		 *
		 */
		public static function log( $log_id, $log_type, $log_msg ) {
			global $wpdb;

			$sql =
				$wpdb->prepare(
					'INSERT INTO ' . $wpdb->prefix .
					'wpda_logging (log_time, log_id, log_type, log_msg) VALUES (now(), %s, %s, %s)'
					, $log_id
					, $log_type
					, $log_msg
				);
			$wpdb->query( $sql );

		}

		/**
		 * Get user role
		 *
		 * @return mixed Current user roles or FALSE if not logged in.
		 * @since   2.0.8
		 *
		 */
		public static function get_current_user_roles() {
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				if ( ! is_array( $user->roles ) ) {
					return ( array ) $user->roles;
				} else {
					return $user->roles;
				}
			} else {
				return false;
			}
		}

		/**
		 * Get user capability
		 *
		 * @return mixed Current users first capability or FALSE if not logged in.
		 * @since   2.0.8
		 *
		 */
		public static function get_current_user_capability() {
			if ( is_user_logged_in() ) {
				$user    = wp_get_current_user();
				$allcaps = array();
				foreach ( $user->allcaps as $key => $val ) {
					array_push( $allcaps, $key );//phpcs:ignore - 8.1 proof
				}

				return $allcaps[0];
			} else {
				return false;
			}
		}

		/**
		 * Convert memory value to integer.
		 *
		 * @param $memory_value string Memory value (eg 128M)
		 *
		 * @return integer Converted value in decimal
		 * @since   2.0.8
		 *
		 */
		public static function convert_memory_to_decimal( $memory_value ) {
			if ( preg_match( '/^(\d+)(.)$/', $memory_value, $matches ) ) {
				if ( $matches[2] == 'G' ) {
					return $matches[1] * 1024 * 1024 * 1024;
				} else if ( $matches[2] == 'M' ) {
					return $matches[1] * 1024 * 1024;
				} else if ( $matches[2] == 'K' ) {
					return $matches[1] * 1024;
				}
			}
		}

		public static function get_current_user_id( $is_anonymous = false ) {
			if ( $is_anonymous ) {
				return -1;
			}

			global $current_user;
			if ( isset( $current_user->ID ) ) {
				return $current_user->ID;
			} else {
				if ( function_exists('wp_get_current_user') ) {
					$wp_user = wp_get_current_user();
					if ( isset( $wp_user->ID ) ) {
						return $wp_user->ID;
					} else {
						return - 1;
					}
				} else {
					return - 1;
				}
			}
		}

		public static function get_current_user_login() {
			global $current_user;
			if ( isset( $current_user->user_login ) ) {
				return $current_user->user_login;
			} else {
				if ( function_exists('wp_get_current_user') ) {
					$wp_user = wp_get_current_user();
					if ( isset( $wp_user->data->user_login ) ) {
						return $wp_user->data->user_login;
					} else {
						return 'anonymous';
					}
				} else {
					return 'anonymous';
				}
			}
		}

		public static function get_current_user_email() {
			global $current_user;
			if ( isset( $current_user->user_email ) ) {
				return $current_user->user_email;
			} else {
				$wp_user = wp_get_current_user();
				if ( isset( $wp_user->data->user_email ) ) {
					return $wp_user->data->user_email;
				} else {
					return 'anonymous';
				}
			}
		}

		/**
		 * Replaces environment variables in where clause with appropriate values
		 *
		 * @param $where_clause
		 *
		 * @return string
		 */
		public static function substitute_environment_vars( $where_clause ) {
			if ( strpos( $where_clause, '$$USERID$$' ) ) {
				$user_id      = WPDA::get_current_user_id();
				$where_clause = str_replace( '$$USERID$$', $user_id, $where_clause );
			}

			if ( strpos( $where_clause, '"$$USER$$"' ) ) {
				$user_login   = WPDA::get_current_user_login();
				$where_clause = str_replace( '"$$USER$$"', '"' . $user_login . '"', $where_clause );
			}
			if ( strpos( $where_clause, "'" . '$$USER$$' . "'" ) ) {
				$user_login   = WPDA::get_current_user_login();
				$where_clause = str_replace( "'" . '$$USER$$' . "'", "'" . $user_login . "'", $where_clause );
			}
			if ( strpos( $where_clause, '$$USER$$' ) ) {
				$user_login   = WPDA::get_current_user_login();
				$where_clause = str_replace( '$$USER$$', "'" . $user_login . "'", $where_clause );
			}

			if ( strpos( $where_clause, '"$$EMAIL$$"' ) ) {
				$user_email      = WPDA::get_current_user_email();
				$where_clause = str_replace( '"$$EMAIL$$"', '"' . $user_email . '"', $where_clause );
			}
			if ( strpos( $where_clause, "'" . '$$EMAIL$$' . "'" ) ) {
				$user_email      = WPDA::get_current_user_email();
				$where_clause = str_replace( "'" . '$$EMAIL$$' . "'", "'" . $user_email . "'", $where_clause );
			}
			if ( strpos( $where_clause, '$$EMAIL$$' ) ) {
				$user_email      = WPDA::get_current_user_email();
				$where_clause = str_replace( '$$EMAIL$$', "'" . $user_email . "'", $where_clause );
			}

			return $where_clause;
		}

		/**
		 * Where clause construction (use filter if applied)
		 *
		 * Param $columns = associative array containing the following column info taken from the data dictionary:
		 *  $columns['column_name']    => information_schema.tables.column_name
		 *  $columns['data_type']      => information_schema.tables.data_type
		 * 	$columns['extra']          => information_schema.tables.extra
		 * 	$columns['column_type'     => information_schema.tables.column_type
		 * 	$columns['is_nullable'     => information_schema.tables.is_nullable
		 * 	$columns['column_default'] => information_schema.tables.column_default
		 *
		 * @param string $schema_name Schema name (optional)
		 * @param string $table_name Table name (optional)
		 * @param string $columns Array containing table columns
		 * @param string $search Search string entered by user
		 * @param boolean $is_case_sensitive Case-sensitive search
		 *
		 * @return string Where clause between ()
		 */
		public static function construct_where_clause( $schema_name, $table_name, $columns, $search, $is_case_sensitive = false ) {
			$where_search_args = self::add_wpda_search_args( $columns );

			if ( ! $is_case_sensitive && has_filter('wpda_construct_where_clause') ) {
				global $wpda_search_args;
				$wpda_search_args = $where_search_args;

				// Use search filter
				$filter = apply_filters(
					'wpda_construct_where_clause',
					'',
					$schema_name,
					$table_name,
					$columns,
					$search
				);
				if ( null !== $filter ) {
					if ( '' === $where_search_args ) {
						return $filter;
					} else {
						if ( '' === $filter ) {
							return $where_search_args;
						} else {
							return "{$filter} and {$where_search_args}";
						}
					}
				}
			}

			// Define case-sensitive search
			$case_sensitive = $is_case_sensitive ? 'binary' : '';

			// Default search behaviour
			if ( '' === $search || null === $search || ! is_array( $columns ) ) {
				return $where_search_args;
			}

			global $wpdb;
			$where_columns = array();

			foreach ( $columns as $column ) {
				if ( 'string' === WPDA::get_type( $column['data_type'] ) ) {
					$where_columns[] = $wpdb->prepare( "`" . str_replace( '`', '', $column['column_name'] ) . "` like {$case_sensitive} '%s'", '%' . esc_sql( $search ) . '%' ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
				}
			}

			if ( 0 === count( $where_columns ) ) {//phpcs:ignore - 8.1 proof
				return '' === $where_search_args ? ' (1=2) ' : $where_search_args;
			}

			if ( '' === $where_search_args ) {
				return ' (' . implode( ' or ', $where_columns ) . ') ';
			} else {
				return ' (' . implode( ' or ', $where_columns ) . ') ' . "and {$where_search_args}";
			}
		}

		public static function add_wpda_search_args( $columns ) {
			$where_columns = array();

			if ( is_array( $columns ) ) {
				global $wpdb;
				$request = array_change_key_case( $_REQUEST ); //phpcs:ignore - 8.1 proof
				foreach ( $columns as $column ) {
					$column_name     = str_replace( '`', '', $column['column_name'] );
					$column_name_lwr = strtolower( $column_name );
					if ( isset( $request["wpda_search_column_{$column_name_lwr}"] ) ) {
						if ( is_array( $request["wpda_search_column_{$column_name_lwr}"] ) ) {
							// Handle multiple values for same column with OR
							$where_columns_arr = array();
							foreach ( $request["wpda_search_column_{$column_name_lwr}"] as $value ) {
								$column_date_type = $column['data_type'];
								$column_value     = wp_strip_all_tags( wp_unslash( $value ) ); // input var okay.
								if ( '' !== $column_value ) {
									if ( 'string' === WPDA::get_type( $column_date_type ) ) {
										$where_columns_arr[] = $wpdb->prepare( "`{$column_name}` like '%s'", esc_sql( $column_value ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
									} elseif ( 'number' === WPDA::get_type( $column_date_type ) ) {
										$where_columns_arr[] = $wpdb->prepare( "`{$column_name}` = '%d'", esc_sql( $column_value ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
									}
								}
							}
							if ( count( $where_columns_arr ) > 0 ) {//phpcs:ignore - 8.1 proof
								$where_columns[] = ' (' . implode( ' or ', $where_columns_arr ) . ') ';//phpcs:ignore - 8.1 proof
							}
						} else {
							// Handle single value
							$column_date_type = $column['data_type'];
							$column_value     = wp_strip_all_tags( wp_unslash( $request[ "wpda_search_column_{$column_name_lwr}" ] ) );
							if ( '' !== $column_value ) {
								if ( 'string' === WPDA::get_type( $column_date_type ) ) {
									$where_columns[] = $wpdb->prepare( "`{$column_name}` like '%s'", esc_sql( $column_value ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
								} elseif ( 'number' === WPDA::get_type( $column_date_type ) ) {
									$where_columns[] = $wpdb->prepare( "`{$column_name}` = '%d'", esc_sql( $column_value ) ); // phpcs:ignore Standard.Category.SniffName.ErrorCode
								}
							}
						}
					}
				}
			}

			if ( 0 === count( $where_columns ) ) {//phpcs:ignore - 8.1 proof
				return '';
			} else {
				$operator =
					isset( $_REQUEST['wpda_search_column_operator'] ) &&
					'or' === strtolower( $_REQUEST['wpda_search_column_operator'] )
						? 'or' : 'and';
				return ' (' . implode( " $operator ", $where_columns ) . ') ';
			}
		}

		public static function schema_disabled( $schema_name ) {
			if ( 'rdb:' === substr( $schema_name, 0, 4) ) {
				$rdb = WPDADB::get_remote_database( $schema_name, true );
				if ( false === $rdb ) {
					return false;
				}
				return true === $rdb[ 'disabled' ];
			}
			return false;
		}

		/**
		 * Check if database schema exists (local and remote databases)
		 *
		 * @param string $schema_name Database schema
		 *
		 * @return bool Exists?
		 */
		public static function schema_exists( $schema_name ) {
			if ( self::schema_disabled( $schema_name ) ) {
				return false;
			}

			$wpdadb = WPDADB::get_db_connection( $schema_name );
			if ( null === $wpdadb ) {
				return false;
			}
			if ( method_exists( $wpdadb, 'is_connected' ) ) {
				if ( ! $wpdadb->is_connected() ) {
					$msg = new WPDA_Message_Box(
						[
							'message_text'           => __( "Remote database '{$schema_name}' not available [check connection: Settings > WP Data Access]", 'wp-data-access' ),
							'message_type'           => 'error',
							'message_is_dismissible' => false,
						]
					);
					$msg->box();

					return false;
				}
			}

			$wpdadb->query(
				$wpdadb->prepare( '
							SELECT TRUE FROM information_schema.schemata WHERE schema_name = %s
						',
					[
						$wpdadb->dbname,
					]
				)
			); // db call ok; no-cache ok.
			$wpdadb->get_results(); // phpcs:ignore Standard.Category.SniffName.ErrorCode

			return 1 === $wpdadb->num_rows;
		}

		public static function get_user_default_scheme() {
			$default_databases = get_option('wpda_default_database');
			if ( false !== $default_databases ) {
				$user_id = get_current_user_id();
				if (
					$user_id > 0 &&
					isset( $default_databases[ $user_id ] ) &&
					self::schema_exists( $default_databases[ $user_id ] )
				) {
					return $default_databases[ $user_id ];
				}
			}

			global $wpdb;
			return $wpdb->dbname;
		}

		public static function shortcode_popup() {
			?>
			<script type="text/javascript">
				jQuery(function() {
					var clipboard = new ClipboardJS('.wpda_shortcode_clipboard');
				});
			</script>
			<style type="text/css">
				.wpda_shortcode_content {
					padding: 0 20px;
				}
                .wpda_shortcode_text {
                    text-align: center;
                    font-size: 105%;
					white-space: nowrap;
                }
                .wpda_shortcode_buttons {
                    text-align: center;
                }
                .button.wpda_shortcode_button {
                    width: 100px !important;
                }
				.wpda_shortcode_link {
					text-decoration: none;
					font-weight: bold;
				}
			</style>
			<?php
		}

		public static function is_editing_post() {
			global $pagenow;
			if ( $pagenow === 'post.php' || $pagenow === 'edit.php'  || $pagenow === 'post-new.php' ) {
				// Editing post in classic editor
				return '';
			}

			if ( isset( $_SERVER["CONTENT_TYPE"] ) && 'application/json' === $_SERVER["CONTENT_TYPE"] ) {
				// Editing post in Gutenberg editor
				return null;
			}

			return false;
		}

		/**
		 * Get all available post types.
		 *
		 * @return array
		 */
		public static function get_post_types() {
			global $wpdb;
			$rows = $wpdb->get_results(
				"select distinct post_type from {$wpdb->posts}",
				'ARRAY_N'
			);

			$cpts = array();
			foreach ( $rows as $row ) {
				$cpts[] = $row[0];
			}

			return $cpts;
		}

		public static function get_table_engine( $schema_name, $table_name ) {
			$table_info = WPDA::get_table_values( $schema_name, $table_name );
			//phpcs:ignore - 8.1 proof
			return (
				1 === count( $table_info ) &&
				'connect' === strtolower( $table_info[0]['engine'] )
			) ? 'connect' : '';
		}

		public static function get_table_values( $schema_name, $table_name ) {
			$wpdadb = WPDADB::get_db_connection( $schema_name );
			return $wpdadb->get_results(
				$wpdadb->prepare(
					"
						select engine as engine,
							   table_rows as table_rows,
							   table_type as table_type
						 from  information_schema.tables
						where  table_schema = %s
						  and  table_name = %s
					",
					[
						$wpdadb->dbname,
						$table_name
					]
				),
				'ARRAY_A'
			);
		}

		/**
		 * Get estimated number of rows in a table
		 *
		 * @param $schema_name Schema name
		 * @param $table_name Table name
			 * @param $wpda_table_settings Table settings (query WPDA_Table_Settings_Model)
		 *
		 * @return int row count estimate or -1 if no estimate available
		 */
		public static function get_row_count_estimate( $schema_name, $table_name, $wpda_table_settings ) {
			$row_count     = null;
			$is_estimate   = null;
			$do_real_count = null;
			$table_info    = self::get_table_values( $schema_name, $table_name );
			if ( 1 === count( $table_info ) ) {//phpcs:ignore - 8.1 proof
				$row_count  = $table_info[0]['table_rows'];

				$system_row_count_estimate    = null;
				$row_count_estimate_value     = null;
				$row_count_estimate_value_hard = null;

				if ( isset( $wpda_table_settings->table_settings->row_count_estimate ) ) {
					$system_row_count_estimate = $wpda_table_settings->table_settings->row_count_estimate;
				}

				if ( isset( $wpda_table_settings->table_settings->row_count_estimate_value ) ) {
					$row_count_estimate_value = $wpda_table_settings->table_settings->row_count_estimate_value;
				}
				if ( isset( $wpda_table_settings->table_settings->row_count_estimate_value_hard ) ) {
					$row_count_estimate_value_hard = $wpda_table_settings->table_settings->row_count_estimate_value_hard;
				}

				if (
					'federated' === strtolower( (string) $table_info[0]['engine'] ) ||
					'innodb' === strtolower( (string) $table_info[0]['engine'] ) ||
					stripos( strtolower( (string) $table_info[0]['table_type'] ), 'view' ) !== false
				) {
					// Handle InnoDB tables, views and system views
					$wpdadb = WPDADB::get_db_connection( $schema_name );
					$view_explain = $wpdadb->get_results(
						'explain select count(*) from `' . str_replace( '`', '', $table_name ) . '`',
						'ARRAY_A'
					);

					if ( isset( $view_explain[0]['rows'] ) ) {
						$row_count = $view_explain[0]['rows'];
					} elseif ( isset( $view_explain[0]['ROWS'] ) ) {
						$row_count = $view_explain[0]['ROWS'];
					}

					if ( true === $system_row_count_estimate ) {
						if ( 'hard' === $row_count_estimate_value && null !== $row_count_estimate_value_hard ) {
							$row_count = $row_count_estimate_value_hard;
						}
						$is_estimate   = true;
						$do_real_count = false;
					} elseif ( false === $system_row_count_estimate ) {
						$is_estimate   = false;
						$do_real_count = true;
					} else {
						if ( intval( $row_count ) > intval( WPDA::get_option( WPDA::OPTION_BE_INNODB_COUNT ) ) ) {
							$is_estimate   = true;
							$do_real_count = false;
						} else {
							$is_estimate   = false;
							$do_real_count = true;
						}
					}
				} elseif ( 'connect' === strtolower( $table_info[0]['engine'] ) ) {
					$getpk = WPDA_List_Columns_Cache::get_list_columns( $schema_name, $table_name );
					$pk    = $getpk->get_table_primary_key();
					if ( is_array( $pk ) && count( $pk ) > 0 ) {//phpcs:ignore - 8.1 proof
						$sql_rowcount_sql =
							'select count(*) from `' . str_replace( '`', '', $table_name ) .
							'` where `' . $pk[0] . '` not in ' .
								'(select null from `' . str_replace( '`', '', $table_name ) . '` where 1=2)';
						$wpdadb = WPDADB::get_db_connection( $schema_name );
						$sql_rowcount  = $wpdadb->get_results( $sql_rowcount_sql, 'ARRAY_N' );
						if ( count( $sql_rowcount ) === 1 ) {//phpcs:ignore - 8.1 proof
							$row_count     = $sql_rowcount[0][0];
							$is_estimate   = false;
							$do_real_count = false;
						} else {
							$is_estimate   = false;
							$do_real_count = true;
						}
					} else {
						$is_estimate   = false;
						$do_real_count = true;
					}
				} else {
					// Handle other table types (MyISAM and NDB)
					$is_estimate   = false;
					$do_real_count = false;
				}
			}

			return array(
				'row_count'     => $row_count,
				'is_estimate'   => $is_estimate,
				'do_real_count' => $do_real_count
			);
		}

		public static function validate_names( $schema_name, $table_name, $column_names = null ) {
			if ( ! self::validate_name( $table_name ) ) {
				return self::validate_name_failed();
			}

			if ( 'rdb:' === substr( $schema_name, 0, 4 ) ) {
				$wpdadb = WPDADB::get_db_connection( $schema_name );
				if ( $wpdadb === null ) {
					return self::validate_name_np();
				} else {
					if ( ! self::validate_name( $wpdadb->dbname ) ) {
						return self::validate_name_failed();
					}
				}
			} else {
				// Added prefix to schema validation: allowing database names to start with a number
				if ( ! self::validate_name( 'prefix_' . $schema_name ) ) {
					return self::validate_name_failed();
				}
			}

			if ( null === $column_names ) {
				$columns      = WPDA_Dictionary_Lists::get_table_columns( $table_name, $schema_name );
				$column_names = array_column( $columns, 'column_name' ); //phpcs:ignore - 8.1 proof
			}
			if ( count( $column_names ) === 0 ) {//phpcs:ignore - 8.1 proof
				return self::validate_name_np();
			}

			$is_valid = true;
			foreach ( $column_names as $column_name ) {
				if ( ! self::validate_name( $column_name ) ) {
					$is_valid = false;
					break;
				}
			}

			return $is_valid ? '' : self::validate_name_failed();
		}

		public static function validate_name( $item_name ) {
			if (
				! ctype_alpha( substr( $item_name, 0, 1 ) ) ||
				! preg_match("/^[_a-zA-Z0-9]+$/", $item_name)
			){
				return false;
			}

			return true;
		}

		public static function validate_name_failed() {
			$title   = __( 'Schema, table or column name(s) restricting plugin features (click to read more and fix)', 'wp-data-access' );
			$warning = "
				<a href='https://wpdataaccess.com/docs/data-explorer/naming-conventions/' target='_blank' style='text-decoration:none'>
					<span class='dashicons dashicons-flag wpda_tooltip' style='color:red;padding-left:5px' title='$title'></span>
				</a>";
			return $warning;
		}

		public static function validate_name_np() {
			$title   = __( 'Schema, table or column name validation not possible (click to read more and fix)', 'wp-data-access' );
			$warning = "
				<a href='https://wpdataaccess.com/docs/data-explorer/naming-conventions/' target='_blank'>
					<span class='dashicons dashicons-warning wpda_tooltip' style='padding-left:5px' title='$title'></span>
				</a>";
			return $warning;
		}

		public static function get_dbms_var( $var_name = null, $schema_name = null ) {
			if ( null === $schema_name ) {
				global $wpdb;
				$wpdadb = $wpdb;
			} else {
				$wpdadb = WPDADB::get_db_connection( $schema_name );
			}

			if ( null === $wpdadb ) {
				return null;
			}

			if ( null !== $var_name ) {
				$var_value = $wpdadb->get_results( "SHOW VARIABLES LIKE '$var_name'", 'ARRAY_N' );
				if ( is_array( $var_value ) && isset( $var_value[0][1] ) ) {
					return $var_value[0][1];
				} else {
					return null;
				}
			} else {
				return $wpdadb->get_results( 'SHOW VARIABLES', 'ARRAY_N' );
			}
		}

		public static function get_dbms_global( $global_name = null, $schema_name = null ) {
			if ( null === $schema_name ) {
				global $wpdb;
				$wpdadb = $wpdb;
			} else {
				$wpdadb = WPDADB::get_db_connection( $schema_name );
			}

			if ( null === $wpdadb ) {
				return null;
			}

			if ( null !== $global_name ) {
				$global_value = $wpdadb->get_results( "SHOW GLOBAL STATUS LIKE '$global_name'", 'ARRAY_N' );
				if ( is_array( $global_value ) && isset( $global_value[0][1] ) ) {
					return $global_value[0][1];
				} else {
					return null;
				}
			} else {
				return $wpdadb->get_results( 'SHOW GLOBAL STATUS', 'ARRAY_N' );
			}
		}

		public static function secondsToTime( $seconds ) {
			$dtF = new \DateTime('@0');
			$dtT = new \DateTime("@$seconds");
			return $dtF->diff( $dtT )->format('%a days, %h hours, %i minutes and %s seconds');
		}

		public static function get_sonce_token() {
			$encrypt_method = 'AES-256-CBC';
			$key            = hash( 'sha256', WPDA::get_option( WPDA::OPTION_PLUGIN_SECRET_KEY ) );
			$iv             = substr( hash( 'sha256', WPDA::get_option( WPDA::OPTION_PLUGIN_SECRET_IV ) ), 0, 16 );

			return base64_encode( openssl_encrypt( WPDA::get_option( WPDA::OPTION_PLUGIN_SONCE_SEED ) . $_SERVER['REMOTE_ADDR'], $encrypt_method, $key, 0, $iv ) );
		}

		public static function wpda_create_sonce( $action = 'undefined' ) {
			$token = self::get_sonce_token();
			$i     = wp_nonce_tick();

			return substr( wp_hash( $i . '|' . $action . '|' . $token, 'nonce' ), -12, 10 );
		}

		public static function wpda_verify_sonce( $sonce, $action = 'undefined' ) {
			$expected = WPDA::wpda_create_sonce($action);

			return hash_equals( $expected , $sonce );
		}

		public static function get_plugin_upload_dir() {
			$uploads = wp_upload_dir();

			return $uploads['basedir'] . DIRECTORY_SEPARATOR . 'wp-data-access' . DIRECTORY_SEPARATOR;
		}

		public static function wpda_create_content_folder() {
			$upload_dir = WPDA::get_plugin_upload_dir();
			if ( ! file_exists( $upload_dir ) ) {
				mkdir( $upload_dir, 0755, true );

				$fw = fopen( $upload_dir . ".htaccess", 'w' );
				if ( false !== $fw ) {
					fwrite( $fw, "IndexIgnore *" );
				}
				fclose( $fw );
			}
		}

		public static function wpda_delete_content_folder() {
			$upload_dir = WPDA::get_plugin_upload_dir();
			if ( file_exists( $upload_dir ) ) {
				$files = glob( $upload_dir . '*', GLOB_MARK );
				foreach ( $files as $file ) {
					unlink( $file );
				}
				unlink( $upload_dir . '.htaccess'  );

				rmdir( $upload_dir );
			}
		}

		public static function sent_header( $content_type, $cors = null, $attachment = null ) {
			if ( null !== $cors ) {
				header( "Access-Control-Allow-Origin: {$cors}" );
			}

			if ( null !== $attachment ) {
				header("Content-Disposition: attachment; filename={$attachment}");
			}

			header( "Content-type: {$content_type}" );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Cache-Control: post-check=0, pre-check=0', false );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		public static function sent_msg( $status, $msg ) {
			echo json_encode(
				array(
					'status' => $status,
					'msg'    => $msg,
				), true
			); // phpcs:ignore - 8.1 proof
		}

		public static function is_post() {
			global $post;
			return 'post' === get_post_type( $post );
		}

		public static function is_page() {
			global $post;
			return 'page' === get_post_type( $post );
		}

		public static function can_manage() {
			$user_id = get_current_user_id();

			$wpda_hide_manage_link = get_option( 'wpda_hide_manage_link' );
			if ( is_array( $wpda_hide_manage_link ) ) {
				$wpda_hide_manage_list = array_flip( $wpda_hide_manage_link ); //phpcs:ignore - 8.1 proof
			} else {
				$wpda_hide_manage_list = array();
			}

			return ! isset( $wpda_hide_manage_list[ $user_id ] );
		}

		/**
		 * Remove backticks from string
		 *
		 * @param string $s Subject.
		 * @return array|string|string[]
		 */
		public static function remove_backticks( $s ) {
			return str_replace( '`', '', $s );
		}

		/**
		 * Sanitize array:
		 * uses sanitize_text_field on all array elements
		 * supports multidimensional arrays
		 *
		 * @param mixed $input Any value (initial value is supposed to be an array).
		 * @param array $is_textarea List of textarea fields.
		 * @return array|mixed
		 */
		public static function sanitize_text_field_array( $input, $is_textarea = array() ) {
			return
				is_array( $input ) ?
					array_map(
						function( $element ) use ( $is_textarea ) { return WPDA::sanitize_text_field_array( $element, $is_textarea ); },
						$input
					) :
					(
						is_scalar( $input ) ?
							( ! isset( $is_textarea[ $input ] ) ? sanitize_text_field( wp_unslash( $input ) ) : sanitize_textarea_field( $input ) ) :
							$input
					);
		}

		/**
		 * Get server IP address
		 *
		 * @return mixed|string
		 */
		public static function get_server_address() {
			if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
				return $_SERVER['SERVER_ADDR'];
			} elseif ( isset( $_SERVER['LOCAL_ADDR'] ) ) {
				return $_SERVER['LOCAL_ADDR'];
			} else {
				return 'UNKNOWN';
			}
		}

		public static function convert_default_value( $item_default_value ) {
			switch( $item_default_value ) {
				case '$$USERID$$':
					return WPDA::get_current_user_id();
				case '$$USER$$':
					return WPDA::get_current_user_login();
				case '$$EMAIL$$':
					return WPDA::get_current_user_email();
				case '$$NOW$$':
				case '$$NOWDT$$':
					global $wpdb;
					$now_db       = $wpdb->get_var( 'select now()' );
					$db_format    = WPDA::DB_DATETIME_FORMAT;
					$convert_date = \DateTime::createFromFormat( $db_format, $now_db );
					if ( false !== $convert_date ) {
						$date_format = WPDA::get_option( WPDA::OPTION_PLUGIN_DATE_FORMAT );
						$time_format = WPDA::get_option( WPDA::OPTION_PLUGIN_TIME_FORMAT );
						$item_format = $date_format;
						if ( '$$NOWDT$$' === $item_default_value ) {
							$item_format .= " {$time_format}";
						}
						return $convert_date->format( $item_format );
					} else {
						return '';
					}
				default:
					return $item_default_value;
			}
		}

		/**
		 * Write error to WordPress log file
		 *
		 * @param string $errmsg Error message
		 * @return void
		 */
		public static function wpda_log_wp_error( $errmsg ) {
			$dbt = debug_backtrace();
			$clr = array_shift( $dbt );//phpcs:ignore - 8.1 proof

			error_log( "WP Data Access error in {$clr['file']}:{$clr['line']}" );
			error_log( print_r( $errmsg, true ) );
		}

		/**
		 * Get the column data types from a custom query
		 *
		 * @param $database Database (schema) name
		 * @param $query Custom query
		 * @return array|null Column data type array or null if the query does not return any columns
		 */
		public static function get_columns_from_query( $database, $query ) {
			$wpdadb = WPDADB::get_db_connection( $database );
			if ( null === $wpdadb ) {
				return null;
			}

			$wpdadb->suppress_errors( true );
			$table_name = 'custom' . mt_rand(0, time());

			// Create temporary table.
			$wpdadb->query(
				"CREATE TEMPORARY TABLE `{$table_name}` AS (select * from (" . $query . ') as t limit 0)'
			);

			if ( '' !== $wpdadb->last_error ) {
				return null;
			}

			// Get query structure.
			$rows = $wpdadb->get_results( "desc `{$table_name}`", 'ARRAY_A' );

			if ( '' !== $wpdadb->last_error ) {
				return null;
			}

			return $rows;
		}

	}

}
