<?php
/**
 * Template Name: Full Width
 *
 * A page template using a CSS grid layout wrapper. All blocks are direct
 * children of the grid; blocks with data-block="full" span edge-to-edge,
 * everything else sits in the bounded central column.
 *
 * To apply: Page → Attributes → Template → Full Width
 */

get_header(); ?>

<?php while ( have_posts() ) : the_post(); ?>
	<div class="page-layout">
		<?php
		$blocks = array_filter( parse_blocks( get_the_content() ), fn( $b ) => ! empty( $b['blockName'] ) );
		foreach ( $blocks as $block ) {
			echo render_block( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</div>
<?php endwhile; ?>

<?php get_footer();
