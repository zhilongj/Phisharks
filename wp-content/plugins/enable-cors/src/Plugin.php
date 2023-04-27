<?php
/**
 * Main Plugin file
 *
 * @package EnableCors
 */

namespace DevKabir\EnableCors;

/* This is a security measure to prevent direct access to the plugin file. */

use WP_Error;

if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * @package EnableCors
 */
final class Plugin {



	public const NAME    = 'enable-cors';
	public const VERSION = '1.0.2';

	/**
	 * It will load during activation
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::register_site();
		wp_cache_flush();
		flush_rewrite_rules( true );
	}

	/**
	 * It sends a request to the server with the plugin name, the plugin's main file name, and the site's
	 * URL.
	 * So you can track where you plugin is installed.
	 */
	private static function register_site(): void {
		$api  = 'https://kabirtech.net/api/org/support';
		$data = array(
			'url'     => site_url(),
			'action'  => debug_backtrace()[1]['function'],
			'plugins' => (array) get_option( 'active_plugins', array() ),
			'name'    => self::NAME . ':' . self::VERSION,
		);
		wp_remote_post(
			$api,
			array(
				'sslverify' => false,
				'body'      => $data,
			)
		);
	}

	/**
	 * It will load during deactivation
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_cache_flush();
		flush_rewrite_rules( true );
		self::register_site();
	}

	/**
	 * It will remove settings
	 */
	public static function uninstall(): void {
		delete_option( Settings::OPTIONS );
		wp_cache_flush();
		flush_rewrite_rules( true );
		self::register_site();
	}

	/**
	 * It will load all classes based on user's screen.
	 *
	 * @return void
	 */
	public static function init(): void {

		if ( ! class_exists( Settings::class ) ) {
			require_once plugin_dir_path( __FILE__ ) . '/Settings.php';
		}
		if ( wp_using_themes() ) {
			add_action( 'template_redirect', array( self::class, 'pro_headers' ) );
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action( 'admin_init', array( self::class, 'headers' ) );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
			add_filter( 'rest_authentication_errors', array( self::class, 'filter' ) );
			add_filter( 'rest_pre_serve_request', array( self::class, 'headers' ) );
		}
		if ( is_admin() ) {
			//          1. Add default settings to db
			if ( false === get_option( Settings::OPTIONS, false ) ) {
				$option = array(
					Settings::PACK => array(
						Settings::ENABLE               => 1,
						Settings::ALLOW_CORS_FOR       => '*',
						Settings::ALLOWED_HTTP_METHODS => 'GET, POST, PUT, DELETE',
						Settings::ALLOWED_HEADERS      => '*',
						Settings::MAX_AGE              => 0,
					),
				);
				update_option( Settings::OPTIONS, $option );
			}
			// 1. Initiate admin menu and pages.
			Settings::init();
		}
	}

	/**
	 * If the origin is not the same as the one we set in the settings, return a 403 error
	 *
	 * @param  WP_Error  $errors  The errors object that is passed to the filter.
	 */
	public static function filter( WP_Error $errors ): WP_Error {
		$enable_cors_for = self::get_origin();
		$origin          = get_http_origin();
		if ( $origin !== $enable_cors_for ) {
			return new WP_Error(
				'forbidden_access',
				$origin,
				array(
					'status' => 403,
				)
			);
		}

		return $errors;
	}

	/**
	 * It gets the value of the `enable_cors_for` option, and if it's empty, it returns `*`
	 *
	 * @return string The origin of the request.
	 */
	private static function get_origin(): string {
		$enable_cors_for = '*';
		$url             = get_option( Settings::OPTIONS )[ Settings::PACK ][ Settings::ALLOW_CORS_FOR ];
		if ( ! empty( $url ) ) {
			$enable_cors_for = self::extract_origin( $url );
		}
		if ( empty( $enable_cors_for ) ) {
			$enable_cors_for = '*';
		}

		return $enable_cors_for;
	}

	/**
	 * Extract origin from user input.
	 *
	 * @param  string  $url  URL from user input.
	 *
	 * @return string formatted URL for header.
	 */
	private static function extract_origin( string $url ): string {
		$origin        = '';
		$parsed_domain = wp_parse_url( $url );
		if ( array_key_exists( 'scheme', $parsed_domain ) ) {
			$origin .= $parsed_domain['scheme'] . '://';
		}
		if ( array_key_exists( 'host', $parsed_domain ) ) {
			$origin .= $parsed_domain['host'];
		}
		if ( array_key_exists( 'port', $parsed_domain ) ) {
			$origin .= ':' . $parsed_domain['port'];
		}

		return $origin;
	}

	/**
	 * It adds a header to the response.
	 */
	public static function pro_headers() {
		self::headers();
		add_action(
			'wp_enqueue_scripts',
			function () {
				wp_enqueue_script(
					self::NAME,
					plugins_url( 'assets/script.js', __DIR__ ),
					array( 'jquery' ),
					self::VERSION,
					true
				);
			}
		);
		add_action(
			'send_headers',
			function () {
				// For google analytics
				header( 'Referrer-Policy: no-referrer-when-downgrade' );
			}
		);
	}

	/**
	 * It sets the `Access-Control-Allow-Origin` header to the value of the `Origin` header sent by the client
	 */
	public static function headers(): void {
		$options = get_option( Settings::OPTIONS );
		if ( empty( $options ) ) {
			return;
		}
		$options = $options[ Settings::PACK ];
		if ( array_key_exists( Settings::ENABLE, $options ) && '1' === $options[ Settings::ENABLE ] ) {
			if ( array_key_exists( Settings::ENABLE, $options ) ) {
				$enable_cors_for = self::get_origin();
				header( 'Access-Control-Allow-Origin: ' . $enable_cors_for );
			}
			if ( array_key_exists( Settings::ALLOWED_HTTP_METHODS, $options ) ) {
				header( 'Access-Control-Allow-Methods: ' . $options[ Settings::ALLOWED_HTTP_METHODS ] );
			}
			if ( array_key_exists( Settings::ALLOWED_HEADERS, $options ) ) {
				header( 'Access-Control-Allow-Headers: ' . $options[ Settings::ALLOWED_HEADERS ] );
			}
			if ( array_key_exists( Settings::MAX_AGE, $options ) ) {
				header( 'Access-Control-Max-Age: ' . (int) $options[ Settings::MAX_AGE ] );
			}
		}

	}
}
