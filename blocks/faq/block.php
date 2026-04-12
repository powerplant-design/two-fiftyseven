<?php
/**
 * 257 FAQ Block — ACF block render template.
 *
 * Renders an accordion-style FAQ panel with:
 * - An optional eyebrow label (defaults to "FAQs")
 * - A repeater of question/answer items that expand and collapse
 *
 * ACF fields:
 *   faq_heading — optional label for the tab above the panel (default "FAQs")
 *   faq_items   — repeater: question (text), answer (wysiwyg), answer_link (link)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$label = get_field( 'faq_heading' ) ?: __( 'FAQs', 'two-fiftyseven' );
$items = get_field( 'faq_items' ) ?: [];

// Unique prefix for answer IDs so multiple FAQ blocks on a page don't clash.
$id_prefix = 'faq-' . sanitize_html_class( $block['id'] ?? uniqid( 'faq-', true ) );
?>

<section class="faq | block">
	<div class="faq__inner">

		<div class="faq__label | text-monospace">
			<?php echo esc_html( $label ); ?>
		</div>

		<div class="faq__panel" data-js="faq">
			<?php if ( $items ) : ?>
				<ul class="faq__list" role="list">
					<?php foreach ( $items as $i => $item ) :
						$question  = ! empty( $item['question'] ) ? $item['question'] : '';
						$answer    = ! empty( $item['answer'] )   ? $item['answer']   : '';
						$answer_link = ! empty( $item['answer_link'] ) && is_array( $item['answer_link'] ) ? $item['answer_link'] : [];
						$link_url    = ! empty( $answer_link['url'] ) ? $answer_link['url'] : '';
						$link_title  = ! empty( $answer_link['title'] ) ? $answer_link['title'] : __( 'Learn more', 'two-fiftyseven' );
						$link_target = ! empty( $answer_link['target'] ) ? $answer_link['target'] : '';
						$answer_id = $id_prefix . '-answer-' . $i;
					?>
						<li class="faq__item">
							<h3 class="faq__question-wrap">
								<button
									class="faq__trigger"
									type="button"
									aria-expanded="false"
									aria-controls="<?php echo esc_attr( $answer_id ); ?>"
								>
									<span class="faq__question | text-m-l font-bold"><?php echo esc_html( $question ); ?></span>
									<span class="faq__icon" aria-hidden="true"></span>
								</button>
							</h3>

							<div
								class="faq__answer-wrapper"
								id="<?php echo esc_attr( $answer_id ); ?>"
								aria-hidden="true"
								role="region"
							>
								<div class="faq__answer-inner">
									<div class="faq__answer | prose text-m-l">
										<?php echo wp_kses_post( $answer ); ?>

										<?php if ( $link_url ) : ?>
											<a
												class="faq__answer-cta btn"
												data-type="secondary"
												href="<?php echo esc_url( $link_url ); ?>"
												<?php if ( $link_target ) : ?>target="<?php echo esc_attr( $link_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
											>
												<?php echo esc_html( $link_title ); ?>
											</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( $is_preview ) : ?>
				<p class="faq__preview-hint">Add FAQ items in the block settings &rarr;</p>
			<?php endif; ?>
		</div>

	</div>
</section>
