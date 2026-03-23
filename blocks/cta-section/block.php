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
 *   cta_heading        - heading text
 *   cta_link           - ACF link field (url/title/target)
 *   cta_background_svg - optional SVG background image
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$heading   = get_field( 'cta_heading' );
$link      = get_field( 'cta_link' ) ?: [];
$bg_image  = get_field( 'cta_background_svg' );
$svg_fit   = get_field( 'cta_svg_fit' );
$svg_fit   = in_array( $svg_fit, [ 'cover', 'contain' ], true ) ? $svg_fit : 'cover';
$bg_svg_id = (int) ( $bg_image['id'] ?? 0 );
$bg_svg    = $bg_svg_id ? two_fiftyseven_get_inline_svg( $bg_svg_id ) : '';

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
?>

<section class="cta-section | block">
	<div class="cta-section__inner">
		<div class="cta-section__panel">

		<?php if ( $bg_svg ) : ?>
			<div class="cta-section__bg <?php echo 'cover' === $svg_fit ? 'svg-cover' : 'svg-contain'; ?>"<?php echo 'contain' === $svg_fit ? ' style="padding: var(--space-l);"' : ''; ?> aria-hidden="true">
				<?php echo $bg_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by two_fiftyseven_get_inline_svg() ?>
			</div>
		<?php endif; ?>

		<div class="cta-section__content stack">
			<?php if ( $heading ) : ?>
				<h2 class="cta-section__heading | text-3xl text-balance"><?php echo esc_html( $heading ); ?></h2>
			<?php elseif ( $is_preview ) : ?>
				<p class="cta-section__preview-hint">Add a CTA heading in the block settings.</p>
			<?php endif; ?>

			<?php if ( $link_url ) : ?>
				<a
					class="btn"
					data-type="primary"
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
