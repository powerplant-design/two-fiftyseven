<?php
/**
 * 257 Text Block — ACF block render template.
 *
 * A large H2 heading with a divider, followed by a 2- or 3-column grid of
 * text items. Each item has an optional H3/H4 subheading, a wysiwyg body,
 * and a configurable text size. Items align horizontally via CSS subgrid so
 * rows stay level even when headings wrap.
 *
 * ACF fields:
 *   text_block_heading      — optional large H2 above the grid
 *   text_block_layout       — select: 'two-col' (default) or 'three-col'
 *   text_block_dark          — bool: force dark colour mode on the block
 *   text_block_colour_space  — select: optional colour space override
 *   text_block_items         — repeater:
 *     heading_level          — select: 'none' / 'h3' / 'h4'
 *     heading                — text (shown when heading_level != none)
 *     body                   — wysiwyg
 *     text_size              — select: 'large' (default) / 'medium'
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$heading       = get_field( 'text_block_heading' );
$intro         = get_field( 'text_block_intro' );
$layout        = get_field( 'text_block_layout' ) ?: 'two-col';
$layout        = in_array( $layout, [ 'two-col', 'three-col' ], true ) ? $layout : 'two-col';
$dark_mode     = (bool) get_field( 'text_block_dark' );
$colour_space  = get_field( 'text_block_colour_space' ) ?: null;
$heading_size  = get_field( 'text_block_heading_size' ) ?: 'heading-m';
$heading_size  = in_array( $heading_size, [ 'heading-l', 'heading-m' ], true ) ? $heading_size : 'heading-m';
$text_size     = get_field( 'text_block_text_size' ) ?: 'large';
$text_size     = 'medium' === $text_size ? 'text-m' : 'text-l';
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
if ( $colour_space && ! in_array( $colour_space, $allowed_spaces, true ) ) {
	$colour_space = null;
}
$items            = get_field( 'text_block_items' ) ?: [];
$primary_button   = get_field( 'text_block_primary_button' ) ?: [];
$secondary_button = get_field( 'text_block_secondary_button' ) ?: [];

// When dark mode is forced without an explicit colour space, inherit the page's
// colour space so only the light/dark axis is overridden, not the palette.
// Must pass $post_id explicitly — inside a block render, get_field() without an
// ID reads from the block context, not the post.
$page_colour_space = get_field( 'colour_space', $post_id ) ?: 'neutral';
$effective_space   = $colour_space ?: ( $dark_mode ? $page_colour_space : null );

// Build section attributes.
$attrs = [ 'class' => 'text-block | block' ];

if ( $effective_space ) {
	$attrs['data-color-space'] = $effective_space;
}
if ( $dark_mode ) {
	$attrs['data-color-mode'] = 'dark';
	$attrs['data-theme']      = ( $effective_space ?: 'neutral' ) . '-dark';
}

$attr_string = '';
foreach ( $attrs as $key => $value ) {
	$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
}
$attr_string .= ' data-block="full"';
?>
<section<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<div class="text-block__inner | wrapper">

		<?php if ( $heading || $intro ) : ?>
			<div class="text-block__header" data-scroll data-scroll-repeat>
				<?php if ( $heading ) : ?>
					<h2 class="text-block__heading | text-3xl measure-narrow text-balance"><?php echo esc_html( $heading ); ?></h2>
				<?php endif; ?>
				<?php if ( $intro ) : ?>
					<p class="text-block__intro | text-xl"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<p class="text-block__preview-hint" style="opacity:0.5;text-align:center;padding:1rem;">Add a heading in the block settings.</p>
		<?php endif; ?>

		<?php if ( ! empty( $items ) ) : ?>
			<ul
				class="text-block__items | text-block__items--<?php echo esc_attr( $layout ); ?>"
				data-scroll
				data-scroll-repeat
				role="list"
			>
				<?php foreach ( $items as $index => $item ) :
					$subheading = $item['heading'] ?? '';
					$body       = $item['body'] ?? '';
					$delay      = ( $index * 100 ) . 'ms';
				?>
					<li class="text-block__item | stack" style="--delay: <?php echo esc_attr( $delay ); ?>">

                    <?php if ( $subheading ) : ?>
						<h3 class="text-block__subheading text-block__subheading--<?php echo esc_attr( $heading_size ); ?>">
							<?php echo esc_html( $subheading ); ?>
						</h3>
						<?php endif; ?>

						<?php if ( $body ) : ?>
							<div class="text-block__body | stack <?php echo esc_attr( $text_size ); ?>">
								<?php echo wp_kses_post( $body ); ?>
							</div>
						<?php endif; ?>

					</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add items in the block settings &rarr;</p>
		<?php endif; ?>

		<?php if ( ! empty( $primary_button['url'] ) || ! empty( $secondary_button['url'] ) ) : ?>
			<div class="cluster cluster__buttons text-block__buttons" data-scroll data-scroll-repeat>
				<?php if ( ! empty( $primary_button['url'] ) ) :
					$p_url    = $primary_button['url'];
					$p_title  = $primary_button['title'] ?: $p_url;
					$p_target = ! empty( $primary_button['target'] ) ? $primary_button['target'] : '';
				?>
					<a
						href="<?php echo esc_url( $p_url ); ?>"
						class="btn"
						data-type="primary"
						<?php if ( $p_target ) : ?>target="<?php echo esc_attr( $p_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
					><?php echo esc_html( $p_title ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $secondary_button['url'] ) ) :
					$s_url    = $secondary_button['url'];
					$s_title  = $secondary_button['title'] ?: $s_url;
					$s_target = ! empty( $secondary_button['target'] ) ? $secondary_button['target'] : '';
				?>
					<a
						href="<?php echo esc_url( $s_url ); ?>"
						class="btn"
						data-type="secondary"
						<?php if ( $s_target ) : ?>target="<?php echo esc_attr( $s_target ); ?>" rel="noopener noreferrer"<?php endif; ?>
					><?php echo esc_html( $s_title ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div>
</section>
