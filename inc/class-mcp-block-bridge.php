<?php
/**
 * MCP Block Field Bridge
 *
 * Syncs ACF block field values between post_content (block comment JSON)
 * and wp_postmeta, making all block fields discoverable and editable via
 * Royal MCP's wp_get_post_meta / wp_update_post_meta tools.
 *
 * Read path:
 *   WP Admin save → save_post → parse post_content → write _mcp_b_* postmeta
 *   MCP: wp_get_post_meta(post_id) → sees all _mcp_b_* keys
 *
 * Write path:
 *   MCP: wp_update_post_meta(post_id, "_mcp_b_page_hero_headline", "new")
 *   → added_post_meta / updated_post_meta hook → parse_blocks → modify → serialize_blocks → front-end updates
 */

class Two57_MCP_Block_Bridge {

	/**
	 * Prevents infinite loops between save_post ↔ postmeta hooks.
	 */
	private static $rebuilding = false;

	/**
	 * Postmeta key prefix for block fields.
	 */
	private const META_PREFIX = '_mcp_b_';

	/**
	 * Hook everything up.
	 */
	public static function init(): void {
		add_action( 'save_post', [ __CLASS__, 'sync_blocks_to_postmeta' ], 20, 1 );
		add_action( 'updated_post_meta', [ __CLASS__, 'rebuild_post_content' ], 10, 4 );
		add_action( 'added_post_meta', [ __CLASS__, 'rebuild_post_content' ], 10, 4 );
	}

	/**
	 * Parse post_content for ACF blocks, extract field values from block
	 * comment JSON, and write each to wp_postmeta with the _mcp_b_ prefix.
	 *
	 * Runs after ACF has saved its own post-level fields (priority 20).
	 */
	public static function sync_blocks_to_postmeta( int $post_id ): void {
		if ( self::$rebuilding ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );
		if ( empty( $blocks ) ) {
			return;
		}

		// Track which fields we set so we can clean up stale ones.
		$seen_keys = [];

		// Counter for ACF blocks only — used by rebuild_post_content
		// to locate the matching block when walking parse_blocks output.
		$acf_block_count = 0;

		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['attrs']['data'] ) ) {
				continue;
			}
			if ( empty( $block['blockName'] ) || 0 !== strpos( $block['blockName'], 'acf/' ) ) {
				continue;
			}

			foreach ( $block['attrs']['data'] as $key => $value ) {
				// ACF stores internal field key refs with a leading underscore.
				if ( 0 === strpos( $key, '_' ) ) {
					continue;
				}

				$meta_key = self::META_PREFIX . $key;

				// Store the field value.
				update_post_meta( $post_id, $meta_key, $value );

				// Store location so the reverse sync knows which block to update.
				update_post_meta( $post_id, "{$meta_key}__block_name", $block['blockName'] );
				update_post_meta( $post_id, "{$meta_key}__acf_block_index", $acf_block_count );

				$seen_keys[ $meta_key ] = true;
			}

			$acf_block_count++;
		}

		// Clean up stale postmeta for blocks that were removed.
		$existing_meta = get_post_meta( $post_id );
		if ( is_array( $existing_meta ) ) {
			foreach ( $existing_meta as $meta_key => $values ) {
				if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
					continue;
				}
				if ( false !== strpos( $meta_key, '__block_' ) || false !== strpos( $meta_key, '__acf_block_' ) ) {
					continue;
				}
				if ( ! isset( $seen_keys[ $meta_key ] ) ) {
					delete_post_meta( $post_id, $meta_key );
					delete_post_meta( $post_id, "{$meta_key}__block_name" );
					delete_post_meta( $post_id, "{$meta_key}__acf_block_index" );
				}
			}
		}
	}

	/**
	 * When MCP writes to a _mcp_b_* postmeta key via wp_update_post_meta,
	 * update the matching block's JSON inside post_content so the change
	 * appears on the front-end.
	 *
	 * Uses WordPress core parse_blocks() / serialize_blocks() rather than
	 * regex because block JSON can contain nested objects (link arrays,
	 * repeater values) that simple patterns can't match.
	 *
	 * Hooks into both updated_post_meta and added_post_meta to catch
	 * first-time writes (INSERT) and subsequent updates (UPDATE).
	 */
	public static function rebuild_post_content(
		int $meta_id,
		int $post_id,
		string $meta_key,
		$meta_value
	): void {
		if ( self::$rebuilding ) {
			return;
		}
		if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
			return;
		}
		// Location helper keys don't need content rebuild.
		if ( false !== strpos( $meta_key, '__block_' ) || false !== strpos( $meta_key, '__acf_block_' ) ) {
			return;
		}

		// Only rebuild if sync has stored location info for this field.
		$block_name = get_post_meta( $post_id, "{$meta_key}__block_name", true );
		if ( empty( $block_name ) ) {
			return;
		}
		$acf_index_raw = get_post_meta( $post_id, "{$meta_key}__acf_block_index", true );
		if ( '' === $acf_index_raw || null === $acf_index_raw || false === $acf_index_raw ) {
			return;
		}
		$acf_index = (int) $acf_index_raw;

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		$blocks = parse_blocks( $post->post_content );
		if ( empty( $blocks ) ) {
			return;
		}

		// Find the matching ACF block by counting acf/ blocks only,
		// since parse_blocks interleaves whitespace blocks at odd indices.
		$current_acf = 0;
		$found_index = null;

		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) || 0 !== strpos( $block['blockName'], 'acf/' ) ) {
				continue;
			}
			if ( $block['blockName'] === $block_name && $current_acf === $acf_index ) {
				$found_index = $index;
				break;
			}
			$current_acf++;
		}

		if ( null === $found_index || empty( $blocks[ $found_index ]['attrs']['data'] ) ) {
			return;
		}

		// Extract the bare field name (strip _mcp_b_ prefix).
		$field_name = substr( $meta_key, strlen( self::META_PREFIX ) );

		// Update the block's data.
		$blocks[ $found_index ]['attrs']['data'][ $field_name ] = $meta_value;

		// Prevent infinite recursion.
		self::$rebuilding = true;

		$new_content = serialize_blocks( $blocks );

		// WordPress 7.0 serialize_block_attributes() encodes < > & " as
		// \u003c \u003e \u0026 \u0022 for XSS protection.  wp_update_post()
		// calls wp_unslash() which strips the leading backslash, breaking
		// the unicode escapes.  We add an extra slash layer so the round-
		// trip preserves the escapes: wp_slash → wp_unslash → intact \uXXXX.
		$new_content = wp_slash( $new_content );

		wp_update_post( [
			'ID'           => $post_id,
			'post_content' => $new_content,
		] );

		self::$rebuilding = false;
	}
}
