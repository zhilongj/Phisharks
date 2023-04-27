<?php
namespace WprAddonsPro;

use Elementor\Utils;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {	exit; } // Exit if accessed directly

/**
 * Main class plugin
 */
class Plugin {

	/**
	 * @var Plugin
	 */
	private static $_instance;

	/**
	 * @var Manager
	 */
	private $_modules_manager;

	/**
	 * @var array
	 */
	private $_localize_settings = [];

	/**
	 * @return string
	 */
	public function get_version() {
		return WPR_ADDONS_PRO_VERSION;
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.0
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wpr-addons' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wpr-addons' ), '1.0' );
	}

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function _includes() {
		// Modules Manager
		require WPR_ADDONS_PRO_PATH . 'includes/modules-manager.php';

		// Custom Controls
		require WPR_ADDONS_PRO_PATH . 'includes/controls/wpr-control-animations-pro.php';

		// Particles
		if ( 'on' === get_option('wpr-particles-toggle', 'on') ) {
			require WPR_ADDONS_PRO_PATH . 'extensions/wpr-particles-pro.php';
		}

		// Parallax
		if ( 'on' === get_option('wpr-parallax-background', 'on') || 'on' === get_option('wpr-parallax-multi-layer', 'on') ) {
			require WPR_ADDONS_PRO_PATH . 'extensions/wpr-parallax-pro.php';
		}

		// Admin Files
		if ( is_admin() ) {
			require WPR_ADDONS_PRO_PATH . 'admin/white-label.php';
			require WPR_ADDONS_PRO_PATH . 'admin/plugin-settings.php';
		}
	}

	public function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
			return;
		}

		$filename = strtolower(
			preg_replace(
				[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
				[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
				$class
			)
		);
		$filename = WPR_ADDONS_PRO_PATH . $filename . '.php';

		if ( is_readable( $filename ) ) {
			include( $filename );
		}
	}

	public function get_localize_settings() {
		return $this->_localize_settings;
	}

	public function add_localize_settings( $setting_key, $setting_value = null ) {
		if ( is_array( $setting_key ) ) {
			$this->_localize_settings = array_replace_recursive( $this->_localize_settings, $setting_key );

			return;
		}

		if ( ! is_array( $setting_value ) || ! isset( $this->_localize_settings[ $setting_key ] ) || ! is_array( $this->_localize_settings[ $setting_key ] ) ) {
			$this->_localize_settings[ $setting_key ] = $setting_value;

			return;
		}

		$this->_localize_settings[ $setting_key ] = array_replace_recursive( $this->_localize_settings[ $setting_key ], $setting_value );
	}

	public function script_suffix() {
		// $dir = is_rtl() ? '-rtl' : '';
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	public function enqueue_panel_styles() {
		wp_enqueue_style(
			'wpr-addons-pro-library-editor-css',
			WPR_ADDONS_PRO_URL . 'assets/css/editor' . $this->script_suffix() . '.css',
			[],
			Plugin::instance()->get_version()
		);

		$plugin_name = get_option('wpr_wl_plugin_name');
		$element_icon_css = '';
		if ( !empty($plugin_name) ) {
			$element_icon_css .= '.elementor-panel .wpr-icon:after {content: "'. substr($plugin_name, 0, 1) .'";}';
			$element_icon_css .= '.elementor-control-type-section[class*=elementor-control-wpr_section_]:after {content: "'. substr($plugin_name, 0, 1) .'";}';
		}

		wp_add_inline_style('wpr-addons-pro-library-editor-css', $element_icon_css);
	}

	public function elementor_init() {
		$this->_modules_manager = new Manager();
	}

	protected function add_actions() {
		add_action( 'elementor/init', [ $this, 'elementor_init' ], 100 );

		// Editor CSS/JS
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_panel_styles' ], 989 );
	}

	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		spl_autoload_register( [ $this, 'autoload' ] );

		$this->_includes();
		$this->add_actions();
	}
	
}

Plugin::instance();