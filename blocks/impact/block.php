<?php
/**
 * 257 Impact — ACF block render template.
 *
 * Full-bleed stats block. Each item is a large number (can include $, %, commas)
 * paired with a label. JavaScript scales the font size so the longest row fills
 * the available width; all rows share that size.
 *
 * ACF fields:
 *   impact_heading  — optional small h3 above the stats (text)
 *   impact_items    — repeater: impact_number (text) + impact_label (text)
 *   impact_bg_image — optional decorative background SVG/image (array)
 *   impact_link     — optional primary CTA button (link array)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$heading  = get_field( 'impact_heading' );
$items    = get_field( 'impact_items' ) ?: [];
$bg_image = get_field( 'impact_bg_image' );
$link     = get_field( 'impact_link' );

$link_url    = ! empty( $link['url'] )    ? $link['url']    : '';
$link_title  = ! empty( $link['title'] )  ? $link['title']  : '';
$link_target = ! empty( $link['target'] ) ? $link['target'] : '';
?>

<section class="impact" data-block="full" data-js="impact">

	<?php
	$inline_svg = ! empty( $bg_image['id'] ) ? two_fiftyseven_get_inline_svg( (int) $bg_image['id'] ) : '';
	if ( $inline_svg || ! empty( $bg_image['url'] ) ) :
	?>
		<div class="impact__media | stack" aria-hidden="true" data-scroll data-scroll-speed="-0.33">
			<?php if ( $inline_svg ) : ?>
				<?php echo $inline_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by two_fiftyseven_get_inline_svg() ?>
			<?php else : ?>
				<img src="<?php echo esc_url( $bg_image['url'] ); ?>" alt="" loading="lazy">
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $heading ) : ?>
		<p class="impact__heading | text-monospace" data-scroll data-scroll-repeat style="--delay: 0ms"><?php echo esc_html( $heading ); ?></p>
	<?php endif; ?>

	<?php if ( $items ) : ?>
		<ul class="impact__rows" data-js="impact-rows" role="list">
			<?php foreach ( $items as $index => $item ) :
				$number    = $item['impact_number'] ?? '';
				$label     = $item['impact_label']  ?? '';
				$delay_ms  = $index * 120;
				if ( ! $number && ! $label ) continue;
			?>
				<li class="impact__row" data-js="impact-row" data-scroll data-scroll-repeat style="--delay: <?php echo esc_attr( $delay_ms ); ?>ms">
					<span class="impact__number"><?php echo esc_html( $number ); ?></span>
					<span class="impact__label"><?php echo esc_html( $label ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php elseif ( $is_preview ) : ?>
		<p style="opacity:0.5;padding-inline:var(--space-xl);">Add stats in the block settings &rarr;</p>
	<?php endif; ?>

	<?php if ( $link_url ) : ?>
		<div class="impact__cta" data-scroll data-scroll-repeat style="--delay: <?php echo esc_attr( count( $items ) * 120 ); ?>ms">
			<a
				class="btn"
				data-type="primary"
				href="<?php echo esc_url( $link_url ); ?>"
				<?php if ( $link_target ) : ?>target="<?php echo esc_attr( $link_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
			>
				<?php echo esc_html( $link_title ); ?>
			</a>
		</div>
	<?php endif; ?>

</section>
