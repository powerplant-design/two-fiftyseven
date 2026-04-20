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
	 * For events, adjacent navigation is ordered by event_sort_date (Ymd meta),
	 * not by publish date. This respects recurring events (which store the next
	 * upcoming weekday occurrence) and one-off events (which store their fixed date).
	 *
	 * Ties on the same sort date are broken by post ID so a post is never its own
	 * neighbour, and the pair always navigates consistently in both directions.
	 */
	$current_sort_date = (string) ( get_post_meta( get_the_ID(), 'event_sort_date', true ) ?: '99991231' );
	$current_id        = get_the_ID();

	$prev_query = new WP_Query( [
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'post__not_in'   => [ $current_id ],
		'meta_key'       => 'event_sort_date',
		'orderby'        => [ 'meta_value' => 'DESC', 'ID' => 'DESC' ],
		'meta_query'     => [ [
			'key'     => 'event_sort_date',
			'value'   => $current_sort_date,
			'compare' => '<=',
			'type'    => 'CHAR',
		] ],
	] );
	$prev_post = $prev_query->have_posts() ? $prev_query->posts[0] : null;

	$next_query = new WP_Query( [
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'no_found_rows'  => true,
		'post__not_in'   => [ $current_id ],
		'meta_key'       => 'event_sort_date',
		'orderby'        => [ 'meta_value' => 'ASC', 'ID' => 'ASC' ],
		'meta_query'     => [ [
			'key'     => 'event_sort_date',
			'value'   => $current_sort_date,
			'compare' => '>=',
			'type'    => 'CHAR',
		] ],
	] );
	$next_post = $next_query->have_posts() ? $next_query->posts[0] : null;

	wp_reset_postdata();
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
