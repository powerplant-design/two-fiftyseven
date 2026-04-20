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

$post_type = get_post_type();

if ( $post_type === 'event' ) {
	/**
	 * For events, adjacent navigation mirrors the archive order exactly.
	 * Upcoming events navigate within upcoming only (ASC by sort date).
	 * Past events navigate within past only (DESC by sort date — newest first,
	 * matching the "Happened" tab order on the archive).
	 * Uses two57_get_event_query_args() so ordering is guaranteed identical
	 * to the archive regardless of tie-breaking behaviour.
	 */
	$current_id  = get_the_ID();
	$is_past     = get_post_meta( $current_id, 'event_has_passed', true ) === '1';
	$tab         = $is_past ? 'past' : 'upcoming';

	if ( ! function_exists( 'two57_get_event_query_args' ) ) {
		$prev_post = null;
		$next_post = null;
	} else {
		$archive_args = two57_get_event_query_args( $tab, 1 );
		$all_events   = new WP_Query( array_merge( $archive_args, [
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		] ) );

		$ids       = array_map( 'intval', $all_events->posts );
		$pos       = array_search( $current_id, $ids, true );
		$prev_post = ( $pos !== false && $pos > 0 )                 ? get_post( $ids[ $pos - 1 ] ) : null;
		$next_post = ( $pos !== false && $pos < count( $ids ) - 1 ) ? get_post( $ids[ $pos + 1 ] ) : null;
		// Past archive is DESC (newest first), so swap so ← = older, → = newer.
		if ( $is_past ) {
			[ $prev_post, $next_post ] = [ $next_post, $prev_post ];
		}
		// No wp_reset_postdata() needed — we never called setup_postdata() on this query.
	}
} else {
	$prev_post = get_previous_post();
	$next_post = get_next_post();
}

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
