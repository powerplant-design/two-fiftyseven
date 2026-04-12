<?php
/**
 * Block: 257 Events Widget
 *
 * Displays a grid of upcoming event cards with an editable heading and
 * optional "View more events" CTA button.
 *
 * Auto mode: queries the next N upcoming events dynamically on page render.
 * Manual mode: renders a fixed set of editor-selected event posts (live data,
 * updated automatically when those posts are edited).
 */

$heading    = get_field( 'events_widget_heading' ) ?: __( 'Coming up at Two/Fiftyseven', 'two-fiftyseven' );
$count      = (int) ( get_field( 'events_widget_count' ) ?: 6 );
$count      = in_array( $count, [ 2, 4, 6, 8, 10 ], true ) ? $count : 6;
$mode       = get_field( 'events_widget_selection_mode' ) ?: 'auto';
$is_preview = ! empty( $block['data']['preview'] );

// ── Fetch events ──────────────────────────────────────────────────────────────

if ( $mode === 'manual' ) {
	$selected_ids = get_field( 'events_widget_manual_items' ) ?: [];
	$selected_ids = array_values( array_filter( array_map( 'intval', (array) $selected_ids ) ) );

	$items = $selected_ids ? get_posts( [
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'post__in'       => $selected_ids,
		'orderby'        => 'post__in',
		'posts_per_page' => $count,
		'no_found_rows'  => true,
	] ) : [];
} else {
	$args                    = two57_get_event_query_args( 'upcoming', 1 );
	$args['posts_per_page']  = $count;
	$args['no_found_rows']   = true;
	unset( $args['paged'] );

	$query = new WP_Query( $args );
	$items = $query->posts;
	wp_reset_postdata();
}

// ── Render ────────────────────────────────────────────────────────────────────
?>

<section class="events-widget | block">
	<div class="events-widget__inner | stack">

		<?php if ( $heading ) : ?>
			<h2 class="events-widget__heading | text-2xl" data-scroll data-scroll-repeat><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( $items ) : ?>

			<ul class="event-cards | grid" data-grid-layout="halves" data-js="events-widget" role="list">
				<?php foreach ( $items as $index => $item ) :
					get_template_part( 'template-parts/event-card', null, [
					'post_id'      => (int) $item->ID,
					'card_index'   => $index,
					'scroll_reveal' => true,
					] );
				endforeach; ?>
			</ul>

		<?php elseif ( $is_preview ) : ?>

			<p class="events-widget__preview-hint text-monospace text-s">
				<?php
				if ( $mode === 'manual' ) {
					esc_html_e( 'Select events in the block settings →', 'two-fiftyseven' );
				} else {
					esc_html_e( 'No upcoming events found. Publish some events to populate this block.', 'two-fiftyseven' );
				}
				?>
			</p>

		<?php endif; ?>

		<div class="events-widget__cta" data-scroll data-scroll-repeat>
			<a class="btn" data-type="secondary" href="<?php echo esc_url( home_url( '/events' ) ); ?>">
				<?php esc_html_e( 'View more events', 'two-fiftyseven' ); ?>
			</a>
		</div>

	</div>
</section>
