<?php // phpcs:ignore Standard.Category.SniffName.ErrorCode
/**
 * This file contains some code which is necessary to use class WP_List_Table in the public area. Part of the code is
 * fake (added to documentation) and is only loaded if the WordPress code needed to handle WP_List_Table is not
 * available (which is the case in the public area). List tables are available in the public area with limitations.
 * The screen options tab is not available and settings cannot be saved.
 *
 * USE THIS AT YOUR OWN RISK!
 *
 * I strongly advise to use list tables and data entry forms in the WordPress dashboard. If you use them in the public
 * area make sure users must log in before they can use this feature. I strongly discourage to give anonymous users
 * access to this feature, but you can...
 *
 * @package plugin
 */

/**
 * Fake class to make WPDA_List_Table available in the public area
 */
class WPDADIEHARD_Screen {
	/**
	 * Placeholder to prevent runtime errors
	 *
	 * @var mixed
	 */
	public $id;

	/**
	 * Placeholder to prevent runtime errors
	 *
	 * @param string $key Key.
	 * @param string $tag Tag.
	 * @return void
	 */
	public function render_screen_reader_content( $key = '', $tag = 'h2' ) {
		return; // Features not available in the public area.
	}
}

/**
 * Fake function
 *
 * @param Object $screen Screen.
 * @return WPDADIEHARD_Screen
 */
function wpdadiehard_convert_to_screen( $screen ) {
	$screen     = new WPDADIEHARD_Screen();
	$screen->id = 'wpdadiehard';

	return $screen;
}

/**
 * Fake function
 *
 * @return WPDADIEHARD_Screen
 */
function wpdadiehard_get_current_screen() {
	$screen     = new WPDADIEHARD_Screen();
	$screen->id = 'wpdadiehard';

	return $screen;
}

if ( ! function_exists( 'get_column_headers' ) ) {
	/**
	 * Fake function
	 *
	 * @return array
	 */
	function get_column_headers() {
		return array(); // Class WPDA_List_Table will fill this array.
	}
}

/**
 * Duplicated from: wp-admin/includes/template.php (24-07-2020)
 *
 * @param mixed $text Text.
 * @param mixed $type Type.
 * @param mixed $name Name.
 * @param mixed $wrap Wrap.
 * @param mixed $other_attributes Attr.
 * @return void
 */
function wpdadiehard_submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
	echo wpdadiehard_get_submit_button( $text, $type, $name, $wrap, $other_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Duplicated from: wp-admin/includes/template.php (24-07-2020)
 *
 * @param mixed $text Text.
 * @param mixed $type Type.
 * @param mixed $name Name.
 * @param mixed $wrap Wrap.
 * @param mixed $other_attributes Attr.
 * @return string
 */
function wpdadiehard_get_submit_button( $text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attributes = '' ) {
	if ( ! is_array( $type ) ) {
		$type = explode( ' ', (string) $type );//phpcs:ignore - 8.1 proof
	}

	$button_shorthand = array( 'primary', 'small', 'large' );
	$classes          = array( 'button' );
	foreach ( $type as $t ) {
		if ( 'secondary' === $t || 'button-secondary' === $t ) {
			continue;
		}
		$classes[] = in_array( $t, $button_shorthand, true ) ? 'button-' . $t : $t;//phpcs:ignore - 8.1 proof
	}
	// Remove empty items, remove duplicate items, and finally build a string.
	$class = implode( ' ', array_unique( array_filter( $classes ) ) ); //phpcs:ignore - 8.1 proof

	$text = $text ? $text : __( 'Save Changes' );

	// Default the id attribute to $name unless an id was specifically provided in $other_attributes.
	$id = $name;
	if ( is_array( $other_attributes ) && isset( $other_attributes['id'] ) ) {
		$id = $other_attributes['id'];
		unset( $other_attributes['id'] );
	}

	$attributes = '';
	if ( is_array( $other_attributes ) ) {
		foreach ( $other_attributes as $attribute => $value ) {
			$attributes .= $attribute . '="' . esc_attr( $value ) . '" '; // Trailing space is important.
		}
	} elseif ( ! empty( $other_attributes ) ) { // Attributes provided as a string.
		$attributes = $other_attributes;
	}

	// Don't output empty name and id attributes.
	$name_attr = $name ? ' name="' . esc_attr( $name ) . '"' : '';
	$id_attr   = $id ? ' id="' . esc_attr( $id ) . '"' : '';

	$button  = '<input type="submit"' . $name_attr . $id_attr . ' class="' . esc_attr( $class );
	$button .= '" value="' . esc_attr( $text ) . '" ' . $attributes . ' />';

	if ( $wrap ) {
		$button = '<p class="submit">' . $button . '</p>';
	}

	return $button;
}
