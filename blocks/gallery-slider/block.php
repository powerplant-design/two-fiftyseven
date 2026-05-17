<?php
/**
 * Gallery Slider — ACF block render template.
 *
 * Full-screen image sequence driven by Lenis scroll. Each image is 100vw × 100svh.
 * With a single image the block is a static full-screen hero. With multiple images,
 * each additional image slides in from the right as the page is scrolled, stacking
 * on top of the previous. A progress indicator shows the current position.
 *
 * ACF fields:
 *   gallery_slider_images    — gallery (array of image objects, min 1)
 *   gallery_slider_anchor_id — optional HTML id for anchor link navigation
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$images    = get_field( 'gallery_slider_images' ) ?: [];
$count     = max( 1, count( $images ) );
$anchor_id = sanitize_html_class( (string) get_field( 'gallery_slider_anchor_id' ) );
?>

<section
	class="gallery-slider"
	data-block="full"
	data-js="gallery-slider"
	data-image-count="<?php echo esc_attr( $count ); ?>"
	style="--image-count: <?php echo esc_attr( $count ); ?>;"
	<?php if ( $anchor_id ) : ?>id="<?php echo esc_attr( $anchor_id ); ?>"<?php endif; ?>
>

	<?php if ( empty( $images ) && $is_preview ) : ?>
		<p style="padding:2rem;opacity:0.5;text-align:center;">Add images in the block settings &rarr;</p>
	<?php endif; ?>

	<div class="gallery-slider__track" data-js="gallery-slider-track">

		<?php foreach ( $images as $index => $image ) : ?>
		<div
			class="gallery-slider__slide"
			data-js="gallery-slide"
			data-index="<?php echo esc_attr( $index ); ?>"
			style="--slide-index: <?php echo esc_attr( $index ); ?>;"
		>
			<?php
			echo wp_get_attachment_image(
				$image['ID'],
				'full',
				false,
				[
					'class'    => 'gallery-slider__img',
					'loading'  => 0 === $index ? 'eager' : 'lazy',
					'decoding' => 'async',
					'sizes'    => '100vw',
				]
			);
			?>
		</div>
		<?php endforeach; ?>

		<?php if ( $count > 1 ) : ?>
		<nav class="gallery-slider__progress" aria-label="Image progress">
			<?php for ( $i = 0; $i < $count; $i++ ) : ?>
			<span
				class="gallery-slider__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"
				data-js="gallery-dot"
				data-index="<?php echo esc_attr( $i ); ?>"
				aria-current="<?php echo 0 === $i ? 'true' : 'false'; ?>"
				role="img"
				aria-label="Image <?php echo esc_attr( $i + 1 ); ?> of <?php echo esc_attr( $count ); ?>"
			></span>
			<?php endfor; ?>
		</nav>
		<?php endif; ?>

	</div>

</section>
