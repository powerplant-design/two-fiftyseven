<?php
/**
 * MCP Event Helper — computes event_sort_date on every save_post,
 * covering both WP Admin saves (acf/save_post) and MCP API saves
 * (save_post, which acf/save_post does not fire for).
 */

// Hook into WordPress's save_post — fires on ALL saves including MCP.
add_action( 'save_post', function ( int $post_id ): void {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'event' ) {
		return;
	}

	$recurring = (bool) get_post_meta( $post_id, 'event_recurring', true );

	if ( $recurring ) {
		$day  = (string) ( get_post_meta( $post_id, 'event_day_of_week', true ) ?: '' );
		$sort = $day ? two57_next_weekday_ymd( $day ) : '99991231';
	} else {
		$sort = (string) ( get_post_meta( $post_id, 'event_date', true ) ?: '99991231' );
	}

	$time = (string) ( get_post_meta( $post_id, 'event_time_start', true ) ?: '' );
	if ( $time ) {
		$dt = \DateTime::createFromFormat( 'H:i', $time );
		if ( $dt ) {
			$sort .= $dt->format( 'Hi' );
		}
	}

	update_post_meta( $post_id, 'event_sort_date', sanitize_text_field( $sort ) );
}, 100 );
