<?php
/**
 * 257 Three Cards — ACF block render template.
 *
 * Three linked cards in a thirds grid. Each card has a solid colour-space
 * background, title, optional description, and an image below the text.
 * An optional centred H2 heading sits above the grid.
 *
 * ACF fields:
 *   tc_heading  — optional H2 heading (text)
 *   tc_cards    — repeater (max 3):
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

$heading        = get_field( 'tc_heading' );
$cards          = get_field( 'tc_cards' ) ?: [];
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
?>

<section class="three-cards | block">

	<div class="three-cards__inner | stack">

		<?php if ( $heading ) : ?>
			<h2 class="three-cards__heading | text-2xl" data-scroll><?php echo esc_html( $heading ); ?></h2>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add a heading in the block settings →</p>
		<?php endif; ?>

		<?php if ( $cards ) : ?>
			<ul class="three-cards__grid | grid" data-grid-layout="thirds">
				<?php foreach ( $cards as $index => $card ) :
					$title       = $card['card_title'] ?? '';
					$description = $card['card_description'] ?? '';
					$link        = $card['card_link'] ?? [];
					$url         = ! empty( $link['url'] )    ? $link['url']    : '#';
					$link_target = ! empty( $link['target'] ) ? $link['target'] : '';
					$image       = $card['card_image'] ?? [];
					$image_id    = (int) ( $image['id'] ?? 0 );
					$image_alt   = ! empty( $image['alt'] ) ? $image['alt'] : '';
					$space       = $card['card_colour_space'] ?? 'neutral';
					if ( ! in_array( $space, $allowed_spaces, true ) ) { $space = 'neutral'; }
					$delay_ms    = $index * 150;
				?>
					<li
						class="three-cards__card"
						data-color-space="<?php echo esc_attr( $space ); ?>"
						data-scroll
						style="--delay: <?php echo $delay_ms; ?>ms"
					>
						<a
							href="<?php echo esc_url( $url ); ?>"
							class="three-cards__card-link"
							<?php if ( $link_target ) : ?>target="<?php echo esc_attr( $link_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
								>
								<?php if ( $image_id ) : ?>
									<div class="three-cards__card-image | frame">
										<?php echo wp_get_attachment_image( $image_id, 'large', false, [
											'alt'     => $image_alt,
											'loading' => 'lazy',
										] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								<?php endif; ?>
							<div class="three-cards__card-body">
								<?php if ( $title ) : ?>
									<h3 class="three-cards__card-title | line-clamp-1"><?php echo esc_html( $title ); ?></h3>
								<?php endif; ?>
								<?php if ( $description ) : ?>
									<p class="three-cards__card-desc | line-clamp-3"><?php echo esc_html( $description ); ?></p>
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


</section><!-- /.three-cards -->
