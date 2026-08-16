/**
 * Shared calculator helpers (formatting + keyboard nav).
 * Imported by workspace-pricing, hours-to-impact and meet-pricing so the
 * money/number formatting and the WAI-ARIA radio-roving pattern stay in one
 * place instead of three near-identical copies.
 */

/**
 * NZD currency, rounded to whole dollars (en-NZ wide no-break spacing).
 */
export function fmt$( n ) {
	return new Intl.NumberFormat( 'en-NZ', {
		style: 'currency',
		currency: 'NZD',
		maximumFractionDigits: 0,
	} ).format( Math.round( n ) );
}

/**
 * Plain integer with en-NZ thousands separators.
 */
export function fmtN( n ) {
	return new Intl.NumberFormat( 'en-NZ' ).format( Math.round( n ) );
}

/**
 * WAI-ARIA radio group roving tabindex. `radios` must already have click
 * handlers wired; this adds arrow-key movement + Enter/Space selection on a
 * capture-phase keydown so Locomotive Scroll can't intercept the arrows.
 * `onSelect( radio )` fires after focus moves (arrow keys) or on the same
 * element (Enter/Space).
 */
export function bindRovingRadio( radios, onSelect ) {
	const navKeys = [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ];

	radios.forEach( ( radio, idx ) => {
		radio.addEventListener( 'keydown', ( e ) => {
			if ( navKeys.includes( e.key ) ) {
				e.preventDefault();
				e.stopPropagation();
				let nextIdx;
				if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
					nextIdx = idx <= 0 ? radios.length - 1 : idx - 1;
				} else {
					nextIdx = idx >= radios.length - 1 ? 0 : idx + 1;
				}
				radios[ nextIdx ].focus();
				onSelect( radios[ nextIdx ] );
			} else if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				e.stopPropagation();
				onSelect( radio );
			}
		}, { capture: true } );
	} );
}