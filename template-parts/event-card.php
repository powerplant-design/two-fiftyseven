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
$badge_color   = ! empty( $args['badge_color'] ) ? $args['badge_color'] : '';
$delay_ms     = $card_index * 160;

if ( ! $post_id ) {
	return;
}

$title         = get_the_title( $post_id );
$permalink     = get_permalink( $post_id );
$excerpt       = get_the_excerpt( $post_id );
$badge         = function_exists( 'two57_format_event_badge' ) ? two57_format_event_badge( $post_id ) : '';
$location_type = function_exists( 'get_field' ) ? (string) ( get_field( 'event_location_type', $post_id ) ?: 'two_fiftyseven' ) : 'two_fiftyseven';
$location_name = function_exists( 'get_field' ) ? (string) ( get_field( 'event_location_name', $post_id ) ?: '' ) : '';

$show_cat_badges = ! empty( $args['show_cat_badges'] );
$cat_names       = [];
if ( $show_cat_badges ) {
	$cat_terms = get_the_terms( $post_id, 'event_category' );
	$cat_names = ( $cat_terms && ! is_wp_error( $cat_terms ) )
		? array_map( fn( $t ) => $t->name, $cat_terms )
		: [];
}
$has_thumb     = has_post_thumbnail( $post_id );
?>

<article class="event-card" <?php if ( $scroll_reveal ) : ?>data-scroll data-scroll-repeat<?php endif; ?> style="--delay: <?php echo esc_attr( $delay_ms ); ?>ms">
	<a class="event-card__link" href="<?php echo esc_url( $permalink ); ?>">

		<div class="event-card__body | stack">

			<?php if ( $badge || $cat_names ) : ?>
				<div class="cluster badge-cluster">
					<?php if ( $badge ) : ?>
						<span class="badge event-card__badge" data-size="medium"<?php if ( $badge_color ) : ?> data-color="<?php echo esc_attr( $badge_color ); ?>"<?php endif; ?>><?php echo esc_html( $badge ); ?></span>
					<?php endif; ?>
					<?php foreach ( $cat_names as $cat_name ) : ?>
						<span class="badge" data-size="medium" data-color="forest"><?php echo esc_html( $cat_name ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

            <div class="event-card__copy">

                <?php if ( $title ) :
				$title_size = mb_strlen( $title ) > 34 ? 'text-l' : 'text-xl';
                ?>
				<h2 class="event-card__title | <?php echo esc_attr( $title_size ); ?> text-balance line-clamp-2"><?php echo esc_html( $title ); ?></h2>
                <?php endif; ?>
                
                <?php if ( $excerpt ) : ?>
                    <p class="event-card__desc | text-m line-clamp-3"><?php echo esc_html( $excerpt ); ?></p>
                    <?php endif; ?>
            </div>

		</div>

		<?php if ( $has_thumb ) : ?>
		<div class="event-card__image | frame">
			<?php echo get_the_post_thumbnail( $post_id, 'large', [ 'loading' => 'lazy' ] ); ?>
		</div>
		<?php endif; ?>

	</a>
</article>
