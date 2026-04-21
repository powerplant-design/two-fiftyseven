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

$no_background  = (bool) get_field( 'cta_no_background' );
$body           = get_field( 'cta_body' ) ?: '';
$image          = get_field( 'cta_image' ) ?: [];
$image_position = get_field( 'cta_image_position' ) ?: 'left';
$image_position = in_array( $image_position, [ 'left', 'right' ], true ) ? $image_position : 'left';
$secondary_link = get_field( 'cta_secondary_link' ) ?: [];
$has_image      = ! empty( $image['id'] );

$secondary_url  = ! empty( $secondary_link['url'] ) ? $secondary_link['url'] : '';
$secondary_text = ! empty( $secondary_link['title'] ) ? $secondary_link['title'] : '';
$secondary_tgt  = ! empty( $secondary_link['target'] ) ? $secondary_link['target'] : '';

// Keep reveal timing consistent — chain shifts one step when label is present.
$heading_delay = $label ? '150ms' : '0ms';
$body_delay    = $label ? '300ms' : '150ms';
$button_delay  = $label ? '450ms' : '300ms';

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
		<?php
		$panel_classes = 'cta-section__panel';
		if ( $has_image ) $panel_classes .= ' cta-section__panel--has-image cta-section__panel--image-' . $image_position;
		if ( $no_background ) $panel_classes .= ' cta-section__panel--no-bg';
		?>
		<div class="<?php echo esc_attr( $panel_classes ); ?>">

		<?php if ( $bg_svg && ! $no_background ) : ?>
			<div class="cta-section__bg <?php echo 'cover' === $svg_fit ? 'svg-cover' : 'svg-contain'; ?>"<?php echo 'contain' === $svg_fit ? ' style="padding: var(--space-l);"' : ''; ?> aria-hidden="true" data-scroll data-scroll-speed="-0.1">
				<?php echo $bg_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by two_fiftyseven_get_inline_svg() ?>
			</div>
		<?php endif; ?>

		<div class="cta-section__content stack" data-scroll data-scroll-repeat>
			<?php if ( $label ) : ?>
				<p class="cta-section__label | text-monospace text-s" style="--delay: 0ms"><?php echo esc_html( $label ); ?></p>
			<?php endif; ?>

			<div class="stack" style="--stack-gap: var(--space-xs);">
				<?php if ( $heading ) : ?>
					<h2 class="cta-section__heading | text-3xl text-balance" style="--delay: <?php echo esc_attr( $heading_delay ); ?>"><?php echo esc_html( $heading ); ?></h2>
				<?php elseif ( $is_preview ) : ?>
					<p class="cta-section__preview-hint">Add a CTA heading in the block settings.</p>
				<?php endif; ?>

				<?php if ( $body ) : ?>
					<div class="cta-section__body | text-l" style="--delay: <?php echo esc_attr( $body_delay ); ?>">
						<?php echo wp_kses_post( $body ); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $link_url || $secondary_url ) : ?>
				<div class="cluster cluster__buttons">
					<?php if ( $link_url ) : ?>
						<a
							class="btn"
							data-type="primary"
							style="--delay: <?php echo esc_attr( $button_delay ); ?>"
							href="<?php echo esc_url( $link_url ); ?>"
							<?php if ( $link_tgt ) : ?>target="<?php echo esc_attr( $link_tgt ); ?>" rel="noopener noreferrer"<?php endif; ?>
						>
							<?php echo esc_html( $link_text ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $secondary_url ) : ?>
						<a
							class="btn"
							data-type="secondary"
							style="--delay: <?php echo esc_attr( $button_delay ); ?>"
							href="<?php echo esc_url( $secondary_url ); ?>"
							<?php if ( $secondary_tgt ) : ?>target="<?php echo esc_attr( $secondary_tgt ); ?>" rel="noopener noreferrer"<?php endif; ?>
						>
							<?php echo esc_html( $secondary_text ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php elseif ( $is_preview ) : ?>
				<p class="cta-section__preview-hint">Add a primary button link in the block settings.</p>
			<?php endif; ?>
		</div>

		<?php if ( $has_image ) : ?>
			<div class="cta-section__media | frame">
				<?php echo wp_get_attachment_image(
					(int) $image['id'],
					'large',
					false,
					[
						'alt'     => ! empty( $image['alt'] ) ? esc_attr( $image['alt'] ) : '',
						'loading' => 'lazy',
					]
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
		</div>
	</div>
</section>
