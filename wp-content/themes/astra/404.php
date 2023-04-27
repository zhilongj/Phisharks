<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#404_not_found
 *
 * @package Astra
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>

<div id="primary" <?php astra_primary_class(); ?>>

	<?php astra_primary_content_top(); ?>

	<section class="error-404 not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'astra' ); ?></h1>
		</header><!-- .page-header -->

		<div class="page-content">
			<h3><?php esc_html_e( 'It looks like nothing was found at this location. Please click the button and the page will redirect to home page.', 'astra' ); ?></h3><br>
		</div><!-- .page-content -->
		<a href="https://phisharks.online/" class="button button-primary">Redirect to home page</a>
	</section><!-- .error-404 -->

	<?php astra_primary_content_bottom(); ?>

</div><!-- #primary -->

<?php get_footer(); ?>

