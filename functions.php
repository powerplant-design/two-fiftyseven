<?php

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
 * Check whether the Vite HMR dev server is currently running.
 * Only ever returns true in a local environment.
 * Result is cached for the lifetime of the PHP process (one page load).
 */
function is_vite_hmr_available(): bool {
	static $available = null;

	if ( $available !== null ) {
		return $available;
	}

	// Only check on local environments.
	if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || WP_ENVIRONMENT_TYPE !== 'local' ) {
		$available = false;
		return $available;
	}

	$ctx       = stream_context_create( [ 'http' => [ 'timeout' => 0.3 ] ] );
	$response  = @file_get_contents( 'http://localhost:5173/@vite/client', false, $ctx ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$available = ( $response !== false );

	return $available;
}


/**
 * Enqueue theme scripts and styles.
 * In local dev with Vite HMR running: load from the dev server.
 * Otherwise: load from the built manifest in assets/dist/.
 */
function two_fiftyseven_enqueue_assets(): void {
	if ( is_vite_hmr_available() ) {
		// Vite dev server — inject HMR client then the entry module.
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
