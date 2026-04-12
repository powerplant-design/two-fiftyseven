<?php
/**
 * 404 Page Template
 *
 * Displayed when WordPress cannot find a matching page.
 * Shows a full-viewport centred layout with a large "404" and a home button.
 */

get_header();
?>

<div class="error-404">
	<p class="error-404__number" aria-hidden="true">404</p>
	<div class="error-404__content | stack">
		<h1 class="error-404__heading"><?php esc_html_e( 'Page not found', 'two-fiftyseven' ); ?></h1>
		<p class="error-404__message"><?php esc_html_e( "Sorry, the page you're looking for doesn't exist.", 'two-fiftyseven' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn" data-type="primary">
			<?php esc_html_e( 'Back to home', 'two-fiftyseven' ); ?>
		</a>
	</div>
</div>

<?php get_footer(); ?>
