<?php
/**
 * 257 CTA Section - ACF block render template.
 *
 * Renders a wrapper-contained call-to-action section with:
 * - Large h3 heading
 * - Primary button link
 * - Optional SVG background image that covers the container
 *
 * ACF fields:
 *   cta_label          - optional small label above the heading
 *   cta_colour_space   - optional colour palette override
 *   cta_heading        - heading text
 *   cta_link           - ACF link field (url/title/target)
 *   cta_background_svg - optional SVG background image
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$label                 = trim( (string) get_field( 'cta_label' ) );
$heading               = get_field( 'cta_heading' );
$link                  = get_field( 'cta_link' ) ?: [];
$bg_image              = get_field( 'cta_background_svg' );
$svg_fit               = get_field( 'cta_svg_fit' );
$colour_space_override = get_field( 'cta_colour_space' ) ?: null;
$svg_fit               = in_array( $svg_fit, [ 'cover', 'contain' ], true ) ? $svg_fit : 'cover';
$bg_svg_id             = (int) ( $bg_image['id'] ?? 0 );
$bg_svg                = $bg_svg_id ? two_fiftyseven_get_inline_svg( $bg_svg_id ) : '';

$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
if ( $colour_space_override && ! in_array( $colour_space_override, $allowed_spaces, true ) ) {
	$colour_space_override = null;
}

// Force decorative background SVGs to behave like cover/contain from block settings.
if ( $bg_svg ) {
	$preserve_aspect_ratio = 'cover' === $svg_fit ? 'xMidYMid slice' : 'xMidYMid meet';

	$bg_svg = preg_replace(
		'/<svg\b([^>]*)\bpreserveAspectRatio="[^"]*"([^>]*)>/i',
		'<svg$1 preserveAspectRatio="' . $preserve_aspect_ratio . '"$2>',
		$bg_svg,
		1
	);

	$bg_svg = preg_replace(
		'/<svg\b(?![^>]*\bpreserveAspectRatio=)/i',
		'<svg preserveAspectRatio="' . $preserve_aspect_ratio . '"',
		$bg_svg,
		1
	);
}

$link_url  = ! empty( $link['url'] ) ? $link['url'] : '';
$link_text = ! empty( $link['title'] ) ? $link['title'] : __( 'Contact', 'two-fiftyseven' );
$link_tgt  = ! empty( $link['target'] ) ? $link['target'] : '';

// Keep reveal timing consistent when the optional label is present.
$heading_delay = $label ? '150ms' : '0ms';
$button_delay  = $label ? '300ms' : '150ms';

$attrs = [
	'class' => 'cta-section | block',
];

if ( $colour_space_override ) {
	$attrs['data-color-space'] = $colour_space_override;
}

$attr_string = '';
foreach ( $attrs as $key => $value ) {
	$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
}
?>

<section<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<div class="cta-section__inner">
		<div class="cta-section__panel">

		<?php if ( $bg_svg ) : ?>
			<div class="cta-section__bg <?php echo 'cover' === $svg_fit ? 'svg-cover' : 'svg-contain'; ?>"<?php echo 'contain' === $svg_fit ? ' style="padding: var(--space-l);"' : ''; ?> aria-hidden="true">
				<?php echo $bg_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by two_fiftyseven_get_inline_svg() ?>
			</div>
		<?php endif; ?>

		<div class="cta-section__content stack">
			<?php if ( $label ) : ?>
				<p class="cta-section__label | text-monospace text-s" data-scroll data-scroll-repeat style="--delay: 0ms"><?php echo esc_html( $label ); ?></p>
			<?php endif; ?>

			<?php if ( $heading ) : ?>
				<h2 class="cta-section__heading | text-3xl text-balance" data-scroll data-scroll-repeat style="--delay: <?php echo esc_attr( $heading_delay ); ?>"><?php echo esc_html( $heading ); ?></h2>
			<?php elseif ( $is_preview ) : ?>
				<p class="cta-section__preview-hint">Add a CTA heading in the block settings.</p>
			<?php endif; ?>

			<?php if ( $link_url ) : ?>
				<a
					class="btn"
					data-type="primary"
					data-scroll
					data-scroll-repeat
					style="--delay: <?php echo esc_attr( $button_delay ); ?>"
					href="<?php echo esc_url( $link_url ); ?>"
					<?php if ( $link_tgt ) : ?>target="<?php echo esc_attr( $link_tgt ); ?>" rel="noopener noreferrer"<?php endif; ?>
				>
					<?php echo esc_html( $link_text ); ?>
				</a>
			<?php elseif ( $is_preview ) : ?>
				<p class="cta-section__preview-hint">Add a primary button link in the block settings.</p>
			<?php endif; ?>
		</div>
		</div>
	</div>
</section>
