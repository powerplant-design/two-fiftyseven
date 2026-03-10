<?php

/**
 * Returns the colour space for the current post/page.
 * Uses the ACF 'colour_space' field if set, otherwise falls back to 'neutral'.
 * JS then combines this with the OS dark/light preference to produce data-theme.
 */
function two_fiftyseven_get_colour_space(): string {
	if ( is_singular() && function_exists( 'get_field' ) ) {
		$space = get_field( 'colour_space' );
		if ( $space ) {
			return sanitize_key( $space );
		}
	}

	return 'neutral';
}


/**
 * Theme setup
 */
function two_fiftyseven_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'align-wide' );

	register_nav_menus( [
		'primary' => __( 'Primary Navigation', 'two-fiftyseven' ),
	] );

	load_theme_textdomain( 'two-fiftyseven', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'two_fiftyseven_setup' );


/**
 * Returns true when running in a local environment.
 * In local: load assets from the Vite dev server.
 * Everywhere else: load from the built manifest in assets/dist/.
 *
 * To override locally (e.g. to test the production build), add:
 *   define( 'VITE_HMR', false );
 * to wp-config.php.
 */
function is_vite_hmr_available(): bool {
	// Allow explicit override via wp-config.php.
	if ( defined( 'VITE_HMR' ) ) {
		return (bool) VITE_HMR;
	}

	return defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local';
}


/**
 * Enqueue theme scripts and styles.
 * In local dev with Vite HMR running: load from the dev server.
 * Otherwise: load from the built manifest in assets/dist/.
 */
function two_fiftyseven_enqueue_assets(): void {
	if ( is_vite_hmr_available() ) {
		// Vite dev server — inject HMR client then the entry module.
		// Scripts are loaded from the Mac host; localhost resolves correctly in the browser.
		wp_enqueue_script( 'vite-client', 'http://localhost:5173/@vite/client', [], null, false );
		wp_enqueue_script( 'two-fiftyseven-main', 'http://localhost:5173/assets/js/main.js', [], null, false );
	} else {
		// Production / staging — read the generated manifest.
		$manifest_path = get_template_directory() . '/assets/dist/.vite/manifest.json';

		if ( ! file_exists( $manifest_path ) ) {
			return;
		}

		$manifest = json_decode( file_get_contents( $manifest_path ), true );
		$entry    = $manifest['assets/js/main.js'] ?? null;

		if ( ! $entry ) {
			return;
		}

		$base_url = get_template_directory_uri() . '/assets/dist/';

		wp_enqueue_script(
			'two-fiftyseven-main',
			$base_url . $entry['file'],
			[],
			null,
			true
		);

		foreach ( $entry['css'] ?? [] as $css_file ) {
			wp_enqueue_style( 'two-fiftyseven-style', $base_url . $css_file, [], null );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'two_fiftyseven_enqueue_assets' );


/**
 * ACF JSON — save and load field groups as JSON files in the theme.
 * Edit field groups in wp-admin and commit the generated files in acf-json/.
 */
add_filter( 'acf/settings/save_json', function (): string {
	return get_template_directory() . '/acf-json';
} );

add_filter( 'acf/settings/load_json', function ( array $paths ): array {
	$paths[] = get_template_directory() . '/acf-json';
	return $paths;
} );


/**
 * Add type="module" to Vite scripts so ES modules load correctly.
 */
add_filter( 'script_loader_tag', function ( string $tag, string $handle ): string {
	$module_handles = [ 'vite-client', 'two-fiftyseven-main' ];

	if ( in_array( $handle, $module_handles, true ) ) {
		// Replace the standard <script src="..."> with a module version.
		return preg_replace( '/(<script\s)/i', '<script type="module" ', $tag );
	}

	return $tag;
}, 10, 2 );


/**
 * Register ACF Gutenberg blocks.
 */
add_action( 'acf/init', function (): void {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type( [
		'name'            => 'colour-section',
		'title'           => __( 'Colour Section', 'two-fiftyseven' ),
		'description'     => __( 'A section wrapper with its own colour space and optional forced light/dark mode.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/colour-section/block.php',
		'category'        => 'layout',
		'icon'            => 'color-picker',
		'keywords'        => [ 'colour', 'color', 'theme', 'section', 'block' ],
		'supports'        => [
			'innerBlocks'     => true,
			'jsx'             => true,
			'align'           => false,
			'mode'            => false,
		],
	] );

	acf_register_block_type( [
		'name'            => 'hero-home',
		'title'           => __( '257 Hero Home', 'two-fiftyseven' ),
		'description'     => __( 'Full-viewport hero with background image, display headline, 3 linked cards, and an icon marquee.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/hero-home/block.php',
		'category'        => 'layout',
		'icon'            => 'cover-image',
		'keywords'        => [ 'hero', 'home', 'banner', 'cards' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => [ 'full' ],
		],
	] );

	acf_register_block_type( [
		'name'            => 'hero-page',
		'title'           => __( '257 Hero Page', 'two-fiftyseven' ),
		'description'     => __( 'Page-level hero with background image, headline, subtitle, and a full-width icon marquee.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/hero-page/block.php',
		'category'        => 'layout',
		'icon'            => 'cover-image',
		'keywords'        => [ 'hero', 'page', 'banner', 'marquee' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => [ 'full' ],
		],
	] );

	acf_register_block_type( [
		'name'            => 'stacked-cards',
		'title'           => __( '257 Stacked Cards', 'two-fiftyseven' ),
		'description'     => __( 'A series of content cards that reveal on scroll, each with a tab label, heading, rich content, optional CTA, and image.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/stacked-cards/block.php',
		'category'        => 'layout',
		'icon'            => 'media-document',
		'keywords'        => [ 'cards', 'tabs', 'stacked', 'scroll', 'reveal' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => false,
		],
	] );
} );


/**
 * Register custom button block styles so "Text link" appears in the editor
 * style panel alongside the native Fill and Outline options.
 */
add_action( 'init', function (): void {
	register_block_style( 'core/button', [
		'name'  => 'text',
		'label' => __( 'Text link', 'two-fiftyseven' ),
	] );
} );


/**
 * Returns inline SVG markup for a media attachment.
 *
 * Requires the Safe SVG plugin (wordpress.org/plugins/safe-svg) to be active
 * for SVG uploads to be permitted and sanitized.
 *
 * All fill/stroke values (except "none" and "currentColor") are replaced with
 * currentColor so the SVG inherits its colour from the CSS `color` property,
 * allowing it to respond to the colour token system (light/dark/colour-space).
 *
 * @param int $attachment_id WordPress attachment ID.
 * @return string Inline SVG markup, or '' if not an SVG attachment.
 */
function two_fiftyseven_get_inline_svg( int $attachment_id ): string {
	$file_path = get_attached_file( $attachment_id );

	if ( ! $file_path || ! file_exists( $file_path ) ) {
		return '';
	}
	if ( 'svg' !== strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) ) ) {
		return '';
	}

	$content = file_get_contents( $file_path );
	if ( ! $content ) {
		return '';
	}

	// Replace hardcoded fill/stroke values (preserving "none" and "currentColor") with currentColor.
	$content = preg_replace( '/\bfill="(?!none|currentColor)[^"]*"/i',   'fill="currentColor"',   $content );
	$content = preg_replace( '/\bstroke="(?!none|currentColor)[^"]*"/i', 'stroke="currentColor"', $content );

	// Mark as decorative — marquee icons carry no semantic meaning.
	$content = preg_replace( '/<svg\b/i', '<svg aria-hidden="true" focusable="false"', $content, 1 );

	return $content;
}
