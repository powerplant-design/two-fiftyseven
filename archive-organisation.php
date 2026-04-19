<?php
/**
 * Organisation Archive Template
 *
 * Dual-filter tabbed archive for the Organisation CPT.
 * Left tabs: category taxonomy filter. Right tabs: use type (ACF meta) filter.
 * On mobile, both tab groups become <select> dropdowns.
 * Initial load renders all organisations server-side.
 * Tab switches and pagination are handled by AJAX (cpt-archive.js).
 *
 * Colour space is set via ACF Options → Archive Settings → Organisation Archive
 * Colour Space, read automatically by two_fiftyseven_get_colour_space().
 */

get_header();

$post_type     = 'organisation';
$taxonomy      = 'organisation_category';
$terms         = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
$has_terms     = ! empty( $terms ) && ! is_wp_error( $terms );
$initial_query = new WP_Query( two57_get_cpt_query_args( $post_type, '', 1, '' ) );

$use_types = [
	'base'   => __( 'Base', 'two-fiftyseven' ),
	'hub'    => __( 'Hub', 'two-fiftyseven' ),
	'desk'   => __( 'Desk', 'two-fiftyseven' ),
	'meet'   => __( 'Meet', 'two-fiftyseven' ),
	'events' => __( 'Events', 'two-fiftyseven' ),
];
?>

<div class="page-layout">

	<header class="post-index-header text-center">
		<h1 class="post-index-header__title"><?php echo esc_html( ( function_exists( 'get_field' ) ? get_field( 'organisation_archive_heading', 'option' ) : '' ) ?: post_type_archive_title( false ) ); ?></h1>
	</header>

	<div class="post-archive" data-js="cpt-archive" data-post-type="<?php echo esc_attr( $post_type ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">

		<div class="post-archive__tabs-row">

			<!-- Desktop: category tabs (left) + use type select (right) -->
			<div class="post-archive__tabs-group">
				<?php if ( $has_terms ) : ?>
				<div class="post-archive__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'two-fiftyseven' ); ?>">
					<button class="post-archive__tab text-monospace" role="tab" data-js="cpt-tab" data-term="" aria-selected="true">
						<?php esc_html_e( 'All', 'two-fiftyseven' ); ?>
					</button>
					<?php foreach ( $terms as $term ) : ?>
					<button class="post-archive__tab text-monospace" role="tab" data-js="cpt-tab" data-term="<?php echo esc_attr( $term->slug ); ?>" aria-selected="false">
						<?php echo esc_html( $term->name ); ?>
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<select class="post-archive__select" data-js="cpt-select" data-filter="use_type" aria-label="<?php esc_attr_e( 'Filter by use type', 'two-fiftyseven' ); ?>">
					<option value=""><?php esc_html_e( 'Use type', 'two-fiftyseven' ); ?></option>
					<?php foreach ( $use_types as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<!-- Mobile: both filters as selects ──────────────── -->
			<div class="post-archive__selects">
				<?php if ( $has_terms ) : ?>
				<select class="post-archive__select" data-js="cpt-select" data-filter="term" aria-label="<?php esc_attr_e( 'Filter by category', 'two-fiftyseven' ); ?>">
					<option value=""><?php esc_html_e( 'Industry', 'two-fiftyseven' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>

				<select class="post-archive__select" data-js="cpt-select" data-filter="use_type" aria-label="<?php esc_attr_e( 'Filter by use type', 'two-fiftyseven' ); ?>">
					<option value=""><?php esc_html_e( 'Use type', 'two-fiftyseven' ); ?></option>
					<?php foreach ( $use_types as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<hr class="post-archive__rule">
		</div>

		<div class="post-archive__grid" id="cpt-grid" data-js="cpt-grid" role="tabpanel">
			<?php get_template_part( 'template-parts/cpt-card-grid', null, [
				'query'        => $initial_query,
				'post_type'    => $post_type,
				'current_page' => 1,
				'total_pages'  => $initial_query->max_num_pages,
			] ); ?>
		</div>

	</div>

</div>

<?php get_footer();
