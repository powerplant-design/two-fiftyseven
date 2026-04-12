/**
 * 257 Impact — fluid font-size fitting.
 *
 * For each `.impact` block, measures every `.impact__row` at a tall reference
 * font size, finds the widest row, then scales all rows down so the widest one
 * exactly fills the container. All rows share that one font size so the rhythm
 * is consistent regardless of content length.
 *
 * Works on both desktop (row = number + gap + label in a single line) and
 * mobile (row = number stacked over label) because CSS flex-direction controls
 * the layout — JS just measures whatever is rendered.
 *
 * A ResizeObserver re-runs the fit whenever the block's width changes (window
 * resize, orientation change, panel open/close in the editor).
 */

const BLOCK_SELECTOR = '[data-js="impact"]';
const ROWS_SELECTOR  = '[data-js="impact-rows"]';
const ROW_SELECTOR   = '[data-js="impact-row"]';

/** Large reference size used for measurement — must be big enough that all
 *  rows overflow the container so we can compute the scale accurately. */
const REF_SIZE = 200;

/** @type {Map<Element, ResizeObserver>} */
const observers = new Map();

/**
 * Fit a single impact block.
 * @param {HTMLElement} block
 */
function fitBlock( block ) {
	const rows = block.querySelector( ROWS_SELECTOR );
	if ( ! rows ) return;

	// Set a tall reference size so every row overflows → gives accurate scrollWidth.
	rows.style.fontSize = REF_SIZE + 'px';

	// Measure AFTER setting the ref size. Use rows.offsetWidth (not block.offsetWidth)
	// so the section's padding-inline is already excluded from the target width.
	const containerWidth = rows.offsetWidth;
	if ( ! containerWidth ) return;

	const rowEls = Array.from( rows.querySelectorAll( ROW_SELECTOR ) );
	if ( ! rowEls.length ) return;

	// Find the scale needed so the widest row exactly fits the container.
	let minScale = Infinity;
	for ( const row of rowEls ) {
		const rowWidth = row.scrollWidth;
		if ( rowWidth > 0 ) {
			minScale = Math.min( minScale, containerWidth / rowWidth );
		}
	}

	if ( ! isFinite( minScale ) ) return;

	rows.style.fontSize = ( REF_SIZE * minScale ) + 'px';
}

/**
 * Fit all impact blocks on the page.
 */
export function fitAll() {
	document.querySelectorAll( BLOCK_SELECTOR ).forEach( fitBlock );
}

/**
 * Initialise fit-text for all current impact blocks and attach a
 * ResizeObserver to each so they re-fit on any size change.
 */
export function initImpact() {
	// Wait for fonts before the first measurement — display font metrics affect width.
	document.fonts.ready.then( () => {
		fitAll();

		document.querySelectorAll( BLOCK_SELECTOR ).forEach( ( block ) => {
			if ( observers.has( block ) ) return;

			const ro = new ResizeObserver( () => fitBlock( block ) );
			ro.observe( block );
			observers.set( block, ro );
		} );
	} );
}

/**
 * Tear down ResizeObservers — call before Swup swaps the DOM.
 */
export function destroyImpact() {
	observers.forEach( ( ro ) => ro.disconnect() );
	observers.clear();
}
