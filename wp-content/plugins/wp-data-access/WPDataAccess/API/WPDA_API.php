<?php

// phpcs:ignore Standard.Category.SniffName.ErrorCode
/**
 * JSON REST API.
 */
namespace WPDataAccess\API;

use  WPDataAccess\WPDA ;
/**
 * JSON REST API main class.
 */
class WPDA_API
{
    const  WPDA_NAMESPACE = 'wpda' ;
    const  WPDA_REST_API_TABLE_ACCESS = 'wpda_rest_api_table_access' ;
    /**
     * Register routes.
     *
     * @return void
     */
    public function init()
    {
        register_rest_route( self::WPDA_NAMESPACE, 'info', array(
            'methods'             => array( \WP_REST_Server::READABLE ),
            'callback'            => function () {
            $license = 'free';
            return self::WPDA_Rest_Response( '', array(
                'license' => $license,
                'version' => WPDA::get_option( WPDA::OPTION_WPDA_VERSION ),
            ) );
        },
            'permission_callback' => '__return_true',
        ) );
        register_rest_route( self::WPDA_NAMESPACE, 'table/dtselect', array(
            'methods'             => array( \WP_REST_Server::READABLE, 'POST' ),
            'callback'            => array( $this, 'table_dtselect' ),
            'permission_callback' => '__return_true',
            'args'                => array(
            'dbs'              => array(
            'required'          => true,
            'description'       => __( 'Remote or local database connection string', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'tbl'              => array(
            'required'          => true,
            'description'       => __( 'Table or view name', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'start'            => array(
            'required'          => false,
            'description'       => __( 'Record offset', 'wp-data-access' ),
            'default'           => 0,
            'minimum'           => 0,
            'sanitize_callback' => function ( $param ) {
            return intval( sanitize_text_field( wp_unslash( $param ) ) );
        },
            'validate_callback' => function ( $param ) {
            return is_numeric( $param ) && $param >= 0;
        },
        ),
            'length'           => array(
            'required'          => false,
            'description'       => __( 'Number of records queried', 'wp-data-access' ),
            'default'           => 10,
            'minimum'           => 1,
            'sanitize_callback' => function ( $param ) {
            return intval( sanitize_text_field( wp_unslash( $param ) ) );
        },
            'validate_callback' => function ( $param ) {
            return is_numeric( $param ) && $param >= 0;
        },
        ),
            'order'            => array(
            'required'          => false,
            'description'       => __( 'Order by array[index][column]/[dir]', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::sanitize_text_field_array( $param );
        },
            'validate_callback' => function ( $param ) {
            return is_array( $param );
        },
        ),
            'search'           => array(
            'required'          => false,
            'description'       => __( 'Search filter', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::sanitize_text_field_array( $param );
        },
            'validate_callback' => function ( $param ) {
            return is_array( $param );
        },
        ),
            'draw'             => array(
            'required'          => false,
            'description'       => __( 'Internal use', 'wp-data-access' ),
            'default'           => 0,
            'sanitize_callback' => function ( $param ) {
            return intval( sanitize_text_field( wp_unslash( $param ) ) );
        },
            'validate_callback' => function ( $param ) {
            return is_numeric( $param ) && $param >= 0;
        },
        ),
            'records_total'    => array(
            'required'          => false,
            'description'       => __( 'Total record count from first query', 'wp-data-access' ),
            'default'           => null,
            'sanitize_callback' => function ( $param ) {
            
            if ( isset( $param ) && is_numeric( $param ) ) {
                return intval( sanitize_text_field( wp_unslash( $param ) ) );
            } else {
                return null;
            }
        
        },
            'validate_callback' => function ( $param ) {
            return is_numeric( $param ) && $param >= 0 || null === $param;
        },
        ),
            'records_filtered' => array(
            'required'          => false,
            'description'       => __( 'Record filtered from previous query', 'wp-data-access' ),
            'default'           => null,
            'sanitize_callback' => function ( $param ) {
            
            if ( isset( $param ) && is_numeric( $param ) ) {
                return intval( sanitize_text_field( wp_unslash( $param ) ) );
            } else {
                return null;
            }
        
        },
        ),
        ),
        ) );
        register_rest_route( self::WPDA_NAMESPACE, 'table/select', array(
            'methods'             => array( \WP_REST_Server::READABLE, 'POST' ),
            'callback'            => array( $this, 'table_select' ),
            'permission_callback' => '__return_true',
            'args'                => array(
            'dbs'       => array(
            'required'          => true,
            'description'       => __( 'Remote or local database connection string', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'tbl'       => array(
            'required'          => true,
            'description'       => __( 'Table or view name', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'page'      => array(
            'required'          => false,
            'description'       => __( 'Page number', 'wp-data-access' ),
            'default'           => 1,
            'minimum'           => 1,
            'sanitize_callback' => function ( $param ) {
            return sanitize_text_field( wp_unslash( $param ) );
        },
            'validate_callback' => function ( $param ) {
            return is_numeric( $param ) && $param > 0;
        },
        ),
            'rows'      => array(
            'required'          => false,
            'description'       => __( 'Rows per page', 'wp-data-access' ),
            'default'           => 10,
            'minimum'           => 1,
            'sanitize_callback' => function ( $param ) {
            return sanitize_text_field( wp_unslash( $param ) );
        },
            'validate_callback' => function ( $param ) {
            return is_numeric( $param ) && $param > 0;
        },
        ),
            'order'     => array(
            'required'          => false,
            'description'       => __( 'Order by (CSV list of column names)', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return sanitize_text_field( wp_unslash( $param ) );
        },
        ),
            'order_dir' => array(
            'required'          => false,
            'description'       => __( 'Order by directions (CSV list of sort directions: asc | desc)', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return sanitize_text_field( wp_unslash( $param ) );
        },
            'validate_callback' => function ( $param ) {
            $valid = true;
            $arr = explode( ',', (string) $param );
            //phpcs:ignore - 8.1 proof
            foreach ( $arr as $value ) {
                $valid = $valid || 'asc' !== $value && 'desc' !== $value;
            }
            return $valid;
        },
        ),
            'search'    => array(
            'required'          => false,
            'description'       => __( 'Search filter', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return sanitize_text_field( wp_unslash( $param ) );
        },
        ),
        ),
        ) );
        register_rest_route( self::WPDA_NAMESPACE, 'table/get', array(
            'methods'             => array( \WP_REST_Server::READABLE, 'POST' ),
            'callback'            => array( $this, 'table_get' ),
            'permission_callback' => '__return_true',
            'args'                => array(
            'dbs' => array(
            'required'          => true,
            'description'       => __( 'Remote or local database connection string', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'tbl' => array(
            'required'          => true,
            'description'       => __( 'Table or view name', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'key' => array(
            'required'          => true,
            'description'       => __( 'Primary key', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::sanitize_text_field_array( $param );
        },
            'validate_callback' => function ( $param ) {
            return is_array( $param );
        },
        ),
        ),
        ) );
        register_rest_route( self::WPDA_NAMESPACE, 'table/meta', array(
            'methods'             => array( 'GET', 'POST' ),
            'callback'            => array( $this, 'table_meta' ),
            'permission_callback' => '__return_true',
            'args'                => array(
            'dbs' => array(
            'required'          => true,
            'description'       => __( 'Remote or local database connection string', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'tbl' => array(
            'required'          => true,
            'description'       => __( 'Table or view name', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
        ),
        ) );
        register_rest_route( self::WPDA_NAMESPACE, 'save-settings', array(
            'methods'             => array( 'POST' ),
            'callback'            => array( $this, 'save_settings' ),
            'permission_callback' => '__return_true',
            'args'                => array(
            'action'   => array(
            'required'          => true,
            'description'       => __( 'Setting type', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return sanitize_text_field( wp_unslash( $param ) );
        },
            'validate_callback' => function ( $param ) {
            return 'dashboard_menus' === $param || 'table_settings' === $param || 'column_settings' === $param || 'rest_api' === $param;
        },
        ),
            'dbs'      => array(
            'required'          => true,
            'description'       => __( 'Remote or local database connection string', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'tbl'      => array(
            'required'          => true,
            'description'       => __( 'Table or view name', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return WPDA::remove_backticks( sanitize_text_field( wp_unslash( $param ) ) );
        },
        ),
            'settings' => array(
            'required'          => true,
            'description'       => __( 'Settings JSON as string', 'wp-data-access' ),
            'sanitize_callback' => function ( $param ) {
            return sanitize_textarea_field( $param );
        },
        ),
        ),
        ) );
    }
    
    /**
     * Get table meta info.
     *
     * @param WP_REST_Request $request Rest API request.
     * @return \WP_Error|\WP_REST_Response
     */
    public function table_meta( $request )
    {
        // Get arguments (already sanitized and validated).
        $dbs = $request->get_param( 'dbs' );
        $tbl = $request->get_param( 'tbl' );
        
        if ( current_user_can( 'manage_options' ) || WPDA_Table::check_table_access(
            $dbs,
            $tbl,
            $request,
            'select'
        ) ) {
            return $this->WPDA_Rest_Response( '', WPDA_Table::get_table_meta_data( $dbs, $tbl ) );
        } else {
            return new \WP_Error( 'error', __( 'Unauthorized', 'wp-data-access' ), array(
                'status' => 401,
            ) );
        }
    
    }
    
    /**
     * Save plugin settings.
     *
     * @param WP_REST_Request $request Rest API request.
     * @return \WP_Error|\WP_REST_Response
     */
    public function save_settings( $request )
    {
        if ( !current_user_can( 'manage_options' ) ) {
            // Only admins.
            return new \WP_Error( 'error', __( 'Unauthorized', 'wp-data-access' ), array(
                'status' => 401,
            ) );
        }
        if ( !wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
            // Invalid nonce.
            return new \WP_Error( 'error', __( 'Unauthorized', 'wp-data-access' ), array(
                'status' => 401,
            ) );
        }
        // Get arguments (already sanitized and validated).
        $dbs = $request->get_param( 'dbs' );
        $tbl = $request->get_param( 'tbl' );
        $settings_string = $request->get_param( 'settings' );
        if ( !is_string( $settings_string ) ) {
            return new \WP_Error( 'error', __( 'Bad request', 'wp-data-access' ), array(
                'status' => 400,
            ) );
        }
        $settings = json_decode( $settings_string );
        if ( false === $settings || is_null( $settings ) ) {
            return new \WP_Error( 'error', __( 'Bad request', 'wp-data-access' ), array(
                'status' => 400,
            ) );
        }
        switch ( $request->get_param( 'action' ) ) {
            case 'dashboard_menus':
                return WPDA_Settings::save_dashboard_menus( $dbs, $tbl, $settings );
            case 'table_settings':
                return WPDA_Settings::save_table_settings( $dbs, $tbl, $settings );
            case 'column_settings':
                return WPDA_Settings::save_column_settings( $dbs, $tbl, $settings );
            case 'rest_api':
                return WPDA_Settings::save_rest_api_settings( $dbs, $tbl, $settings );
        }
        return new \WP_Error( 'error', __( 'Bad request', 'wp-data-access' ), array(
            'status' => 400,
        ) );
    }
    
    /**
     * Database table query using the full primary key. Must return exactly one row.
     *
     * @param WP_REST_Request $request Rest API request.
     * @return \WP_Error|\WP_REST_Response
     */
    public function table_get( $request )
    {
        // Get arguments (already sanitized and validated).
        $dbs = $request->get_param( 'dbs' );
        $tbl = $request->get_param( 'tbl' );
        $key = $request->get_param( 'key' );
        
        if ( WPDA_Table::check_table_access(
            $dbs,
            $tbl,
            $request,
            'select'
        ) ) {
            return WPDA_Table::get( $dbs, $tbl, $key );
        } else {
            return new \WP_Error( 'error', __( 'Unauthorized', 'wp-data-access' ), array(
                'status' => 401,
            ) );
        }
    
    }
    
    /**
     * Database table query.
     *
     * Supports: searching, ordering and pagination.
     *
     * @param WP_REST_Request $request Rest API request.
     * @return \WP_Error|\WP_REST_Response
     */
    public function table_dtselect( $request )
    {
        // Get arguments (already sanitized and validated).
        $dbs = $request->get_param( 'dbs' );
        $tbl = $request->get_param( 'tbl' );
        $start = $request->get_param( 'start' );
        $length = $request->get_param( 'length' );
        $order = $request->get_param( 'order' );
        $search = $request->get_param( 'search' );
        $draw = $request->get_param( 'draw' );
        $records_total = $request->get_param( 'records_total' );
        $records_filtered = $request->get_param( 'records_filtered' );
        
        if ( WPDA_Table::check_table_access(
            $dbs,
            $tbl,
            $request,
            'select'
        ) ) {
            return WPDA_Table::dtselect(
                $dbs,
                $tbl,
                $start,
                $length,
                $order,
                $search,
                $draw,
                $records_total,
                $records_filtered
            );
        } else {
            return new \WP_Error( 'error', __( 'Unauthorized', 'wp-data-access' ), array(
                'status' => 401,
            ) );
        }
    
    }
    
    /**
     * Database table query.
     *
     * Supports: searching, ordering and pagination.
     *
     * @param WP_REST_Request $request Rest API request.
     * @return \WP_Error|\WP_REST_Response
     */
    public function table_select( $request )
    {
        // Get arguments (already sanitized and validated).
        $dbs = $request->get_param( 'dbs' );
        $tbl = $request->get_param( 'tbl' );
        $page = $request->get_param( 'page' );
        $rows = $request->get_param( 'rows' );
        $order = $request->get_param( 'order' );
        $order_dir = $request->get_param( 'order_dir' );
        $search = $request->get_param( 'search' );
        
        if ( WPDA_Table::check_table_access(
            $dbs,
            $tbl,
            $request,
            'select'
        ) ) {
            return WPDA_Table::select(
                $dbs,
                $tbl,
                $page,
                $rows,
                $order,
                $order_dir,
                $search
            );
        } else {
            return new \WP_Error( 'error', __( 'Unauthorized', 'wp-data-access' ), array(
                'status' => 401,
            ) );
        }
    
    }
    
    /**
     * Write standard JSON response.
     *
     * @param string $message Response text message.
     * @param mixed $data Response data.
     * @return \WP_REST_Response
     */
    public static function WPDA_Rest_Response( $message = '', $data = null )
    {
        return new \WP_REST_Response( array(
            'code'    => 'ok',
            'message' => $message,
            'data'    => $data,
        ), 200 );
    }

}