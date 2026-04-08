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

		// Event archive: read colour space from ACF Options (default purple).
		if ( is_post_type_archive( 'event' ) ) {
			$space = function_exists( 'get_field' ) ? get_field( 'event_colour_space', 'option' ) : '';
			return sanitize_key( $space ?: 'purple' );
		}

		// Event singles are always purple.
		if ( is_singular( 'event' ) ) {
			return 'purple';
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

	$host = $_SERVER['HTTP_HOST'] ?? '';
	return str_contains( $host, 'localhost' ) || str_contains( $host, '.local' ) || str_contains( $host, '127.0.0.1' );
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

	// Expose admin-ajax URL and nonce for the Events AJAX module.
	wp_localize_script( 'two-fiftyseven-main', 'two57Ajax', [
		'url'      => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'two57_events' ),
		'cptNonce' => wp_create_nonce( 'two57_cpt_archive' ),
	] );
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

	acf_register_block_type( [
		'name'            => 'three-cards',
		'title'           => __( '257 Three Cards', 'two-fiftyseven' ),
		'description'     => __( 'Three linked cards with per-card background images and colour space settings, and an optional centred heading.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/three-cards/block.php',
		'category'        => 'layout',
		'icon'            => 'grid-view',
		'keywords'        => [ 'cards', 'grid', 'links', 'spaces', 'three' ],
		'mode'            => 'edit',
		'supports'        => [
			'innerBlocks' => false,
			'align'       => false,
		],
	] );

	acf_register_block_type( [
		'name'            => 'events-widget',
		'title'           => __( '257 Events Widget', 'two-fiftyseven' ),
		'description'     => __( 'Grid of upcoming event cards with optional manual selection and a “View more” CTA.', 'two-fiftyseven' ),
		'render_template' => get_template_directory() . '/blocks/events-widget/block.php',
		'category'        => 'layout',
		'icon'            => 'calendar-alt',
		'keywords'        => [ 'events', 'calendar', 'cards', 'upcoming' ],
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

	register_post_type( 'event', [
		'labels' => [
			'name'          => __( 'Events', 'two-fiftyseven' ),
			'singular_name' => __( 'Event', 'two-fiftyseven' ),
			'add_new_item'  => __( 'Add New Event', 'two-fiftyseven' ),
			'edit_item'     => __( 'Edit Event', 'two-fiftyseven' ),
			'view_item'     => __( 'View Event', 'two-fiftyseven' ),
			'search_items'  => __( 'Search Events', 'two-fiftyseven' ),
			'not_found'     => __( 'No events found.', 'two-fiftyseven' ),
		],
		'public'       => true,
		'has_archive'  => 'events',
		'rewrite'      => [ 'slug' => 'event' ],
		'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-calendar-alt',
	] );

	// ── CPT category taxonomies ───────────────────────────────────
	$cpt_taxonomies = [
		'person_category'       => [ 'object_type' => 'person',       'slug' => 'person-category',       'singular' => 'Person Category',       'plural' => 'Person Categories' ],
		'organisation_category' => [ 'object_type' => 'organisation', 'slug' => 'organisation-category', 'singular' => 'Organisation Category', 'plural' => 'Organisation Categories' ],
		'media_item_category'   => [ 'object_type' => 'media_item',   'slug' => 'media-category',        'singular' => 'Media Category',        'plural' => 'Media Categories' ],
	];

	foreach ( $cpt_taxonomies as $taxonomy => $config ) {
		register_taxonomy( $taxonomy, $config['object_type'], [
			'labels'            => [
				'name'          => __( $config['plural'],   'two-fiftyseven' ),
				'singular_name' => __( $config['singular'], 'two-fiftyseven' ),
				'add_new_item'  => sprintf( __( 'Add New %s', 'two-fiftyseven' ), $config['singular'] ),
				'edit_item'     => sprintf( __( 'Edit %s', 'two-fiftyseven' ), $config['singular'] ),
				'search_items'  => sprintf( __( 'Search %s', 'two-fiftyseven' ), $config['plural'] ),
			],
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => $config['slug'] ],
		] );
	}
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


/**
 * ============================================================
 * Events — Helper: compute next weekday date
 * ============================================================
 *
 * @param string $day_abbr  3-letter abbreviation: MON TUE WED THU FRI SAT SUN.
 * @return string           Ymd-formatted date string, e.g. '20260421'.
 */
function two57_next_weekday_ymd( string $day_abbr ): string {
	$map = [
		'MON' => 'Monday',
		'TUE' => 'Tuesday',
		'WED' => 'Wednesday',
		'THU' => 'Thursday',
		'FRI' => 'Friday',
		'SAT' => 'Saturday',
		'SUN' => 'Sunday',
	];
	$day_name = $map[ strtoupper( trim( $day_abbr ) ) ] ?? 'Monday';

	if ( strtolower( date( 'l' ) ) === strtolower( $day_name ) ) {
		return date( 'Ymd' );
	}

	return date( 'Ymd', (int) strtotime( 'next ' . $day_name ) );
}


/**
 * Events — Helper: format event badge text.
 *
 * Recurring:  "MON / 9AM–10AM"  (+ "@ LOCATION" if offsite)
 * One-off:    "TUE 21 APR / 5.30PM"
 *
 * @param int $post_id
 * @return string
 */
function two57_format_event_badge( int $post_id ): string {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$recurring     = (bool) get_field( 'event_recurring', $post_id );
	$time_start    = (string) ( get_field( 'event_time_start', $post_id ) ?: '' );
	$time_end      = (string) ( get_field( 'event_time_end', $post_id ) ?: '' );
	$location_type = (string) ( get_field( 'event_location_type', $post_id ) ?: 'two_fiftyseven' );
	$location_name = (string) ( get_field( 'event_location_name', $post_id ) ?: '' );

	// Format H:i → "9.30AM", "12:00" → "12PM", "9:00" → "9AM".
	$fmt = static function ( string $t ): string {
		if ( ! $t ) {
			return '';
		}
		$dt = \DateTime::createFromFormat( 'H:i', $t );
		if ( ! $dt ) {
			return $t;
		}
		$h = (int) $dt->format( 'g' );
		$m = $dt->format( 'i' );
		$a = $dt->format( 'A' );
		return $m === '00' ? "{$h}{$a}" : "{$h}.{$m}{$a}";
	};

	$time_str = $fmt( $time_start );
	if ( $time_end ) {
		$time_str .= '–' . $fmt( $time_end );
	}

	$badge = '';

	if ( $recurring ) {
		$day   = strtoupper( (string) ( get_field( 'event_day_of_week', $post_id ) ?: '' ) );
		$badge = $day;
		if ( $time_str ) {
			$badge .= ' / ' . $time_str;
		}
	} else {
		$raw_date = (string) ( get_field( 'event_date', $post_id ) ?: '' );
		if ( $raw_date ) {
			$dt = \DateTime::createFromFormat( 'Ymd', $raw_date );
			if ( $dt ) {
				// e.g. "TUE 21 APR" — 3-letter day + day number + 3-letter month.
				$badge = strtoupper( $dt->format( 'D j M' ) );
			}
		}
		if ( $time_str ) {
			$badge .= ( $badge ? ' / ' : '' ) . $time_str;
		}
	}

	if ( $location_type === 'offsite' && $location_name ) {
		$badge .= ' @ ' . strtoupper( $location_name );
	}

	return $badge;
}


/**
 * Events — Helper: build WP_Query args for upcoming or past events.
 *
 * @param string $tab    'upcoming' or 'past'.
 * @param int    $paged  Pagination page number.
 * @return array
 */
function two57_get_event_query_args( string $tab, int $paged = 1 ): array {
	$common = [
		'post_type'      => 'event',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'paged'          => $paged,
		'meta_key'       => 'event_sort_date',
		'orderby'        => 'meta_value',
	];

	if ( $tab === 'past' ) {
		return array_merge( $common, [
			'order'      => 'DESC',
			'meta_query' => [
				[
					'key'   => 'event_has_passed',
					'value' => '1',
				],
			],
		] );
	}

	// Upcoming: exclude passed events; include those with no has_passed meta (new posts).
	return array_merge( $common, [
		'order'      => 'ASC',
		'meta_query' => [
			'relation' => 'OR',
			[
				'key'     => 'event_has_passed',
				'value'   => '1',
				'compare' => '!=',
			],
			[
				'key'     => 'event_has_passed',
				'compare' => 'NOT EXISTS',
			],
		],
	] );
}


/**
 * Events — Save post hook: compute and store event_sort_date.
 *
 * For recurring events: next occurrence of the chosen weekday (Ymd).
 * For one-off events:   the stored event_date (Ymd).
 *
 * This allows WP_Query to orderby meta_value on event_sort_date.
 */
add_action( 'acf/save_post', function ( $post_id ): void {
	if ( get_post_type( $post_id ) !== 'event' ) {
		return;
	}
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	$recurring = (bool) get_field( 'event_recurring', $post_id );

	if ( $recurring ) {
		$day_abbr  = (string) ( get_field( 'event_day_of_week', $post_id ) ?: '' );
		$sort_date = $day_abbr ? two57_next_weekday_ymd( $day_abbr ) : '99991231';
	} else {
		$sort_date = (string) ( get_field( 'event_date', $post_id ) ?: '99991231' );
	}

	update_post_meta( $post_id, 'event_sort_date', sanitize_text_field( $sort_date ) );
}, 20 );


/**
 * Events — AJAX handler: return event card grid HTML for tab + page.
 *
 * Expects POST: action, nonce, tab (upcoming|past), paged (int).
 * Returns JSON: { success: true, data: { html, totalPages, currentPage } }.
 */
function two57_events_ajax(): void {
	check_ajax_referer( 'two57_events', 'nonce' );

	$tab   = isset( $_POST['tab'] ) && $_POST['tab'] === 'past' ? 'past' : 'upcoming';
	$paged = max( 1, (int) ( $_POST['paged'] ?? 1 ) );

	$query = new WP_Query( two57_get_event_query_args( $tab, $paged ) );

	ob_start();
	get_template_part( 'template-parts/event-card-grid', null, [
		'query'        => $query,
		'current_page' => $paged,
		'total_pages'  => $query->max_num_pages,
	] );
	$html = ob_get_clean();

	wp_reset_postdata();

	wp_send_json_success( [
		'html'        => (string) $html,
		'totalPages'  => (int) $query->max_num_pages,
		'currentPage' => $paged,
	] );
}
add_action( 'wp_ajax_two57_events',        'two57_events_ajax' );
add_action( 'wp_ajax_nopriv_two57_events', 'two57_events_ajax' );


/**
 * ============================================================
 * CPT Archives — Helper: build WP_Query args with optional category filter.
 *
 * @param string $post_type  Whitelisted post type: post|person|organisation|media_item.
 * @param string $term_slug  Taxonomy term slug to filter by. Empty string = all terms.
 * @param int    $paged      Pagination page number.
 * @return array             WP_Query args array.
 * ============================================================
 */
function two57_get_cpt_query_args( string $post_type, string $term_slug, int $paged = 1 ): array {
	$allowed_types = [ 'post', 'person', 'organisation', 'media_item' ];
	if ( ! in_array( $post_type, $allowed_types, true ) ) {
		$post_type = 'post';
	}

	$taxonomy_map = [
		'post'         => 'category',
		'person'       => 'person_category',
		'organisation' => 'organisation_category',
		'media_item'   => 'media_item_category',
	];

	$args = [
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'paged'          => $paged,
		'orderby'        => 'date',
		'order'          => 'DESC',
	];

	$taxonomy  = $taxonomy_map[ $post_type ] ?? '';
	$term_slug = sanitize_text_field( $term_slug );

	if ( $taxonomy && $term_slug ) {
		$args['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $term_slug,
			],
		];
	}

	return $args;
}


/**
 * ============================================================
 * CPT Archives — AJAX handler: return card grid HTML.
 *
 * Expects POST: action, nonce, post_type, taxonomy, term (slug), paged.
 * Returns JSON: { success: true, data: { html, totalPages, currentPage } }.
 * ============================================================
 */
function two57_cpt_archive_ajax(): void {
	check_ajax_referer( 'two57_cpt_archive', 'nonce' );

	$allowed_types = [ 'post', 'person', 'organisation', 'media_item' ];
	$post_type = isset( $_POST['post_type'] ) && in_array( $_POST['post_type'], $allowed_types, true )
		? $_POST['post_type']
		: 'post';

	$term_slug = sanitize_text_field( $_POST['term'] ?? '' );
	$paged     = max( 1, (int) ( $_POST['paged'] ?? 1 ) );

	$query = new WP_Query( two57_get_cpt_query_args( $post_type, $term_slug, $paged ) );

	ob_start();
	get_template_part( 'template-parts/cpt-card-grid', null, [
		'query'        => $query,
		'post_type'    => $post_type,
		'current_page' => $paged,
		'total_pages'  => $query->max_num_pages,
	] );
	$html = ob_get_clean();

	wp_reset_postdata();

	wp_send_json_success( [
		'html'        => (string) $html,
		'totalPages'  => (int) $query->max_num_pages,
		'currentPage' => $paged,
	] );
}
add_action( 'wp_ajax_two57_cpt_archive',        'two57_cpt_archive_ajax' );
add_action( 'wp_ajax_nopriv_two57_cpt_archive', 'two57_cpt_archive_ajax' );
