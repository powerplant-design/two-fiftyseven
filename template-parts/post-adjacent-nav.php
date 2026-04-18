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

$post_type     = get_post_type();
$archive_url   = get_post_type_archive_link( $post_type );
$archive_labels = [
	'post'         => __( 'Back to all posts', 'two-fiftyseven' ),
	'event'        => __( 'Back to all events', 'two-fiftyseven' ),
	'organisation' => __( 'Back to all organisations', 'two-fiftyseven' ),
	'person'       => __( 'Back to all people', 'two-fiftyseven' ),
	'media_item'   => __( 'Back to all media', 'two-fiftyseven' ),
];
$archive_label = $archive_labels[ $post_type ] ?? __( 'Back', 'two-fiftyseven' );

if ( ! $prev_post && ! $next_post && ! $archive_url ) {
	return;
}
?>

<nav class="post-layout__adjacent | repel" aria-label="<?php esc_attr_e( 'Post navigation', 'two-fiftyseven' ); ?>">
	<div class="post-layout__adjacent-item post-layout__adjacent-item--prev">
		<?php if ( $prev_post ) : ?>
			<a class="btn" data-type="secondary" href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">
				&larr; <?php echo esc_html( get_the_title( $prev_post ) ); ?>
			</a>
		<?php endif; ?>
	</div>
	<div class="post-layout__adjacent-item post-layout__adjacent-item--next">
		<?php if ( $next_post ) : ?>
			<a class="btn" data-type="secondary" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>">
				<?php echo esc_html( get_the_title( $next_post ) ); ?> &rarr;
			</a>
		<?php endif; ?>
	</div>
</nav>

<?php if ( $archive_url ) : ?>
	<div class="post-layout__adjacent-back">
		<a class="btn" data-type="primary" href="<?php echo esc_url( $archive_url ); ?>">
			<?php echo esc_html( $archive_label ); ?>
		</a>
	</div>
<?php endif; ?>
