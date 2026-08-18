<?php
/**
 * 257 Office Costs Calculator — ACF block render template.
 *
 * Configures a Wellington office cost top-to-bottom: rent (grade + precinct
 * modifiers), outgoings, utilities, cleaning, consumables, compliance +
 * insurance, furniture amortisation, admin overhead, lease legals, booking
 * software and custom lines. The total is recomputed live and compared against
 * what the same team costs at two/fiftyseven in a savings band. Configurations
 * can be saved as up to three local scenarios and side-by-side compared.
 *
 * ACF fields:
 *   oc_eyebrow     — optional small label above the heading (text)
 *   oc_heading     — H1 heading (text)
 *   oc_tagline     — intro paragraph below the heading (textarea)
 *   colour_space   — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/office-costs.js
 * Root selector: [data-js="calc-office-costs-v2"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'oc_eyebrow' );
$heading      = get_field( 'oc_heading' );
$tagline      = get_field( 'oc_tagline' );
$colour_space = get_field( 'colour_space' ) ?: 'forest';
$allowed      = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed, true ) ) {
	$colour_space = 'forest';
}
?>

<section
	class="office-costs | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php two57_calc_intro( (string) $eyebrow, (string) $heading, (string) $tagline, (bool) $is_preview ); ?>

		<div class="calc__body" data-js="calc-office-costs-v2">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>

				<!-- ── Group 1 · Team ──────────────────────────────── -->
				<h3 class="office-costs__group-heading">Team</h3>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">
						Team size
						<span class="calc-source" tabindex="0">
							<button type="button" class="calc-source__trigger" aria-label="About team size">i</button>
							<span class="calc-source__pop" role="tooltip">
								<span class="calc-source__pop-label">Team size</span>
								Default: 1 person. At team ≥ 10, booking software auto-enables.
							</span>
						</span>
					</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-oc-team-dec aria-label="Decrease team size">&minus;</button>
							<div class="calc__slider" data-oc-team-slider>
								<input type="range" class="calc__slider-input" data-oc-team-range
									min="0" max="15" step="1" value="1" aria-label="Team size">
							</div>
							<button type="button" class="calc__stepper-btn" data-oc-team-inc aria-label="Increase team size">&plus;</button>
						</div>
						<output class="calc__slider-value" data-oc-team-out aria-live="polite">1</output>
					</div>
					<input type="hidden" data-occv2-team-size value="1">
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">
						Days per week &middot; per person
						<span class="calc-source" tabindex="0">
							<button type="button" class="calc-source__trigger" aria-label="About days per week">i</button>
							<span class="calc-source__pop" role="tooltip">
								<span class="calc-source__pop-label">Days per week</span>
								Default: 5 days/week. Used for the per-person-per-day figure (46 working weeks).
							</span>
						</span>
					</span>
					<ul class="office-costs__days-roster" data-oc-days-roster></ul>
				</div>

				<!-- ── Group 2 · Office ────────────────────────────── -->
				<h3 class="office-costs__group-heading">Office</h3>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">
						Grade
						<span class="calc-source" tabindex="0">
							<button type="button" class="calc-source__trigger" aria-label="About office grade">i</button>
							<span class="calc-source__pop" role="tooltip">
								<span class="calc-source__pop-label">Office grade</span>
								Default: B-grade fitted. A-grade adds ~35%, B-grade unfitted ~22% less, C-grade ~38% less.
							</span>
						</span>
					</span>
					<div class="office-costs__radio-cards" role="radiogroup" aria-label="Office grade" data-oc-grade-group>
						<button type="button" role="radio" class="office-costs__radio-card" data-occv2-grade="A-grade" aria-checked="false">
							<span class="office-costs__radio-indicator" aria-hidden="true"></span>
							<span class="office-costs__radio-card-title">A-grade</span>
							<span class="office-costs__radio-card-body | text-s">Premium fit-out. +35% on rent.</span>
						</button>
						<button type="button" role="radio" class="office-costs__radio-card" data-occv2-grade="B-grade fitted" aria-checked="true">
							<span class="office-costs__radio-indicator" aria-hidden="true"></span>
							<span class="office-costs__radio-card-title">B-grade fitted</span>
							<span class="office-costs__radio-card-body | text-s">Standard fit-out. Baseline rent.</span>
						</button>
						<button type="button" role="radio" class="office-costs__radio-card" data-occv2-grade="B-grade unfitted" aria-checked="false">
							<span class="office-costs__radio-indicator" aria-hidden="true"></span>
							<span class="office-costs__radio-card-title">B-grade unfitted</span>
							<span class="office-costs__radio-card-body | text-s">Structural shell. −22% on rent.</span>
						</button>
						<button type="button" role="radio" class="office-costs__radio-card" data-occv2-grade="C-grade" aria-checked="false">
							<span class="office-costs__radio-indicator" aria-hidden="true"></span>
							<span class="office-costs__radio-card-title">C-grade</span>
							<span class="office-costs__radio-card-body | text-s">Basic fit-out. −38% on rent.</span>
						</button>
					</div>
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">
						Precinct
						<span class="calc-source" tabindex="0">
							<button type="button" class="calc-source__trigger" aria-label="About precinct">i</button>
							<span class="calc-source__pop" role="tooltip">
								<span class="calc-source__pop-label">Precinct</span>
								Modifier on rent: Lambton 1.20 · CBD core 1.15 · Thorndon 1.05 · CBD fringe 1.00 · Mt Vic 0.95 · Te Aro 0.92 · Kelburn 0.85.
							</span>
						</span>
					</span>
					<select class="calc__select" data-occv2-precinct aria-label="Precinct">
						<option value="CBD core">CBD core</option>
						<option value="CBD fringe">CBD fringe</option>
						<option value="Te Aro">Te Aro</option>
						<option value="Thorndon">Thorndon</option>
						<option value="Lambton">Lambton</option>
						<option value="Kelburn">Kelburn</option>
						<option value="Mt Vic">Mt Vic</option>
					</select>
				</div>

				<div class="calc__fields-grid">
					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							m² per person
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About m² per person">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">m² per person</span>
									Default: 9 m²/person. 6 = high-density, 10 = conventional standard, 15 = generous spec.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-sqm-pp value="9" min="6" max="15" step="0.5" aria-label="m² per person">
							<span class="office-costs__input-suffix | text-s">m²/person</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Rent
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About rent">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Rent $/m²/yr</span>
									Default: $310/m²/yr · Wellington B-grade fitted CBD core. Grade + precinct modifiers apply.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-oc-rent-display value="310" min="120" max="570" step="5" aria-label="Rent per m² per year (reflects grade)">
							<input type="hidden" data-occv2-rent-sqm value="310">
							<span class="office-costs__input-suffix | text-s">$/m²/yr &middot; <span data-oc-grade-label>B-grade fitted</span></span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Outgoings
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About outgoings">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Outgoings % of rent</span>
									Default: 27% of rent (property tax, insurance, services, maintenance).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-outgoings-pct value="27" min="20" max="35" step="1">
							<span class="office-costs__input-suffix | text-s">% of rent</span>
						</div>
					</div>
				</div>

				<!-- ── Group 3 · Utilities ─────────────────────────── -->
				<h3 class="office-costs__group-heading">Utilities</h3>

				<div class="calc__fields-grid">
					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Internet
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About internet">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Internet</span>
									Default: $200/mo for business fibre.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-internet-mo value="200" min="99" max="400" step="10">
							<span class="office-costs__input-suffix | text-s">$/month</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Power
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About power watts per m²">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Power W/m²</span>
									Default: 50 W/m² · BRANZ standard for an active NZ office.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-power-w-sqm value="50" min="40" max="70" step="1">
							<span class="office-costs__input-suffix | text-s">W/m² active load</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Power hours/year
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About power hours per year">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Power hours/year</span>
									Default: 1,840 hrs · 46 working weeks × 5 days × 8 hrs.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-power-hrs value="1840" min="1500" max="2400" step="20">
							<span class="office-costs__input-suffix | text-s">active hours/yr</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Power price
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About power price per kwh">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Power $/kWh</span>
									Default: $0.30/kWh all-up NZ commercial rate (EECA).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-power-kwh value="0.30" min="0.22" max="0.42" step="0.01">
							<span class="office-costs__input-suffix | text-s">$/kWh all-up</span>
						</div>
					</div>
				</div>

				<!-- ── Group 4 · Cleaning + consumables ────────────── -->
				<h3 class="office-costs__group-heading">Cleaning + consumables</h3>

				<div class="calc__fields-grid">
					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Cleaning
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About cleaning hours per m² per year">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Cleaning hours</span>
									Default: 1.2 hr/m²/yr · NZ commercial cleaning standard (BSCA).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-cleaning-hr-sqm value="1.2" min="1.0" max="1.5" step="0.1">
							<span class="office-costs__input-suffix | text-s">hr/m²/yr</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Cleaning rate
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About cleaning rate per hour">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Cleaning rate</span>
									Default: $45/hr · Wellington midmarket commercial rate (Clean Planet).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-cleaning-hr value="45" min="38" max="55" step="1">
							<span class="office-costs__input-suffix | text-s">$/hr charge-out</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Kitchen + bathroom
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About kitchen and bathroom">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Kitchen + bathroom</span>
									Default: $300/pp/yr consumables (Officemax NZ).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-kb-pp value="300" min="200" max="450" step="10">
							<span class="office-costs__input-suffix | text-s">$/pp/yr</span>
						</div>
					</div>
				</div>

				<!-- ── Group 5 · Compliance + insurance ─────────────── -->
				<h3 class="office-costs__group-heading">Compliance + insurance</h3>

				<div class="calc__fields-grid">
					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Insurance
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About insurance">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Insurance</span>
									Default: $200/pp/yr combined (ICNZ).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-insurance-pp value="200" min="150" max="400" step="10">
							<span class="office-costs__input-suffix | text-s">$/pp/yr combined</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							First aid
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About first aid training">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">First aid</span>
									Default: $28/pp/yr (H&S Act 2015 compliance, St John NZ).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-first-aid-pp value="28" min="15" max="50" step="1">
							<span class="office-costs__input-suffix | text-s">$/pp/yr amortised</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Fire warden
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About fire warden training">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Fire warden</span>
									Default: $18/pp/yr (FENZ requirement).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-fire-warden-pp value="18" min="10" max="35" step="1">
							<span class="office-costs__input-suffix | text-s">$/pp/yr amortised</span>
						</div>
					</div>
				</div>

				<!-- ── Group 6 · Furniture + admin + legals ─────────── -->
				<h3 class="office-costs__group-heading">Furniture + admin + legals</h3>

				<div class="calc__fields-grid">
					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Furniture
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About furniture">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Furniture</span>
									Default: $2,000/pp · standard NZ workplace spec (Govt Property Group).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-furniture-pp value="2000" min="1200" max="3500" step="100">
							<span class="office-costs__input-suffix | text-s">$/pp one-off</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Furniture amort
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About furniture amortisation">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Furniture amortisation</span>
									Default: 5 years · NZ IFRS depreciation period.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-furniture-yrs value="5" min="3" max="10" step="1">
							<span class="office-costs__input-suffix | text-s">years</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Admin
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About admin percent of hours">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Admin %</span>
									Default: 6% of team hours · NZ workplace overhead studies.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-admin-pct value="6" min="4" max="10" step="0.5">
							<span class="office-costs__input-suffix | text-s">% of team hours</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Admin rate
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About admin rate per hour">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Admin rate</span>
									Default: $70/hr loaded · PayScale NZ + 30% on-costs.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-admin-rate value="70" min="55" max="90" step="1">
							<span class="office-costs__input-suffix | text-s">$/hr loaded</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Lease legals
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About lease legals">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Lease legals</span>
									Default: $3,500 one-off · NZ commercial lease review (LawyerFinder NZ).
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-legals value="3500" min="2000" max="6000" step="100">
							<span class="office-costs__input-suffix | text-s">$ one-off</span>
						</div>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">
							Lease term
							<span class="calc-source" tabindex="0">
								<button type="button" class="calc-source__trigger" aria-label="About lease term">i</button>
								<span class="calc-source__pop" role="tooltip">
									<span class="calc-source__pop-label">Lease term</span>
									Default: 3 years · NZ Property Council standard for small/mid tenants.
								</span>
							</span>
						</span>
						<div class="office-costs__input-row">
							<input type="number" class="calc__input" data-occv2-lease-yrs value="3" min="1" max="10" step="1">
							<span class="office-costs__input-suffix | text-s">years</span>
						</div>
					</div>
				</div>

				<!-- ── Group 7 · Add-ons + custom lines ─────────────── -->
				<h3 class="office-costs__group-heading">Add-ons + custom lines</h3>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">
						Booking software
						<span class="calc-source" tabindex="0">
							<button type="button" class="calc-source__trigger" aria-label="About booking software">i</button>
							<span class="calc-source__pop" role="tooltip">
								<span class="calc-source__pop-label">Booking software</span>
								Default: $8/pp/mo, auto-enables at team ≥ 10 (Skedda).
							</span>
						</span>
					</span>
					<div class="calc__option-card" data-oc-booking-card>
						<label class="calc__option-head | calc__check">
							<input type="checkbox" data-occv2-booking-toggle>
							<span class="calc__check-box" aria-hidden="true"></span>
							<span class="calc__check-label">Booking software</span>
						</label>
						<div class="office-costs__input-row office-costs__input-row--booking" data-oc-booking-cost-wrap hidden>
							<input type="number" class="calc__input" data-occv2-booking-cost value="8" min="5" max="15" step="1" aria-label="Booking software cost per person per month">
							<span class="office-costs__input-suffix | text-s">/pp/mo</span>
						</div>
					</div>
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Additional expenses</span>
					<ul class="calc__repeat" data-occv2-custom-rows></ul>
					<button type="button" class="calc__add-btn" data-occv2-custom-add>
						<span aria-hidden="true">+</span> Add another
					</button>
					<p class="calc__microcopy | text-s">Add one-off annual line items, e.g. reception or a coffee machine.</p>
				</div>

			</div>

			<aside class="calc__result | stack calc__result--sticky" aria-label="Live office cost result">
				<div class="calc__result-grid" role="status" aria-live="polite">

					<div data-result-content>

						<div class="calc__result-grid-headline">
							<div class="calc__result-col calc__result-col--accent">
								<span class="calc__result-label | text-l">Annual total office costs</span>
								<span class="calc__result-figure | text-3xl" data-result-annual>$0</span>
								<!-- <span class="calc__result-unit | text-monospace text-xs">rent through legals, everything</span> -->
							</div>
						</div>

						<div class="office-costs__stats | stack">
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">Per month</span>
								<span class="calc__stat-value | text-l" data-result-monthly>$0</span>
								<span class="calc__stat-unit | text-s">of your budget</span>
							</div>
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">Per person / month</span>
								<span class="calc__stat-value | text-l" data-result-pp-month>$0</span>
								<span class="calc__stat-unit | text-s">per head</span>
							</div>
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">Per person / day</span>
								<span class="calc__stat-value | text-l" data-result-pp-day>$0</span>
								<span class="calc__stat-unit | text-s">46 working weeks</span>
							</div>
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">Per m² / year</span>
								<span class="calc__stat-value | text-l" data-result-per-sqm>$0</span>
								<span class="calc__stat-unit | text-s">across your floor</span>
							</div>
						</div>

						<div class="calc__chart-savings" data-oc-vs257 hidden>
							<div class="calc__chart-headline">
								<span class="calc__chart-saving">Same team at two/fiftyseven</span>
								<span class="calc__chart-saving-value">Save <span data-oc-save-figure>$0</span>/year</span>
							</div>
						</div>

					</div>

					<p class="calc__result-empty | text-s" data-result-empty hidden>Select your team size to see your office cost, and configure every line below to tune it</p>

				</div>

				<div class="office-costs__scenarios | stack">
					<span class="office-costs__scenarios-label | text-monospace text-s">Scenarios</span>
					<div class="office-costs__scenarios-row">
						<button type="button" class="office-costs__scenario-slot" data-scenario-slot="1" aria-label="Scenario 1">
							<span class="office-costs__scenario-num">1</span>
							<span class="office-costs__scenario-name" data-scenario-slot-name>Empty</span>
							<span class="office-costs__scenario-value" data-scenario-slot-value>&middot;</span>
						</button>
						<button type="button" class="office-costs__scenario-slot" data-scenario-slot="2" aria-label="Scenario 2">
							<span class="office-costs__scenario-num">2</span>
							<span class="office-costs__scenario-name" data-scenario-slot-name>Empty</span>
							<span class="office-costs__scenario-value" data-scenario-slot-value>&middot;</span>
						</button>
						<button type="button" class="office-costs__scenario-slot" data-scenario-slot="3" aria-label="Scenario 3">
							<span class="office-costs__scenario-num">3</span>
							<span class="office-costs__scenario-name" data-scenario-slot-name>Empty</span>
							<span class="office-costs__scenario-value" data-scenario-slot-value>&middot;</span>
						</button>
					</div>
					<div class="office-costs__scenarios-actions">
						<button type="button" class="office-costs__scenarios-btn" data-scenario-save>Save current &rarr;</button>
						<button type="button" class="office-costs__scenarios-btn" data-scenario-compare>Compare all &rarr;</button>
						<button type="button" class="office-costs__scenarios-btn" data-scenario-reset>Reset all &rarr;</button>
					</div>
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

				<div class="stack">
					<h3 class="calc__breakdown-heading | text-xl">Where every dollar goes</h3>
					<p class="calc__breakdown-prose | text-m">Every line is derived from the inputs above and links to its source.</p>
					
					<div class="calc__compare" data-occv2-lines-slot></div>
					<div class="calc__compare">
						<div class="calc__compare-row calc__compare-row--total">
							<div class="calc__compare-row-label font-bold">Annual total</div>
							<div class="calc__compare-row-value" data-occv2-lines-total>$0</div>
						</div>
					</div>
				</div>

				<div class="stack">
					<h3 class="calc__breakdown-heading | text-xl office-costs__sub-heading">How your office spends it</h3>
					<div class="office-costs__category-grid" data-occv2-category-slot></div>
				</div>
				
				<div class="stack">
					<h3 class="calc__breakdown-heading | text-xl office-costs__sub-heading">What your office quietly funds</h3>
					<p class="calc__breakdown-prose | text-m">The living-wage, climate and community value already bundled into a standard office shop.</p>
					<div class="calc__compare" data-occv2-value-add></div>
				</div>	

			</div>
		</details>

		<?php two57_calc_share( [
			'title'       => 'save your budget, share it, compare it on',
			'email_title' => 'Email me this breakdown',
			'email_body'  => 'The line-by-line breakdown with sources, straight to your inbox, ready to take to finance or procurement.',
			'copy_body'   => 'Same inputs, same numbers — your team clicks the link and sees the exact same configuration.',
		] ); ?>

		<dialog class="office-costs__compare" data-scenario-compare-dialog aria-labelledby="office-costs-compare-title">
			<div class="office-costs__compare-head">
				<h2 id="office-costs-compare-title" class="office-costs__compare-title | text-xl">Compare scenarios</h2>
				<button type="button" class="office-costs__compare-close" data-scenario-compare-close aria-label="Close">&times;</button>
			</div>
			<div class="office-costs__compare-grid" data-scenario-compare-grid></div>
		</dialog>

	</div>

</section>