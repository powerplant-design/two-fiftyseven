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
 *     content    — rich text body (wysiwyg)
 *     button     — optional CTA (link)
 *     image      — card image (image, array)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$items = get_field( 'stacked_cards_items' ) ?: [];
?>

<div class="stacked-cards" data-js="stacked-cards" data-card-count="<?php echo esc_attr( count( $items ) ); ?>">

	<?php if ( empty( $items ) && $is_preview ) : ?>
		<p style="padding:2rem;opacity:0.5;text-align:center;">Add cards in the block settings &rarr;</p>
	<?php endif; ?>

	<div class="stacked-cards__track" data-js="stacked-cards-track">

	<?php foreach ( $items as $index => $item ) :
		$heading   = $item['heading']   ?? '';
		$content   = $item['content']   ?? '';
		$tab_label = $item['tab_label'] ?? '';
		$button    = $item['button']    ?? [];
		$image     = $item['image']     ?? [];
	?>
		<div
			class="stacked-cards__item"
			data-js="stacked-card"
			data-index="<?php echo esc_attr( $index ); ?>"
			style="--card-index: <?php echo esc_attr( $index ); ?>;"
		>
			<?php if ( $tab_label ) : ?>
				<?php if ( $index > 0 ) : ?>
					<button type="button" class="stacked-cards__tab" data-js="stacked-card-tab">
						<?php echo esc_html( $tab_label ); ?>
					</button>
				<?php else : ?>
					<div class="stacked-cards__tab" aria-hidden="true">
						<?php echo esc_html( $tab_label ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="stacked-cards__panel | grid" data-grid-layout="halves">

				<div class="stacked-cards__body | stack">

					<?php if ( $heading ) : ?>
						<h3 class="stacked-cards__heading"><?php echo esc_html( $heading ); ?></h3>
					<?php endif; ?>

					<?php if ( $content ) : ?>
						<div class="stacked-cards__content | prose">
							<?php echo wp_kses_post( $content ); ?>
						</div>
					<?php endif; ?>

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

				</div>

				<?php if ( ! empty( $image['url'] ) ) :
					$attachment_id = (int) ( $image['id'] ?? 0 );
					$alt           = ! empty( $image['alt'] ) ? $image['alt'] : '';
				?>
					<div class="stacked-cards__image">
						<?php if ( $attachment_id ) : ?>
							<?php echo wp_get_attachment_image(
								$attachment_id,
								'large',
								false,
								[
									'alt'     => $alt,
									'loading' => $index === 0 ? 'eager' : 'lazy',
									'class'   => 'stacked-cards__img',
								]
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php else : ?>
							<img
								src="<?php echo esc_url( $image['url'] ); ?>"
								alt="<?php echo esc_attr( $alt ); ?>"
								class="stacked-cards__img"
								loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
							>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div>
		</div>
	<?php endforeach; ?>

	</div><!-- /.stacked-cards__track -->

</div><!-- /.stacked-cards -->
