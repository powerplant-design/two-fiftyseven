<?php
/**
 * Events Archive Template
 *
 * Dual-filter tabbed archive for the Event CPT.
 * Left tabs: event_category taxonomy filter.
 * Right select: Happening / Happened (upcoming vs past) filter.
 * On mobile, both filters become <select> dropdowns.
 * Initial load renders upcoming events server-side.
 * Tab switches and pagination are handled by AJAX (events-archive.js).
 */

get_header();

$taxonomy      = 'event_category';
$terms         = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
$has_terms     = ! empty( $terms ) && ! is_wp_error( $terms );
$initial_query = new WP_Query( two57_get_event_query_args( 'upcoming', 1 ) );
?>

<div class="page-layout">

	<header class="post-index-header text-center">
		<h1 class="post-index-header__title"><?php echo esc_html( ( function_exists( 'get_field' ) ? get_field( 'event_archive_heading', 'option' ) : '' ) ?: __( 'Events', 'two-fiftyseven' ) ); ?></h1>
	</header>

	<div class="post-archive" data-js="events-archive">

		<div class="post-archive__tabs-row">

			<!-- Desktop: category tabs (left) + happening/happened select (right) -->
			<div class="post-archive__tabs-group">
				<?php if ( $has_terms ) : ?>
				<div class="post-archive__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'two-fiftyseven' ); ?>">
					<button class="post-archive__tab text-monospace" role="tab" data-js="events-tab" data-term="" aria-selected="true">
						<?php esc_html_e( 'All', 'two-fiftyseven' ); ?>
					</button>
					<?php foreach ( $terms as $term ) : ?>
					<button class="post-archive__tab text-monospace" role="tab" data-js="events-tab" data-term="<?php echo esc_attr( $term->slug ); ?>" aria-selected="false">
						<?php echo esc_html( $term->name ); ?>
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<select class="post-archive__select" data-js="events-select" data-filter="tab" aria-label="<?php esc_attr_e( 'Filter by status', 'two-fiftyseven' ); ?>">
					<option value="upcoming" selected><?php esc_html_e( 'Happening', 'two-fiftyseven' ); ?></option>
					<option value="past"><?php esc_html_e( 'Happened', 'two-fiftyseven' ); ?></option>
				</select>
			</div>

			<!-- Mobile: both filters as selects ──────────────── -->
			<div class="post-archive__selects">
				<?php if ( $has_terms ) : ?>
				<select class="post-archive__select" data-js="events-select" data-filter="term" aria-label="<?php esc_attr_e( 'Filter by category', 'two-fiftyseven' ); ?>">
				<option value=""><?php esc_html_e( 'Type of', 'two-fiftyseven' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>

				<select class="post-archive__select" data-js="events-select" data-filter="tab" aria-label="<?php esc_attr_e( 'Filter by status', 'two-fiftyseven' ); ?>">
					<option value="upcoming" selected><?php esc_html_e( 'Happening', 'two-fiftyseven' ); ?></option>
					<option value="past"><?php esc_html_e( 'Happened', 'two-fiftyseven' ); ?></option>
				</select>
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
