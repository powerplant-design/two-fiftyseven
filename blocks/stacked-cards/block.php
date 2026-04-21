<?php
/**
 * Stacked Cards — ACF block render template.
 *
 * Renders a series of cards that reveal one-by-one on scroll.
 * Each card has a tab label, H3 heading, rich content, an optional
 * CTA button, and an image.
 *
 * ACF fields:
 *   stacked_cards_items — repeater:
 *     tab_label  — short label shown on the card tab (text)
 *     heading    — card heading (text)
 *     colour_space — optional colour space override (select)
 *     content    — rich text body (wysiwyg)
 *     button     — optional CTA (link)
 *     image      — card image (image, array)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$items         = get_field( 'stacked_cards_items' ) ?: [];
$card_count    = max( 1, count( $items ) );
$anchor_id     = sanitize_html_class( (string) get_field( 'stacked_cards_anchor_id' ) );
$section_title = get_field( 'stacked_cards_section_title' );
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
?>

<section
	class="stacked-cards"
	data-js="stacked-cards"
	data-card-count="<?php echo esc_attr( $card_count ); ?>"
	style="--card-count: <?php echo esc_attr( $card_count ); ?>;"
	<?php if ( $anchor_id ) : ?>id="<?php echo esc_attr( $anchor_id ); ?>"<?php endif; ?>
>

	<?php if ( empty( $items ) && $is_preview ) : ?>
		<p style="padding:2rem;opacity:0.5;text-align:center;">Add cards in the block settings &rarr;</p>
	<?php endif; ?>

	<?php if ( $section_title ) : ?>
		<h2 class="stacked-cards__section-title | text-2xl measure-narrow text-balance"><?php echo esc_html( $section_title ); ?></h2>
	<?php endif; ?>

	<div class="stacked-cards__track" data-js="stacked-cards-track">

	<?php foreach ( $items as $index => $item ) :
		$heading              = $item['heading'] ?? '';
		$content              = $item['content'] ?? '';
		$tab_label            = $item['tab_label'] ?? '';
		$button               = $item['button'] ?? [];
		$secondary_button     = $item['secondary_button'] ?? [];
		$image                = $item['image'] ?? [];
		$colour_space_override = $item['colour_space'] ?? null;
		$body_size            = ( $item['body_size'] ?? 'large' ) === 'regular' ? 'text-m' : 'text-l';

		if ( $colour_space_override && ! in_array( $colour_space_override, $allowed_spaces, true ) ) {
			$colour_space_override = null;
		}
	?>
		<div
			class="stacked-cards__item"
			data-js="stacked-card"
			data-index="<?php echo esc_attr( $index ); ?>"
			<?php if ( $colour_space_override ) : ?>data-color-space="<?php echo esc_attr( $colour_space_override ); ?>"<?php endif; ?>
			style="--card-index: <?php echo esc_attr( $index ); ?>;"
		>
			<?php if ( $tab_label ) : ?>
				<?php if ( $index === 0 ) : ?>
					<div class="stacked-cards__tab | text-monospace">
						<?php echo esc_html( $tab_label ); ?>
					</div>
				<?php else : ?>
					<button type="button" class="stacked-cards__tab | text-monospace" data-js="stacked-card-tab">
						<?php echo esc_html( $tab_label ); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>

			<div class="stacked-cards__panel">

				<div class="stacked-cards__body | stack">

					<?php if ( $heading ) : ?>
						<h3 class="stacked-cards__heading"><?php echo esc_html( $heading ); ?></h3>
					<?php endif; ?>

					<?php if ( $content ) : ?>
						<div class="stacked-cards__content | stack <?php echo esc_attr( $body_size ); ?>">
							<?php echo wp_kses_post( $content ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $button['url'] ) || ! empty( $secondary_button['url'] ) ) : ?>
						<div class="cluster cluster__buttons">
							<?php if ( ! empty( $button['url'] ) ) :
								$btn_url    = $button['url'];
								$btn_title  = $button['title'] ?: $btn_url;
								$btn_target = ! empty( $button['target'] ) ? $button['target'] : '';
							?>
								<a
									href="<?php echo esc_url( $btn_url ); ?>"
									class="btn"
									data-type="primary"
									<?php if ( $btn_target ) : ?>target="<?php echo esc_attr( $btn_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
								>
									<?php echo esc_html( $btn_title ); ?>
								</a>
							<?php endif; ?>
							<?php if ( ! empty( $secondary_button['url'] ) ) :
								$sec_url    = $secondary_button['url'];
								$sec_title  = $secondary_button['title'] ?: $sec_url;
								$sec_target = ! empty( $secondary_button['target'] ) ? $secondary_button['target'] : '';
							?>
								<a
									href="<?php echo esc_url( $sec_url ); ?>"
									class="btn"
									data-type="secondary"
									<?php if ( $sec_target ) : ?>target="<?php echo esc_attr( $sec_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
								>
									<?php echo esc_html( $sec_title ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				</div>

				<?php if ( ! empty( $image['id'] ) ) :
					$alt = ! empty( $image['alt'] ) ? $image['alt'] : '';
				?>
					<div class="stacked-cards__image | frame">
						<?php echo wp_get_attachment_image(
							(int) $image['id'],
							'large',
							false,
							[
								'alt'     => $alt,
								'loading' => $index === 0 ? 'eager' : 'lazy',
							]
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

			</div>
		</div>
	<?php endforeach; ?>

	</div><!-- /.stacked-cards__track -->

</section><!-- /.stacked-cards -->
