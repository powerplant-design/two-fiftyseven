<?php
/**
 * 257 Hours to Impact Calculator — ACF block render template.
 *
 * Translates a team's hours at two/fiftyseven into the dollar value of
 * subsidised space funded via the Impact Discount ($1/person-hour).
 *
 * ACF fields:
 *   ht_eyebrow     — optional small label above the heading (text)
 *   ht_heading     — H1 heading (text)
 *   ht_tagline     — intro paragraph below the heading (textarea)
 *   colour_space   — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/hours-to-impact.js
 * Root selector: [data-js="calc-hours-to-impact"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'ht_eyebrow' );
$heading      = get_field( 'ht_heading' );
$tagline      = get_field( 'ht_tagline' );
$colour_space = get_field( 'colour_space' ) ?: 'forest';
$allowed      = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed, true ) ) {
	$colour_space = 'forest';
}

// Read SSOT values for server-side fallback / no-JS display
$giving_rate = function_exists( 'get_field' ) ? (float) get_field( 'giving_rate_per_person_hour', 'option' ) : 1;
$paid_forward = function_exists( 'get_field' ) ? get_field( 'paid_forward_total_display', 'option' ) : '$450,000+';
?>

<section
	class="hours-to-impact | block"
	data-color-space="<?php echo esc_attr( $colour_space ); ?>"
>

	<div class="wrapper">

		<?php if ( $eyebrow || $heading || $tagline ) : ?>
			<div class="calc__intro | stack" data-scroll data-scroll-repeat>
				<?php if ( $eyebrow ) : ?>
					<p class="calc__eyebrow | text-monospace text-s">
						<?php echo esc_html( $eyebrow ); ?>
					</p>
				<?php endif; ?>
				<?php if ( $heading ) : ?>
					<h1 class="calc__heading | text-3xl text-wrap-balance"><?php echo esc_html( $heading ); ?></h1>
				<?php endif; ?>
				<?php if ( $tagline ) : ?>
					<p class="calc__tagline | text-l text-wrap-balance">
						<?php echo nl2br( esc_html( $tagline ) ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<p style="opacity:0.5;text-align:center;padding:1rem;">Add a heading in the block settings →</p>
		<?php endif; ?>

		<div class="calc__body" data-js="calc-hours-to-impact">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Team size</span>
					<div class="calc__slider-row">
						<div class="calc__slider-controls">
							<button type="button" class="calc__stepper-btn" data-calc-team-dec aria-label="Decrease team size">&minus;</button>
							<div class="calc__slider" data-calc-team-slider>
								<input type="range" class="calc__slider-input" data-calc-team-range
									min="0" max="30" step="1" value="0" aria-label="Team size">
							</div>
							<button type="button" class="calc__stepper-btn" data-calc-team-inc aria-label="Increase team size">&plus;</button>
						</div>
						<output class="calc__slider-value" data-calc-team-out aria-live="polite">0</output>
					</div>
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s">Days per week in office</span>
					<div class="calc__radio-group | cluster" role="radiogroup" aria-label="Days per week in office" data-calc-days-group>
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
						<small class="calc__microcopy | text-s">NZ standard: 46 (52 minus 4 leave minus 11 stat hols)</small>
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

			<aside class="calc__result | stack" aria-label="Live result">
				<div class="calc__result-grid" role="status" aria-live="polite">
					<div class="calc__result-col">
						<span class="calc__result-label | text-l">Your team's hours, a year</span>
						<span class="calc__result-figure | text-3xl" data-calc-result-hours>0 hrs</span>
						<span class="calc__result-unit | text-monospace text-xs">person-hours combined</span>
					</div>
					<div class="calc__result-col hours-to-impact__result-col--accent">
						<span class="calc__result-label | text-l">Subsidised space funded</span>
						<span class="calc__result-figure | text-3xl" data-calc-giving>$0</span>
						<span class="calc__result-unit | text-monospace text-xs">at $<?php echo esc_html( number_format( $giving_rate, 0 ) ); ?> per person-hour</span>
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

			<details class="calc__breakdown" id="methodology">
				<summary aria-hidden="true" class="calc__breakdown-summary | text-monospace text-s">Breakdown</summary>
				<div class="calc__breakdown-body">

					<div class="calc__breakdown-grid">
						<!-- Left column: methodology + per-person rate -->
						<div class="calc__breakdown-col | stack">
							<h3 class="calc__breakdown-heading | text-l">How the figure is calculated</h3>
							<p class="calc__breakdown-prose | text-m">
								The ratio is derived from five years of measured revenue versus measured discount given at two/fiftyseven. The $<?php echo esc_html( number_format( $giving_rate, 0 ) ); ?> per person-hour figure sits below the actual realised ratio in each of those years &middot; so the calculator under-promises by design. Reality has consistently outpaced this figure.
							</p>

							<h3 class="calc__breakdown-heading | text-l">Per-person rate this year</h3>
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">One person at two/fiftyseven funds</span>
								<span class="calc__stat-value | text-l" data-calc-per-person-hours>0</span>
								<span class="calc__stat-unit | text-s">hours of subsidised space per working year</span>
							</div>
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">Roughly</span>
								<span class="calc__stat-value | text-l" data-calc-per-person-giving>$0</span>
								<span class="calc__stat-unit | text-s">in dollar terms per person, per year</span>
							</div>
						</div>

						<!-- Right column: the rest -->
						<div class="calc__breakdown-col | stack">
							<h3 class="calc__breakdown-heading | text-l">Working year defaults</h3>
							<p class="calc__breakdown-prose | text-m">
								46 weeks &times; 5 days &times; 8 hours = 1,840 hrs per person per year. NZ standard: 52 weeks minus 4 weeks annual leave minus 11 stat holidays.
							</p>

							<h3 class="calc__breakdown-heading | text-l">Ratio source</h3>
							<p class="calc__breakdown-prose | text-m">
								$<?php echo esc_html( number_format( $giving_rate, 2 ) ); ?> per person-hour, verified across 5 years of two/fiftyseven internal usage and subsidy records. Reality has consistently outpaced this figure.
							</p>

							<h3 class="calc__breakdown-heading | text-l">All-time redistributed</h3>
							<p class="calc__breakdown-prose | text-m">
								<?php echo esc_html( $paid_forward ); ?> across 5,000 meetings + events and 1,500 workdays (current as of Marketing Association mihi 2026).
							</p>
						</div>
					</div>

				</div>
			</details>

			<div class="calc__share | stack" data-calc-share>
			<p class="calc__share-eyebrow | text-monospace text-s">Take this with you</p>
			<h2 class="calc__share-title | text-3xl text-wrap-balance">save your number, share it, send it on</h2>

			<div class="calc__share-row">

				<!-- Email card -->
				<div class="calc__share-card | stack">
					<h3 class="calc__share-card-title | text-l font-bold">Email me these numbers</h3>
					<p class="calc__share-card-body">Get the numbers and a one-line summary in your inbox, ready to forward to your team.</p>
					<form class="calc__share-form | cluster" data-calc-share-email novalidate>
						<input
							class="calc__share-input"
							type="email" name="email"
							placeholder="you@example.com"
							autocomplete="email"
							data-calc-share-email-input
							aria-label="Your email"
							required
						>
						<input
							class="calc__share-honeypot visually-hidden"
							type="text" name="website" tabindex="-1" autocomplete="off"
							aria-hidden="true"
							data-calc-share-honeypot
						>
						<button class="btn" data-type="primary" type="submit" data-calc-share-submit>Send →</button>
						<p class="calc__share-consent | text-s">
							<label class="calc__share-check">
								<input type="checkbox" name="consent" checked data-calc-share-consent>
								<span class="calc__share-consent-text">By submitting, I agree to two/fiftyseven contacting me to follow up about these numbers — see the <a href="/contact-policy/">Contact Policy</a></span>
							</label>
						</p>
					</form>
					<p class="calc__share-status | text-xs text-monospace" data-calc-share-status role="status" aria-live="polite"></p>
				</div>

				<!-- Copy link card -->
				<div class="calc__share-card | stack">
					<h3 class="calc__share-card-title | text-l font-bold">Share the numbers</h3>
					<p class="calc__share-card-body">Same numbers, any browser, your team clicks and sees the exact same numbers.</p>
					<button class="btn" data-type="secondary" type="button" data-calc-share-copy>Copy link →</button>
				</div>

			</div>
		</div>

		</div>

	</div>

</section>
