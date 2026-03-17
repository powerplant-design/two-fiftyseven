<?php
/**
 * Decorative background SVG for .page-layout.
 * Inlined so it inherits CSS colour via currentColor / fill: currentColor.
 */
$svg_path = get_template_directory() . '/assets/images/257-brain-coral.svg';
if ( ! file_exists( $svg_path ) ) {
	return;
}
?>
<div class="page-layout__bg" aria-hidden="true">
	<?php echo file_get_contents( $svg_path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — static theme file ?>
</div>
