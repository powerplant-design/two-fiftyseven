<?php
/**
 * Template Part: Post Sidebar Brand Logo
 *
 * Renders an inline SVG brand logo in the sidebar. Used by CPT single templates.
 *
 * @param string $args['logo_svg'] Sanitized inline SVG from two_fiftyseven_get_inline_svg().
 */

$logo_svg = $args['logo_svg'] ?? '';

if ( ! $logo_svg ) {
	return;
}
?>

<span class="post-layout__logo" aria-hidden="true">
	<?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — sanitized by two_fiftyseven_get_inline_svg() ?>
</span>
