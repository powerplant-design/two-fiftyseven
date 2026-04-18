<?php
/**
 * Template Part: Logo Marquee
 *
 * Renders the full-width scrolling logo strip used in the Hero Page block.
 * All ACF / CPT resolution happens in the calling block; this part is a
 * pure renderer that accepts a flat array of attachment IDs.
 *
 * @param int[]  $args['attachment_ids']  SVG attachment IDs to display. Required.
 * @param string $args['label']           Optional eyebrow text above the strip.
 */

$attachment_ids = $args['attachment_ids'] ?? [];
$label          = $args['label'] ?? '';

if ( empty( $attachment_ids ) ) {
	return;
}

// Repeat IDs enough times to visually fill the strip, then double for a seamless
// CSS loop. Each logo+gap ≈ 120px, so we need ~24 items to fill a 2560px screen.
// Formula: ceil(24 / count) gives passes to fill the strip, × 2 for the loop.
$count         = count( $attachment_ids );
$passes_needed = max( 2, (int) ceil( 24 / $count ) );
$repeated_ids  = array_merge( ...array_fill( 0, $passes_needed * 2, $attachment_ids ) );
?>

<div class="hero-page__marquee-wrap | stack">

	<?php if ( $label ) : ?>
		<p class="hero-page__marquee-label | text-xs text-monospace"><?php echo esc_html( $label ); ?></p>
	<?php endif; ?>

	<div class="hero-page__marquee" aria-hidden="true">
		<ul class="hero-page__marquee-track" data-js="marquee-track">
			<?php foreach ( $repeated_ids as $attachment_id ) :
				$attachment_id = (int) $attachment_id;
				if ( ! $attachment_id ) { continue; }
				$inline_svg = two_fiftyseven_get_inline_svg( $attachment_id );
				if ( ! $inline_svg ) { continue; }
			?>
				<li class="hero-page__marquee-item">
					<?php echo $inline_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sanitized by two_fiftyseven_get_inline_svg() ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

</div>
