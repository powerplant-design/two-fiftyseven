<?php
/**
 * Template Part: Post Adjacent Navigation
 *
 * Renders ← Prev (older) on the left and Next → (newer) on the right inside
 * a `repel` utility so they sit at opposite ends of the content column.
 *
 * Works for all post types — get_previous_post() / get_next_post() implicitly
 * scope to the current post type.
 */

$prev_post = get_previous_post();
$next_post = get_next_post();

if ( ! $prev_post && ! $next_post ) {
	return;
}
?>

<nav class="post-layout__adjacent | repel" aria-label="<?php esc_attr_e( 'Post navigation', 'two-fiftyseven' ); ?>">
	<div class="post-layout__adjacent-item post-layout__adjacent-item--prev">
		<?php if ( $prev_post ) : ?>
			<a class="btn" data-type="text" href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">
				&larr; <?php echo esc_html( get_the_title( $prev_post ) ); ?>
			</a>
		<?php endif; ?>
	</div>
	<div class="post-layout__adjacent-item post-layout__adjacent-item--next">
		<?php if ( $next_post ) : ?>
			<a class="btn" data-type="text" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>">
				<?php echo esc_html( get_the_title( $next_post ) ); ?> &rarr;
			</a>
		<?php endif; ?>
	</div>
</nav>
