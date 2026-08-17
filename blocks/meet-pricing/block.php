<?php
/**
 * 257 Meet Pricing Calculator — ACF block render template.
 *
 * Quote tool for meeting + event room bookings. Pick room/duration/dates/addons,
 * see an itemised total live. Optional Host variant restricts to the large rooms.
 *
 * ACF fields:
 *   mp_eyebrow     — optional small label above the heading (text)
 *   mp_heading     — H1 heading (text)
 *   mp_tagline     — intro paragraph below the heading (textarea)
 *   room_set       — all / host (select)
 *   colour_space   — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/meet-pricing.js
 * Root selector: [data-js="calc-meet-pricing"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'mp_eyebrow' );
$heading      = get_field( 'mp_heading' );
$tagline      = get_field( 'mp_tagline' );
$colour_space = get_field( 'colour_space' ) ?: 'purple';
$room_set     = get_field( 'room_set' ) ?: 'all';
$allowed_cs   = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed_cs, true ) ) {
	$colour_space = 'purple';
}
$allowed_rs = [ 'all', 'host' ];
if ( ! in_array( $room_set, $allowed_rs, true ) ) {
	$room_set = 'all';
}

// Read SSOT values for no-JS / server-side display.
$discount_pct   = function_exists( 'get_field' ) ? (float) get_field( 'impact_discount_pct', 'option' ) : 50;
if ( $discount_pct <= 0 ) {
	$discount_pct = 50;
}
$eligibility_ceiling = function_exists( 'get_field' ) ? (int) get_field( 'impact_eligibility_revenue_ceiling', 'option' ) : 200000;
$paid_forward_display = function_exists( 'get_field' ) ? get_field( 'paid_forward_total_display', 'option' ) : '$450,000+';

// Room slug → ACF key stub. Cap is rendered into the tile label; rates are
// read from window.twofiftyseven.rooms by the engine (no duplication in DOM).
// Shared slug/name/key SSOT is two57_meet_rooms() (functions.php) — the slugs
// match the keys the wp_head injector emits on window.twofiftyseven.rooms (so
// ssot.rooms[slug] is always populated).
$rooms_all = two57_meet_rooms();
$rooms_host = [
	'workshop' => $rooms_all['workshop'],
	'event'    => $rooms_all['event'],
	'entire'   => $rooms_all['entire'],
];
$rooms = 'host' === $room_set ? $rooms_host : $rooms_all;

// Per-room capacity from the SSOT (rendered into the tile label).
$room_cap = static function ( string $key ) {
	if ( ! function_exists( 'get_field' ) ) {
		return 0;
	}
	return (int) get_field( 'room_' . $key . '_capacity', 'option' );
};
?>

<section
	class="meet-pricing | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php two57_calc_intro( (string) $eyebrow, (string) $heading, (string) $tagline, (bool) $is_preview ); ?>

		<div class="calc__body" data-js="calc-meet-pricing">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>

				<!-- ── Step 1 · People ───────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">How many people</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-calc-people-dec aria-label="Decrease people">&minus;</button>
							<div class="calc__slider" data-calc-people-slider>
								<input type="range" class="calc__slider-input" data-calc-people-range
									min="0" max="73" step="1" value="5" aria-label="Number of people">
							</div>
							<button type="button" class="calc__stepper-btn" data-calc-people-inc aria-label="Increase people">&plus;</button>
						</div>
						<output class="calc__slider-value" data-calc-people-out aria-live="polite">6</output>
					</div>
				</div>

				<!-- ── Step 2 · Room ─────────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Which space</span>
					<p class="meet-pricing__room-rec | text-s" data-calc-room-rec hidden>
						Recommended for <strong data-calc-rec-people>6</strong> people:
						<strong data-calc-rec-room>Meeting Room</strong>. You can size up if you want more space.
					</p>
					<div class="meet-pricing__room-grid" role="radiogroup" aria-label="Choose a room" data-calc-room-group>
						<?php foreach ( $rooms as $slug => $info ) :
							$cap = $room_cap( $info['key'] );
							?>
							<button
								type="button"
								role="radio"
								class="meet-pricing__room-option"
								data-calc-room="<?php echo esc_attr( $slug ); ?>"
								aria-checked="false"
							>
								<span class="meet-pricing__room-name"><?php echo esc_html( $info['name'] ); ?></span>
								<span class="meet-pricing__room-cap | text-monospace text-xs">Up to <?php echo esc_html( number_format( $cap ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- ── Step 3 · Duration ─────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">How long</span>
					<div class="meet-pricing__duration-grid" role="radiogroup" aria-label="Duration" data-calc-duration-group>
						<button type="button" role="radio" class="meet-pricing__duration-option" data-calc-duration="hour" aria-checked="false">
							<span class="meet-pricing__duration-name">By the hour</span>
							<span class="meet-pricing__duration-cap | text-monospace text-xs">Flexible</span>
						</button>
						<button type="button" role="radio" class="meet-pricing__duration-option" data-calc-duration="day" aria-checked="false">
							<span class="meet-pricing__duration-name">Full day</span>
							<span class="meet-pricing__duration-cap | text-monospace text-xs">8 hours</span>
						</button>
						<button type="button" role="radio" class="meet-pricing__duration-option" data-calc-duration="evening" aria-checked="false">
							<span class="meet-pricing__duration-name">Evening</span>
							<span class="meet-pricing__duration-cap | text-monospace text-xs">4hr block</span>
						</button>
					</div>
				</div>

				<!-- ── Step 4 · Days (repeatable) ───────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">When</span>
					<ul class="calc__repeat" data-calc-days-list></ul>
					<button type="button" class="calc__add-btn" data-calc-add-day>
						<span aria-hidden="true">+</span> Add day
					</button>
					<p class="calc__microcopy | text-s">Include your set-up time. Each day can be different — e.g. Day 1: 9-5, Day 2: 9-3.</p>
				</div>

				<!-- ── Step 5 · Add-ons ─────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Extras</span>
					<ul class="meet-pricing__addon-list">

						<li class="meet-pricing__addon calc__option-card" data-calc-addon="tea">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-addon-checkbox>
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meet-pricing__addon-title">Tea + coffee</span>
								<span class="meet-pricing__addon-price | text-monospace text-xs">$5 / $10 per person</span>
							</label>
							<p class="meet-pricing__addon-body | text-s">Organic, brewed fresh — single serve or bottomless (refilled throughout the day).</p>
							<div class="meet-pricing__addon-extra">
								<select class="calc__select" data-calc-addon-tea-type aria-label="Tea type">
									<option value="5">Single serve · $5pp</option>
									<option value="10">Bottomless · $10pp</option>
								</select>
							</div>
						</li>

						<li class="meet-pricing__addon calc__option-card" data-calc-addon="projector">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-addon-checkbox>
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meet-pricing__addon-title">Projector</span>
								<span class="meet-pricing__addon-price | text-monospace text-xs">$50</span>
							</label>
							<p class="meet-pricing__addon-body | text-s">Screen, projector and dongles brought into your room.</p>
						</li>

						<li class="meet-pricing__addon calc__option-card" data-calc-addon="sound">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-addon-checkbox>
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meet-pricing__addon-title">Sound system</span>
								<span class="meet-pricing__addon-price | text-monospace text-xs">$50</span>
							</label>
							<p class="meet-pricing__addon-body | text-s">PA, mics, mixer for your session.</p>
						</li>

						<li class="meet-pricing__addon calc__option-card" data-calc-addon="catering">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-addon-checkbox>
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meet-pricing__addon-title">Catering organised by us</span>
								<span class="meet-pricing__addon-price | text-monospace text-xs">Your budget + $100 organising</span>
							</label>
							<p class="meet-pricing__addon-body | text-s">Through Karaka Cafe, Blue Carrot or Food Envy. Catering passed through at cost — set your own per-head budget below. We add a $100 organising fee. You are welcome to organise your own catering from any other provider.</p>
							<div class="meet-pricing__addon-extra">
								<label for="meet-pricing-catering-perhead" class="text-monospace text-xs">$ per head</label>
								<input
									type="number"
									id="meet-pricing-catering-perhead"
									data-calc-addon-catering-perhead
									value="25" min="0" max="200" step="5"
									aria-label="Catering per-head budget"
								>
							</div>
						</li>

					</ul>
				</div>

				<!-- ── Step 6 · Impact Discount (applicant-side) ─────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Impact discount</span>
					<div class="meet-pricing__addon calc__option-card" data-calc-addon="impact">
						<label class="calc__option-head | calc__check">
							<input type="checkbox" data-calc-addon-checkbox>
							<span class="calc__check-box" aria-hidden="true"></span>
							<span class="meet-pricing__addon-title">Apply Impact Discount · <?php echo esc_html( number_format( $discount_pct, 0 ) ); ?>% off room rate</span>
						</label>
						<p class="meet-pricing__addon-body | text-s">
							For charity, NGO, B-Corp under $<?php echo esc_html( number_format( $eligibility_ceiling / 1000 ) ); ?>k · carbon-zero under $<?php echo esc_html( number_format( $eligibility_ceiling / 1000 ) ); ?>k · community volunteer · tangata whenua / indigenous-led.
						</p>
					</div>
				</div>

			</div>

			<aside class="calc__result | stack calc__result--sticky" aria-label="Live quote">
				<div class="calc__result-grid" role="status" aria-live="polite">

					<!-- L1 · Big total -->
					<div class="calc__result-col">
						<span class="calc__result-label | text-l">Estimated total</span>
						<span class="calc__result-figure | text-3xl" data-calc-quote-total>$0</span>
						<span class="calc__result-unit | text-monospace text-xs">excl. GST</span>
					</div>

					<!-- L2 · Itemised list -->
					<div class="calc__compare" data-calc-quote-items>
						<p class="calc__result-empty | text-s" data-calc-quote-prompt>Pick a room and duration to see your itemised quote</p>
					</div>

					<!-- L3 · Impact statement (giving + paid-forward context) -->
					<div class="meet-pricing__impact" data-calc-impact hidden>
						<p class="meet-pricing__impact-label | text-monospace text-xs" data-calc-impact-label>Your booking also funds</p>
						<p class="meet-pricing__impact-amount | text-2xl" data-calc-impact-amount>$0</p>
						<p class="meet-pricing__impact-context | text-s" data-calc-impact-context>
							of subsidised space for charities + community orgs. Contributing to
							<strong data-calc-impact-total><?php echo esc_html( $paid_forward_display ); ?></strong>
							paid forward since 2021.
						</p>
					</div>

				</div>
			</aside>

		</div>

			<?php two57_calc_share( [
				'title'      => 'save your quote, send it on',
				'email_title' => 'Email me this quote',
				'email_body'  => 'All your selections + itemised price in your inbox, ready to forward to whoever\'s signing off the booking.',
				'copy_body'   => 'Same selections, same price — your team clicks and sees the exact same quote you just built.',
			] ); ?>

		</div>

</section>