<?php
/**
 * Hero Home — ACF block render template.
 *
 * Renders the full-viewport hero with:
 *   - A full-bleed background image
 *   - A large display headline
 *   - A colour-token panel containing 3 linked cards + an icon marquee
 *
 * ACF fields:
 *   hero_headline           — display heading (textarea, supports <br>)
 *   hero_background_image   — background image (array)
 *   hero_panel_colour_space — colour palette for the panel (select)
 *   hero_panel_mode         — light / dark / auto mode for the panel (select)
 *   hero_cards              — repeater: card_title, card_description, card_link
 *   hero_marquee_icons      — repeater: icon_image (array)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused — no inner blocks).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$headline    = get_field( 'hero_headline' );
$bg_image    = get_field( 'hero_background_image' );
$panel_space = get_field( 'hero_panel_colour_space' ) ?: 'forest';
$panel_mode  = get_field( 'hero_panel_mode' ) ?: 'dark';
$cards       = get_field( 'hero_cards' ) ?: [];
$icons       = get_field( 'hero_marquee_icons' ) ?: [];

// Sanitise against allowed values.
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
$allowed_modes  = [ 'auto', 'light', 'dark' ];
if ( ! in_array( $panel_space, $allowed_spaces, true ) ) { $panel_space = 'forest'; }
if ( ! in_array( $panel_mode, $allowed_modes, true ) )   { $panel_mode  = 'dark'; }

// Build panel data attributes.
$panel_data = 'data-color-space="' . esc_attr( $panel_space ) . '"';
if ( 'auto' !== $panel_mode ) {
	$panel_data .= ' data-color-mode="' . esc_attr( $panel_mode ) . '"';
	$panel_data .= ' data-theme="' . esc_attr( $panel_space . '-' . $panel_mode ) . '"';
}

// Background image inline CSS custom property.
$bg_style = '';
if ( ! empty( $bg_image['url'] ) ) {
	$bg_style = ' style="--hero-bg: url(\'' . esc_url( $bg_image['url'] ) . '\')"';
}

// Duplicate icons for a seamless CSS marquee loop.
$marquee_icons = ! empty( $icons ) ? array_merge( $icons, $icons ) : [];
?>

<section class="hero-home"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>

	<div class="hero-home__backdrop | overlay" aria-hidden="true"></div>

	<div class="hero-home__inner wrapper stack">

		<?php if ( $headline ) : ?>
			<h1 class="hero-home__headline"><?php echo wp_kses( $headline, [ 'br' => [] ] ); ?></h1>
		<?php elseif ( $is_preview ) : ?>
			<p class="hero-home__preview-hint" style="color:white;opacity:0.5;text-align:center;">Add a headline in the block settings →</p>
		<?php endif; ?>

		<div class="hero-home__panel | stack" <?php echo $panel_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>

			<p class="hero-home__eyebrow">Need space for</p>

			<?php if ( $cards ) : ?>
				<ul class="hero-home__cards | grid cards" data-grid-layout="thirds">
					<?php foreach ( $cards as $card ) :
						$link        = $card['card_link'] ?? [];
						$url         = ! empty( $link['url'] )    ? $link['url']    : '#';
						$link_target = ! empty( $link['target'] ) ? $link['target'] : '';
					?>
					<li class="hero-home__card | card">
						<a
							href="<?php echo esc_url( $url ); ?>"
							<?php if ( $link_target ) : ?>target="<?php echo esc_attr( $link_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
						>
							<?php if ( ! empty( $card['card_title'] ) ) : ?>
								<h2 class="hero-home__card-title | card-title"><?php echo esc_html( $card['card_title'] ); ?></h2>
							<?php endif; ?>
							<?php if ( ! empty( $card['card_description'] ) ) : ?>
								<p class="hero-home__card-desc | card-desc"><?php echo esc_html( $card['card_description'] ); ?></p>
							<?php endif; ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( $is_preview ) : ?>
				<p style="opacity:0.5;text-align:center;padding:1rem;">Add cards in the block settings →</p>
			<?php endif; ?>

			<?php if ( $marquee_icons ) : ?>
				<div class="hero-home__marquee" aria-hidden="true">
					<ul class="hero-home__marquee-track">
						<?php
						// Repeat icons enough times to fill the strip (minimum 6 passes),
						// then double the result for a seamless CSS looping animation.
						$min_passes     = 6;
						$passes_needed  = max( $min_passes, (int) ceil( $min_passes / count( $marquee_icons ) ) );
						$repeated       = array_merge( ...array_fill( 0, $passes_needed * 2, $marquee_icons ) );

						foreach ( $repeated as $item ) :
							$icon          = $item['icon_image'] ?? [];
							$attachment_id = (int) ( $icon['id'] ?? 0 );
							if ( empty( $icon['url'] ) ) { continue; }
							$inline_svg = $attachment_id ? two_fiftyseven_get_inline_svg( $attachment_id ) : '';
						?>
							<li class="hero-home__marquee-item">
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
			<?php endif; ?>

		</div><!-- /.hero-home__panel -->

	</div><!-- /.hero-home__inner -->

</section><!-- /.hero-home -->
