<?php
/**
 * It will provide settings page
 *
 * @package EnableCors
 */

namespace DevKabir\EnableCors;

use DOMDocument;

/* This is a security measure to prevent direct access to the plugin file. */
if ( ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * @package EnableCors
 */
final class Settings {
	public const OPTIONS = 'enable-cors-options';
	public const PACK = 'freemium';
	public const ENABLE = 'enable';
	public const ALLOW_CORS_FOR = 'enable-cors-for';
	public const ALLOWED_HTTP_METHODS = 'allowed-http-methods';
	public const ALLOWED_HEADERS = 'allowed-headers';
	public const MAX_AGE = 'max-age';

	/**
	 * It adds a submenu page to the WooCommerce menu.
	 */
	public static function page(): void {
		add_options_page(
			__( 'Enable CORS', 'enable-cors' ),
			__( 'Enable CORS', 'enable-cors' ),
			'manage_options',
			'enable-cors',
			array( self::class, 'render' )
		);
	}

	/**
	 * It sanitizes the inputs
	 *
	 * @param array $inputs The array of inputs to sanitize.
	 */
	public static function sanitize( array $inputs ): array {
		foreach ( $inputs as $package => $fields ) {
			foreach ( $fields as $name => $value ) {
				$inputs[ $package ][ $name ] = sanitize_text_field( $value );
			}
		}
		wp_cache_flush();

		return $inputs;
	}

	/**
	 * It creates a settings page for the plugin
	 */
	public static function settings(): void {
		$options = get_option( self::OPTIONS, array() );
		register_setting(
			'enable-cors',
			self::OPTIONS,
			array(
				'sanitize_callback' => array(
					self::class,
					'sanitize',
				),
			)
		);
		$labels = array(
			'title'                    => __( 'Basic CORS Settings', 'enable-cors' ),
			self::ENABLE               => __( 'Enable CORS', 'enable-cors' ),
			self::ALLOW_CORS_FOR       => __( 'Allow CORS for', 'enable-cors' ),
			self::ALLOWED_HTTP_METHODS => __( 'Allowed HTTP methods', 'enable-cors' ),
			self::ALLOWED_HEADERS      => __( 'Allowed Headers', 'enable-cors' ),
			self::MAX_AGE              => __( 'Max age of CORS', 'enable-cors' ),
		);
		$fields = array(
			self::PACK =>
				array(
					'title'  => $labels['title'],
					'fields' => array(
						$labels[ self::ENABLE ] => array(
							'type'       => 'checkbox',
							'name'       => self::OPTIONS . '[' . self::PACK . '][' . self::ENABLE . ']',
							'value'      => 1,
							'user_input' => isset( $options[ self::PACK ][ self::ENABLE ] ),
						),

						$labels[ self::ALLOW_CORS_FOR ]       => array(
							'type'        => 'text',
							'name'        => self::OPTIONS . '[' . self::PACK . '][' . self::ALLOW_CORS_FOR . ']',
							'value'       => $options[ self::PACK ][ self::ALLOW_CORS_FOR ] ?? null,
							'placeholder' => 'https://example.com',
						),
						$labels[ self::ALLOWED_HTTP_METHODS ] => array(
							'type'        => 'text',
							'name'        => self::OPTIONS . '[' . self::PACK . '][' . self::ALLOWED_HTTP_METHODS . ']',
							'value'       => '' !== $options[ self::PACK ][ self::ALLOWED_HTTP_METHODS ] ? $options[ self::PACK ][ self::ALLOWED_HTTP_METHODS ] : '*',
							'placeholder' => 'GET, POST, ...',
						),
						$labels[ self::ALLOWED_HEADERS ]      => array(
							'type'        => 'text',
							'name'        => self::OPTIONS . '[' . self::PACK . '][' . self::ALLOWED_HEADERS . ']',
							'value'       => $options[ self::PACK ][ self::ALLOWED_HEADERS ] ?? '*',
							'placeholder' => 'Content-Type, Content-Disposition, ...',
						),
						$labels[ self::MAX_AGE ]              => array(
							'type'        => 'number',
							'name'        => self::OPTIONS . '[' . self::PACK . '][' . self::MAX_AGE . ']',
							'value'       => $options[ self::PACK ][ self::MAX_AGE ] ?? '0',
							'placeholder' => '86400',
						),

					),
				),
		);
		foreach ( $fields as $page => $inputs ) {
			$section = 'enable-cors_section_' . $page;
			add_settings_section(
				$section,
				$inputs['title'],
				null,
				'enable-cors'
			);

			foreach ( $inputs['fields'] as $title => $input ) {
				add_settings_field(
					'enable-cors_field_' . str_replace( ' ', '_', strtolower( $title ) ),
					$title,
					static function () use ( $input ) {
						self::generate_input( $input );
					},
					'enable-cors',
					$section
				);
			}
		}
	}

	/**
	 * It generates an HTML input element
	 *
	 * @param array $config an array of parameters that are used to generate the input.
	 */
	public static function generate_input( array $config ): void {
		$doc = new DOMDocument();

		$input = $doc->createElement( 'input' );
		$type  = $config['type'];
		$name  = $config['name'];
		$value = $config['value'];

		$input->setAttribute( 'type', $type );
		$input->setAttribute( 'class', 'widefat' );
		$input->setAttribute( 'name', $name );
		$input->setAttribute( 'value', esc_attr( $value ) );

		if ( 'checkbox' === $type ) {
			$checked = $config['user_input'];
			if ( $checked ) {
				$input->setAttribute( 'checked', 'checked' );
			}
		}
		if (array_key_exists('placeholder', $config)) {
			$placeholder = $config['placeholder'];
			if ( in_array( $type, array( 'url', 'text', 'number' ), true ) ) {
				$input->setAttribute( 'placeholder', $placeholder ?? null );
			}
        } else {
			$placeholder = false;
        }

		$doc->appendChild( $input );
		if ( ! empty( $placeholder ) ) {
			$p = $doc->createElement( 'p', $placeholder ?? null );
			$p->setAttribute( 'class', 'description' );
			$doc->appendChild( $p );
		}
		$field        = $doc->saveHTML();
		$allowed_html = array(
			'p'     => array(
				'class' => array(),
			),
			'input' => array(
				'type'        => array(),
				'class'       => array(),
				'name'        => array(),
				'value'       => array(),
				'checked'     => array(),
				'placeholder' => array(),
			),
		);
		echo wp_kses( $field, $allowed_html );
	}

	/**
	 * It adds a new menu item to the admin menu, and then registers the settings for the plugin
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'page' ) );
		add_action( 'admin_init', array( self::class, 'settings' ) );
	}

	/**
	 * It creates a form that submits to the options.php file, which is a WordPress core file that handles saving
	 * options
	 */
	public static function render(): void {
		?>
        <div class="wrap">
            <h1>
				<?php esc_html_e( 'Enable CORS', 'enable-cors' ); ?>
            </h1>
            <form method="post" action="options.php">
				<?php
				settings_fields( 'enable-cors' );
				do_settings_sections( 'enable-cors' );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}
}
