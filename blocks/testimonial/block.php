<?php
/**
 * 257 Testimonial — ACF block render template.
 *
 * Renders a large pull-quote carousel (Swiper) with an optional decorative
 * background image, colour space / mode override, and per-slide attribution.
 * One decorative SVG per block; slides are a repeater.
 *
 * ACF fields:
 *   testimonial_slides[]           — repeater of quotes
 *     slide_quote                  — the quote text (textarea)
 *     slide_name                   — person name (text)
 *     slide_role                   — person role / title (text)
 *     slide_organisation           — organisation name (text)
 *     slide_person_post            — optional Person post to link the name to (post ID)
 *     slide_organisation_post      — optional Organisation post to link the org to (post ID)
 *   testimonial_image              — decorative background image (array)
 *   testimonial_colour_space       — colour palette override; null = inherit (select, allow_null)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$slides                = get_field( 'testimonial_slides' ) ?: [];
$image                 = get_field( 'testimonial_image' );
$colour_space_override = get_field( 'testimonial_colour_space' ) ?: null;

// Sanitise against allowed spaces.
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
if ( $colour_space_override && ! in_array( $colour_space_override, $allowed_spaces, true ) ) {
	$colour_space_override = null;
}

// Build the attribute map for the outer <section>.
$attrs = [
	'class'      => 'testimonial',
	'data-block' => 'full',
];

if ( $colour_space_override ) {
	$attrs['data-color-space'] = $colour_space_override;
}

// Render attribute string — all values sanitised above.
$attr_string = '';
foreach ( $attrs as $key => $value ) {
	$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
}

$slide_count = count( $slides );
?>

<section<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>

	<?php
	$inline_svg = ! empty( $image['id'] ) ? two_fiftyseven_get_inline_svg( (int) $image['id'] ) : '';
	if ( $inline_svg || ! empty( $image['url'] ) ) :
	?>
		<div class="testimonial__media" aria-hidden="true" data-scroll data-scroll-speed="-0.3">
			<?php if ( $inline_svg ) : ?>
				<?php echo $inline_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in two_fiftyseven_get_inline_svg ?>
			<?php else : ?>
				<img src="<?php echo esc_url( $image['url'] ); ?>" alt="" loading="lazy">
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="testimonial__inner">

		<?php if ( ! empty( $slides ) ) : ?>

			<div class="swiper testimonial__swiper" data-slides="<?php echo esc_attr( $slide_count ); ?>">
				<div class="swiper-wrapper">

					<?php foreach ( $slides as $slide ) :
						$quote             = $slide['slide_quote'] ?? '';
						$name              = $slide['slide_name'] ?? '';
						$role              = $slide['slide_role'] ?? '';
						$organisation      = $slide['slide_organisation'] ?? '';
						$person_post_id    = ! empty( $slide['slide_person_post'] ) ? (int) $slide['slide_person_post'] : null;
						$org_post_id       = ! empty( $slide['slide_organisation_post'] ) ? (int) $slide['slide_organisation_post'] : null;
						$person_url        = $person_post_id ? get_permalink( $person_post_id ) : null;
						$organisation_url  = $org_post_id ? get_permalink( $org_post_id ) : null;

						if ( ! $quote && ! $name ) continue;

						$quote_class = mb_strlen( wp_strip_all_tags( $quote ) ) < 33
							? 'testimonial__quote text-4xl text-wrap-balance'
							: 'testimonial__quote text-3xl text-wrap-balance';
					?>
					<div class="swiper-slide">

						<?php if ( $quote ) : ?>
							<blockquote class="<?php echo esc_attr( $quote_class ); ?>">
								<?php echo wp_kses( $quote, [ 'br' => [], 'em' => [], 'strong' => [] ] ); ?>
							</blockquote>
						<?php endif; ?>

						<?php if ( $name || $role || $organisation ) : ?>
							<p class="testimonial__attribution text-monospace text-s">
								<?php if ( $name ) : ?>
									<span class="testimonial__name">
										<?php if ( $person_url ) : ?>
											<a href="<?php echo esc_url( $person_url ); ?>"><?php echo esc_html( $name ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $name ); ?>
										<?php endif; ?>
									</span>
								<?php endif; ?>
								<?php if ( $role ) : ?>
									<span class="testimonial__sep" aria-hidden="true"> / </span>
									<span class="testimonial__role"><?php echo esc_html( $role ); ?></span>
								<?php endif; ?>
								<?php if ( $organisation ) : ?>
									<span class="testimonial__sep" aria-hidden="true"> / </span>
									<span class="testimonial__org">
										<?php if ( $organisation_url ) : ?>
											<a href="<?php echo esc_url( $organisation_url ); ?>"><?php echo esc_html( $organisation ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $organisation ); ?>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</p>
						<?php endif; ?>

					</div><!-- /.swiper-slide -->
					<?php endforeach; ?>

				</div><!-- /.swiper-wrapper -->

				<?php if ( $slide_count > 1 ) : ?>
					<div class="swiper-pagination"></div>
					<button class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Previous testimonial', 'two-fiftyseven' ); ?>"></button>
					<button class="swiper-button-next" aria-label="<?php esc_attr_e( 'Next testimonial', 'two-fiftyseven' ); ?>"></button>
				<?php endif; ?>

			</div><!-- /.testimonial__swiper -->

		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;">Add quotes in the block settings &rarr;</p>
		<?php endif; ?>

	</div><!-- /.testimonial__inner -->

</section><!-- /.testimonial -->
