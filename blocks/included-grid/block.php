<?php
/**
 * 257 Included Grid — ACF block render template.
 *
 * A "what's included" panel: a heading + intro on one side, a grid of chrome'd
 * tiles on the other. Each tile has a bold title and 1-2 sentences of body.
 * Used for room-booking inclusions, membership inclusions, event-booking
 * inclusions — anywhere a "you get X, Y, Z" list needs to be readable at a
 * glance without becoming a spec sheet.
 *
 * ACF fields:
 *   ig_eyebrow      — short label above the heading (text, optional)
 *   ig_heading      — H2 heading (text)
 *   ig_intro        — supporting paragraph (textarea)
 *   ig_layout       — select: 'intro-left' (default) or 'intro-top'
 *   ig_colour_space — select: colour space override (optional)
 *   ig_items        — repeater (min 2, max 8):
 *     item_title    — bold tile heading (text, required)
 *     item_body     — supporting text (textarea, required)
 *     item_svg      — optional decorative SVG shown as background on right side (image)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'ig_eyebrow' );
$heading      = get_field( 'ig_heading' );
$intro        = get_field( 'ig_intro' );
$layout       = get_field( 'ig_layout' ) ?: 'intro-left';
$layout       = in_array( $layout, [ 'intro-left', 'intro-top' ], true ) ? $layout : 'intro-left';
$colour_space = get_field( 'ig_colour_space' ) ?: null;
$items        = get_field( 'ig_items' ) ?: [];
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
if ( $colour_space && ! in_array( $colour_space, $allowed_spaces, true ) ) {
	$colour_space = null;
}

$attrs = [ 'class' => 'included-grid | block included-grid--' . $layout ];
if ( $colour_space ) {
	$attrs['data-color-space'] = $colour_space;
}
$attrs['data-block'] = 'full';

$attr_string = '';
foreach ( $attrs as $key => $value ) {
	$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
}
?>
<section<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<div class="included-grid__inner | wrapper">

		<?php if ( $heading || $intro || $eyebrow ) : ?>
			<div class="included-grid__intro | stack" data-scroll data-scroll-repeat>
				<?php if ( $eyebrow ) : ?>
					<p class="included-grid__eyebrow | text-monospace text-s"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<?php if ( $heading ) : ?>
					<h2 class="included-grid__heading | text-3xl text-wrap-balance"><?php echo esc_html( $heading ); ?></h2>
				<?php endif; ?>
				<?php if ( $intro ) : ?>
					<p class="included-grid__body | text-l text-wrap-balance"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add a heading in the block settings &rarr;</p>
		<?php endif; ?>

		<?php if ( ! empty( $items ) ) : ?>
			<ul class="included-grid__cards | grid" data-scroll data-scroll-repeat role="list">
			<?php foreach ( $items as $index => $item ) :
				$title    = $item['item_title'] ?? '';
				$body     = $item['item_body'] ?? '';
				$svg_data = $item['item_svg'] ?? [];
				$svg_url  = ! empty( $svg_data['url'] ) ? $svg_data['url'] : '';
				$delay_ms = ( $index * 80 );
			?>
				<li
					class="included-grid__card<?php if ( $svg_url ) : ?> included-grid__card--has-svg<?php endif; ?>"
					style="--delay: <?php echo (int) $delay_ms; ?>ms;<?php if ( $svg_url ) : ?> --shape-url: url(<?php echo esc_url( $svg_url ); ?>);<?php endif; ?>"
				>
					<div class="included-grid__card-content">
						<?php if ( $title ) : ?>
							<h3 class="included-grid__card-title | text-l font-bold"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( $body ) : ?>
							<p class="included-grid__card-body"><?php echo esc_html( $body ); ?></p>
						<?php endif; ?>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add tiles in the block settings &rarr;</p>
		<?php endif; ?>

	</div>
</section>
