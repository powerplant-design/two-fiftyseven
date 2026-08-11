/**
 * two/fiftyseven · inject-prices
 * ----------------------------------------------------------------------------
 * Reads the canonical PRICES object exposed by calc-office-costs.js (on
 * window.twofiftyseven.prices) and writes the formatted price/name into every
 * [data-price] element on the page after DOM ready.
 *
 * Usage:
 *   <span data-price="dedicated">$0</span>                 → "$659"
 *   <span data-price="dedicated" data-price-format="name">…</span>
 *                                                          → "Dedicated 7 days/week"
 *   <span data-price="dedicated" data-price-format="short">…</span>
 *                                                          → "Dedicated"
 *   <span data-price="dedicated" data-price-format="full">…</span>
 *                                                          → "Dedicated 7 days/week · $659/monthly"
 *   <span data-price="dedicated" data-price-format="price-unit">…</span>
 *                                                          → "$659/monthly"
 *
 * Load AFTER calc-office-costs.js — that script populates the PRICES global.
 * Existing text content acts as a graceful fallback if JS is disabled.
 * ============================================================================
 */
(function () {
  function init() {
    var prices = (window.twofiftyseven && window.twofiftyseven.prices) || null;
    if (!prices) {
      console.warn('[inject-prices] PRICES global not found; ensure calc-office-costs.js loaded first');
      return;
    }
    document.querySelectorAll('[data-price]').forEach(function (el) {
      var key = el.getAttribute('data-price');
      var p = prices[key];
      if (!p) {
        console.warn('[inject-prices] unknown price key:', key);
        return;
      }
      var fmt = el.getAttribute('data-price-format') || 'price';
      var out;
      switch (fmt) {
        case 'name':       out = p.name; break;
        case 'short':      out = p.shortName; break;
        case 'full':       out = p.name + ' · $' + p.price + '/' + p.unit; break;
        case 'price-unit': out = '$' + p.price + '/' + p.unit; break;
        default:           out = '$' + p.price;
      }
      el.textContent = out;
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
