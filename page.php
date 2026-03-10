<?php get_header(); ?>

<?php
while ( have_posts() ) :
	the_post();

	$full_bleed_blocks = [ 'acf/hero-home', 'acf/hero-page' ];
	$blocks            = array_filter(
		parse_blocks( get_the_content() ),
		fn( $b ) => ! empty( $b['blockName'] )
	);
	$has_full_bleed    = (bool) count( array_filter(
		$blocks,
		fn( $b ) => in_array( $b['blockName'], $full_bleed_blocks, true )
	) );

	if ( $has_full_bleed ) {
		// Full-bleed mode: hero renders outside wrapper, other blocks get wrapped.
		$wrap_open = false;
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'], $full_bleed_blocks, true ) ) {
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
	} else {
		echo '<div class="wrapper">';
		echo '<article id="post-' . get_the_ID() . '" ' . get_post_class( '', get_the_ID() ) . '>';
		echo '<div class="entry-content">';
		the_content();
		echo '</div>';
		echo '</article>';
		echo '</div>';
	}

endwhile;
?>

<?php get_footer(); ?>
