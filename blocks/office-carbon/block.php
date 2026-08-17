<?php
/**
 * 257 Office Carbon Calculator — ACF block render template.
 *
 * Compares the operational carbon footprint of a private central-Wellington
 * office against being at two/fiftyseven, for sustainability / ESG /
 * procurement leads. Tadpole ACE 2025 emission factors, verified offset at
 * 200% (public-facing claim), every figure linked to its primary source.
 *
 * ACF fields:
 *   oc_eyebrow     — optional small label above the heading (text)
 *   oc_heading     — H1 heading (text)
 *   oc_tagline     — intro paragraph below the heading (textarea)
 *   colour_space   — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/office-carbon.js
 * Root selector: [data-js="calc-office-carbon"]
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
	class="office-carbon | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php two57_calc_intro( (string) $eyebrow, (string) $heading, (string) $tagline, (bool) $is_preview ); ?>

		<div class="calc__body" data-js="calc-office-carbon">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Team size</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-calc-team-dec aria-label="Decrease team size">&minus;</button>
							<div class="calc__slider" data-calc-team-slider>
<input type="range" class="calc__slider-input" data-calc-team-range
								min="0" max="15" step="1" value="0" aria-label="Team size">
							</div>
							<button type="button" class="calc__stepper-btn" data-calc-team-inc aria-label="Increase team size">&plus;</button>
						</div>
						<output class="calc__slider-value" data-calc-team-out aria-live="polite">0</output>
					</div>
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Days p/week in office</span>
					<div class="calc__radio-group calc__radio-group--days" role="radiogroup" aria-label="Days per week in office" data-calc-days-group>
						<button type="button" role="radio" class="calc__radio-label" data-calc-days="1" aria-checked="false">1</button>
						<button type="button" role="radio" class="calc__radio-label" data-calc-days="2" aria-checked="false">2</button>
						<button type="button" role="radio" class="calc__radio-label" data-calc-days="3" aria-checked="false">3</button>
						<button type="button" role="radio" class="calc__radio-label" data-calc-days="4" aria-checked="false">4</button>
						<button type="button" role="radio" class="calc__radio-label" data-calc-days="5" aria-checked="false">5</button>
					</div>
				</div>

				<div class="calc__fields-grid">
					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">Working weeks p/year</span>
						<input
							class="calc__input"
							type="number" min="1" max="52" value=""
							data-calc-weeks
							aria-label="Working weeks per year"
							placeholder="46"
						>
						<small class="calc__microcopy | text-s">NZ standard: 46 (52 minus 4 leave minus 2 stat hols)</small>
					</div>

					<div class="calc__field">
						<span class="calc__field-label | text-monospace text-s">Hours p/day</span>
						<input
							class="calc__input"
							type="number" min="1" max="24" step="0.5" value=""
							data-calc-hours
							aria-label="Hours per day"
							placeholder="8"
						>
						<small class="calc__microcopy | text-s">8 default: Adjust if your team runs longer</small>
					</div>
				</div>
			</div>

			<aside class="calc__result | stack calc__result--sticky" aria-label="Live result">
				<div class="calc__result-grid" role="status" aria-live="polite">
					<div class="calc__result-col">
						<span class="calc__result-label | text-l">Run your own office</span>
						<span class="calc__result-figure | text-3xl" data-calc-result-private>0 t</span>
						<span class="calc__result-unit | text-monospace text-xs">annual CO₂e · operational</span>
					</div>
					<div class="calc__result-col">
						<span class="calc__result-label | text-l">At two/fiftyseven</span>
						<span class="calc__result-figure | text-3xl" data-calc-result-ours>0 t</span>
						<span class="calc__result-unit | text-monospace text-xs">measured · before offset</span>
					</div>
					<div class="calc__result-col calc__result-col--accent">
						<span class="calc__result-label | text-l">Net after 200% offset</span>
						<span class="calc__result-figure | text-3xl" data-calc-result-positive>0 t</span>
						<span class="calc__result-unit | text-monospace text-xs">carbon-positive for your share</span>
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

					<div class="calc__breakdown-grid">
						<!-- Left column: private NZ office -->
						<div class="calc__breakdown-col | stack">
							<h3 class="calc__breakdown-heading | text-l">Run your own office · annual emissions</h3>
							<div class="calc__compare" data-calc-private-lines></div>
							<div class="calc__compare">
								<div class="calc__compare-row calc__compare-row--total">
									<div class="calc__compare-row-label font-bold">Total · private NZ office</div>
									<div class="calc__compare-row-value" data-calc-breakdown-total>0 t</div>
								</div>
							</div>
							<p class="calc__breakdown-prose | text-m">
								50 W/m² × your square-metre share, 0.5 kg/person-day of waste, and a 15 km round-trip mixed EV/ICE commute. Every line links to its primary source.
							</p>
						</div>

						<!-- Right column: measured at 257 + how far ahead -->
						<div class="calc__breakdown-col | stack">
							<h3 class="calc__breakdown-heading | text-l">At two/fiftyseven · measured</h3>
							<div class="calc__compare" data-calc-ours-lines></div>
							<div class="calc__compare">
								<div class="calc__compare-row calc__compare-row--total">
									<div class="calc__compare-row-label font-bold">Net at two/fiftyseven (after 200% offset)</div>
									<div class="calc__compare-row-value" data-calc-breakdown-net>0 t</div>
								</div>
							</div>

							<h3 class="calc__breakdown-heading | text-l">How far ahead two/fiftyseven is</h3>
							<div class="calc__compare">
								<div class="calc__compare-row">
									<div class="calc__compare-row-label">vs running your own office</div>
									<div class="calc__compare-row-value" data-calc-saved-figure>0 t</div>
								</div>
								<div class="calc__compare-row">
									<div class="calc__compare-row-label">carbon-positive total, your share</div>
									<div class="calc__compare-row-value" data-calc-net-figure>0 t</div>
								</div>
							</div>
						</div>
					</div>

					<div class="stack">

					<h3 class="calc__breakdown-heading | text-l">Net position after offsets</h3>
					<p class="calc__breakdown-prose | text-m">
						Ecotricity supply is Toitū climate positive at 125%. We then purchase 24 Ekos NZUs annually against the residual measured footprint. The combined position lands at approximately 200% — twice what we measure, publicly claimed and externally verifiable. Your team's share of the measured footprint, halved twice over by the offset position, is what the headline figure represents.
					</p>

					<h3 class="calc__breakdown-heading | text-l">Biodiversity credits · a separate stream</h3>
					<p class="calc__breakdown-prose | text-m">
						Your spend at two/fiftyseven also funds biodiversity restoration at Sanctuary Mountain Maungatautari (3,363 ha) via Ekos BioCredita. Per Ekos founder Sean Weaver: <em>&ldquo;Ekos biodiversity credits are not offsets of any kind. The Ekos BioCredita programme does not commodify nature or put a price on nature.&rdquo;</em> We list this stream separately and never aggregate it into the carbon offset percentage.
					</p>

					<h2 class="calc__breakdown-heading | text-xl">Numbers formatted for direct quotation</h2>
					<p class="calc__breakdown-prose | text-m">
						For your sustainability report — the figures below mirror your inputs above, with full source attribution for an ESG appendix or procurement summary. Copy the block and paste it under &ldquo;Scope metrics &mdash; our team&rdquo;.
					</p>
					<div class="calc__compare" data-calc-citation-block>
				<div class="calc__compare-row">
					<div class="calc__compare-row-label">Team size · days/week · weeks/year</div>
					<div class="calc__compare-row-value"><span data-calc-export-team>0</span> people · <span data-calc-export-days>0</span> d/wk · <span data-calc-export-weeks>0</span> wk/yr</div>
				</div>
				<div class="calc__compare-row">
					<div class="calc__compare-row-label">Private NZ office baseline (operational)</div>
					<div class="calc__compare-row-value" data-calc-export-private>0 t</div>
				</div>
				<div class="calc__compare-row">
					<div class="calc__compare-row-label">Measured at two/fiftyseven (Tadpole ACE 2025)</div>
					<div class="calc__compare-row-value" data-calc-export-ours>0 t</div>
				</div>
				<div class="calc__compare-row">
					<div class="calc__compare-row-label">Net position after 200% offset</div>
					<div class="calc__compare-row-value" data-calc-export-offset>0 t</div>
				</div>
				<div class="calc__compare-row">
					<div class="calc__compare-row-label">Methodology + offset mechanism</div>
					<div class="calc__compare-row-value">Tadpole ACE 2025 · Ekos NZUs + Ecotricity 125%</div>
				</div>
			</div>
					<button type="button" class="btn" data-type="secondary" data-calc-copy-citation>Copy citation block →</button>

					</div>

				</div>
		</details>

		<?php two57_calc_share(); ?>

	</div>

</section>