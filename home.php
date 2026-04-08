<?php
/**
 * Post Index Template (home.php)
 *
 * Tabbed category filtering for the blog posts archive.
 * Initial load renders server-side; tab switches and pagination use AJAX (cpt-archive.js).
 *
 * WordPress uses this template when a static Posts Page is assigned in
 * Settings → Reading. The assigned page provides the title and URL slug.
 */

get_header();

$post_type       = 'post';
$taxonomy        = 'category';
$all_terms       = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true ] );
$terms           = is_array( $all_terms ) ? array_values( array_filter( $all_terms, fn( $t ) => $t->slug !== 'uncategorized' ) ) : [];
$options_heading = function_exists( 'get_field' ) ? get_field( 'posts_archive_heading', 'option' ) : '';
$page_title      = $options_heading ?: ( get_queried_object()?->post_title ?: __( 'Posts', 'two-fiftyseven' ) );
$initial_query   = new WP_Query( two57_get_cpt_query_args( $post_type, '', 1 ) );
?>

<div class="page-layout">

	<header class="post-index-header text-center">
		<h1 class="post-index-header__title"><?php echo esc_html( $page_title ); ?></h1>
	</header>

	<div class="post-archive" data-js="cpt-archive" data-post-type="<?php echo esc_attr( $post_type ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">

		<?php if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) : ?>
		<div class="post-archive__tabs-row">
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
			<hr class="post-archive__rule">
		</div>
		<?php else : ?>
			<hr>
		<?php endif; ?>

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
