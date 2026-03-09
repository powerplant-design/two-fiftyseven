<?php
/**
 * Colour Section — ACF block render template.
 *
 * Outputs a <div> wrapper with data-color-space and (optionally) data-theme
 * on it, creating an isolated colour context for all inner blocks.
 *
 * Slots into the design-token system:
 *   data-color-space  — the palette (neutral / maroon / forest / purple)
 *   data-color-mode   — when set, JS will lock the mode and ignore OS preference
 *   data-theme        — written directly for forced modes (no-JS fallback)
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML.
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$colour_space = get_field( 'colour_space' ) ?: 'neutral';
$mode         = get_field( 'mode' ) ?: 'auto';

// Sanitise values against the allowed sets.
$allowed_spaces = [ 'neutral', 'maroon', 'forest', 'purple' ];
$allowed_modes  = [ 'auto', 'light', 'dark' ];

if ( ! in_array( $colour_space, $allowed_spaces, true ) ) {
	$colour_space = 'neutral';
}
if ( ! in_array( $mode, $allowed_modes, true ) ) {
	$mode = 'auto';
}

// Build HTML attributes.
$attrs = [
	'class'            => 'colour-section',
	'data-color-space' => $colour_space,
];

if ( 'auto' !== $mode ) {
	// Tell JS to lock this element to the chosen mode (ignores OS preference).
	$attrs['data-color-mode'] = $mode;
	// Write data-theme directly as a no-JS fallback.
	$attrs['data-theme'] = $colour_space . '-' . $mode;
}

// Richer preview label in the block editor.
if ( $is_preview ) {
	$label        = esc_html( ucfirst( $colour_space ) . ( 'auto' !== $mode ? ' / ' . ucfirst( $mode ) : ' / Auto' ) );
	$attrs['data-preview-label'] = $label;
}

// Render the attribute string.
$attr_string = '';
foreach ( $attrs as $key => $value ) {
	$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
}
?>
<div<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner blocks HTML. ?>
</div>
