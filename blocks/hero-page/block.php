<?php
/**
 * Hero Page — ACF block render template.
 *
 * Renders a page-level hero with:
 *   - A full-bleed background image
 *   - A display headline (h1)
 *   - An optional subtitle paragraph
 *   - A full-width icon marquee
 *
 * ACF fields:
 *   page_hero_headline         — display heading (textarea, supports <br>)
 *   page_hero_subtitle         — subtitle paragraph (textarea)
 *   page_hero_background_image — background image (array)
 *   page_hero_marquee_label    — eyebrow text above marquee (e.g. "As used by")
 *   page_hero_marquee_icons    — repeater: icon_image (array)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused — no inner blocks).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$headline      = get_field( 'page_hero_headline' );
$subtitle      = get_field( 'page_hero_subtitle' );
$bg_image      = get_field( 'page_hero_background_image' );
$marquee_label = get_field( 'page_hero_marquee_label' );
$icons         = get_field( 'page_hero_marquee_icons' ) ?: [];

// Background image inline CSS custom property.
$bg_style = '';
if ( ! empty( $bg_image['url'] ) ) {
	$bg_style = ' style="--hero-bg: url(\'' . esc_url( $bg_image['url'] ) . '\')"';
}

// Repeat icons enough times to fill the marquee strip, then double for seamless loop.
$marquee_icons = [];
if ( ! empty( $icons ) ) {
	$min_passes    = 6;
	$passes_needed = max( $min_passes, (int) ceil( $min_passes / count( $icons ) ) );
	$marquee_icons = array_merge( ...array_fill( 0, $passes_needed * 2, $icons ) );
}
?>

<section class="hero-page" data-block="full"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>

	<div class="hero-page__backdrop | overlay" aria-hidden="true"></div>

	<div class="hero-page__inner wrapper stack">
    
    <?php if ( $headline ) : ?>
        <h1 class="hero-page__headline text-3xl"><?php echo wp_kses( $headline, [ 'br' => [] ] ); ?></h1>
		<?php elseif ( $is_preview ) : ?>
				<p style="color:white;opacity:0.5;text-align:center;">Add a headline in the block settings →</p>
            <?php endif; ?>
            
            <?php if ( $subtitle ) : ?>
                <h2 class="hero-page__subtitle text-xl"><?php echo wp_kses( $subtitle, [ 'br' => [], 'strong' => [], 'em' => [] ] ); ?></h2>
            <?php endif; ?>
                
            </div>

	<?php if ( $marquee_icons ) : ?>
		<div class="hero-page__marquee-wrap | stack"">

			<?php if ( $marquee_label ) : ?>
				<p class="hero-page__marquee-label"><?php echo esc_html( $marquee_label ); ?></p>
			<?php endif; ?>

			<div class="hero-page__marquee" aria-hidden="true">
				<ul class="hero-page__marquee-track" data-js="marquee-track">
					<?php foreach ( $marquee_icons as $item ) :
						$icon          = $item['icon_image'] ?? [];
						$attachment_id = (int) ( $icon['id'] ?? 0 );
						if ( empty( $icon['url'] ) ) { continue; }
						$inline_svg = $attachment_id ? two_fiftyseven_get_inline_svg( $attachment_id ) : '';
					?>
						<li class="hero-page__marquee-item">
							<?php if ( $inline_svg ) : ?>
								<?php echo $inline_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized on upload ?>
							<?php else : ?>
								<img
									src="<?php echo esc_url( $icon['url'] ); ?>"
									alt=""
									width="<?php echo esc_attr( $icon['width'] ?? 48 ); ?>"
									height="<?php echo esc_attr( $icon['height'] ?? 48 ); ?>"
									loading="lazy"
								>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

		</div>
	<?php elseif ( $is_preview ) : ?>
		<p style="color:white;opacity:0.5;text-align:center;padding:1rem;">Add marquee icons in the block settings →</p>
	<?php endif; ?>

</section>
