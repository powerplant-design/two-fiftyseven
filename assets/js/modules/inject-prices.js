/**
 * Two-Fifty-Seven · inject-prices
 * ----------------------------------------------------------------------------
 * Reads the canonical PRICES object from window.twofiftyseven.prices (populated
 * by the wp_head SSOT injector from ACF Options) and writes the formatted
 * price/name into every [data-price] element on the page.
 *
 * Usage:
 *   <span data-price="dedicated">$0</span>                            → "$659"
 *   <span data-price="dedicated" data-price-format="name">…</span>    → "Dedicated 7 days/week"
 *   <span data-price="dedicated" data-price-format="short">…</span>   → "Dedicated"
 *   <span data-price="dedicated" data-price-format="full">…</span>    → "Dedicated 7 days/week · $659/monthly"
 *   <span data-price="dedicated" data-price-format="price-unit">…</span> → "$659/monthly"
 *
 * Existing text content acts as a graceful fallback if JS is disabled.
 * ============================================================================
 */

export function initInjectPrices() {
	const prices = ( window.twofiftyseven && window.twofiftyseven.prices ) || null;
	if ( ! prices ) {
		return;
	}

	document.querySelectorAll( '[data-price]' ).forEach( ( el ) => {
		const key = el.getAttribute( 'data-price' );
		const p = prices[ key ];
		if ( ! p ) {
			console.warn( '[inject-prices] unknown price key:', key );
			return;
		}

		const fmt = el.getAttribute( 'data-price-format' ) || 'price';
		let out;

		switch ( fmt ) {
			case 'name':
				out = p.name;
				break;
			case 'short':
				out = p.shortName;
				break;
			case 'full':
				out = p.name + ' · $' + p.price + '/' + p.unit;
				break;
			case 'price-unit':
				out = '$' + p.price + '/' + p.unit;
				break;
			default:
				out = '$' + p.price;
		}

		el.textContent = out;
	} );
}
