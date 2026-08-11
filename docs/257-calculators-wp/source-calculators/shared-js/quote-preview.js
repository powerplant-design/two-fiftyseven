/* ───────────────────────────────────────────────────────────────────────
 * QUOTE PREVIEW · embeddable estimator widget
 *
 * Initialises every .quick-quote on the page. Reads behavioural config
 * from data-attributes on the widget root. Editorial copy (eyebrow,
 * title, sub) stays in the HTML so each page has full control.
 *
 * Config attributes on .quick-quote:
 *   data-default-people   default slider position (default: 6)
 *   data-default-hours    default hours slider position (default: 4)
 *   data-default-room     pre-select this room (e.g. "silver-linings")
 *   data-rooms            comma-separated room ids to keep visible;
 *                         if omitted, all rooms are shown
 *                         e.g. "silver-linings,studio,workshop,event"
 *   data-pricing-url      URL of the standalone full quote tool
 *                         (default: "/meetings/pricing/")
 *
 * State is passed through to data-pricing-url as URL params so the
 * standalone calc pre-fills.
 * ─────────────────────────────────────────────────────────────────────── */

(function() {
  function initWidget(root) {
    // ── Config from data-attributes ───────────────────────────────────
    const defaultPeople = parseInt(root.dataset.defaultPeople, 10) || 6;
    const defaultHours = parseInt(root.dataset.defaultHours, 10) || 4;
    const defaultRoom = root.dataset.defaultRoom || '';
    const visibleRooms = (root.dataset.rooms || '').trim();
    const pricingUrl = root.dataset.pricingUrl || '/meetings/pricing/';

    // ── State ─────────────────────────────────────────────────────────
    const state = {
      room: null,
      roomName: null,
      rates: { day: 0, hour: 0, evening: 0 },
      hours: defaultHours,
      people: defaultPeople,
      impactDiscount: false
    };

    // ── Helpers ───────────────────────────────────────────────────────
    const fmt = n => '$' + Math.round(n).toLocaleString('en-NZ');
    const select = (group, el) => {
      group.querySelectorAll('[data-selected]').forEach(x => x.removeAttribute('data-selected'));
      if (el) el.setAttribute('data-selected', 'true');
    };

    // ── Element refs (all scoped to this widget root) ─────────────────
    const roomsGroup = root.querySelector('[data-js="rooms"]');
    let roomPills = Array.from(roomsGroup.querySelectorAll('.qq-pill'));

    // Filter visible rooms based on data-rooms (hide rest with `hidden` attribute)
    if (visibleRooms) {
      const allowed = visibleRooms.split(',').map(s => s.trim());
      roomPills.forEach(pill => {
        if (!allowed.includes(pill.dataset.room)) pill.setAttribute('hidden', '');
      });
      roomPills = roomPills.filter(p => !p.hasAttribute('hidden'));
    }

    const peopleEl = root.querySelector('[data-js="qq-people"]');
    const peopleValEl = root.querySelector('[data-js="qq-people-value"]');
    const hoursEl = root.querySelector('[data-js="qq-hours"]');
    const hoursValEl = root.querySelector('[data-js="qq-hours-value"]');
    const amountEl = root.querySelector('[data-js="qq-amount"]');
    const impactBlock = root.querySelector('[data-js="qq-impact"]');
    const impactLabelEl = impactBlock.querySelector('.qq-impact__label');
    const impactAmountWrapEl = impactBlock.querySelector('.qq-impact__amount');
    const impactContextEl = impactBlock.querySelector('.qq-impact__context');
    const detailedCtaEl = root.querySelector('[data-js="qq-cta-detailed"]');
    const impactToggleEl = root.querySelector('[data-js="qq-impact-toggle"]');

    // ── People scale: 1-60 by 1, 70-200 by 10 ─────────────────────────
    const peopleScale = (() => {
      const a = [];
      for (let i = 1; i <= 60; i++) a.push(i);
      for (let i = 70; i <= 200; i += 10) a.push(i);
      return a;
    })();
    const peopleIndexOf = n => {
      let best = 0, bestDiff = Infinity;
      for (let i = 0; i < peopleScale.length; i++) {
        const d = Math.abs(peopleScale[i] - n);
        if (d < bestDiff) { bestDiff = d; best = i; }
      }
      return best;
    };
    peopleEl.min = 0;
    peopleEl.max = peopleScale.length - 1;
    peopleEl.value = peopleIndexOf(defaultPeople);
    state.people = peopleScale[+peopleEl.value];

    // Hours slider — default
    hoursEl.value = defaultHours;
    state.hours = defaultHours;

    // ── Room logic ────────────────────────────────────────────────────
    function recommendRoomForPeople(people) {
      const fit = roomPills.find(p => +p.dataset.cap >= people);
      return fit || roomPills[roomPills.length - 1];
    }
    function setRoomFromPill(pill) {
      select(roomsGroup, pill);
      state.room = pill.dataset.room;
      state.roomName = pill.textContent.trim();
      state.rates = {
        day: +pill.dataset.day,
        hour: +pill.dataset.hour,
        evening: +pill.dataset.evening
      };
    }
    function updatePillAvailability() {
      roomPills.forEach(pill => {
        if (+pill.dataset.cap < state.people) {
          pill.setAttribute('aria-disabled', 'true');
        } else {
          pill.removeAttribute('aria-disabled');
        }
      });
    }
    roomPills.forEach(pill => {
      pill.addEventListener('click', e => {
        e.preventDefault();
        if (pill.getAttribute('aria-disabled') === 'true') return;
        setRoomFromPill(pill);
        render();
      });
    });

    // ── People slider listener ────────────────────────────────────────
    peopleEl.addEventListener('input', e => {
      const idx = Math.max(0, Math.min(peopleScale.length - 1, +e.target.value || 0));
      state.people = peopleScale[idx];
      peopleValEl.textContent = state.people + ' ' + (state.people === 1 ? 'person' : 'people');
      updatePillAvailability();
      if (state.room) {
        const current = roomPills.find(p => p.dataset.room === state.room);
        if (current && +current.dataset.cap < state.people) {
          setRoomFromPill(recommendRoomForPeople(state.people));
        }
      } else {
        setRoomFromPill(recommendRoomForPeople(state.people));
      }
      render();
    });

    // ── Hours slider listener ─────────────────────────────────────────
    hoursEl.addEventListener('input', e => {
      state.hours = Math.max(1, +e.target.value || 1);
      hoursValEl.textContent = state.hours + ' hr' + (state.hours !== 1 ? 's' : '');
      render();
    });

    // ── Impact Discount toggle ────────────────────────────────────────
    function setImpactDiscount(on) {
      state.impactDiscount = !!on;
      impactToggleEl.setAttribute('data-on', state.impactDiscount ? 'true' : 'false');
      impactToggleEl.setAttribute('aria-pressed', state.impactDiscount ? 'true' : 'false');
      render();
    }
    impactToggleEl.addEventListener('click', () => setImpactDiscount(!state.impactDiscount));
    impactToggleEl.addEventListener('keydown', e => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        setImpactDiscount(!state.impactDiscount);
      }
    });

    // ── Copy-swap dictionary for the impact card ──────────────────────
    const IMPACT_COPY = {
      contributing: {
        label: 'Your booking also funds',
        amountSuffix: ' of subsidised space',
        context: 'contributing to <span class="qq-impact__total"></span> paid forward since 2021'
      },
      receiving: {
        label: 'You\'re supported by',
        amountSuffix: ' paid forward by others',
        context: 'so spaces like ours stay open to charities, NGOs, and community work'
      }
    };

    // ── Render ────────────────────────────────────────────────────────
    function render() {
      let total = state.rates.hour * state.hours;
      if (state.impactDiscount) total = total * 0.5;
      amountEl.textContent = fmt(total);

      const impactDonation = Math.round(state.hours * state.people);
      if (state.impactDiscount) {
        impactBlock.hidden = false;
        const copy = IMPACT_COPY.receiving;
        impactLabelEl.textContent = copy.label;
        impactAmountWrapEl.innerHTML =
          '<span data-js="qq-impact-amount">' + fmt(total) + '</span>' + copy.amountSuffix;
        impactContextEl.innerHTML = copy.context;
      } else if (impactDonation > 0) {
        impactBlock.hidden = false;
        const copy = IMPACT_COPY.contributing;
        impactLabelEl.textContent = copy.label;
        impactAmountWrapEl.innerHTML =
          '<span data-js="qq-impact-amount">' + fmt(impactDonation) + '</span>' + copy.amountSuffix;
        impactContextEl.innerHTML = copy.context;
      } else {
        impactBlock.hidden = true;
      }

      // Carry state to the full quote tool
      const params = new URLSearchParams({
        room: state.room || '',
        dur: 'hour',
        hours: state.hours,
        people: state.people,
        impact: state.impactDiscount ? '1' : '0'
      });
      detailedCtaEl.href = pricingUrl + '?' + params.toString();
    }

    // ── Initial bootstrap ─────────────────────────────────────────────
    updatePillAvailability();
    // Apply default-room if set (and it fits people); otherwise auto-pick
    if (defaultRoom) {
      const defaultPill = roomPills.find(p => p.dataset.room === defaultRoom);
      if (defaultPill && +defaultPill.dataset.cap >= state.people) {
        setRoomFromPill(defaultPill);
      } else {
        setRoomFromPill(recommendRoomForPeople(state.people));
      }
    } else {
      setRoomFromPill(recommendRoomForPeople(state.people));
    }
    peopleValEl.textContent = state.people + ' ' + (state.people === 1 ? 'person' : 'people');
    hoursValEl.textContent = state.hours + ' hr' + (state.hours !== 1 ? 's' : '');
    render();
  }

  // Find and initialise every widget on the page
  function bootstrap() {
    document.querySelectorAll('.quick-quote').forEach(initWidget);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
