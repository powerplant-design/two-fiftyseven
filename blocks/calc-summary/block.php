<?php
/**
 * 257 Calc Summary — ACF block render template.
 *
 * Compact dashboard widget: two sliders (team size + avg days/week) feed four
 * linked cards — annual cost at 257, savings vs a private office, net carbon
 * position, and hours-to-impact giving. Each card is a whole-card link to the
 * detailed calculator that owns that figure, so this block is a teaser that
 * funnels into the full calculators.
 *
 * ACF fields:
 *   cs_eyebrow       — optional small label above the heading (text)
 *   cs_heading       — H1 heading (text)
 *   cs_tagline       — intro paragraph below the heading (textarea)
 *   link_inclusions  — "see what's included" destination (page_link)
 *   link_office_costs — savings card destination (page_link)
 *   link_carbon      — carbon card destination (page_link)
 *   link_giving      — giving card destination (page_link)
 *   colour_space     — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/calc-summary.js
 * Root selector: [data-js="calc-summary"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow       = get_field( 'cs_eyebrow' );
$heading       = get_field( 'cs_heading' );
$tagline       = get_field( 'cs_tagline' );
$colour_space  = get_field( 'colour_space' ) ?: 'forest';
$allowed_cs    = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed_cs, true ) ) {
	$colour_space = 'forest';
}

$link_inclusions  = get_field( 'link_inclusions' ) ?: '/pricing/';
$link_office_costs = get_field( 'link_office_costs' ) ?: '/calculator/office-costs/';
$link_carbon      = get_field( 'link_carbon' ) ?: '/calculator/office-carbon/';
$link_giving      = get_field( 'link_giving' ) ?: '/calculator/hours-to-impact/';
?>

<section
	class="calc-summary | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php two57_calc_intro( (string) $eyebrow, (string) $heading, (string) $tagline, (bool) $is_preview ); ?>

		<div data-js="calc-summary">

			<!-- ── Inputs row: team size + avg days/week ─────────────── -->
			<div class="calc__inputs calc-summary__inputs" data-scroll data-scroll-repeat>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Team size</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<div class="calc__slider" data-cs-team-slider>
								<input type="range" class="calc__slider-input" data-cs-team-range
									min="0" max="14" step="1" value="0" aria-label="Team size">
							</div>
						</div>
						<div class="calc-summary__readout">
							<output class="calc__slider-value" data-cs-team-out aria-live="polite">1</output>
						</div>
					</div>
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Avg days/week</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<div class="calc__slider" data-cs-days-slider>
								<input type="range" class="calc__slider-input" data-cs-days-range
									min="0" max="4" step="1" value="2" aria-label="Average days per week">
							</div>
						</div>
						<div class="calc-summary__readout">
							<output class="calc__slider-value" data-cs-days-out aria-live="polite">3</output>
						</div>
					</div>
				</div>

			</div>

			<!-- ── 4 linked result cards ─────────────────────────────── -->
			<div class="calc-summary__cards" role="status" aria-live="polite" data-scroll data-scroll-repeat>

				<a class="calc-summary__card calc-summary__card--cost" href="<?php echo esc_url( $link_inclusions ); ?>">
					<span class="calc-summary__eyebrow | text-monospace text-xs">At two/fiftyseven</span>
					<span class="calc-summary__figure" data-cs-cost>$0<span class="calc-summary__figure-unit">/yr</span></span>
					<span class="calc-summary__sub">all-in, nothing extra.</span>
					<span class="calc-summary__link">see what's included <span class="calc-summary__caret" aria-hidden="true"></span></span>
				</a>

				<a class="calc-summary__card calc-summary__card--saving" href="<?php echo esc_url( $link_office_costs ); ?>">
					<span class="calc-summary__eyebrow | text-monospace text-xs">You'd save</span>
					<span class="calc-summary__figure" data-cs-save>$0</span>
					<span class="calc-summary__sub">vs running your own central-Wellington office at <span data-cs-private>$0</span>/yr.</span>
					<span class="calc-summary__link">see how it adds up <span class="calc-summary__caret" aria-hidden="true"></span></span>
				</a>

				<a class="calc-summary__card calc-summary__card--carbon" href="<?php echo esc_url( $link_carbon ); ?>">
					<span class="calc-summary__eyebrow | text-monospace text-xs">Carbon position</span>
					<span class="calc-summary__figure" data-cs-carbon>0<span class="calc-summary__figure-unit"> t CO&#8322;e/yr</span></span>
					<span class="calc-summary__sub">after 200% verified offset, scaled by team and days/week.</span>
					<span class="calc-summary__link">see the methodology <span class="calc-summary__caret" aria-hidden="true"></span></span>
				</a>

				<a class="calc-summary__card calc-summary__card--giving" href="<?php echo esc_url( $link_giving ); ?>">
					<span class="calc-summary__eyebrow | text-monospace text-xs">Hours to impact</span>
					<span class="calc-summary__figure" data-cs-giving>$0</span>
					<span class="calc-summary__sub">of subsidised space funded by your team's hours, in a working year.</span>
					<span class="calc-summary__link">see where it goes <span class="calc-summary__caret" aria-hidden="true"></span></span>
				</a>

			</div>

		</div>

	</div>

</section>