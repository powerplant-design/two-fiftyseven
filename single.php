<?php
/**
 * Single Post Template
 *
 * Renders a single post with:
 *   - A hero zone: post title (h1) + optional featured image (toggle)
 *   - A two-column post layout:
 *       Left  (2/5) — sticky sidebar: posted/updated dates, author, external links, back link
 *       Right (3/5) — scrolling rich post content + adjacent-post navigation
 *
 * ACF fields (group_two57_post_options):
 *   show_featured_image — true_false; shows/hides the featured image in the hero
 *   image_orientation   — select; 'landscape' or 'portrait'
 *   post_links          — repeater of link fields ({ url, title, target })
 */

get_header();

while ( have_posts() ) :
	the_post();

	$show_featured_image = get_field( 'show_featured_image' ) !== false;
	$image_orientation   = get_field( 'image_orientation' ) ?: 'landscape';
	$post_links          = get_field( 'post_links' ) ?: [];
	$badge_terms         = [];
	$terms               = get_the_terms( get_the_ID(), 'category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( $t->slug !== 'uncategorized' ) {
				$badge_terms[] = $t->name;
			}
		}
	}
?>

<div class="page-layout">

	<?php get_template_part( 'template-parts/post-hero', null, [
		'show_featured_image' => $show_featured_image,
		'image_orientation'   => $image_orientation,
	] ); ?>

	<div class="post-layout">

		<aside class="post-layout__sidebar">

			<?php if ( $badge_terms ) : ?>
				<div class="cluster badge-cluster">
					<?php foreach ( $badge_terms as $badge_term_name ) : ?>
						<span class="badge" data-size="medium"><?php echo esc_html( $badge_term_name ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/post-sidebar-meta' ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-links', null, [
				'post_links' => $post_links,
			] ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-back', null, [
				'back_href'  => get_permalink( get_option( 'page_for_posts' ) ),
				'back_label' => 'Kōrero',
			] ); ?>

		</aside>

		<div class="post-layout__content | prose stack">
			<?php the_content(); ?>
			<?php get_template_part( 'template-parts/post-adjacent-nav' ); ?>
		</div>

	</div><!-- /.post-layout -->

</div><!-- /.page-layout -->

<?php endwhile; ?>
<?php get_footer();
