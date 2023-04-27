<?php

/**
 * Suppress "error - 0 - No summary was found for this file" on phpdoc generation
 *
 * @package WPDataAccess\Data_Tables
 */
namespace WPDataAccess\Data_Tables;

use  stdClass ;
use  WPDataAccess\Connection\WPDADB ;
use  WPDataAccess\Data_Dictionary\WPDA_Dictionary_Exist ;
use  WPDataAccess\Data_Dictionary\WPDA_List_Columns_Cache ;
use  WPDataAccess\Macro\WPDA_Macro ;
use  WPDataAccess\Plugin_Table_Models\WPDA_Publisher_Model ;
use  WPDataAccess\Plugin_Table_Models\WPDA_Media_Model ;
use  WPDataAccess\Plugin_Table_Models\WPDA_Table_Settings_Model ;
use  WPDataAccess\List_Table\WPDA_List_Table ;
use  WPDataAccess\Premium\WPDAPRO_Data_Publisher\WPDAPRO_Data_Publisher_Manage_Styles ;
use  WPDataAccess\Premium\WPDAPRO_Geo_Location\WPDAPRO_Geo_Location_WS ;
use  WPDataAccess\Settings\WPDA_Settings_DataTables ;
use  WPDataAccess\Templates\WPDAPRO_Template_Data_Publisher_Color ;
use  WPDataAccess\Templates\WPDAPRO_Template_Data_Publisher_Space ;
use  WPDataAccess\Templates\WPDA_Template_Data_Publisher_Corner ;
use  WPDataAccess\WPDA ;
/**
 * Class WPDA_Data_Tables
 *
 * @author  Peter Schulz
 * @since   1.0.0
 */
class WPDA_Data_Tables
{
    const  METHODS = array( 'httpGet', 'httpPost', 'httpRequest' ) ;
    protected static  $pub_ids = array() ;
    protected  $pub_id_seq = '' ;
    protected  $wpda_list_columns = null ;
    protected  $wpda_dictionary_checks = null ;
    protected  $json = null ;
    protected  $table_settings = null ;
    protected  $hyperlink_positions = array() ;
    protected  $columns = array() ;
    protected  $column_labels = null ;
    protected  $primary_index_sorted = array() ;
    protected  $buttons = '[]' ;
    //CWG This is correct
    protected  $geomap = '' ;
    protected  $geo_search = '' ;
    protected  $geo_search_type = null ;
    protected  $read_more_html = '' ;
    protected  $serverSide = false ;
    public static function enqueue_styles_and_script( $styling = 'default' )
    {
        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-resizable' );
        // Plugin css
        wp_enqueue_style( 'wpda_datatables_default' );
        wp_enqueue_style( 'dashicons' );
        // Needed to display icons for media attachments
        // Plugin js
        wp_enqueue_script( 'wpda_datatables' );
        // Add jQuery DataTables library scripts
        if ( is_admin() && WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES ) === 'on' || !is_admin() && WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES ) === 'on' ) {
            wp_enqueue_script( 'jquery_datatables' );
        }
        if ( is_admin() && WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES_RESPONSE ) === 'on' || !is_admin() && WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES_RESPONSE ) === 'on' ) {
            wp_enqueue_script( 'jquery_datatables_responsive' );
        }
        $style_added = false;
        
        if ( !$style_added || 'default' === $styling ) {
            // Add jQuery DataTables library styles
            if ( is_admin() && WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES ) === 'on' || !is_admin() && WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES ) === 'on' ) {
                wp_enqueue_style( 'jquery_datatables' );
            }
            if ( is_admin() && WPDA::get_option( WPDA::OPTION_BE_LOAD_DATATABLES_RESPONSE ) === 'on' || !is_admin() && WPDA::get_option( WPDA::OPTION_FE_LOAD_DATATABLES_RESPONSE ) === 'on' ) {
                wp_enqueue_style( 'jquery_datatables_responsive' );
            }
        }
    
    }
    
    /**
     * Generate jQuery DataTable code
     *
     * Table and column names provided are checked for existency and access to prevent hacking the DataTable code
     * and SQL injection.
     *
     * @param int    $pub_id Table ID.
     * @param string $pub_name Table name.
     * @param string $database Database name.
     * @param string $table_name Database table name.
     * @param string $column_names Comma separated list of column names.
     * @param string $responsive Yes = responsive mode, No = No responsive mode.
     * @param int    $responsive_cols Number of columns to be displayed in responsive mode.
     * @param string $responsive_type Modal, Collapsed or Expanded (only if $responsive = Yes).
     * @param string $responsive_icon Yes = show icon, No = do not show icon (only if $responsive = Yes).
     * @param string $sql_orderby SQL default order by
     * @param string $filter_field_name Filter field name (CSV)
     * @param string $filter_field_value Filter field value (CSV)
     * @param string $nl2br Convert New Line characters to BR tags
     *
     * @return string response wpda_datatables_ajax_call
     *
     * @since   1.0.0
     */
    public function show(
        $pub_id,
        $pub_name,
        $database,
        $table_name,
        $column_names,
        $responsive,
        $responsive_cols,
        $responsive_type,
        $responsive_icon,
        $sql_orderby,
        $filter_field_name = '',
        $filter_field_value = '',
        $nl2br = '',
        $dashboard_styling = false,
        $is_embedded = false
    )
    {
        if ( '' === $pub_id && '' === $pub_name && '' === $table_name ) {
            return '<p>' . __( 'ERROR: Missing argument [need at least pub_id, pub_name or table argument]', 'wp-data-access' ) . '</p>';
        }
        
        if ( '' !== $pub_id || '' !== $pub_name ) {
            // Get data table information
            
            if ( '' !== $pub_id ) {
                $publication = WPDA_Publisher_Model::get_publication( $pub_id );
            } else {
                $publication = WPDA_Publisher_Model::get_publication_by_name( $pub_name );
            }
            
            if ( false === $publication ) {
                // Querying tables in other schema's is not allowed!
                return '<p>' . __( 'ERROR: Data table not found', 'wp-data-access' ) . '</p>';
            }
            $pub_id = $publication[0]['pub_id'];
            $database = $publication[0]['pub_schema_name'];
            $data_source = $publication[0]['pub_data_source'];
            $table_name = $publication[0]['pub_table_name'];
            $column_names = $publication[0]['pub_column_names'];
            $pub_query = $publication[0]['pub_query'];
            $pub_cpt_query = $publication[0]['pub_cpt_query'];
            $pub_cpt_format = $publication[0]['pub_cpt_format'];
            $responsive = strtolower( (string) $publication[0]['pub_responsive'] );
            $responsive_popup_title = $publication[0]['pub_responsive_popup_title'];
            $responsive_cols = $publication[0]['pub_responsive_cols'];
            $responsive_type = strtolower( (string) $publication[0]['pub_responsive_type'] );
            $responsive_icon = strtolower( (string) $publication[0]['pub_responsive_icon'] );
            $pub_flat_scrollx = strtolower( (string) $publication[0]['pub_flat_scrollx'] );
            $pub_format = $publication[0]['pub_format'];
            $sql_orderby = $publication[0]['pub_default_orderby'];
            if ( null === $sql_orderby || 'null' === $sql_orderby ) {
                $sql_orderby = '';
            }
            $pub_table_options_searching = $publication[0]['pub_table_options_searching'];
            $pub_table_options_ordering = $publication[0]['pub_table_options_ordering'];
            $pub_table_options_paging = $publication[0]['pub_table_options_paging'];
            $pub_table_options_serverside = $publication[0]['pub_table_options_serverside'];
            $pub_table_options_advanced = $publication[0]['pub_table_options_advanced'];
            $pub_table_options_advanced = str_replace( array(
                "\r\n",
                "\r",
                "\n",
                "\t"
            ), '', (string) $pub_table_options_advanced );
            $pub_responsive_modal_hyperlinks = $publication[0]['pub_responsive_modal_hyperlinks'];
            $pub_sort_icons = $publication[0]['pub_sort_icons'];
            $pub_styles = $publication[0]['pub_styles'];
            $pub_extentions = $publication[0]['pub_extentions'];
            
            if ( !isset( self::$pub_ids[$pub_id] ) ) {
                self::$pub_ids[$pub_id] = 1;
                $this->pub_id_seq = '';
            } else {
                self::$pub_ids[$pub_id]++;
                $this->pub_id_seq = '_' . self::$pub_ids[$pub_id];
            }
        
        } else {
            $pub_id = '0';
            $data_source = 'Table';
            $pub_query = null;
            $pub_cpt_query = null;
            $pub_cpt_format = '';
            $responsive_popup_title = '';
            $pub_format = '';
            $pub_table_options_searching = 'on';
            $pub_table_options_ordering = 'on';
            $pub_table_options_paging = 'on';
            $pub_table_options_serverside = 'on';
            $pub_table_options_advanced = '';
            $pub_responsive_modal_hyperlinks = '';
            $pub_sort_icons = 'default';
            $pub_styles = 'default';
            $pub_extentions = '';
            $pub_flat_scrollx = 'no';
            $this->pub_id_seq = '';
        }
        
        // Activate scripts and styles
        $styling = $this->set_style( $dashboard_styling, ( isset( $publication ) ? $publication : null ) );
        self::enqueue_styles_and_script( $styling );
        // Create JSON object from advanced settings
        try {
            $this->json = json_decode( (string) $pub_table_options_advanced );
            if ( null === $this->json ) {
                $this->json = new stdClass();
            }
        } catch ( \Exception $e ) {
            $this->json = new stdClass();
        }
        // Add extension support
        $this->extension_wizard( $pub_extentions );
        // Check for extra header column
        $header2 = $this->add_extra_header();
        // Check button usage
        $use_buttons_extension = $this->use_buttons_extension();
        switch ( $pub_sort_icons ) {
            case 'none':
                // Hide sort icons
                wp_enqueue_style( 'wpda_datatables_hide_sort_icons' );
                break;
            default:
                // Show sort icons
        }
        
        if ( 'on' !== $pub_table_options_searching || null === $pub_table_options_searching ) {
            $pub_table_options_searching = 'false';
        } else {
            $pub_table_options_searching = 'true';
        }
        
        
        if ( 'on' !== $pub_table_options_ordering || null === $pub_table_options_ordering ) {
            $pub_table_options_ordering = 'false';
        } else {
            $pub_table_options_ordering = 'true';
        }
        
        
        if ( 'on' !== $pub_table_options_paging || null === $pub_table_options_paging ) {
            $pub_table_options_paging = 'false';
        } else {
            $pub_table_options_paging = 'true';
        }
        
        if ( !isset( $this->json->serverSide ) ) {
            if ( 'on' !== $pub_table_options_serverside || null === $pub_table_options_serverside ) {
                $this->json->serverSide = false;
            }
        }
        
        if ( '' === $responsive_popup_title || null === $responsive_popup_title || 'Row details' === $responsive_popup_title ) {
            $responsive_popup_title = __( 'Row details', 'wp-data-access' );
            // Set title of modal window here to support i18n.
        }
        
        // WordPress database is default
        
        if ( '' === $database ) {
            global  $wpdb ;
            $database = $wpdb->dbname;
        }
        
        
        if ( 'Query' === $data_source || 'CPT' === $data_source ) {
        } else {
            // Check if table exists to prevent SQL injection
            $this->wpda_dictionary_checks = new WPDA_Dictionary_Exist( $database, $table_name );
            if ( !$this->wpda_dictionary_checks->table_exists( '0' === $pub_id, false ) ) {
                // Table not found.
                return '<p>' . __( 'ERROR: Invalid table name or not authorized', 'wp-data-access' ) . '</p>';
            }
            // Load table settings
            $table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $database );
            if ( isset( $table_settings_db[0]['wpda_table_settings'] ) ) {
                $this->table_settings = json_decode( (string) $table_settings_db[0]['wpda_table_settings'] );
            }
            // Get table settings > hyperlinks
            $hyperlinks = array();
            if ( isset( $this->table_settings->hyperlinks ) ) {
                foreach ( $this->table_settings->hyperlinks as $hyperlink ) {
                    $hyperlink_label = ( isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '' );
                    $hyperlink_html = ( isset( $hyperlink->hyperlink_html ) ? $hyperlink->hyperlink_html : '' );
                    
                    if ( $hyperlink_label !== '' && $hyperlink_html !== '' ) {
                        array_push( $hyperlinks, $hyperlink_label );
                        //phpcs:ignore - 8.1 proof
                    }
                
                }
            }
            // Check for geolocation support
            $geolocation = $this->get_geolocation_settings();
            $row_count_estimate = WPDA::get_row_count_estimate( $database, $table_name, $this->table_settings );
            $calc_estimate = $row_count_estimate['is_estimate'];
            // Get table columns
            $this->wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $database, $table_name );
            // Set columns to be queried
            $this->columns = $this->get_columns( $column_names );
            // Get column labels
            $this->column_labels = $this->get_labels( $pub_format );
            // Define dat table columns
            $wpda_database_columns = $this->define_columns( $use_buttons_extension, $hyperlinks, $geolocation );
        }
        
        // Run filters to allow plugin users to add custom features
        
        if ( has_filter( 'wpda_wpdataaccess_prepare' ) ) {
            $wpda_wpdataaccess_prepare_filter = apply_filters(
                'wpda_wpdataaccess_prepare',
                '',
                $database,
                $table_name,
                $pub_id,
                $this->columns,
                $this->table_settings
            );
        } else {
            $wpda_wpdataaccess_prepare_filter = '';
        }
        
        // Get jQuery DataTables language
        $language = $this->get_language();
        // Create dynamic columns variable name (must be unique per data table to support multiple data tables on one page)
        $columnsvar = 'wpdaDbColumns' . preg_replace( '/[^a-zA-Z0-9]/', '', $table_name ) . $pub_id;
        // Add button extension
        $this->add_buttons( $use_buttons_extension, $pub_id, $table_name );
        // Add geolocation support
        $this->add_geolocation(
            $geolocation,
            $pub_id,
            $table_name,
            $database
        );
        // Add read more button
        $read_more = $this->add_read_more( $pub_id, $table_name, $pub_table_options_paging );
        // Update extra header of necessary
        $header2 = $this->update_extra_header( $header2, $pub_table_options_searching );
        // Apply global styling
        $dataTablesClass = $this->add_global_style( $styling, $pub_styles );
        // Themes like DIVI use IDs to overwrite all defaults of others. This style reassures correct positioning
        // of the responsive icon. This cannot be added to the plugin CSS files. It needs an ID to overwrite other
        // ID styling.
        $styling_default = "\n\t\t\t\t<style>\n\t\t\t\t\t#" . esc_attr( $table_name ) . "{$pub_id}.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>td.dtr-control,\n\t\t\t\t\t#" . esc_attr( $table_name ) . "{$pub_id}.dataTable.wpda-datatable.dtr-inline.collapsed>tbody>tr>th.dtr-control {\n    \t\t\t\t\tpadding-left: 2.2em;\n\t\t\t\t\t}\n\t\t\t\t</style>";
        // Add premium styling
        $styling_template = $this->add_styling_template(
            $pub_id,
            $table_name,
            $dashboard_styling,
            ( isset( $publication ) ? $publication : null )
        );
        // Prepare values needed for ajax request
        $database_value = $database;
        $column_names_value = $column_names;
        
        if ( '0' != $pub_id ) {
            $database_value = '';
            $column_names_value = '';
        }
        
        // Convert JSON to string
        $json_value = $this->prepare_json( $responsive, $pub_flat_scrollx );
        // Generate nonce
        $wpnonce = $this->generate_nonce( $table_name, $column_names_value, $is_embedded );
        $wpda_post_vars = [];
        $http_get_args = [];
        $http_post_args = [];
        return $wpda_wpdataaccess_prepare_filter . $styling_default . $styling_template . "<div class='wpda_publication_container'><table id=\"" . esc_attr( $table_name ) . "{$pub_id}{$this->pub_id_seq}\" class=\"{$dataTablesClass}\" cellspacing=\"0\">" . '<thead>' . $this->show_header(
            $responsive,
            $responsive_cols,
            $hyperlinks,
            $header2,
            $geolocation
        ) . '</thead>' . '<tfoot>' . $this->show_header(
            $responsive,
            $responsive_cols,
            $hyperlinks,
            '',
            $geolocation
        ) . '</tfoot>' . '</table></div>' . $this->read_more_html . "<script type='text/javascript'>" . "var {$columnsvar}_advanced_options = " . $json_value . '; ' . "var {$columnsvar} = [" . $wpda_database_columns . '];' . "var {$columnsvar}_geosearch_options = " . json_encode( $this->geo_search_type ) . '; ' . "var {$table_name}_{$pub_id}_args = [" . json_encode( $wpda_post_vars, true ) . ']; ' . "var {$table_name}_{$pub_id}_gets = [" . json_encode( $http_get_args, true ) . ']; ' . "var {$table_name}_{$pub_id}_posts = [" . json_encode( $http_post_args, true ) . ']; ' . 'jQuery(function () {' . '	wpda_datatables_ajax_call(' . "\t\t{$columnsvar}," . '		"' . esc_attr( $database_value ) . '",' . '		"' . esc_attr( $table_name ) . '",' . '		"' . esc_attr( $column_names_value ) . '",' . '		"' . esc_attr( $responsive ) . '",' . '		"' . esc_attr( $responsive_popup_title ) . '",' . '		"' . esc_attr( $responsive_type ) . '",' . '		"' . esc_attr( $responsive_icon ) . '",' . '		"' . esc_attr( $language ) . '",' . '		"' . htmlentities( $sql_orderby ) . '",' . "\t\t{$pub_table_options_searching}," . "\t    {$pub_table_options_ordering}," . "\t\t{$pub_table_options_paging}," . "\t\t{$columnsvar}_advanced_options," . "\t\t{$pub_id}," . '		"' . esc_attr( $pub_responsive_modal_hyperlinks ) . '",' . '		[' . implode( ',', $this->hyperlink_positions ) . '],' . '		"' . esc_attr( $filter_field_name ) . '",' . '		"' . esc_attr( $filter_field_value ) . '",' . '		"' . esc_attr( $nl2br ) . '",' . "\t\t{$this->buttons}," . "\t\t\"{$read_more}\"," . '		"' . (( $calc_estimate ? 'true' : 'false' )) . '",' . '		"' . trim( preg_replace( '/\\s+/', ' ', $this->geo_search ) ) . '",' . "\t\t{$columnsvar}_geosearch_options," . '		"' . $wpnonce . '",' . '		"' . $this->pub_id_seq . '"' . '	);' . '});' . '</script>' . $this->geomap;
    }
    
    protected function set_style( $dashboard_styling, $publication )
    {
        $styling = 'default';
        return $styling;
    }
    
    protected function extension_wizard( $pub_extentions )
    {
    }
    
    protected function add_extra_header()
    {
        $header2 = '';
        return $header2;
    }
    
    protected function use_buttons_extension()
    {
        $use_buttons_extension = false;
        return $use_buttons_extension;
    }
    
    protected function get_geolocation_settings()
    {
    }
    
    protected function get_columns( $column_names )
    {
        
        if ( '*' === $column_names ) {
            // Get all column names from table.
            $columns = array();
            foreach ( $this->wpda_list_columns->get_table_columns() as $column ) {
                $columns[] = $column['column_name'];
            }
            return $columns;
        } else {
            //phpcs:ignore - 8.1 proof
            $columns = explode( ',', (string) $column_names );
            // Create column ARRAY
            // Check if columns exist to prevent sql injection
            $i = 0;
            foreach ( $columns as $column ) {
                
                if ( 'wpda_hyperlink_' !== substr( $column, 0, 15 ) ) {
                    if ( !$this->wpda_dictionary_checks->column_exists( $column ) ) {
                        // Column not found
                        return __( 'ERROR: Column', 'wp-data-access' ) . ' ' . esc_attr( $column ) . ' ' . __( 'not found', 'wp-data-access' );
                    }
                } else {
                    $this->hyperlink_positions[] = $i;
                }
                
                $i++;
            }
            return $columns;
        }
    
    }
    
    protected function get_labels( $pub_format )
    {
        try {
            $pub_format_json = json_decode( (string) $pub_format, true );
            
            if ( isset( $pub_format_json['pub_format']['column_labels'] ) && is_array( $pub_format_json['pub_format']['column_labels'] ) ) {
                return array_merge( $this->wpda_list_columns->get_table_column_headers(), $pub_format_json['pub_format']['column_labels'] );
            } else {
                return $this->wpda_list_columns->get_table_column_headers();
            }
        
        } catch ( \Exception $e ) {
            return $this->wpda_list_columns->get_table_column_headers();
        }
    }
    
    protected function define_columns( $use_buttons_extension, $hyperlinks, $geolocation )
    {
        $wpda_database_columns = '';
        for ( $i = 0 ;  $i < count( $this->columns ) ;  $i++ ) {
            
            if ( 'wpda_hyperlink_' !== substr( $this->columns[$i], 0, 15 ) ) {
                $column_label = ( isset( $this->column_labels[$this->columns[$i]] ) ? $this->column_labels[$this->columns[$i]] : $this->columns[$i] );
            } else {
                $column_label = $hyperlinks[substr( $this->columns[$i], strrpos( $this->columns[$i], '_' ) + 1 )];
            }
            
            $data_type = WPDA::get_type( $this->wpda_list_columns->get_column_data_type( $this->columns[$i] ) );
            $data_type_class = "wpda_format_{$data_type}";
            $wpda_database_columns_obj = (object) null;
            $wpda_database_columns_obj->className = "{$this->columns[$i]} {$data_type_class}";
            $wpda_database_columns_obj->name = $this->columns[$i];
            $wpda_database_columns_obj->targets = $i;
            $wpda_database_columns_obj->label = $column_label;
            $wpda_database_columns_obj->searchBuilderType = WPDA::get_sb_type( $this->wpda_list_columns->get_column_data_type( $this->columns[$i] ) );
            $wpda_database_columns .= json_encode( $wpda_database_columns_obj );
            if ( $i < count( $this->columns ) - 1 ) {
                //phpcs:ignore - 8.1 proof
                $wpda_database_columns .= ',';
            }
        }
        return $wpda_database_columns;
    }
    
    protected function get_language()
    {
        // Check data table specific language
        if ( isset( $this->json->wpda_language, WPDA_Settings_DataTables::FRONTEND_LANG[$this->json->wpda_language] ) ) {
            return WPDA_Settings_DataTables::FRONTEND_LANG[$this->json->wpda_language];
        }
        // Get data table global language
        $language = WPDA::get_option( WPDA::OPTION_DP_LANGUAGE );
        $language_code = ( isset( WPDA_Settings_DataTables::FRONTEND_LANG[$language] ) ? WPDA_Settings_DataTables::FRONTEND_LANG[$language] : 'en-GB' );
        return $language_code;
    }
    
    protected function add_buttons( $use_buttons_extension, $pub_id, $table_name )
    {
    }
    
    private function get_button_caption()
    {
        return ( isset( $this->json->wpda_button_caption ) && null !== $this->json->wpda_button_caption ? $this->json->wpda_button_caption : 'label' );
    }
    
    protected function add_export_button( $button_type, $icon, $hint )
    {
    }
    
    protected function add_geolocation(
        $geolocation,
        $pub_id,
        $table_name,
        $database
    )
    {
        $this->geo_search_type = (object) null;
    }
    
    protected function add_read_more( $pub_id, $table_name, $pub_table_options_paging )
    {
        if ( 'false' === $pub_table_options_paging && isset( $this->json->serverSide ) && ('true' === $this->json->serverSide || true === $this->json->serverSide) ) {
            $this->read_more_html = '<div id="' . esc_attr( $table_name ) . "{$pub_id}_more_container\" class='wpda_more_container' >" . '<button id="' . esc_attr( $table_name ) . "{$pub_id}_more_button\" type='button' class='wpda_more_button dt-button'>SHOW MORE</button>" . '</div>';
        }
        return ( '' === $this->read_more_html ? 'false' : 'true' );
    }
    
    protected function update_extra_header( $header2, $pub_table_options_searching )
    {
        return $header2;
    }
    
    protected function add_global_style( $styling, $pub_styles )
    {
        $dataTablesClass = str_replace( array( ',', 'default' ), array( ' ', 'display' ), $pub_styles );
        return $dataTablesClass;
    }
    
    protected function add_styling_template(
        $pub_id,
        $table_name,
        $dashboard_styling,
        $publication
    )
    {
        $styling_template = '';
        // Needed for jdt
        $this->json->wpda_styling = $styling_template;
        return $styling_template;
    }
    
    protected function prepare_json( $responsive, $pub_flat_scrollx )
    {
        if ( !isset( $this->json->dom ) ) {
            $this->json->dom = 'lfrtip';
        }
        if ( 'no' === strtolower( $responsive ) && 'yes' === strtolower( $pub_flat_scrollx ) && !isset( $this->json->scrollX ) ) {
            $this->json->scrollX = true;
        }
        // Convert JSON to string
        return json_encode( $this->json );
    }
    
    protected function generate_nonce( $table_name, $column_names_value, $is_embedded )
    {
        // Generate nonce
        $nonce_seed = 'wpda-publication-' . $table_name . '-' . $column_names_value;
        
        if ( !$is_embedded ) {
            // Normal WordPress nonce
            return wp_create_nonce( $nonce_seed );
        } else {
            // Plugin string based nonce to secure embedding
            return WPDA::wpda_create_sonce( $nonce_seed );
        }
    
    }
    
    /**
     * Show table header (footer as well)
     *
     * @param string $responsive Yes = responsive mode, No = Flat mode.
     * @param int    $responsive_cols Number of columns to be displayed in responsive mode.
     * @param array  $hyperlinks Hyperlinks defined in column settings.
     * @param string $header2 Adds an extra header row if TRUE.
     * @param mixed  $geolocation
     *
     * @return HTML output
     */
    protected function show_header(
        $responsive,
        $responsive_cols,
        $hyperlinks,
        $header2,
        $geolocation
    )
    {
        $count = 0;
        $html_output = '';
        $html_search = '';
        foreach ( $this->columns as $column ) {
            $class = '';
            if ( 'yes' === $responsive ) {
                if ( is_numeric( $responsive_cols ) ) {
                    if ( (int) $responsive_cols > 0 ) {
                        
                        if ( $count >= 0 && $count < $responsive_cols ) {
                            $class = 'all';
                        } else {
                            $class = 'none';
                        }
                    
                    }
                }
            }
            
            if ( 'wpda_hyperlink_' !== substr( $column, 0, 15 ) ) {
                $column_label = ( isset( $this->column_labels[$column] ) ? $this->column_labels[$column] : $column );
            } else {
                $column_label = $hyperlinks[substr( $column, strrpos( $column, '_' ) + 1 )];
            }
            
            
            if ( 'header' === $header2 || 'both' === $header2 ) {
                $html_search .= "<td class=\"{$class}\" data-column_name_search=\"{$column}\" data-column_name_label=\"{$column_label}\"></td>";
                $html_output .= "<th class=\"{$class}\" data-column_name=\"{$column}\">{$column_label}</th>";
            } else {
                $html_output .= "<th class=\"{$class}\" data-column_name_search=\"{$column}\">{$column_label}</th>";
            }
            
            $count++;
        }
        if ( '' !== $html_search ) {
            $html_search = "<tr>{$html_search}</tr>";
        }
        return "{$html_search}<tr>{$html_output}</tr>";
    }
    
    private function url_params( $where )
    {
        return $where;
    }
    
    /**
     * Performs jQuery DataTable query
     *
     * Once a jQuery DataTable is build using {@see WPDA_Data_Tables::show()}, the DataTable is filled according
     * to the search criteria and pagination settings on the Datable. The query is performed through this function.
     * The query result is returned (echo) in JSON format. Table and column names are checked for existence and
     * access to prevent hacking the DataTable code and SQL injection.
     *
     * @since   1.0.0
     *
     * @see WPDA_Data_Tables::show()
     */
    public function get_data()
    {
        $where = '';
        $_filter = array();
        $has_sp = false;
        $sp_columns = array();
        $pub_id = ( isset( $_REQUEST['pubid'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pubid'] ) ) : '' );
        // input var okay.
        $database = ( isset( $_REQUEST['wpdasrc'] ) ? str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['wpdasrc'] ) ) ) : '' );
        // input var okay.
        $table_name = ( isset( $_REQUEST['wpdatabs'] ) ? str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['wpdatabs'] ) ) ) : '' );
        // input var okay.
        $columns = ( isset( $_REQUEST['wpdacols'] ) ? str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['wpdacols'] ) ) ) : '*' );
        // input var okay.
        $wpnonce = ( isset( $_REQUEST['wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpnonce'] ) ) : '' );
        // input var okay.
        
        if ( '' === $pub_id && '' === $table_name ) {
            // input var okay.
            // Database and table name must be set!
            $this->create_empty_response( 'Missing arguments' );
            wp_die();
        }
        
        $this->serverSide = true;
        // Set pagination values.
        $offset = 0;
        
        if ( isset( $_REQUEST['start'] ) ) {
            $offset = sanitize_text_field( wp_unslash( $_REQUEST['start'] ) );
            // input var okay.
        }
        
        $limit = -1;
        // jQuery DataTables default.
        
        if ( isset( $_REQUEST['length'] ) ) {
            $limit = sanitize_text_field( wp_unslash( $_REQUEST['length'] ) );
            // input var okay.
        }
        
        $publication_mode = 'normal';
        
        if ( -1 == $limit && isset( $_REQUEST['more_start'] ) && isset( $_REQUEST['more_limit'] ) ) {
            $publication_mode = 'more';
            $offset = sanitize_text_field( wp_unslash( $_REQUEST['more_start'] ) );
            // input var okay.
            $limit = sanitize_text_field( wp_unslash( $_REQUEST['more_limit'] ) );
            // input var okay.
        }
        
        
        if ( '' !== $pub_id && '0' != $pub_id ) {
            // Get data
            $publication = WPDA_Publisher_Model::get_publication( $pub_id );
            
            if ( false === $publication ) {
                // Data table not found
                $this->create_empty_response( 'Invalid arguments' );
                wp_die();
            }
            
            $kill_token = false;
            $pub_table_options_advanced = $publication[0]['pub_table_options_advanced'];
            $pub_table_options_advanced = str_replace( array(
                "\r\n",
                "\r",
                "\n",
                "\t"
            ), '', $pub_table_options_advanced );
            try {
                $json = json_decode( (string) $pub_table_options_advanced );
                $kill_token = isset( $json->killToken ) && (true === $json->killToken || 'true' === $json->killToken);
            } catch ( \Exception $e ) {
                $json = null;
            }
            $database = $publication[0]['pub_schema_name'];
            $table_name = $publication[0]['pub_table_name'];
            $columns = $publication[0]['pub_column_names'];
            // Check token
            $table_name_verify = ( 'Query' === $publication[0]['pub_data_source'] || 'CPT' === $publication[0]['pub_data_source'] ? "custom{$pub_id}" : $table_name );
            
            if ( !$kill_token && !wp_verify_nonce( $wpnonce, 'wpda-publication-' . $table_name_verify . '-' ) && !WPDA::wpda_verify_sonce( $wpnonce, 'wpda-publication-' . $table_name_verify . '-' ) ) {
                $this->create_empty_response( 'Token expired, please refresh page' );
                wp_die();
            }
            
            // Get default where
            if ( isset( $publication[0]['pub_default_where'] ) ) {
                if ( null !== $publication[0]['pub_default_where'] && '' !== trim( $publication[0]['pub_default_where'] ) ) {
                    $where = $publication[0]['pub_default_where'];
                }
            }
            // Get server side options.
            
            if ( isset( $json->serverSide ) ) {
                $this->serverSide = true === $json->serverSide || 'true' === $json->serverSide;
            } else {
                if ( 'on' !== $publication[0]['pub_table_options_serverside'] || null === $publication[0]['pub_table_options_serverside'] ) {
                    $this->serverSide = false;
                }
            }
        
        } else {
            // Check token = old shortcode usage
            
            if ( !wp_verify_nonce( $wpnonce, 'wpda-publication-' . $table_name . '-' . $columns ) && !WPDA::wpda_verify_sonce( $wpnonce, 'wpda-publication-' . $table_name . '-' . $columns ) ) {
                $this->create_empty_response( 'Token expired, please refresh page' );
                wp_die();
            }
            
            // Do not allow to access other schemas
            
            if ( strpos( $table_name, '.' ) ) {
                $this->create_empty_response( 'Wrong argument' );
                wp_die();
            }
            
            // Check access
            $wpda_dictionary_checks = new WPDA_Dictionary_Exist( $database, $table_name );
            
            if ( !$wpda_dictionary_checks->table_exists( true, false ) ) {
                $this->create_empty_response( 'Not authorized' );
                wp_die();
            }
            
            // Init var to prevent undefined var messages.
            $json = null;
        }
        
        if ( '' !== $where && 'where' !== strtolower( trim( substr( $where, 0, 5 ) ) ) ) {
            $where = "where {$where}";
        }
        if ( '' !== $where ) {
            $_filter = array(
                'filter_default' => $where,
            );
        }
        $wpdadb = WPDADB::get_db_connection( $database );
        
        if ( null === $wpdadb ) {
            $this->create_empty_response( 'Invalid connection' );
            wp_die();
            // Remote database not available
        }
        
        // Add field filters from shortcode
        $filter_field_name = str_replace( '`', '', sanitize_text_field( wp_unslash( $_REQUEST['filter_field_name'] ) ) );
        // input var okay.
        $filter_field_value = sanitize_text_field( wp_unslash( $_REQUEST['filter_field_value'] ) );
        // input var okay.
        
        if ( '' !== $filter_field_name && '' !== $filter_field_value ) {
            $filter_field_name_array = array_map( 'trim', explode( ',', $filter_field_name ) );
            //phpcs:ignore - 8.1 proof
            $filter_field_value_array = array_map( 'trim', explode( ',', $filter_field_value ) );
            //phpcs:ignore - 8.1 proof
            if ( count( $filter_field_name_array ) === count( $filter_field_value_array ) ) {
                //phpcs:ignore - 8.1 proof
                // Add filter to where clause
                for ( $i = 0 ;  $i < count( $filter_field_name_array ) ;  $i++ ) {
                    //phpcs:ignore - 8.1 proof
                    
                    if ( '' === $where ) {
                        $where = $wpdadb->prepare( " where `{$filter_field_name_array[$i]}` like %s ", array( $filter_field_value_array[$i] ) );
                    } else {
                        $where .= $wpdadb->prepare( " and `{$filter_field_name_array[$i]}` like %s ", array( $filter_field_value_array[$i] ) );
                    }
                    
                    $_filter['filter_field_name'] = $filter_field_name;
                    $_filter['filter_field_value'] = $filter_field_value;
                }
            }
        }
        
        // Get all column names from table (must be comma separated string)
        $this->wpda_list_columns = WPDA_List_Columns_Cache::get_list_columns( $database, $table_name );
        $table_columns = $this->wpda_list_columns->get_table_columns();
        // Save column:data_type pairs for fast access
        $column_array_ordered = array();
        foreach ( $table_columns as $column ) {
            $column_array_ordered[$column['column_name']] = $column['data_type'];
        }
        // Load table settings
        $table_settings_db = WPDA_Table_Settings_Model::query( $table_name, $database );
        
        if ( isset( $table_settings_db[0]['wpda_table_settings'] ) ) {
            $table_settings = json_decode( (string) $table_settings_db[0]['wpda_table_settings'] );
        } else {
            $table_settings = array();
        }
        
        
        if ( '*' === $columns ) {
            // Get all column names from table (must be comma separated string).
            $column_array = array();
            foreach ( $table_columns as $column ) {
                $column_array[] = $column['column_name'];
            }
            $columns = implode( ',', $column_array );
        } else {
            // Check if columns exist (prevent sql injection).
            $wpda_dictionary_checks = new WPDA_Dictionary_Exist( $database, $table_name );
            $column_array = explode( ',', (string) $columns );
            //phpcs:ignore - 8.1 proof
            $has_dynamic_hyperlinks = false;
            foreach ( $column_array as $column ) {
                
                if ( 'wpda_hyperlink_' !== substr( $column, 0, 15 ) ) {
                    
                    if ( !$wpda_dictionary_checks->column_exists( $column ) ) {
                        // Column not found.
                        $this->create_empty_response( 'Invalid column name' );
                        wp_die();
                    }
                
                } else {
                    $has_dynamic_hyperlinks = true;
                }
            
            }
            
            if ( $has_dynamic_hyperlinks ) {
                // Check for columns needed for substitution and missing in the query
                $hyperlink_substitution_columns = array();
                if ( isset( $table_settings->hyperlinks ) ) {
                    foreach ( $table_settings->hyperlinks as $hyperlink ) {
                        if ( isset( $hyperlink->hyperlink_html ) ) {
                            foreach ( $table_columns as $column ) {
                                if ( stripos( $hyperlink->hyperlink_html, "\$\${$column['column_name']}\$\$" ) !== false ) {
                                    $hyperlink_substitution_columns[$column['column_name']] = true;
                                }
                            }
                        }
                    }
                }
                foreach ( $hyperlink_substitution_columns as $hyperlink_substitution_column => $val ) {
                    if ( !in_array( $hyperlink_substitution_column, $column_array ) ) {
                        //phpcs:ignore - 8.1 proof
                        $columns .= ",{$hyperlink_substitution_column}";
                    }
                }
            }
        
        }
        
        // Save column name without backticks for later use
        $column_array_clean = $column_array;
        // Set order by.
        $orderby = '';
        
        if ( isset( $_REQUEST['order'] ) && is_array( $_REQUEST['order'] ) ) {
            // input var okay.
            $orderby_columns = array();
            $orderby_args = array();
            // Sanitize argument array and write result to temporary sanitizes array for processing:
            foreach ( $_REQUEST['order'] as $order_column ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                if ( isset( $order_column['column'] ) ) {
                    $orderby_args[] = array(
                        'column' => sanitize_sql_orderby( wp_unslash( $order_column['column'] ) ),
                        'dir'    => ( isset( $order_column['dir'] ) ? sanitize_text_field( wp_unslash( $order_column['dir'] ) ) : '' ),
                    );
                }
            }
            foreach ( $orderby_args as $order_column ) {
                // input var okay.
                $column_index = $order_column['column'];
                $column_name = str_replace( '`', '', $column_array[$column_index] );
                $column_dir = $order_column['dir'];
                $orderby_columns[] = "`{$column_name}` {$column_dir}";
            }
            $orderby = implode( ',', $orderby_columns );
        }
        
        // Add search criteria.
        
        if ( isset( $_REQUEST['search']['value'] ) ) {
            $search_value = sanitize_text_field( wp_unslash( $_REQUEST['search']['value'] ) );
            // input var okay.
        } else {
            $search_value = '';
        }
        
        $where_columns = WPDA::construct_where_clause(
            $database,
            $table_name,
            $this->wpda_list_columns->get_table_columns(),
            $search_value
        );
        if ( '' !== $where_columns ) {
            
            if ( '' === $where ) {
                $where = " where {$where_columns} ";
            } else {
                $where .= " and {$where_columns} ";
            }
        
        }
        if ( '' !== $where ) {
            $where = WPDA::substitute_environment_vars( $where );
        }
        if ( '' !== $search_value ) {
            $_filter['filter_dyn'] = $search_value;
        }
        foreach ( $_REQUEST as $key => $value ) {
            if ( 'wpda_search_' === substr( $key, 0, 12 ) ) {
                $_filter['filter_args'][$key] = $value;
            }
        }
        $geo_radius_col = '';
        // Execute query.
        $column_array = explode( ',', (string) $columns );
        //phpcs:ignore - 8.1 proof
        $column_array_orig = $column_array;
        $images_array = array();
        $imagesurl_array = array();
        $attachments_array = array();
        $hyperlinks_array = array();
        $hyperlinks_array_col = array();
        $audio_array = array();
        $video_array = array();
        
        if ( isset( $publication[0]['pub_format'] ) && '' !== $publication[0]['pub_format'] && null !== $publication[0]['pub_format'] ) {
            try {
                $pub_format = json_decode( (string) $publication[0]['pub_format'], true );
            } catch ( \Exception $e ) {
                $pub_format = null;
            }
            $column_images = array();
            $column_attachments = array();
            if ( isset( $pub_format['pub_format']['column_images'] ) ) {
                $column_images = $pub_format['pub_format']['column_images'];
            }
            if ( isset( $pub_format['pub_format']['column_attachments'] ) ) {
                $column_attachments = $pub_format['pub_format']['column_attachments'];
            }
            $i = 0;
            foreach ( $column_array as $col ) {
                
                if ( isset( $column_images[$col] ) ) {
                    array_push( $images_array, $i );
                    //phpcs:ignore - 8.1 proof
                }
                
                $i++;
            }
            $i = 0;
            foreach ( $column_array as $col ) {
                
                if ( isset( $column_attachments[$col] ) ) {
                    array_push( $attachments_array, $i );
                    //phpcs:ignore - 8.1 proof
                }
                
                $i++;
            }
        } else {
            $pub_format = null;
        }
        
        // Check media columns defined on plugin level and add to arrays
        $i = 0;
        foreach ( $column_array as $col ) {
            
            if ( 'Image' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
                
                if ( !isset( $images_array[$i] ) ) {
                    array_push( $images_array, $i );
                    //phpcs:ignore - 8.1 proof
                }
            
            } elseif ( 'ImageURL' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
                array_push( $imagesurl_array, $i );
                //phpcs:ignore - 8.1 proof
            } elseif ( 'Attachment' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
                
                if ( !isset( $attachments_array[$i] ) ) {
                    array_push( $attachments_array, $i );
                    //phpcs:ignore - 8.1 proof
                }
            
            } elseif ( 'Hyperlink' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
                
                if ( !isset( $hyperlinks_array[$i] ) ) {
                    array_push( $hyperlinks_array, $i );
                    //phpcs:ignore - 8.1 proof
                    array_push( $hyperlinks_array_col, $col );
                    //phpcs:ignore - 8.1 proof
                }
            
            } elseif ( 'Audio' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
                array_push( $audio_array, $i );
                //phpcs:ignore - 8.1 proof
            } elseif ( 'Video' === WPDA_Media_Model::get_column_media( $table_name, $col, $database ) ) {
                array_push( $video_array, $i );
                //phpcs:ignore - 8.1 proof
            }
            
            $i++;
        }
        // Change dynamic hyperlinks
        $update = array();
        $i = 0;
        $hyperlinks_column_index = array();
        foreach ( $column_array as $col ) {
            
            if ( 'wpda_hyperlink_' === substr( $col, 0, 15 ) ) {
                $update[$col] = "'x' as {$col}";
                $hyperlinks_column_index[$i] = substr( $col, 15 );
            } else {
                $update[$col] = '`' . str_replace( '`', '', $col ) . '`';
            }
            
            $i++;
        }
        $column_array = $update;
        $columns_backticks = implode( ',', $column_array ) . $geo_radius_col;
        $query = "select {$columns_backticks} from `{$wpdadb->dbname}`.`{$table_name}` {$where}";
        if ( '' !== $orderby ) {
            $query .= " order by {$orderby} ";
        }
        if ( -1 != $limit ) {
            $query .= " limit {$limit} offset {$offset}";
        }
        $hyperlinks = array();
        if ( count( $hyperlinks_column_index ) ) {
            //phpcs:ignore - 8.1 proof
            if ( isset( $table_settings->hyperlinks ) ) {
                foreach ( $table_settings->hyperlinks as $hyperlink ) {
                    $hyperlink_label = ( isset( $hyperlink->hyperlink_label ) ? $hyperlink->hyperlink_label : '' );
                    $hyperlink_target = ( isset( $hyperlink->hyperlink_target ) ? $hyperlink->hyperlink_target : false );
                    $hyperlink_html = ( isset( $hyperlink->hyperlink_html ) ? $hyperlink->hyperlink_html : '' );
                    if ( $hyperlink_label !== '' && $hyperlink_html !== '' ) {
                        //phpcs:ignore - 8.1 proof
                        array_push( $hyperlinks, array(
                            'hyperlink_label'  => $hyperlink_label,
                            'hyperlink_target' => $hyperlink_target,
                            'hyperlink_html'   => $hyperlink_html,
                        ) );
                    }
                }
            }
        }
        $nl2br = ( isset( $_REQUEST['nl2br'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nl2br'] ) ) : '' );
        // input var okay.
        
        if ( 'on' === $nl2br || 'yes' === $nl2br || 'true' === $nl2br ) {
            $nl2br = 'on';
        } else {
            if ( '' !== $pub_id ) {
                if ( isset( $publication[0]['pub_table_options_nl2br'] ) ) {
                    $nl2br = $publication[0]['pub_table_options_nl2br'];
                }
            }
        }
        
        $wpdadb->suppress_errors( true );
        $rows = $wpdadb->get_results( $query, 'ARRAY_N' );
        // phpcs:ignore Standard.Category.SniffName.ErrorCode
        
        if ( '' !== $wpdadb->last_error ) {
            $this->create_empty_response( $wpdadb->last_error, $query );
            wp_die();
        }
        
        $rows_final = array();
        foreach ( $rows as $row ) {
            $row_orig = $row;
            if ( 'on' === $nl2br && null !== $nl2br ) {
                // Replace NL with BR tags
                for ( $nl = 0 ;  $nl < count( $row ) ;  $nl++ ) {
                    //phpcs:ignore - 8.1 proof
                    $row[$nl] = nl2br( (string) $row[$nl] );
                }
            }
            foreach ( $hyperlinks_column_index as $key => $value ) {
                
                if ( isset( $hyperlinks[$value] ) ) {
                    $hyperlink_html = ( isset( $hyperlinks[$value]['hyperlink_html'] ) ? $hyperlinks[$value]['hyperlink_html'] : '' );
                    
                    if ( '' !== $hyperlink_html ) {
                        $i = 0;
                        foreach ( $column_array as $column ) {
                            $column_name = str_replace( '`', '', $column );
                            $hyperlink_html = str_replace( "\$\${$column_name}\$\$", $row[$i], $hyperlink_html );
                            $i++;
                        }
                    }
                    
                    $macro = new WPDA_Macro( $hyperlink_html );
                    $hyperlink_html = $macro->exe_macro();
                    
                    if ( '' !== $hyperlink_html ) {
                        
                        if ( false !== strpos( ltrim( $hyperlink_html ), '&lt;' ) ) {
                            $row[$key] = html_entity_decode( $hyperlink_html );
                        } else {
                            $hyperlink_label = ( isset( $hyperlinks[$value]['hyperlink_label'] ) ? $hyperlinks[$value]['hyperlink_label'] : '' );
                            $hyperlink_target = ( isset( $hyperlinks[$value]['hyperlink_target'] ) ? $hyperlinks[$value]['hyperlink_target'] : false );
                            $target = ( true === $hyperlink_target ? "target='_blank'" : '' );
                            $row[$key] = "<a href='" . str_replace( ' ', '+', $hyperlink_html ) . "' {$target}>{$hyperlink_label}</a>";
                        }
                    
                    } else {
                        $row[$key] = '';
                    }
                
                } else {
                    $row[$key] = 'ERROR';
                }
            
            }
            for ( $i = 0 ;  $i < count( $imagesurl_array ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                $row[$imagesurl_array[$i]] = '<img src="' . $row[$imagesurl_array[$i]] . '" width="100%">';
            }
            for ( $i = 0 ;  $i < count( $images_array ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                $image_ids = explode( ',', (string) $row[$images_array[$i]] );
                //phpcs:ignore - 8.1 proof
                $image_src = '';
                foreach ( $image_ids as $image_id ) {
                    $url = wp_get_attachment_url( esc_attr( $image_id ) );
                    
                    if ( false !== $url ) {
                        $image_src .= ( '' !== $image_src ? '<br/>' : '' );
                        $image_src .= '<img src="' . $url . '" width="100%">';
                    }
                
                }
                $row[$images_array[$i]] = $image_src;
            }
            for ( $i = 0 ;  $i < count( $attachments_array ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                $media_ids = explode( ',', (string) $row[$attachments_array[$i]] );
                //phpcs:ignore - 8.1 proof
                $media_links = '';
                foreach ( $media_ids as $media_id ) {
                    $url = wp_get_attachment_url( esc_attr( $media_id ) );
                    
                    if ( false !== $url ) {
                        $mime_type = get_post_mime_type( $media_id );
                        
                        if ( false !== $mime_type ) {
                            $title = get_the_title( esc_attr( $media_id ) );
                            $media_links .= WPDA_List_Table::column_media_attachment( $url, $title, $mime_type );
                        }
                    
                    }
                
                }
                $row[$attachments_array[$i]] = $media_links;
            }
            
            if ( isset( $hyperlinks_array ) ) {
                $hyperlink_definition = ( isset( $table_settings->table_settings->hyperlink_definition ) && 'text' === $table_settings->table_settings->hyperlink_definition ? 'text' : 'json' );
                for ( $i = 0 ;  $i < count( $hyperlinks_array ) ;  $i++ ) {
                    //phpcs:ignore - 8.1 proof
                    
                    if ( 'json' === $hyperlink_definition ) {
                        $hyperlink = json_decode( (string) $row[$hyperlinks_array[$i]], true );
                        
                        if ( is_array( $hyperlink ) && isset( $hyperlink['label'] ) && isset( $hyperlink['url'] ) && isset( $hyperlink['target'] ) ) {
                            
                            if ( '' === $hyperlink['url'] ) {
                                $row[$hyperlinks_array[$i]] = $hyperlink['label'];
                            } else {
                                $row[$hyperlinks_array[$i]] = "<a href='{$hyperlink['url']}' target='{$hyperlink['target']}'>{$hyperlink['label']}</a>";
                            }
                        
                        } else {
                            $row[$hyperlinks_array[$i]] = '';
                        }
                    
                    } else {
                        
                        if ( null !== $row[$hyperlinks_array[$i]] && '' !== $row[$hyperlinks_array[$i]] ) {
                            $hyperlink_label = $this->wpda_list_columns->get_column_label( $hyperlinks_array_col[$i] );
                            $row[$hyperlinks_array[$i]] = "<a href='{$row[$hyperlinks_array[$i]]}' target='_blank'>{$hyperlink_label}</a>";
                        } else {
                            $row[$hyperlinks_array[$i]] = '';
                        }
                    
                    }
                
                }
            }
            
            for ( $i = 0 ;  $i < count( $audio_array ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                $media_ids = explode( ',', (string) $row[$audio_array[$i]] );
                //phpcs:ignore - 8.1 proof
                $media_links = '';
                foreach ( $media_ids as $media_id ) {
                    
                    if ( 'audio' === substr( get_post_mime_type( $media_id ), 0, 5 ) ) {
                        $url = wp_get_attachment_url( esc_attr( $media_id ) );
                        
                        if ( false !== $url ) {
                            $title = get_the_title( esc_attr( $media_id ) );
                            if ( false !== $url ) {
                                $media_links .= '<div class="wpda_tooltip" title="' . $title . '">' . do_shortcode( '[audio src="' . $url . '"]' ) . '</div>';
                            }
                        }
                    
                    }
                
                }
                $row[$audio_array[$i]] = $media_links;
            }
            for ( $i = 0 ;  $i < count( $video_array ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                $media_ids = explode( ',', (string) $row[$video_array[$i]] );
                //phpcs:ignore - 8.1 proof
                $media_links = '';
                foreach ( $media_ids as $media_id ) {
                    
                    if ( 'video' === substr( get_post_mime_type( $media_id ), 0, 5 ) ) {
                        $url = wp_get_attachment_url( esc_attr( $media_id ) );
                        if ( false !== $url ) {
                            if ( false !== $url ) {
                                $media_links .= do_shortcode( '[video src="' . $url . '"]' );
                            }
                        }
                    }
                
                }
                $row[$video_array[$i]] = $media_links;
            }
            // Format date and time columns
            for ( $i = 0 ;  $i < count( $row ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                if ( '' !== $row[$i] && null !== $row[$i] ) {
                    if ( isset( $column_array_clean[$i] ) ) {
                        if ( isset( $column_array_ordered[$column_array_clean[$i]] ) ) {
                            switch ( $column_array_ordered[$column_array_clean[$i]] ) {
                                case 'date':
                                    $row[$i] = date_i18n( get_option( 'date_format' ), strtotime( $row[$i] ) );
                                    break;
                                case 'time':
                                    $row[$i] = date_i18n( get_option( 'time_format' ), strtotime( $row[$i] ) );
                                    break;
                                case 'datetime':
                                case 'timestamp':
                                    $row[$i] = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row[$i] ) );
                            }
                        }
                    }
                }
            }
            // Remove script tags if available
            for ( $i = 0 ;  $i < count( $row ) ;  $i++ ) {
                //phpcs:ignore - 8.1 proof
                $row[$i] = str_replace( array( '<script>', '</script>' ), array( '&lt;script&gt;', '&lt;/script&gt;' ), (string) $row[$i] );
            }
            array_push( $rows_final, $row );
            //phpcs:ignore - 8.1 proof
        }
        
        if ( $this->serverSide ) {
            
            if ( isset( $_REQUEST['records_total'] ) && is_numeric( $_REQUEST['records_total'] ) && (!isset( $json->wpda_count_on_each_request ) || false === $json->wpda_count_on_each_request) ) {
                // Prevent row count on each request
                $rows_estimate = sanitize_text_field( wp_unslash( $_REQUEST['records_total'] ) );
                // input var okay.
                $do_real_count = false;
            } else {
                $row_count_estimate = WPDA::get_row_count_estimate( $database, $table_name, $table_settings );
                $rows_estimate = $row_count_estimate['row_count'];
                $do_real_count = $row_count_estimate['do_real_count'];
            }
        
        } else {
            $rows_estimate = count( $rows_final );
            //phpcs:ignore - 8.1 proof
            $do_real_count = false;
        }
        
        
        if ( 'more' === $publication_mode ) {
            // Use estimate row count
            $count_table = $rows_estimate;
            $count_table_filtered = $rows_estimate;
        } else {
            
            if ( !$do_real_count ) {
                // Use estimate row count
                $count_table = $rows_estimate;
            } else {
                // Count rows in table = real row count
                $query2 = "select count(*) from `{$wpdadb->dbname}`.`{$table_name}`";
                $count_rows = $wpdadb->get_results( $query2, 'ARRAY_N' );
                // phpcs:ignore Standard.Category.SniffName.ErrorCode
                $count_table = $count_rows[0][0];
                // Number of rows in table.
            }
            
            
            if ( isset( $_REQUEST['wpda_use_estimates_only'] ) && 'true' === $_REQUEST['wpda_use_estimates_only'] ) {
                // Prevent row count, only estimates required
                $count_table_filtered = $count_table;
            } else {
                
                if ( '' !== $where ) {
                    
                    if ( isset( $_REQUEST['records_filtered'] ) && is_numeric( $_REQUEST['records_filtered'] ) && (!isset( $json->wpda_count_on_each_request ) || false === $json->wpda_count_on_each_request) ) {
                        $count_table_filtered = sanitize_text_field( wp_unslash( $_REQUEST['records_filtered'] ) );
                        // input var okay.
                    } else {
                        // Count rows in selection (only necessary if a search criteria was entered).
                        $query3 = "select count(*) from `{$wpdadb->dbname}`.`{$table_name}` {$where}";
                        $count_rows_filtered = $wpdadb->get_results( $query3, 'ARRAY_N' );
                        // phpcs:ignore Standard.Category.SniffName.ErrorCode
                        $count_table_filtered = $count_rows_filtered[0][0];
                        // Number of rows in table.
                    }
                
                } else {
                    // No search criteria entered: # filtered rows = # table rows.
                    $count_table_filtered = $count_table;
                }
            
            }
        
        }
        
        // Convert query result to jQuery DataTables object.
        $obj = (object) null;
        $obj->draw = ( isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 0 );
        $obj->recordsTotal = intval( $count_table );
        $obj->recordsFiltered = intval( $count_table_filtered );
        $obj->data = $rows_final;
        $obj->error = $wpdadb->last_error;
        if ( 'on' === WPDA::get_option( WPDA::OPTION_PLUGIN_DEBUG ) ) {
            $obj->debug = array(
                'columns'           => $columns,
                'columns_backticks' => $columns_backticks,
                'query'             => $query,
                'where'             => $where,
                'orderby'           => $orderby,
                'filter'            => $_filter,
                'advanced_settings' => $json,
                'column_labels'     => $this->wpda_list_columns->get_table_column_headers(),
                'labels'            => array_flip( $this->get_labels( json_encode( $pub_format ) ) ),
                'serverSide'        => $this->serverSide,
            );
        }
        // Send header
        
        if ( !WPDA::wpda_verify_sonce( $wpnonce, 'wpda-publication-' . $table_name . '-' ) ) {
            WPDA::sent_header( 'application/json' );
        } else {
            // Enable CORS for embedded publications
            WPDA::sent_header( 'application/json', '*' );
        }
        
        // Convert object to json. jQuery DataTables needs json format.
        echo  json_encode( $obj ) ;
        wp_die();
    }
    
    private function get_data_custom_query(
        $publication,
        $json,
        $has_sp,
        $sp_columns,
        $sql_query,
        $is_cpt,
        $offset,
        $limit
    )
    {
    }
    
    private function get_orderby_from_request()
    {
        $orderby = '';
        // Init order by.
        if ( isset( $_REQUEST['order'] ) && is_array( $_REQUEST['order'] ) ) {
            // input var okay.
            foreach ( $_REQUEST['order'] as $order_column ) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                
                if ( isset( $order_column['column'], $order_column['dir'] ) && is_numeric( $order_column['column'] ) && ('asc' === $order_column['dir'] || 'desc' === $order_column['dir']) ) {
                    $preprend = ( '' === $orderby ? ' order by ' : ',' );
                    $orderby .= $preprend . (intval( $order_column['column'] ) + 1) . ' ' . $order_column['dir'];
                }
            
            }
        }
        return $orderby;
    }
    
    private function sp(
        $wpdadb,
        $table_name,
        $columns,
        $panes,
        $where,
        $bt = '`'
    )
    {
        $sp = array();
        return $sp;
    }
    
    public function qb_group( $data )
    {
    }
    
    public function qb_criteria( $crit )
    {
    }
    
    private function qb( $labels )
    {
    }
    
    private function create_empty_response( $error = '', $debug = '' )
    {
        $obj = (object) null;
        $obj->draw = 0;
        $obj->recordsTotal = 0;
        $obj->recordsFiltered = 0;
        $obj->data = array();
        $obj->error = $error;
        $obj->debug = $debug;
        echo  json_encode( $obj ) ;
    }

}