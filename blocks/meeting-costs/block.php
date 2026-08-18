<?php
/**
 * 257 Meeting Costs Calculator — ACF block render template.
 *
 * Compares running a meeting, workshop, away-day or event at a Wellington
 * industry-standard venue against running it at two/fiftyseven. Group size +
 * day times drive a mid-market industry benchmark band; catering, AV,
 * facilitation, materials and custom lines are added as selected. The space
 * two/fiftyseven would put you in is picked automatically from the group size.
 *
 * ACF fields:
 *   mc_eyebrow     — optional small label above the heading (text)
 *   mc_heading     — H1 heading (text)
 *   mc_tagline     — intro paragraph below the heading (textarea)
 *   colour_space   — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/meeting-costs.js
 * Root selector: [data-js="calc-meeting-costs"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'mc_eyebrow' );
$heading      = get_field( 'mc_heading' );
$tagline      = get_field( 'mc_tagline' );
$colour_space = get_field( 'colour_space' ) ?: 'forest';
$allowed      = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed, true ) ) {
	$colour_space = 'forest';
}

// Shared SSOT values for no-JS / server-side display.
$discount_pct = function_exists( 'get_field' ) ? (float) get_field( 'impact_discount_pct', 'option' ) : 50;
if ( $discount_pct <= 0 ) {
	$discount_pct = 50;
}
?>

<section
	class="meeting-costs | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php two57_calc_intro( (string) $eyebrow, (string) $heading, (string) $tagline, (bool) $is_preview ); ?>

		<div class="calc__body" data-js="calc-meeting-costs">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>

				<!-- ── Step 1 · Group size ─────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Group size</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-calc-size-dec aria-label="Decrease group size">&minus;</button>
							<div class="calc__slider" data-calc-size-slider>
								<input type="range" class="calc__slider-input" data-calc-size-range
									min="0" max="200" step="1" value="0" aria-label="Group size">
							</div>
							<button type="button" class="calc__stepper-btn" data-calc-size-inc aria-label="Increase group size">&plus;</button>
						</div>
						<output class="calc__slider-value" data-calc-size-out aria-live="polite">0</output>
					</div>
				</div>

				<!-- ── Step 2 · When (repeating day rows) ─────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">When</span>
					<ul class="calc__repeat" data-calc-days-list></ul>
					<button type="button" class="calc__add-btn" data-calc-add-day>
						<span aria-hidden="true">+</span> Add a day
					</button>
					<p class="calc__microcopy | text-s meeting-costs__duration-label" data-calc-duration-label></p>
					<p class="calc__microcopy | text-s">Two or more populated days counts as a multi-day booking.</p>
				</div>

				<!-- ── Step 3 · Catering ───────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Catering</span>
					<ul class="meeting-costs__card-list">
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="tea">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Tea + coffee</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Organic, brewed fresh, bottomless all day.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="breakfast">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Breakfast · light</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Pastries + fruit + coffee, laid out from 8am.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="lunch-light">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Lunch · light</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Salads, sandwiches and wraps, a light refuel.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="lunch-hearty">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Lunch · hearty</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Hot mains + sides, a proper sit-down lunch.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="afternoon">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Afternoon tea</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Sweet + savoury pick-me-ups mid-arvo.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="drinks">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Drinks (evening)</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Wine + beer for evening sessions.</p>
						</li>
					</ul>
				</div>

				<!-- ── Step 4 · AV ─────────────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">AV</span>
					<ul class="meeting-costs__card-list">
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="projector">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Projector + screen</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Screen, projector and dongles ready to go.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="sound">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Sound system + mic</span>
							</label>
							<p class="meeting-costs__card-body | text-s">PA, mics and mixer for talks or workshops.</p>
						</li>
					</ul>
				</div>

				<!-- ── Step 5 · Facilitation ───────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Facilitation</span>
					<div class="meeting-costs__radio-cards" role="radiogroup" aria-label="Facilitation" data-calc-fac-group>
						<button type="button" role="radio" class="meeting-costs__radio-card" data-calc-fac="none" aria-checked="true">
							<span class="meeting-costs__radio-indicator" aria-hidden="true"></span>
							<span class="meeting-costs__card-title">None</span>
							<span class="meeting-costs__card-body | text-s">Run it yourself, no facilitator booked.</span>
						</button>
						<button type="button" role="radio" class="meeting-costs__radio-card" data-calc-fac="half" aria-checked="false">
							<span class="meeting-costs__radio-indicator" aria-hidden="true"></span>
							<span class="meeting-costs__card-title">Half-day external</span>
							<span class="meeting-costs__card-body | text-s">External facilitator for half a day.</span>
						</button>
						<button type="button" role="radio" class="meeting-costs__radio-card" data-calc-fac="full" aria-checked="false">
							<span class="meeting-costs__radio-indicator" aria-hidden="true"></span>
							<span class="meeting-costs__card-title">Full-day external</span>
							<span class="meeting-costs__card-body | text-s">External facilitator for the full day.</span>
						</button>
						<button type="button" role="radio" class="meeting-costs__radio-card" data-calc-fac="senior" aria-checked="false">
							<span class="meeting-costs__radio-indicator" aria-hidden="true"></span>
							<span class="meeting-costs__card-title">Senior / multi-day</span>
							<span class="meeting-costs__card-body | text-s">Senior facilitator, or multi-day booking.</span>
						</button>
					</div>
				</div>

				<!-- ── Step 6 · Materials ──────────────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Materials</span>
					<ul class="meeting-costs__card-list">
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="boards">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Whiteboards / flipcharts</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Ready to write on, included with every space.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="postits">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Post-its + pens</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Sticky notes + pens for every table.</p>
						</li>
						<li class="meeting-costs__card calc__option-card">
							<label class="calc__option-head | calc__check">
								<input type="checkbox" data-calc-check="printing">
								<span class="calc__check-box" aria-hidden="true"></span>
								<span class="meeting-costs__card-title">Printing</span>
							</label>
							<p class="meeting-costs__card-body | text-s">Handouts, prints and posters for the session.</p>
						</li>
					</ul>
				</div>

				<!-- ── Step 7 · Setup + pack-down ──────────────────── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Setup + pack-down</span>
					<div class="meeting-costs__radio-cards" role="radiogroup" aria-label="Setup and pack down" data-calc-setup-group>
						<button type="button" role="radio" class="meeting-costs__radio-card" data-calc-setup="standard" aria-checked="true">
							<span class="meeting-costs__radio-indicator" aria-hidden="true"></span>
							<span class="meeting-costs__card-title">Standard</span>
							<span class="meeting-costs__card-body | text-s">Standard setup + pack-down, included. Full room reset between sessions.</span>
						</button>
						<button type="button" role="radio" class="meeting-costs__radio-card" data-calc-setup="complex" aria-checked="false">
							<span class="meeting-costs__radio-indicator" aria-hidden="true"></span>
							<span class="meeting-costs__card-title">Complex</span>
							<span class="meeting-costs__card-body | text-s">Multi-room or non-standard reset.</span>
						</button>
					</div>
				</div>

				<!-- ── Step 8 · Custom expenses (repeating rows) ──── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Additional expenses</span>
					<ul class="calc__repeat" data-calc-custom-list></ul>
					<button type="button" class="calc__add-btn" data-calc-custom-add>
						<span aria-hidden="true">+</span> Add another
					</button>
					<p class="calc__microcopy | text-s">Add one-off line items, e.g. a photographer.</p>
				</div>

				<!-- ── Step 9 · Impact Discount (applicant-side) ───── -->
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Impact discount</span>
					<div class="calc__option-card" data-calc-addon="impact">
						<label class="calc__option-head | calc__check">
							<input type="checkbox" data-calc-addon-checkbox>
							<span class="calc__check-box" aria-hidden="true"></span>
							<span class="calc__check-label">Apply Impact Discount · <?php echo esc_html( number_format( $discount_pct, 0 ) ); ?>% Off</span>
						</label>
						<p class="meeting-costs__addon-body | text-s">
							For charity, NGO, B-Corp under $200k · carbon-zero under $200k · community volunteer · tangata whenua / indigenous-led.
						</p>
					</div>
				</div>

			</div>

			<aside class="calc__result | stack calc__result--sticky" aria-label="Live cost comparison">
				<div class="calc__result-grid" role="status" aria-live="polite">

					<!-- Headline · the two/fiftyseven figure (matches workspace-pricing) -->
					<div data-result-headline>
						<div class="calc__result-grid-headline">
							<div class="calc__result-col calc__result-col--accent">
								<span class="calc__result-label | text-l">At two/fiftyseven</span>
								<span class="calc__result-figure | text-3xl" data-calc-result-ours>$0</span>
								<span class="calc__result-unit | text-monospace text-xs" data-calc-result-space></span>
							</div>
						</div>
					</div>

					<!-- Comparison bars · venue vs two/fiftyseven -->
					<div class="calc__chart | stack" data-result-compare>
						<div class="calc__chart-row">
							<div class="calc__chart-headline">
								<span class="calc__chart-label">Comparable local venue</span>
								<span class="calc__chart-value">
									<span data-calc-chart-venue-low>$0</span>&ndash;<span data-calc-chart-venue-high>$0</span>
								</span>
							</div>
							<div class="calc__chart-bar-wrap">
								<div class="calc__chart-bar calc__chart-bar--low" style="--bar-pct: 0%"></div>
								<div class="calc__chart-bar calc__chart-bar--high" style="--bar-pct: 0%"></div>
							</div>
						</div>
						<div class="calc__chart-row calc__chart-row--ours">
							<div class="calc__chart-headline">
								<span class="calc__chart-label">At two/fiftyseven</span>
								<span class="calc__chart-value" data-calc-chart-ours>$0</span>
							</div>
							<div class="calc__chart-bar-wrap">
								<div class="calc__chart-bar calc__chart-bar--ours" style="--bar-pct: 0%"></div>
							</div>
						</div>
						<div class="calc__chart-savings">
							<div class="calc__chart-headline">
								<span class="calc__chart-saving">Savings</span>
								<span class="calc__chart-saving-value">
									<span data-calc-chart-save-low>$0</span>&ndash;<span data-calc-chart-save-high>$0</span>
								</span>
							</div>
						</div>
					</div>

					<p class="calc__result-empty | text-s" data-calc-result-prompt hidden>Enter a group size and each day's start + end time to see your comparison</p>

				</div>

				<button
					type="button"
					class="calc__breakdown-trigger"
					data-breakdown-trigger
					aria-controls="methodology"
					aria-expanded="false"
				>
					<span class="text-monospace">Show working</span>
					<span class="calc__breakdown-caret" aria-hidden="true"></span>
				</button>
			</aside>

		</div>

		<details class="calc__breakdown" id="methodology">
			<summary aria-hidden="true" class="calc__breakdown-summary | text-monospace text-s">Breakdown</summary>
			<div class="calc__breakdown-body">

				<div class="calc__breakdown-grid">

					<!-- Left column · industry-standard pricing (band) -->
					<div class="calc__breakdown-col | stack">
						<h3 class="calc__breakdown-heading | text-l">Current local pricing</h3>
						<div class="calc__compare" data-calc-industry-lines></div>
						<div class="calc__compare">
							<div class="calc__compare-row calc__compare-row--total">
								<div class="calc__compare-row-label font-bold">Total · current local pricing</div>
								<div class="calc__compare-row-value" data-calc-industry-total>$0</div>
							</div>
						</div>
						<p class="calc__breakdown-prose | text-m">
							The industry band is a mid-range sample of comparable central-Wellington venues' published 2026 rate cards, scaled by group size. Every line links to its source.
						</p>
					</div>

					<!-- Right column · at two/fiftyseven -->
					<div class="calc__breakdown-col | stack">
						<h3 class="calc__breakdown-heading | text-l">At two/fiftyseven</h3>
						<div class="calc__compare" data-calc-ours-lines></div>
						<div class="calc__compare">
							<div class="calc__compare-row calc__compare-row--total">
								<div class="calc__compare-row-label font-bold">Total at two/fiftyseven</div>
								<div class="calc__compare-row-value" data-calc-ours-total>$0</div>
							</div>
						</div>
						<p class="calc__breakdown-prose | text-m">
							Catering is free when you arrange it directly and charged at cost when we arrange it — the 2/57 figure uses the industry midpoint. Room rate comes from the space picked for your group size.
						</p>
					</div>

				</div>

			</div>
		</details>

		<?php two57_calc_share( [
			'title'      => 'save your comparison, send it on',
			'email_title' => 'Email me these numbers',
			'email_body'  => 'Your event spec + the industry-vs-two/fiftyseven comparison in your inbox, ready to take to the team or budget meeting.',
			'copy_body'   => 'Same inputs, same numbers — your team clicks the link and sees the exact same comparison.',
		] ); ?>

	</div>

</section>