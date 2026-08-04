<?php
/**
 * 257 Three Cards — ACF block render template.
 *
 * Three linked cards in a thirds grid. Each card has a solid colour-space
 * background, title, optional description, and an image below the text.
 * An optional centred H2 heading sits above the grid.
 *
 * ACF fields:
 *   tc_eyebrow      — optional small label above the heading (text)
 *   tc_heading      — optional H2 heading (text)
 *   tc_intro        — optional body copy below the heading (textarea)
 *   tc_heading_size — heading-l (4xl) or heading-m (3xl), default heading-m (select)
 *   tc_filled_cards — show hover bg by default, only image zooms on hover (bool)
 *   tc_cards        — repeater (max 3):
 *     card_title        — card heading (text)
 *     card_description  — optional body copy (textarea)
 *     card_link         — CTA link (link, array)
 *     card_image        — card image shown below the text (image, array)
 *     card_colour_space — colour space override (select)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow        = get_field( 'tc_eyebrow' );
$heading        = get_field( 'tc_heading' );
$intro          = get_field( 'tc_intro' );
$heading_size   = get_field( 'tc_heading_size' ) ?: 'heading-m';
$heading_size   = in_array( $heading_size, [ 'heading-l', 'heading-m' ], true ) ? $heading_size : 'heading-m';
$filled         = (bool) get_field( 'tc_filled_cards' );
$cards          = get_field( 'tc_cards' ) ?: [];
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
?>

<section class="three-cards | block<?php echo $filled ? ' is-filled' : ''; ?>">

	<div class="three-cards__inner | stack">

		<?php if ( $eyebrow || $heading || $intro ) : ?>
			<div class="three-cards__intro | stack" data-scroll data-scroll-repeat>
				<?php if ( $eyebrow ) : ?>
					<p class="three-cards__eyebrow | text-monospace text-s">
						<?php echo esc_html( $eyebrow ); ?>
					</p>
				<?php endif; ?>
				<?php if ( $heading ) : ?>
					<h2 class="three-cards__heading | text-<?php echo 'heading-l' === $heading_size ? '4xl' : '3xl'; ?> text-wrap-balance"><?php echo esc_html( $heading ); ?></h2>
				<?php endif; ?>
				<?php if ( $intro ) : ?>
					<p class="three-cards__body | text-l text-wrap-balance">
						<?php echo esc_html( $intro ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add a heading in the block settings →</p>
		<?php endif; ?>

		<?php if ( $cards ) : ?>
			<ul class="three-cards__grid | grid" data-grid-layout="thirds" data-scroll data-scroll-repeat>
				<?php foreach ( $cards as $index => $card ) :
					$title       = $card['card_title'] ?? '';
					$description = $card['card_description'] ?? '';
					$link        = $card['card_link'] ?? [];
					$raw_url     = ! empty( $link['url'] ) ? $link['url'] : '#';
					$url         = ( $raw_url !== '#' ) ? wp_make_link_relative( $raw_url ) : '#';
					$link_target = ! empty( $link['target'] ) ? $link['target'] : '';
					$image       = $card['card_image'] ?? [];
					$image_id    = (int) ( $image['id'] ?? 0 );
					$image_alt   = ! empty( $image['alt'] ) ? $image['alt'] : '';
					$space       = $card['card_colour_space'] ?? 'neutral';
					if ( ! in_array( $space, $allowed_spaces, true ) ) { $space = 'neutral'; }
					$delay_ms    = $index * 160;
				?>
					<li
						class="three-cards__card"
						data-color-space="<?php echo esc_attr( $space ); ?>"
						style="--delay: <?php echo $delay_ms; ?>ms"
					>
						<a
							href="<?php echo esc_url( $url ); ?>"
							class="three-cards__card-link"
							<?php if ( $link_target ) : ?>target="<?php echo esc_attr( $link_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
								>
						<?php if ( $image_id ) :
							$image_mime = get_post_mime_type( $image_id );
							$is_svg     = ( $image_mime === 'image/svg+xml' );
							if ( $is_svg ) :
								$image_url = wp_get_attachment_url( $image_id );
							?>
								<div
									class="three-cards__card-image three-cards__card-image--has-svg | frame"
									<?php if ( $image_url ) : ?>style="--shape-url: url(<?php echo esc_url( $image_url ); ?>)"<?php endif; ?>
									aria-hidden="true"
								>
									<?php echo two_fiftyseven_get_inline_svg( $image_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized inline SVG ?>
								</div>
							<?php else : ?>
								<div class="three-cards__card-image | frame">
									<?php echo wp_get_attachment_image( $image_id, 'large', false, [
										'alt'     => $image_alt,
										'loading' => 'lazy',
									] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
							<div class="three-cards__card-body">
								<?php if ( $title ) : ?>
									<h3 class="three-cards__card-title | text-xl font-bold line-clamp-2"><?php echo esc_html( $title ); ?></h3>
								<?php endif; ?>
								<?php if ( $description ) : ?>
									<p class="three-cards__card-desc | line-clamp-5"><?php echo esc_html( $description ); ?></p>
								<?php endif; ?>
							</div>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add cards in the block settings →</p>
		<?php endif; ?>

	</div>

</section><!-- /.three-cards -->
