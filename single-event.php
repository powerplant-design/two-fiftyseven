<?php
/**
 * Single Event Template
 */

get_header();

while ( have_posts() ) :
	the_post();

	$show_featured_image = get_field( 'show_featured_image' ) !== false;
	$image_orientation   = get_field( 'image_orientation' ) ?: 'portrait';
	$post_links          = get_field( 'post_links' ) ?: [];
	$logo_id             = function_exists( 'get_field' ) ? (int) get_field( 'brand_logo' ) : 0;
	$logo_svg            = $logo_id ? two_fiftyseven_get_inline_svg( $logo_id ) : '';
	$event_subheading    = function_exists( 'get_field' ) ? (string) ( get_field( 'event_subheading' ) ?: '' ) : '';
	$badge_terms         = [];
	$terms               = get_the_terms( get_the_ID(), 'event_category' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$badge_terms[] = $t->name;
		}
	}
?>

<div class="page-layout">

	<?php get_template_part( 'template-parts/post-hero', null, [
		'show_featured_image' => $show_featured_image,
		'image_orientation'   => $image_orientation,
		'subheading'          => $event_subheading,
	] ); ?>

	<div class="post-layout">

		<aside class="post-layout__sidebar">

			<?php get_template_part( 'template-parts/post-sidebar-logo', null, [
				'logo_svg' => $logo_svg,
			] ); ?>

			<?php if ( $badge_terms ) : ?>
				<div class="cluster badge-cluster">
					<?php foreach ( $badge_terms as $badge_term_name ) : ?>
						<span class="badge" data-size="medium" data-color="forest"><?php echo esc_html( $badge_term_name ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/post-sidebar-event-meta' ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-links', null, [
				'post_links' => $post_links,
			] ); ?>

			<?php get_template_part( 'template-parts/post-sidebar-back', null, [
				'back_href'  => get_post_type_archive_link( 'event' ),
				'back_label' => __( 'Events', 'two-fiftyseven' ),
			] ); ?>

		</aside>

		<div class="post-layout__content | prose stack">
			<?php the_content(); ?>
			<?php get_template_part( 'template-parts/post-adjacent-nav' ); ?>
		</div>

	</div>

</div>

<?php endwhile; ?>
<?php get_footer();
