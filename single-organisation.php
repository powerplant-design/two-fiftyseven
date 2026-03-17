<?php
/**
 * Single Organisation Template
 *
 * Renders a single Organisation post with hero, sidebar (brand logo, meta, links,
 * back-to-archive), rich content, and adjacent-post navigation.
 *
 * ACF fields:
 *   show_featured_image — true_false (group_two57_post_options)
 *   image_orientation   — select, 'landscape'|'portrait' (group_two57_post_options)
 *   post_links          — repeater of link fields (group_two57_post_options)
 *   brand_logo          — image ID, SVG (group_two57_cpt_brand_logo)
 */

get_header();

while ( have_posts() ) :
	the_post();

	$show_featured_image = get_field( 'show_featured_image' ) !== false;
	$image_orientation   = get_field( 'image_orientation' ) ?: 'landscape';
	$post_links          = get_field( 'post_links' ) ?: [];
	$logo_id             = function_exists( 'get_field' ) ? (int) get_field( 'brand_logo' ) : 0;
	$logo_svg            = $logo_id ? two_fiftyseven_get_inline_svg( $logo_id ) : '';
?>

<div class="page-layout">

	<?php get_template_part( 'template-parts/post-hero', null, [
		'show_featured_image' => $show_featured_image,
		'image_orientation'   => $image_orientation,
	] ); ?>

	<div class="post-layout">

		<aside class="post-layout__sidebar">

			<?php get_template_part( 'template-parts/post-sidebar-logo', null, [
				'logo_svg' => $logo_svg,
			] ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-meta' ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-links', null, [
				'post_links' => $post_links,
			] ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-back', null, [
				'back_href'  => get_post_type_archive_link( 'organisation' ),
				'back_label' => __( 'Organisations', 'two-fiftyseven' ),
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
