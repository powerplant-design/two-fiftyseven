<?php
/**
 * 257 Testimonial — ACF block render template.
 *
 * Renders a large pull-quote with an optional decorative background image,
 * colour space / mode override, and an attribution line.
 *
 * ACF fields:
 *   testimonial_quote        — the quote text (textarea)
 *   testimonial_name         — person name (text)
 *   testimonial_role         — person role / title (text)
 *   testimonial_organisation — organisation name (text)
 *   testimonial_image        — decorative background image (array)
 *   testimonial_colour_space — colour palette override; null = inherit from page (select, allow_null)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$quote                 = get_field( 'testimonial_quote' );
$name                  = get_field( 'testimonial_name' );
$role                  = get_field( 'testimonial_role' );
$organisation          = get_field( 'testimonial_organisation' );
$image                 = get_field( 'testimonial_image' );
$colour_space_override = get_field( 'testimonial_colour_space' ) ?: null;

// Sanitise against allowed spaces.
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
if ( $colour_space_override && ! in_array( $colour_space_override, $allowed_spaces, true ) ) {
	$colour_space_override = null;
}

// Build the attribute map for the outer <section>.
// If a colour space override is set, write data-color-space so JS can resolve
// data-theme against the visitor's OS/user mode preference. Without an override
// the block inherits the page's colour context from <html>.
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
?>

<section<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>

	<?php
	$inline_svg = ! empty( $image['id'] ) ? two_fiftyseven_get_inline_svg( (int) $image['id'] ) : '';
	if ( $inline_svg || ! empty( $image['url'] ) ) :
	?>
		<div class="testimonial__media" aria-hidden="true">
			<?php if ( $inline_svg ) : ?>
				<?php echo $inline_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in two_fiftyseven_get_inline_svg ?>
			<?php else : ?>
				<img src="<?php echo esc_url( $image['url'] ); ?>" alt="" loading="lazy">
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="testimonial__inner wrapper">

		<?php if ( $quote ) :
			$quote_class = mb_strlen( wp_strip_all_tags( $quote ) ) < 33 ? 'testimonial__quote text-4xl text-wrap-balance' : 'testimonial__quote text-3xl text-wrap-balance';
		?>
			<blockquote class="<?php echo esc_attr( $quote_class ); ?>" data-scroll style="--delay: 0ms">
				<?php echo wp_kses( $quote, [ 'br' => [], 'em' => [], 'strong' => [] ] ); ?>
			</blockquote>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;">Add a quote in the block settings &rarr;</p>
		<?php endif; ?>

		<?php if ( $name || $role || $organisation ) : ?>
			<p class="testimonial__attribution text-monospace text-s" data-scroll style="--delay: 150ms">
				<?php if ( $name ) : ?>
					<span class="testimonial__name"><?php echo esc_html( $name ); ?></span>
				<?php endif; ?>
				<?php if ( $role ) : ?>
					<span class="testimonial__sep" aria-hidden="true"> / </span>
					<span class="testimonial__role"><?php echo esc_html( $role ); ?></span>
				<?php endif; ?>
				<?php if ( $organisation ) : ?>
					<span class="testimonial__sep" aria-hidden="true"> / </span>
					<span class="testimonial__org"><?php echo esc_html( $organisation ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>

	</div><!-- /.testimonial__inner -->

</section><!-- /.testimonial -->
