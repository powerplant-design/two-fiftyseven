<?php
/**
 * Template Part: Event Card
 *
 * Renders a single event card used in both the archive grid and the events-widget block.
 *
 * @param int  $args['post_id']      The event post ID. Required.
 * @param int  $args['card_index']   0-based index used for the stagger delay. Default 0.
 * @param bool $args['scroll_reveal'] When true, adds data-scroll for Locomotive Scroll. Default false.
 */

$post_id      = (int) ( $args['post_id'] ?? get_the_ID() );
$card_index   = (int) ( $args['card_index'] ?? 0 );
$scroll_reveal = ! empty( $args['scroll_reveal'] );
$delay_ms     = $card_index * 200;

if ( ! $post_id ) {
	return;
}

$title         = get_the_title( $post_id );
$permalink     = get_permalink( $post_id );
$excerpt       = get_the_excerpt( $post_id );
$badge         = function_exists( 'two57_format_event_badge' ) ? two57_format_event_badge( $post_id ) : '';
$location_type = function_exists( 'get_field' ) ? (string) ( get_field( 'event_location_type', $post_id ) ?: 'two_fiftyseven' ) : 'two_fiftyseven';
$location_name = function_exists( 'get_field' ) ? (string) ( get_field( 'event_location_name', $post_id ) ?: '' ) : '';
$has_thumb     = has_post_thumbnail( $post_id );
?>

<article class="event-card" <?php if ( $scroll_reveal ) : ?>data-scroll<?php endif; ?> style="--delay: <?php echo esc_attr( $delay_ms ); ?>ms">
	<a class="event-card__link" href="<?php echo esc_url( $permalink ); ?>">

		<div class="event-card__body | stack">

			<?php if ( $badge ) : ?>
				<span class="event-card__badge text-monospace"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>

			<!-- <?php if ( $location_type === 'offsite' && $location_name ) : ?>
				<span class="event-card__location text-monospace text-s"><?php echo esc_html( $location_name ); ?></span>
			<?php endif; ?> -->

			<?php if ( $title ) :
				$title_size = mb_strlen( $title ) > 34 ? 'text-l' : 'text-xl';
			?>
				<h2 class="event-card__title | <?php echo esc_attr( $title_size ); ?> text-balance line-clamp-2"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( $excerpt ) : ?>
				<p class="event-card__desc | text-m line-clamp-3"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>

		</div>

		<div class="event-card__image | frame">
			<?php if ( $has_thumb ) : ?>
				<?php echo get_the_post_thumbnail( $post_id, 'medium_large', [ 'loading' => 'lazy' ] ); ?>
			<?php endif; ?>
		</div>

	</a>
</article>
