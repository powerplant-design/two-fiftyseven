<?php
/**
 * Events Archive Template
 *
 * Tabbed view of Upcoming and Past events.
 * Initial load renders the Upcoming tab server-side.
 * Tab switches and pagination are handled by AJAX (events-archive.js).
 */

get_header();

$initial_query = new WP_Query( two57_get_event_query_args( 'upcoming', 1 ) );
?>

<div class="page-layout">

	<header class="post-index-header text-center">
		<h1 class="post-index-header__title"><?php echo esc_html( ( function_exists( 'get_field' ) ? get_field( 'event_archive_heading', 'option' ) : '' ) ?: __( 'Events', 'two-fiftyseven' ) ); ?></h1>
	</header>

	<div class="post-archive" data-js="events-archive">

		<div class="post-archive__tabs-row">
			<div class="post-archive__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Event tabs', 'two-fiftyseven' ); ?>">
				<button
					class="post-archive__tab text-monospace"
					role="tab"
					data-js="events-tab"
					data-tab="upcoming"
					aria-selected="true"
					aria-controls="events-grid">
					<?php esc_html_e( 'Upcoming', 'two-fiftyseven' ); ?>
				</button>
				<button
					class="post-archive__tab text-monospace"
					role="tab"
					data-js="events-tab"
					data-tab="past"
					aria-selected="false"
					aria-controls="events-grid">
					<?php esc_html_e( 'Past', 'two-fiftyseven' ); ?>
				</button>
			</div>
			<hr class="post-archive__rule">
		</div>

		<div class="post-archive__grid" id="events-grid" data-js="events-grid" role="tabpanel">
			<?php get_template_part( 'template-parts/event-card-grid', null, [
				'query'        => $initial_query,
				'current_page' => 1,
				'total_pages'  => $initial_query->max_num_pages,
			] ); ?>
		</div>

	</div>

</div>

<?php get_footer();
