<?php
/**
 * Template Name: Full Width
 *
 * A page template with no content wrapper — blocks manage their own layout.
 * Use this on any page where a full-bleed block (e.g. Hero Home) sits at the top.
 *
 * To apply: Page → Attributes → Template → Full Width
 */

get_header(); ?>

<?php while ( have_posts() ) : the_post(); ?>
	<?php
	// Parse blocks so we can render the hero full-bleed and wrap everything else.
	// Empty/whitespace blocks (parse_blocks artefacts) are stripped with the filter.
	$blocks    = array_filter( parse_blocks( get_the_content() ), fn( $b ) => ! empty( $b['blockName'] ) );
	$wrap_open = false;

	$full_bleed_blocks = [ 'acf/hero-home', 'acf/hero-page' ];

	foreach ( $blocks as $block ) {
		if ( in_array( $block['blockName'], $full_bleed_blocks, true ) ) {
			// Close any open wrapper before the hero (e.g. hero placed mid-page).
			if ( $wrap_open ) {
				echo '</div>';
				$wrap_open = false;
			}
			echo render_block( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			if ( ! $wrap_open ) {
				echo '<div class="wrapper">';
				$wrap_open = true;
			}
			echo render_block( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	if ( $wrap_open ) {
		echo '</div>';
	}
	?>
<?php endwhile; ?>

<?php get_footer();
