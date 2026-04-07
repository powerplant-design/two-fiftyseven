<?php
/**
 * Template Part: Event Sidebar Meta
 *
 * Displays event-specific meta in the post sidebar:
 * date/time badge, add-to-calendar link, location (if offsite), cost, recurring indicator.
 */

if ( ! function_exists( 'get_field' ) ) {
	return;
}

$post_id       = get_the_ID();
$recurring     = (bool) get_field( 'event_recurring', $post_id );
$has_passed    = (bool) get_field( 'event_has_passed', $post_id );
$time_start    = (string) ( get_field( 'event_time_start', $post_id ) ?: '' );
$time_end      = (string) ( get_field( 'event_time_end', $post_id ) ?: '' );
$add_to_cal    = get_field( 'event_add_to_calendar', $post_id ) ?: [];
$location_type = (string) ( get_field( 'event_location_type', $post_id ) ?: 'two_fiftyseven' );
$location_name = (string) ( get_field( 'event_location_name', $post_id ) ?: '' );
$location_link = get_field( 'event_location_map_link', $post_id ) ?: [];
$cost_type     = (string) ( get_field( 'event_cost_type', $post_id ) ?: 'free' );
$cost_price    = (string) ( get_field( 'event_cost_price', $post_id ) ?: '' );
$raw_date      = (string) ( get_field( 'event_date', $post_id ) ?: '' );

// Format times: H:i → "9.30AM", "12:00" → "12PM".
$fmt_time = static function ( string $t ): string {
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

$time_display = $fmt_time( $time_start );
if ( $time_end ) {
	$time_display .= '&ndash;' . $fmt_time( $time_end );
}

// Build human-readable date display.
$date_display = '';
if ( $recurring ) {
	$day_abbr = strtoupper( (string) ( get_field( 'event_day_of_week', $post_id ) ?: '' ) );
	if ( $day_abbr ) {
		// Full day name for the sidebar (e.g. "Every Tuesday").
		$day_map = [
			'MON' => 'Monday', 'TUE' => 'Tuesday', 'WED' => 'Wednesday',
			'THU' => 'Thursday', 'FRI' => 'Friday', 'SAT' => 'Saturday', 'SUN' => 'Sunday',
		];
		$date_display = 'Every ' . ( $day_map[ $day_abbr ] ?? $day_abbr );
	}
} elseif ( $raw_date ) {
	$dt = \DateTime::createFromFormat( 'Ymd', $raw_date );
	if ( $dt ) {
		// e.g. "Tuesday 21 April 2026" or "Tuesday 21 April" (passed: same format).
		$date_display = $dt->format( 'l j F Y' );
	}
}
?>

<div class="post-layout__meta | stack">

	<?php if ( $date_display || $time_display ) : ?>
	<div>
		<span class="post-layout__meta-label text-monospace text-s">
			<?php echo $has_passed ? esc_html__( 'Occurred', 'two-fiftyseven' ) : esc_html__( 'When', 'two-fiftyseven' ); ?>
		</span>
		<?php if ( $date_display ) : ?>
			<p><?php echo esc_html( $date_display ); ?></p>
		<?php endif; ?>
		<?php if ( $time_display ) : ?>
			<p><?php echo wp_kses( $time_display, [ 'span' => [], 'abbr' => [] ] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $add_to_cal['url'] ) && ! $has_passed ) : ?>
	<div>
		<a class="btn" data-type="text"
			href="<?php echo esc_url( $add_to_cal['url'] ); ?>"
			<?php if ( ! empty( $add_to_cal['target'] ) ) : ?>target="<?php echo esc_attr( $add_to_cal['target'] ); ?>" rel="noopener noreferrer"<?php endif; ?>>
			<?php echo esc_html( $add_to_cal['title'] ?: __( 'Add to calendar', 'two-fiftyseven' ) ); ?>
		</a>
	</div>
	<?php endif; ?>

	<?php if ( $location_type === 'offsite' && $location_name ) : ?>
	<div>
		<span class="post-layout__meta-label text-monospace text-s"><?php esc_html_e( 'Location', 'two-fiftyseven' ); ?></span>
		<?php if ( ! empty( $location_link['url'] ) ) : ?>
			<p>
				<a class="btn" data-type="text"
					href="<?php echo esc_url( $location_link['url'] ); ?>"
					target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $location_name ); ?>
				</a>
			</p>
		<?php else : ?>
			<p><?php echo esc_html( $location_name ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( ! $has_passed ) : ?>
	<div>
		<span class="post-layout__meta-label text-monospace text-s"><?php esc_html_e( 'Cost', 'two-fiftyseven' ); ?></span>
		<p>
			<?php if ( $cost_type === 'paid' && $cost_price ) : ?>
				$<?php echo esc_html( $cost_price ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Free', 'two-fiftyseven' ); ?>
			<?php endif; ?>
		</p>
	</div>
	<?php endif; ?>


</div>
