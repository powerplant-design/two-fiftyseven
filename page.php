<?php get_header(); ?>

<?php
while ( have_posts() ) :
	the_post();

	$blocks = array_filter(
		parse_blocks( get_the_content() ),
		fn( $b ) => ! empty( $b['blockName'] )
	);
	foreach ( $blocks as $block ) {
		echo render_block( $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

endwhile;
?>

<?php get_footer(); ?>
