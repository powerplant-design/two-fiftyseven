<?php

/**
 * Returns the colour space for the current post/page.
 * Uses the ACF 'colour_space' field if set, otherwise falls back to 'neutral'.
 * JS then combines this with the OS dark/light preference to produce data-theme.
 */
function two_fiftyseven_get_colour_space(): string {
	if ( function_exists( 'get_field' ) ) {
		// Singular pages: read directly from the current post.
		if ( is_singular() ) {
			$space = get_field( 'colour_space' );
			if ( $space ) {
				return sanitize_key( $space );
			}
		}

		// Posts index (home.php): read from the Page assigned as the posts page.
		if ( is_home() ) {
			$posts_page_id = (int) get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$space = get_field( 'colour_space', $posts_page_id );
				if ( $space ) {
					return sanitize_key( $space );
				}
			}
		}

		// CPT archives: read colour space from ACF Options page.
		$cpt_archive_fields = [
			'organisation' => 'organisation_colour_space',
			'person'       => 'person_colour_space',
			'media_item'   => 'media_item_colour_space',
		];
		foreach ( $cpt_archive_fields as $post_type => $option_field ) {
			if ( is_post_type_archive( $post_type ) ) {
				$space = get_field( $option_field, 'option' );
				if ( $space ) {
					return sanitize_key( $space );
				}
			}
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
		'primary'   => __( 'Primary Navigation', 'two-fiftyseven' ),
		'secondary' => __( 'Secondary Navigation', 'two-fiftyseven' ),
	] );

	load_theme_textdomain( 'two-fiftyseven', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'two_fiftyseven_setup' );


/**
 * Open custom links in primary/secondary menus in a new tab.
 */
add_filter( 'nav_menu_link_attributes', function ( array $atts, $menu_item, $args ): array {
	$location = isset( $args->theme_location ) ? (string) $args->theme_location : '';

	if ( ! in_array( $location, [ 'primary', 'secondary' ], true ) ) {
		return $atts;
	}

	if ( empty( $menu_item->type ) || $menu_item->type !== 'custom' ) {
		return $atts;
	}

	$atts['target'] = '_blank';

	$existing_rel = isset( $atts['rel'] ) ? trim( (string) $atts['rel'] ) : '';
	$rel_tokens   = $existing_rel === '' ? [] : preg_split( '/\s+/', $existing_rel );
	$rel_tokens[] = 'noopener';
	$rel_tokens[] = 'noreferrer';
	$atts['rel']  = implode( ' ', array_unique( array_filter( $rel_tokens ) ) );

	return $atts;
}, 10, 3 );


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

	acf_register_block_type( [
		'name'            => 'testimonial',
		'title'           => __( '257 Testimonial', 'two-fiftyseven' ),
		'description'     => __( 'A large pull-quote with optional decorative background image, colour space override, and attribution line.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/testimonial/block.php',
		'category'        => 'text',
		'icon'            => 'format-quote',
		'keywords'        => [ 'testimonial', 'quote', 'pullquote', 'review' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => false,
		],
	] );

	acf_register_block_type( [
		'name'            => 'case-studies',
		'title'           => __( '257 Case Studies', 'two-fiftyseven' ),
		'description'     => __( 'Three selected Organisation cards with editable heading and archive CTA.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/case-studies/block.php',
		'category'        => 'layout',
		'icon'            => 'screenoptions',
		'keywords'        => [ 'case', 'studies', 'organisation', 'cards' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => false,
		],
	] );

	acf_register_block_type( [
		'name'            => 'cta-section',
		'title'           => __( '257 CTA Section', 'two-fiftyseven' ),
		'description'     => __( 'Wrapper-contained CTA with large heading, primary button link, and optional SVG background.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/cta-section/block.php',
		'category'        => 'layout',
		'icon'            => 'megaphone',
		'keywords'        => [ 'cta', 'call to action', 'button', 'banner' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => false,
		],
	] );

	acf_register_block_type( [
		'name'            => 'post-header',
		'title'           => __( '257 Post Header', 'two-fiftyseven' ),
		'description'     => __( 'Centered archive-style page heading (h1).', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/post-header/block.php',
		'category'        => 'text',
		'icon'            => 'heading',
		'keywords'        => [ 'post', 'header', 'title', 'heading', 'archive' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => false,
		],
	] );

	acf_register_block_type( [
		'name'            => 'faq',
		'title'           => __( '257 FAQ', 'two-fiftyseven' ),
		'description'     => __( 'Accordion-style FAQ panel with an optional eyebrow label and repeater of question/answer items.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/faq/block.php',
		'category'        => 'text',
		'icon'            => 'format-chat',
		'keywords'        => [ 'faq', 'accordion', 'questions', 'answers', 'help' ],
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
 * Ordering for the posts index and CPT archives.
 * People archive: alphabetical by title.
 * Everything else: newest first.
 */
add_action( 'pre_get_posts', function ( WP_Query $query ): void {
	if ( ! $query->is_main_query() ) {
		return;
	}
	if ( $query->is_post_type_archive( [ 'person', 'organisation' ] ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	} elseif ( $query->is_home() || $query->is_post_type_archive( 'media_item' ) ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
} );


/**
 * Override adjacent-post navigation to use alphabetical (title) order for
 * post types whose archives are ordered alphabetically.
 *
 * WordPress's get_previous_post() / get_next_post() always navigate by date.
 * These four filters swap the WHERE and ORDER BY clauses to use post_title
 * instead, so ← / → on a single person or organisation post follows A–Z order.
 */
function two_fiftyseven_adjacent_post_where_by_title( string $where, bool $in_same_term, $excluded_terms, string $taxonomy, WP_Post $post ): string {
	if ( ! in_array( $post->post_type, [ 'person', 'organisation' ], true ) ) {
		return $where;
	}
	global $wpdb;
	$op    = str_contains( current_filter(), 'previous' ) ? '<' : '>';
	$where = $wpdb->prepare(
		"WHERE p.post_title {$op} %s AND p.post_type = %s AND p.post_status = 'publish'",
		$post->post_title,
		$post->post_type
	);
	return $where;
}

function two_fiftyseven_adjacent_post_sort_by_title( string $order_by, WP_Post $post, string $order ): string {
	if ( ! in_array( $post->post_type, [ 'person', 'organisation' ], true ) ) {
		return $order_by;
	}
	return "ORDER BY p.post_title {$order} LIMIT 1";
}

add_filter( 'get_previous_post_where', 'two_fiftyseven_adjacent_post_where_by_title', 10, 5 );
add_filter( 'get_next_post_where',     'two_fiftyseven_adjacent_post_where_by_title', 10, 5 );
add_filter( 'get_previous_post_sort',  'two_fiftyseven_adjacent_post_sort_by_title',  10, 3 );
add_filter( 'get_next_post_sort',      'two_fiftyseven_adjacent_post_sort_by_title',  10, 3 );


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

/**
 * Serve individual posts under the /korero/ URL prefix.
 *
 * post_link — prepends /korero/ to generated post URLs so get_permalink()
 *             returns https://example.com/korero/post-slug/.
 *
 * init      — registers a rewrite rule (at top priority) so WordPress
 *             correctly routes /korero/post-slug/ back to that post.
 *
 * After adding these hooks, visit Settings → Permalinks and click Save to
 * flush the rewrite rule cache.
 */
add_filter( 'post_link', function ( string $url ): string {
	// Only rewrite pretty permalink URLs (not ?p=123 fallbacks).
	if ( str_contains( $url, '?p=' ) ) {
		return $url;
	}
	return str_replace(
		trailingslashit( home_url() ),
		trailingslashit( home_url() ) . 'korero/',
		$url
	);
} );

add_action( 'init', function (): void {
	add_rewrite_rule(
		'korero/([^/]+)/?$',
		'index.php?name=$matches[1]',
		'top'
	);
}, 20 );


/**
 * Register custom post types: Organisation, Person, Media Item.
 *
 * After adding these, visit Settings → Permalinks and click Save to flush
 * the rewrite rule cache so archive and single URLs resolve correctly.
 */
add_action( 'init', function (): void {
	register_post_type( 'organisation', [
		'labels' => [
			'name'          => __( 'Organisations', 'two-fiftyseven' ),
			'singular_name' => __( 'Organisation', 'two-fiftyseven' ),
			'add_new_item'  => __( 'Add New Organisation', 'two-fiftyseven' ),
			'edit_item'     => __( 'Edit Organisation', 'two-fiftyseven' ),
			'view_item'     => __( 'View Organisation', 'two-fiftyseven' ),
			'search_items'  => __( 'Search Organisations', 'two-fiftyseven' ),
			'not_found'     => __( 'No organisations found.', 'two-fiftyseven' ),
		],
		'public'       => true,
		'has_archive'  => 'organisations',
		'rewrite'      => [ 'slug' => 'organisation' ],
		'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-portfolio',
	] );

	register_post_type( 'person', [
		'labels' => [
			'name'          => __( 'People', 'two-fiftyseven' ),
			'singular_name' => __( 'Person', 'two-fiftyseven' ),
			'add_new_item'  => __( 'Add New Person', 'two-fiftyseven' ),
			'edit_item'     => __( 'Edit Person', 'two-fiftyseven' ),
			'view_item'     => __( 'View Person', 'two-fiftyseven' ),
			'search_items'  => __( 'Search People', 'two-fiftyseven' ),
			'not_found'     => __( 'No people found.', 'two-fiftyseven' ),
		],
		'public'       => true,
		'has_archive'  => 'people',
		'rewrite'      => [ 'slug' => 'people' ],
		'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-admin-users',
	] );

	register_post_type( 'media_item', [
		'labels' => [
			'name'          => __( 'Media', 'two-fiftyseven' ),
			'singular_name' => __( 'Media Item', 'two-fiftyseven' ),
			'add_new_item'  => __( 'Add New Media Item', 'two-fiftyseven' ),
			'edit_item'     => __( 'Edit Media Item', 'two-fiftyseven' ),
			'view_item'     => __( 'View Media Item', 'two-fiftyseven' ),
			'search_items'  => __( 'Search Media', 'two-fiftyseven' ),
			'not_found'     => __( 'No media items found.', 'two-fiftyseven' ),
		],
		'public'       => true,
		'has_archive'  => 'media',
		'rewrite'      => [ 'slug' => 'media' ],
		'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-format-video',
	] );
}, 0 );


/**
 * Register ACF Options page for per-archive colour space settings.
 */
add_action( 'acf/init', function (): void {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	acf_add_options_page( [
		'page_title'  => __( 'Archive Settings', 'two-fiftyseven' ),
		'menu_title'  => __( 'Archive Settings', 'two-fiftyseven' ),
		'menu_slug'   => 'archive-settings',
		'capability'  => 'manage_options',
		'parent_slug' => '',
		'autoload'    => false,
	] );
} );
