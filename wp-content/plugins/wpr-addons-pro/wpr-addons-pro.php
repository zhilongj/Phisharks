<?php

/*
 * Plugin Name: Royal Elementor Addons Pro (Premium)
 * Description: The only plugin you need for Elementor page builder.
 * Plugin URI: https://wp-royal.com/
 * Author: WP Royal
 * Version: 1.3.6
 * Update URI: https://api.freemius.com
 * Author URI: https://wp-royal.com/
 * Elementor tested up to: 3.11.2
 * Elementor Pro tested up to: 3.11.2
 *
 * Text Domain: wpr-addons
*/
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly
define( 'WPR_ADDONS_PRO_VERSION', '1.3.6' );
define( 'WPR_ADDONS_PRO__FILE__', __FILE__ );
define( 'WPR_ADDONS_PRO_PLUGIN_BASE', plugin_basename( WPR_ADDONS_PRO__FILE__ ) );
define( 'WPR_ADDONS_PRO_PATH', plugin_dir_path( WPR_ADDONS_PRO__FILE__ ) );
define( 'WPR_ADDONS_PRO_MODULES_PATH', WPR_ADDONS_PRO_PATH . 'modules/' );
define( 'WPR_ADDONS_PRO_URL', plugins_url( '/', WPR_ADDONS_PRO__FILE__ ) );
define( 'WPR_ADDONS_PRO_ASSETS_URL', WPR_ADDONS_PRO_URL . 'assets/' );
define( 'WPR_ADDONS_PRO_MODULES_URL', WPR_ADDONS_PRO_URL . 'modules/' );
/**
 * Feemius Integration
 */

if ( !function_exists( 'wpr_fs' ) ) {
    $register_freemius = true;
    
    if ( is_admin() ) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
        if ( !is_plugin_active( 'royal-elementor-addons/wpr-addons.php' ) ) {
            $register_freemius = false;
        }
    }
    
    
    if ( $register_freemius ) {
        // Create a helper function for easy SDK access.
        function wpr_fs()
        {
            global  $wpr_fs ;
            
            if ( !isset( $wpr_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                
                if ( empty(get_option( 'wpr_wl_plugin_links' )) ) {
                    $wpr_fs_menu = array(
                        'slug'        => 'wpr-addons',
                        'support'     => false,
                        'contact'     => false,
                        'affiliation' => true,
                    );
                } else {
                    $wpr_fs_menu = array(
                        'slug'        => 'wpr-addons',
                        'first-path'  => 'admin.php?page=wpr-templates-kit',
                        'support'     => false,
                        'contact'     => false,
                        'account'     => false,
                        'affiliation' => false,
                    );
                }
                
                $wpr_fs = fs_dynamic_init( array(
                    'id'              => '8416',
                    'slug'            => 'wpr-addons',
                    'premium_slug'    => 'wpr-addons-pro',
                    'type'            => 'plugin',
                    'public_key'      => 'pk_a0b21b234a7c9581a555b9ee9f28a',
                    'is_premium'      => true,
                    'is_premium_only' => true,
                    'has_addons'      => false,
                    'has_paid_plans'  => true,
                    'has_affiliation' => 'selected',
                    'menu'            => $wpr_fs_menu,
                    'is_live'         => true,
                ) );
            }
            
            return $wpr_fs;
        }
        
        // Init Freemius.
        wpr_fs();
        // Signal that SDK was initiated.
        do_action( 'wpr_fs_loaded' );
        wpr_fs()->add_filter( 'show_deactivation_subscription_cancellation', '__return_false' );
        if ( wpr_fs()->can_use_premium_code() ) {
            define( 'WPR_ADDONS_PRO_LICENSE', true );
        }
    }

}

/**
 * Load gettext translate for our text domain.
 *
 * @since 1.0
 *
 * @return void
 */
function wpr_addons_pro_load_plugin()
{
    load_plugin_textdomain( 'wpr-addons' );
    
    if ( !did_action( 'elementor/loaded' ) || !defined( 'WPR_ADDONS_VERSION' ) ) {
        add_action( 'admin_notices', 'wpr_addons_pro_fail_load' );
        return;
    }
    
    require WPR_ADDONS_PRO_PATH . 'plugin.php';
}

add_action( 'plugins_loaded', 'wpr_addons_pro_load_plugin' );
/**
 * Show in WP Dashboard notice about the plugin is not activated.
 *
 * @since 1.0
 *
 * @return void
 */
function wpr_addons_pro_fail_load()
{
    $screen = get_current_screen();
    if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
        return;
    }
    
    if ( _is_wpr_addons_installed() ) {
        if ( !current_user_can( 'activate_plugins' ) || is_plugin_active( 'royal-elementor-addons/wpr-addons.php' ) ) {
            return;
        }
        $plugin = 'royal-elementor-addons/wpr-addons.php';
        $activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
        $message = '<div class="error"><p>' . esc_html__( 'Royal Elementor Addons Pro is not working because you need to activate the Royal Elementor Addons plugin.', 'wpr-addons' ) . '</p>';
        $message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__( 'Activate Royal Elementor Addons Now', 'wpr-addons' ) ) . '</p></div>';
        echo  $message ;
    } else {
        if ( !current_user_can( 'install_plugins' ) ) {
            return;
        }
        $install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=royal-elementor-addons' ), 'install-plugin_royal-elementor-addons' );
        $message = '<div class="error"><p>' . esc_html__( 'Royal Elementor Addons Pro is not working because you need to install the Royal Elementor Addons plugin.', 'wpr-addons' ) . '</p>';
        $message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__( 'Install Royal Elementor Addons Now', 'wpr-addons' ) ) . '</p></div>';
        echo  $message ;
    }

}

function _is_wpr_addons_installed()
{
    $file_path = 'royal-elementor-addons/wpr-addons.php';
    $installed_plugins = get_plugins();
    return isset( $installed_plugins[$file_path] );
}

// Reset Options on Deactivation
function royal_addons_pro_deactivation()
{
    delete_option( 'wpr_wl_hide_elements_tab' );
    delete_option( 'wpr_wl_hide_extensions_tab' );
    delete_option( 'wpr_wl_hide_settings_tab' );
    delete_option( 'wpr_wl_hide_white_label_tab' );
}

register_deactivation_hook( __FILE__, 'royal_addons_pro_deactivation' );