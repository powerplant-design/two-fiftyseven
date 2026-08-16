<?php
/**
 * 257 Workspace Pricing Calculator — ACF block render template.
 *
 * Compares running a private central-Wellington office against being at 257
 * for a 1–15 person team. Every team member picks their own membership tier;
 * the total is the sum of their rates.
 *
 * ACF fields:
 *   wp_eyebrow     — optional small label above the heading (text)
 *   wp_heading     — H1 heading (text)
 *   wp_tagline     — intro paragraph below the heading (textarea)
 *   colour_space   — neutral / forest / purple / maroon (select)
 *
 * Engine: assets/js/modules/workspace-pricing.js
 * Root selector: [data-js="calc-office-costs"]
 *
 * @var array  $block      Block settings and attributes from ACF.
 * @var string $content    Rendered inner blocks HTML (unused).
 * @var bool   $is_preview True when rendering the block preview in the editor.
 * @var int    $post_id    The current post/page ID.
 */

$eyebrow      = get_field( 'wp_eyebrow' );
$heading      = get_field( 'wp_heading' );
$tagline      = get_field( 'wp_tagline' );
$colour_space = get_field( 'colour_space' ) ?: 'forest';
$allowed      = [ 'neutral', 'forest', 'purple', 'maroon' ];
if ( ! in_array( $colour_space, $allowed, true ) ) {
	$colour_space = 'forest';
}

// Read SSOT values for no-JS / server-side display
$annual_pct  = function_exists( 'get_field' ) ? (float) get_field( 'annual_prepay_discount_pct', 'option' ) : 10;
if ( $annual_pct <= 0 ) {
	$annual_pct = 10;
}
$mem_ded = function_exists( 'get_field' ) ? (int) get_field( 'membership_dedicated_monthly', 'option' ) : 659;
?>

<section
	class="workspace-pricing | block"
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

		<div data-js="calc-office-costs">

			<div class="calc__body">

			<div class="calc__inputs | stack" data-scroll data-scroll-repeat>
				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s | cluster">
						Team size
						<span class="calc-source">
							<button class="calc-source__trigger" type="button" aria-label="About team size">i</button>
							<span class="calc-source__pop" role="tooltip">Up to 15 members. Bigger teams are a conversation, talk to Ash.</span>
						</span>
					</span>
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
					<span class="calc__field-label | text-monospace text-s | cluster">
						Memberships
						<span class="calc-source">
							<button class="calc-source__trigger" type="button" aria-label="About memberships">i</button>
							<span class="calc-source__pop" role="tooltip">Each member picks their own tier. Flexi tiers bill monthly only.</span>
						</span>
					</span>
					<ul class="calc__roster" data-calc-roster></ul>
					<div class="workspace-pricing__annual-card calc__option-card" data-calc-annual-wrap hidden>
						<label class="calc__option-head | calc__check">
							<input type="checkbox" data-calc-annual aria-label="Pay annually and save on Dedicated Memberships">
							<span class="calc__check-box" aria-hidden="true"></span>
							<span class="workspace-pricing__annual-title | text-m">Pay annually and save <?php echo esc_html( number_format( $annual_pct, 0 ) ); ?>% on Dedicated Memberships</span>
						</label>
					</div>
				</div>

				<div class="calc__field">
					<span class="calc__field-label | text-monospace text-s | cluster">
						Private office lease term
						<span class="calc-source">
							<button class="calc-source__trigger" type="button" aria-label="About the private office lease term">i</button>
							<span class="calc-source__pop" role="tooltip">Used to compare against a private office, not a commitment you're making here. A longer lease term spreads the fit-out + legal costs, which lowers the estimated private-office bill — so this shows the fair comparison for your situation.</span>
						</span>
					</span>
					<div class="calc__radio-group | cluster" role="radiogroup" aria-label="Private office lease term" data-calc-commitment-group>
						<button type="button" role="radio" class="calc__radio-label" data-calc-commitment="1" aria-checked="false">1 yr</button>
						<button type="button" role="radio" class="calc__radio-label" data-calc-commitment="3" aria-checked="false">3 yr</button>
						<button type="button" role="radio" class="calc__radio-label" data-calc-commitment="5" aria-checked="false">5 yr</button>
					</div>
				</div>
			</div>

			<aside class="calc__result | stack calc__result--sticky" aria-label="Live result">
				<div class="calc__result-grid" role="status" aria-live="polite">
					<div data-result-headline>
						<div class="calc__result-grid-headline">
							<div class="calc__result-col">
								<span class="calc__result-label | text-l">Your team's monthly total</span>
								<span class="calc__result-figure | text-3xl" data-calc-mini-total>$0</span>
								<span class="calc__result-unit | text-monospace text-xs">ex GST &middot; memberships only</span>
							</div>
							<div class="calc__result-col workspace-pricing__result-col--accent">
								<span class="calc__result-label | text-l">Annual total</span>
								<span class="calc__result-figure | text-3xl" data-calc-ours-total>$0</span>
								<span class="calc__result-unit | text-monospace text-xs"><span data-result-team-size>0</span> <span data-result-team-suffix>members</span></span>
							</div>
						</div>
					</div>

					<div class="workspace-pricing__chart | stack" data-result-compare>
						<!-- <h3 class="calc__result-label | text-l">Once a year, to house this team</h3> -->
						<div class="workspace-pricing__chart-row">
							<div class="workspace-pricing__chart-headline">
								<span class="workspace-pricing__chart-label">Private office</span>
								<span class="workspace-pricing__chart-value" data-result-private>$0</span>
							</div>
							<div class="workspace-pricing__bar-wrap">
								<div class="workspace-pricing__bar workspace-pricing__bar--private" style="--bar-pct: 100%"></div>
							</div>
						</div>
						<div class="workspace-pricing__chart-row">
							<div class="workspace-pricing__chart-headline">
								<span class="workspace-pricing__chart-label">Other coworking</span>
								<span class="workspace-pricing__chart-value">
									<span data-result-other-coworking-low>$0</span>&ndash;<span data-result-other-coworking-high>$0</span>
								</span>
							</div>
							<div class="workspace-pricing__bar-wrap">
								<div class="workspace-pricing__bar workspace-pricing__bar--coworking-low" style="--bar-pct: 0%"></div>
								<div class="workspace-pricing__bar workspace-pricing__bar--coworking-high" style="--bar-pct: 0%"></div>
							</div>
						</div>
						<div class="workspace-pricing__chart-row workspace-pricing__chart-row--ours">
							<div class="workspace-pricing__chart-headline">
								<span class="workspace-pricing__chart-label">two/fiftyseven</span>
								<span class="workspace-pricing__chart-value" data-result-ours-annual>$0</span>
							</div>
							<div class="workspace-pricing__bar-wrap">
								<div class="workspace-pricing__bar workspace-pricing__bar--ours" style="--bar-pct: 0%"></div>
							</div>
						</div>
						<div class="workspace-pricing__chart-savings">
							<div class="workspace-pricing__chart-headline">
								<span class="workspace-pricing__chart-saving">Save vs a private office</span>
								<span class="workspace-pricing__chart-saving-value" data-result-save-private>$0</span>
							</div>
							<div class="workspace-pricing__chart-headline">
								<span class="workspace-pricing__chart-saving">Save vs other coworking</span>
								<span class="workspace-pricing__chart-saving-value">
									<span data-result-save-coworking-low>$0</span>&ndash;<span data-result-save-coworking-high>$0</span>
								</span>
							</div>
						</div>
					</div>

					<p class="calc__result-empty | text-s text-monospace" data-result-empty>Select your team size to see your number</p>
				</div>

				<button
					type="button"
					class="calc__breakdown-trigger"
					data-breakdown-trigger
					aria-controls="workspace-pricing-methodology"
					aria-expanded="false"
				>
					<span class="text-monospace">Show working</span>
					<span class="calc__breakdown-caret" aria-hidden="true"></span>
				</button>
			</aside>

		</div>

			<details class="calc__breakdown" id="workspace-pricing-methodology">
				<summary aria-hidden="true" class="calc__breakdown-summary | text-monospace text-s">Breakdown</summary>
				<div class="calc__breakdown-body">

					<div class="calc__breakdown-grid">
						<!-- Left column: private office line items -->
						<div class="calc__breakdown-col | stack">
							<h3 class="calc__breakdown-heading | text-l">Estimated private office costs <?php echo esc_html( gmdate( 'Y' ) ); ?></h3>
							<div class="calc__compare" data-calc-private-lines></div>
							<div class="calc__compare">
								<div class="calc__compare-row">
									<div class="calc__compare-row-label">Monthly total</div>
									<div class="calc__compare-row-value" data-calc-private-monthly>$0</div>
								</div>
								<div class="calc__compare-row calc__compare-row--total">
									<div class="calc__compare-row-label font-bold">Annual total</div>
									<div class="calc__compare-row-value" data-calc-private-total>$0</div>
								</div>
							</div>
							<p class="calc__breakdown-prose | text-m">
								Includes rent, outgoings, fit-out amortised over your commitment, and the team's admin + MHFR burden. Booking software only kicks in at 10+ people.
							</p>
						</div>

						<!-- Right column: memberships + giving -->
						<div class="calc__breakdown-col | stack">
							<h3 class="calc__breakdown-heading | text-l">Your memberships</h3>
							<div class="calc__compare" data-calc-ours-lines></div>
							<div class="calc__compare">
								<div class="calc__compare-row" data-calc-dedicated-save-row hidden>
									<div class="calc__compare-row-label">Dedicated pays <?php echo esc_html( number_format( $annual_pct, 0 ) ); ?>% less when paid annually</div>
									<div class="calc__compare-row-value" data-calc-dedicated-save>$0</div>
								</div>
								<div class="calc__compare-row">
									<div class="calc__compare-row-label">Monthly total</div>
									<div class="calc__compare-row-value" data-calc-mini-total>$0</div>
								</div>
								<div class="calc__compare-row calc__compare-row--total">
									<div class="calc__compare-row-label font-bold">Annual total</div>
									<div class="calc__compare-row-value" data-calc-ours-total>$0</div>
								</div>
							</div>

							<h3 class="calc__breakdown-heading | text-l">Kaupapa bridge</h3>
							<div class="calc__stat">
								<span class="calc__stat-label | text-monospace text-xs">Time your team spends at 257 funds</span>
								<span class="calc__stat-value | text-l" data-calc-bridge-figure>$0</span>
								<span class="calc__stat-unit | text-s">of subsidised space for others, valued at $1/hr (dedicated counts 5 days)</span>
							</div>
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