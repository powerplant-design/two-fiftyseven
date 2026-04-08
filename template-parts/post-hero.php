<?php
/**
 * Template Part: Post Hero
 *
 * Renders the full-width hero zone for a single post/CPT.
 *
 * @param bool   $args['show_featured_image'] Whether the featured image is enabled. Default true.
 * @param string $args['image_orientation']   'landscape' or 'portrait'. Default 'landscape'.
 * @param string $args['subheading']          Override subheading text. Falls back to post_subheading ACF field.
 */

$show_featured_image = $args['show_featured_image'] ?? true;
$image_orientation   = $args['image_orientation'] ?? 'landscape';

// Allow callers to pass a subheading directly (e.g. when the field name differs).
// Falls back to the shared post_subheading ACF field used by other CPTs.
if ( array_key_exists( 'subheading', $args ?? [] ) ) {
	$subheading = (string) $args['subheading'];
} else {
	$subheading = function_exists( 'get_field' ) ? (string) ( get_field( 'post_subheading' ) ?: '' ) : '';
}

$has_thumb   = $show_featured_image && has_post_thumbnail();
$title_class = mb_strlen( get_the_title() ) < 28 ? 'post-hero__title' : 'post-hero__title text-3xl';
?>

<section class="post-hero<?php echo $has_thumb ? ' post-hero--with-image' : ''; ?>">
	<?php if ( $subheading ) : ?>
		<div class="post-hero__heading-group | stack">
			<h1 class="<?php echo esc_attr( $title_class ); ?>"><?php the_title(); ?></h1>
			<p class="post-hero__subheading | text-xl text-balance"><?php echo esc_html( $subheading ); ?></p>
		</div>
	<?php else : ?>
		<h1 class="<?php echo esc_attr( $title_class ); ?>"><?php the_title(); ?></h1>
	<?php endif; ?>
	<?php if ( $has_thumb ) : ?>
		<div class="post-hero__image post-hero__image--<?php echo esc_attr( $image_orientation ); ?>">
			<?php the_post_thumbnail( 'large' ); ?>
		</div>
	<?php endif; ?>
</section>
