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

/**
 * Bind a calc's numeric stepper — range input + −/+ buttons + value readout.
 * The wiring is identical across every calculator engine (team size in
 * workspace-pricing / hours-to-impact / office-carbon, people in
 * meet-pricing); only the scale differs — value-based for the team calcs,
 * index-based for meet's stepped people scale.
 *
 * Selectors are passed as strings so each engine keeps its own data-attr
 * naming (`[data-calc-team-*]` vs `[data-calc-people-*]`).
 *
 * @param {HTMLElement} root  Calc root holding the stepper nodes.
 * @param {Object}      cfg
 *   @param {string}         cfg.rangeSel  selector for the <input type="range">
 *   @param {string}         cfg.sliderSel selector for the slider wrapper (--pct)
 *   @param {string}         cfg.outSel    selector for the <output> readout
 *   @param {string}         cfg.decSel    selector for the − button
 *   @param {string}         cfg.incSel    selector for the + button
 *   @param {number}         cfg.max       max index (drives --pct + button disable)
 *   @param {function(number):number} cfg.valueFor  index → displayed value
 *   @param {function():number} cfg.current returns the current index
 *   @param {function(number):void} cfg.onUpdate fired after clamp with the new index
 * @returns {{ paint: (i:number)=>void, paintCurrent: () => void }}
 */
export function bindStepper( root, cfg ) {
	const range = root.querySelector( cfg.rangeSel );
	const slider = root.querySelector( cfg.sliderSel );
	const out = root.querySelector( cfg.outSel );
	const dec = root.querySelector( cfg.decSel );
	const inc = root.querySelector( cfg.incSel );

	function paint( idx ) {
		if ( range ) range.value = String( idx );
		if ( slider ) slider.style.setProperty( '--pct', `${ cfg.max > 0 ? ( idx / cfg.max ) * 100 : 0 }%` );
		if ( out ) out.value = String( cfg.valueFor( idx ) );
		if ( dec ) dec.disabled = idx <= 0;
		if ( inc ) inc.disabled = idx >= cfg.max;
	}

	function set( idx ) {
		const clamped = Math.max( 0, Math.min( cfg.max, idx ) );
		paint( clamped );
		if ( cfg.onUpdate ) cfg.onUpdate( clamped );
	}

	if ( range ) range.addEventListener( 'input', () => set( parseInt( range.value, 10 ) ) );
	if ( dec ) dec.addEventListener( 'click', () => set( cfg.current() - 1 ) );
	if ( inc ) inc.addEventListener( 'click', () => set( cfg.current() + 1 ) );

	return {
		paint,
		paintCurrent() { paint( cfg.current() ); },
	};
}

/**
 * Make bounded number inputs stepper-only — typed digits never enter, so
 * values can't exceed min/max; native up/down arrows + spinner buttons move
 * them. Capture phase + stopPropagation so Locomotive Scroll can't hijack the
 * arrows. Used by the weeks/hours fields (office-carbon + hours-to-impact).
 *
 * @param {NodeList|HTMLElement[]} inputs  The number inputs to guard.
 */
export function restrictStepperInputs( inputs ) {
	const stepperOnly = [ 'ArrowUp', 'ArrowDown', 'Tab', 'Enter' ];
	inputs.forEach( ( input ) => {
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowUp' || e.key === 'ArrowDown' ) {
				e.stopPropagation();
				return;
			}
			if ( ! stepperOnly.includes( e.key ) ) {
				e.preventDefault();
			}
		}, { capture: true } );
	} );
}

/**
 * Proxy the "Show working" trigger button into the full-width <details>
 * breakdown (shared by every calc with a disclosure panel). The <details>
 * lives outside the calc root, so it's looked up by id.
 *
 * @param {HTMLElement} root       Calc root holding [data-breakdown-trigger].
 * @param {string}      detailsId  id of the <details> element.
 */
export function bindBreakdownTrigger( root, detailsId ) {
	const trigger = root.querySelector( '[data-breakdown-trigger]' );
	const details = document.getElementById( detailsId );
	if ( ! trigger || ! details ) return;

	trigger.addEventListener( 'click', () => {
		const wasOpen = details.open;
		details.open = ! wasOpen;
		trigger.setAttribute( 'aria-expanded', String( ! wasOpen ) );
		if ( ! wasOpen ) {
			details.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	} );
	details.addEventListener( 'toggle', () => {
		trigger.setAttribute( 'aria-expanded', String( details.open ) );
	} );
}

/**
 * Open/close the .calc-source tooltip popups on trigger click + click-away.
 * Shared by workspace-pricing and office-carbon — the two calcs that render
 * cited breakdown rows.
 *
 * @param {HTMLElement} scope  The element scoping the .calc-source nodes.
 */
export function bindSourceTooltips( scope ) {
	scope.addEventListener( 'click', ( e ) => {
		const trigger = e.target.closest( '.calc-source__trigger' );
		if ( trigger ) {
			const wrap = trigger.closest( '.calc-source' );
			const isOpen = wrap.dataset.open === 'true';
			scope.querySelectorAll( '.calc-source[data-open="true"]' ).forEach( ( el ) => { el.dataset.open = 'false'; } );
			wrap.dataset.open = isOpen ? 'false' : 'true';
			e.stopPropagation();
		} else {
			scope.querySelectorAll( '.calc-source[data-open="true"]' ).forEach( ( el ) => { el.dataset.open = 'false'; } );
		}
	} );
}