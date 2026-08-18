<?php
/**
 * 257 Calc Widget — ACF block render template.
 *
 * Lightweight quote teaser for meeting + event room bookings. Pick people,
 * hours, and a room (auto-recommended by capacity), see a starting estimate
 * live. Deep-links to the full meet-pricing calculator with state carried
 * via URL params. Optional Impact Discount toggle (50% off for eligible
 * for-purpose organisations).
 *
 * Reads all rates from window.twofiftyseven.rooms (no duplication in DOM).
 * Room slugs match two57_meet_rooms() so ssot.rooms[slug] is always populated.
 *
 * ACF fields:
 *   cw_eyebrow      — optional small label above the heading (text)
 *   cw_heading      — H1 heading (text)
 *   cw_tagline      — intro paragraph below the heading (textarea)
 *   room_set        — all / host (select: all rooms or large rooms only)
 *   pricing_url     — full quote page (page_link — admin selects a page)
 *   colour_space    — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/calc-widget.js
 * Root selector: [data-js="calc-widget"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'cw_eyebrow' );
$heading      = get_field( 'cw_heading' );
$tagline      = get_field( 'cw_tagline' );
$colour_space = get_field( 'colour_space' ) ?: 'purple';
$room_set     = get_field( 'room_set' ) ?: 'all';
$pricing_url  = get_field( 'pricing_url' ) ?: '/meetings/pricing/';

$allowed_cs = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed_cs, true ) ) {
	$colour_space = 'purple';
}
$allowed_rs = [ 'all', 'host' ];
if ( ! in_array( $room_set, $allowed_rs, true ) ) {
	$room_set = 'all';
}

// SSOT values for no-JS / server-side display.
$discount_pct   = function_exists( 'get_field' ) ? (float) get_field( 'impact_discount_pct', 'option' ) : 50;
if ( $discount_pct <= 0 ) {
	$discount_pct = 50;
}
$eligibility_ceiling = function_exists( 'get_field' ) ? (int) get_field( 'impact_eligibility_revenue_ceiling', 'option' ) : 200000;
$paid_forward_display = function_exists( 'get_field' ) ? get_field( 'paid_forward_total_display', 'option' ) : '$450,000+';

// Room set — shared slug/name SSOT is two57_meet_rooms().
$rooms_all = two57_meet_rooms();
$rooms_host = [
	'workshop' => $rooms_all['workshop'],
	'event'    => $rooms_all['event'],
	'entire'   => $rooms_all['entire'],
];
$rooms = 'host' === $room_set ? $rooms_host : $rooms_all;

// Per-room capacity from the SSOT.
$room_cap = static function ( string $key ) {
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}
	return (int) get_field( 'room_' . $key . '_capacity', 'option' );
};
?>

<section
	class="calc-widget | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php two57_calc_intro( (string) $eyebrow, (string) $heading, (string) $tagline, (bool) $is_preview ); ?>

		<div class="calc__body" data-js="calc-widget" data-pricing-url="<?php echo esc_url( $pricing_url ); ?>" data-room-set="<?php echo esc_attr( $room_set ); ?>">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>

				<!-- ── People ─────────────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">How many people</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-cw-people-dec aria-label="Decrease people">&minus;</button>
							<div class="calc__slider" data-cw-people-slider>
								<input type="range" class="calc__slider-input" data-cw-people-range
									min="0" max="73" step="1" value="5" aria-label="Number of people">
							</div>
							<button type="button" class="calc__stepper-btn" data-cw-people-inc aria-label="Increase people">&plus;</button>
						</div>
						<output class="calc__slider-value" data-cw-people-out aria-live="polite">6</output>
					</div>
				</div>

				<!-- ── Hours ──────────────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">How many hours</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-cw-hours-dec aria-label="Decrease hours">&minus;</button>
							<div class="calc__slider" data-cw-hours-slider>
								<input type="range" class="calc__slider-input" data-cw-hours-range
									min="1" max="12" step="1" value="4" aria-label="Number of hours">
							</div>
							<button type="button" class="calc__stepper-btn" data-cw-hours-inc aria-label="Increase hours">&plus;</button>
						</div>
						<output class="calc__slider-value" data-cw-hours-out aria-live="polite">4</output>
					</div>
				</div>

				<!-- ── Room (auto-recommended, tap to change) ─────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Room <span class="calc-widget__room-hint">auto-picked, tap to change</span></span>
					<div class="calc-widget__room-grid" role="radiogroup" aria-label="Choose a room" data-cw-room-group>
						<?php foreach ( $rooms as $slug => $info ) :
							$cap = $room_cap( $info['key'] );
							?>
							<button
								type="button"
								role="radio"
								class="calc-widget__room-option"
								data-cw-room="<?php echo esc_attr( $slug ); ?>"
								data-cw-cap="<?php echo esc_attr( $cap ); ?>"
								aria-checked="false"
							>
								<span class="calc-widget__room-name"><?php echo esc_html( $info['name'] ); ?></span>
								<span class="calc-widget__room-cap | text-monospace text-xs">Up to <?php echo esc_html( number_format( $cap ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- ── Impact Discount ─────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Impact discount</span>
					<div class="calc__option-card" data-cw-impact-card>
						<label class="calc__option-head | calc__check">
							<input type="checkbox" data-cw-impact-checkbox>
							<span class="calc__check-box" aria-hidden="true"></span>
							<span class="calc-widget__impact-title">Apply Impact Discount · <?php echo esc_html( number_format( $discount_pct, 0 ) ); ?>% off room rate</span>
						</label>
						<p class="calc-widget__impact-body | text-s">
							For charity, NGO, B-Corp under $<?php echo esc_html( number_format( $eligibility_ceiling / 1000 ) ); ?>k · carbon-zero under $<?php echo esc_html( number_format( $eligibility_ceiling / 1000 ) ); ?>k · community volunteer · tangata whenua / indigenous-led.
						</p>
					</div>
				</div>

			</div>

			<aside class="calc__result | stack calc__result--sticky" aria-label="Quick estimate">
				<div class="calc__result-grid" role="status" aria-live="polite">

					<!-- Estimated total -->
					<div class="calc__result-col">
						<span class="calc__result-label | text-l">Estimated total</span>
						<span class="calc__result-figure | text-3xl" data-cw-amount>$0</span>
						<span class="calc__result-unit | text-monospace text-xs">excl. GST · room only</span>
					</div>

					<!-- Impact funding statement -->
					<div class="calc-widget__impact" data-cw-impact hidden>
						<p class="calc__result-label | text-l" data-cw-impact-label>Your booking also funds</p>
						<p class="calc-widget__impact-amount | text-2xl" data-cw-impact-amount>$0</p>
						<p class="calc-widget__impact-context | text-s" data-cw-impact-context></p>
					</div>

				</div>

				<!-- CTA → full quote tool -->
				<a
					class="calc__breakdown-trigger"
					data-cw-cta
					href="<?php echo esc_url( $pricing_url ); ?>"
				>
					<span class="text-monospace">Get a full quote</span>
					<span class="calc__breakdown-caret calc-widget__cta-caret" aria-hidden="true"></span>
				</a>

			</aside>

		</div>

	</div>

</section>
