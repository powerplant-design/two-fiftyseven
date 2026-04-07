<?php
/**
 * Media Archive Template
 *
 * Tabbed category filtering for the Media Item CPT.
 * Initial load renders all media items server-side.
 * Tab switches and pagination are handled by AJAX (cpt-archive.js).
 *
 * Colour space is set via ACF Options → Archive Settings → Media Archive
 * Colour Space, read automatically by two_fiftyseven_get_colour_space().
 */

get_header();

$post_type     = 'media_item';
$taxonomy      = 'media_item_category';
$terms         = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
$initial_query = new WP_Query( two57_get_cpt_query_args( $post_type, '', 1 ) );
?>

<div class="page-layout">

	<header class="post-index-header text-center">
		<h1 class="post-index-header__title"><?php echo esc_html( ( function_exists( 'get_field' ) ? get_field( 'media_item_archive_heading', 'option' ) : '' ) ?: post_type_archive_title( false ) ); ?></h1>
	</header>

	<div class="cpt-archive" data-js="cpt-archive" data-post-type="<?php echo esc_attr( $post_type ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">

		<?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
		<div class="cpt-archive__tabs-row">
			<div class="cpt-archive__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter by category', 'two-fiftyseven' ); ?>">
				<button class="cpt-archive__tab text-monospace" role="tab" data-js="cpt-tab" data-term="" aria-selected="true">
					<?php esc_html_e( 'All', 'two-fiftyseven' ); ?>
				</button>
				<?php foreach ( $terms as $term ) : ?>
				<button class="cpt-archive__tab text-monospace" role="tab" data-js="cpt-tab" data-term="<?php echo esc_attr( $term->slug ); ?>" aria-selected="false">
					<?php echo esc_html( $term->name ); ?>
				</button>
				<?php endforeach; ?>
			</div>
			<hr class="cpt-archive__rule">
		</div>
		<?php else : ?>
			<hr>
		<?php endif; ?>

		<div class="cpt-archive__grid" id="cpt-grid" data-js="cpt-grid" role="tabpanel">
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
