
/**
 * destinations.js
 * Drives the Destinations page: debounced AJAX search, sliding category
 * tabs, an inline filter panel (Weekday/Weekend toggle, crowd pills,
 * rating slider, sort), and re-rendering the card grid from JSON —
 * all hitting the same search.php endpoint so state never gets out
 * of sync between search box, tabs, and filter panel.
 */
// (function () {
//   'use strict';

//   const SEARCH_ENDPOINT = 'search.php';
//   const DEBOUNCE_MS = 300;

//   // ---- element refs -----------------------------------------------
//   const searchInput   = document.getElementById('destSearchInput');
//   const searchSpinner = document.getElementById('destSearchSpinner');

//   const tabsWrap    = document.getElementById('destTabs');
//   const tabsIndicator = document.getElementById('destTabsIndicator');
//   const tabButtons  = Array.from(document.querySelectorAll('.dest-tab'));

//   const filtersBtn   = document.getElementById('destFiltersBtn');
//   const filterPanel  = document.getElementById('destFilterPanel');

//   const dayToggle       = document.getElementById('destDayToggle');
//   const dayToggleIndicator = document.getElementById('destDayToggleIndicator');
//   const dayToggleBtns    = Array.from(document.querySelectorAll('.dest-daytoggle__opt'));

//   const crowdPillsWrap = document.getElementById('destCrowdPills');
//   const crowdPills     = Array.from(document.querySelectorAll('.dest-pill'));

//   const ratingSlider = document.getElementById('destRatingSlider');
//   const ratingValue   = document.getElementById('destRatingValue');

//   const sortSelect = document.getElementById('destSortSelect');

//   const applyBtn = document.getElementById('destApplyBtn');
//   const resetBtn = document.getElementById('destResetBtn');

//   const grid  = document.getElementById('destGrid');
//   const count = document.getElementById('destCount');

//   // ---- state (mirrors the params search.php expects) --------------
//   const state = {
//     q: '',
//     category: 'all',
//     day_type: 'weekday',
//     crowd: 'all',
//     min_rating: '1.0',
//     sort: 'popularity',
//   };

//   let debounceTimer = null;
//   let inFlightController = null;

//   // ---- sliding indicators ------------------------------------------
//   function moveIndicator(activeEl, indicatorEl, wrapEl) {
//     if (!activeEl || !indicatorEl || !wrapEl) return;
//     const wrapRect = wrapEl.getBoundingClientRect();
//     const btnRect = activeEl.getBoundingClientRect();
//     indicatorEl.style.width = btnRect.width + 'px';
//     indicatorEl.style.transform = `translateX(${btnRect.left - wrapRect.left - 5}px)`;
//   }

//   function syncTabIndicator() {
//     const active = tabsWrap.querySelector('.dest-tab.is-active');
//     moveIndicator(active, tabsIndicator, tabsWrap);
//   }

//   function syncDayToggleIndicator() {
//     const active = dayToggle.querySelector('.dest-daytoggle__opt.is-active');
//     const isWeekend = active && active.dataset.daytype === 'weekend';
//     dayToggleIndicator.style.transform = isWeekend ? 'translateX(100%)' : 'translateX(0)';
//   }

//   // ---- card rendering ------------------------------------------------
//   function escapeHtml(str) {
//     const div = document.createElement('div');
//     div.textContent = str == null ? '' : String(str);
//     return div.innerHTML;
//   }

//   function cardHtml(d) {
//     const category = d.category || '';
//     const crowd = (d.crowd_level || 'low').toLowerCase();
//     const crowdLabel = crowd.charAt(0).toUpperCase() + crowd.slice(1);
//     return `
//       <a class="dest-card" href="place-details.php?id=${encodeURIComponent(d.id)}" data-id="${d.id}">
//         <div class="dest-card__media">
//           <img src="${escapeHtml(d.image)}" alt="${escapeHtml(d.name)}" loading="lazy">
//           <span class="dest-card__tag">${escapeHtml(category)}</span>
//           <span class="dest-card__crowd dest-card__crowd--${crowd}">
//             <span class="dest-card__crowd-dot"></span>${escapeHtml(crowdLabel)}
//           </span>
//         </div>
//         <div class="dest-card__body">
//           <h3 class="dest-card__title">${escapeHtml(d.name)}</h3>
//           <p class="dest-card__location"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>${escapeHtml(d.location)}</p>
//           <div class="dest-card__stats">
//             <span><i class="bi bi-star-fill" aria-hidden="true"></i>${Number(d.rating).toFixed(1)}</span>
//             <span class="dest-card__stat-sep"></span>
//             <span><i class="bi bi-calendar-event" aria-hidden="true"></i>${escapeHtml(d.season || '—')}</span>
//           </div>
//         </div>
//       </a>`;
//   }

//   function emptyStateHtml() {
//     return `
//       <div class="dest-empty">
//         <i class="bi bi-map" aria-hidden="true"></i>
//         <h3>No destinations match that</h3>
//         <p>Try a different search term, or reset the filters.</p>
//         <button class="dest-btn dest-btn--primary" type="button" id="destEmptyResetBtn">Reset Filters</button>
//       </div>`;
//   }

//   function renderResults(results) {
//     grid.innerHTML = results.length
//       ? results.map(cardHtml).join('')
//       : emptyStateHtml();

//     if (count) count.textContent = results.length;

//     const emptyReset = document.getElementById('destEmptyResetBtn');
//     if (emptyReset) emptyReset.addEventListener('click', resetAll);
//   }

//   // ---- AJAX fetch (debounced + abortable) ----------------------------
//   function runSearch() {
//     if (searchSpinner) searchSpinner.hidden = false;
//     if (inFlightController) inFlightController.abort();
//     inFlightController = new AbortController();

//     const params = new URLSearchParams(state);

//     fetch(`${SEARCH_ENDPOINT}?${params.toString()}`, { signal: inFlightController.signal })
//       .then((res) => {
//         if (!res.ok) throw new Error('Network response was not ok');
//         return res.json();
//       })
//       .then((data) => {
//         renderResults(data.results || []);
//       })
//       .catch((err) => {
//         if (err.name !== 'AbortError') {
//           console.error('Destination search failed:', err);
//         }
//       })
//       .finally(() => {
//         if (searchSpinner) searchSpinner.hidden = true;
//       });
//   }

//   function queueSearch() {
//     clearTimeout(debounceTimer);
//     debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
//   }

//   // ---- search box -----------------------------------------------------
//   if (searchInput) {
//     searchInput.addEventListener('input', (e) => {
//       state.q = e.target.value.trim();
//       queueSearch();
//     });
//   }

//   // ---- category tabs ---------------------------------------------------
//   tabButtons.forEach((btn) => {
//     btn.addEventListener('click', () => {
//       tabButtons.forEach((b) => {
//         b.classList.remove('is-active');
//         b.setAttribute('aria-selected', 'false');
//       });
//       btn.classList.add('is-active');
//       btn.setAttribute('aria-selected', 'true');
//       state.category = btn.dataset.category;
//       syncTabIndicator();
//       runSearch();
//     });
//   });

//   // ---- Filters panel open/close (inline, not a modal) -------------------
//   if (filtersBtn && filterPanel) {
//     filtersBtn.addEventListener('click', () => {
//       const isOpen = filtersBtn.getAttribute('aria-expanded') === 'true';

//       if (isOpen) {
//         // Closing: play the collapse transition first, then set [hidden]
//         // once it finishes so the panel is fully removed from layout/tab order.
//         filtersBtn.setAttribute('aria-expanded', 'false');
//         filterPanel.classList.remove('is-open');
//         const onCollapseEnd = (e) => {
//           if (e.target !== filterPanel || e.propertyName !== 'max-height') return;
//           filterPanel.hidden = true;
//           filterPanel.removeEventListener('transitionend', onCollapseEnd);
//         };
//         filterPanel.addEventListener('transitionend', onCollapseEnd);
//       } else {
//         // Opening: unhide first, force a reflow so the browser registers the
//         // collapsed state, then add is-open so the transition actually runs.
//         filterPanel.hidden = false;
//         void filterPanel.offsetHeight;
//         filterPanel.classList.add('is-open');
//         filtersBtn.setAttribute('aria-expanded', 'true');
//         syncDayToggleIndicator();
//       }
//     });
//   }

//   // ---- Weekday / Weekend toggle -----------------------------------------
//   dayToggleBtns.forEach((btn) => {
//     btn.addEventListener('click', () => {
//       dayToggleBtns.forEach((b) => b.classList.remove('is-active'));
//       btn.classList.add('is-active');
//       state.day_type = btn.dataset.daytype; // 'weekday' | 'weekend'
//       syncDayToggleIndicator();
//       runSearch(); // crowd_level on every card updates immediately
//     });
//   });

//   // ---- Crowd level pills -------------------------------------------------
//   crowdPills.forEach((pill) => {
//     pill.addEventListener('click', () => {
//       crowdPills.forEach((p) => p.classList.remove('is-active'));
//       pill.classList.add('is-active');
//       state.crowd = pill.dataset.crowd;
//     });
//   });

//   // ---- Rating slider -------------------------------------------------------
//   function updateSliderFill() {
//     const min = Number(ratingSlider.min);
//     const max = Number(ratingSlider.max);
//     const val = Number(ratingSlider.value);
//     const pct = ((val - min) / (max - min)) * 100;
//     ratingSlider.style.background =
//       `linear-gradient(to right, var(--primary-color, #1f8a54) 0%, var(--primary-color, #1f8a54) ${pct}%, var(--primary-light, #e6f5ec) ${pct}%, var(--primary-light, #e6f5ec) 100%)`;
//     ratingValue.textContent = `${val.toFixed(1)} ★`;
//   }

//   if (ratingSlider) {
//     ratingSlider.addEventListener('input', () => {
//       state.min_rating = ratingSlider.value;
//       updateSliderFill();
//     });
//     updateSliderFill(); // initial paint
//   }

//   // ---- Sort dropdown --------------------------------------------------------
//   if (sortSelect) {
//     sortSelect.addEventListener('change', () => {
//       state.sort = sortSelect.value;
//     });
//   }

//   // ---- Apply / Reset ------------------------------------------------------
//   if (applyBtn) {
//     applyBtn.addEventListener('click', () => {
//       runSearch();
//     });
//   }

//   function resetAll() {
//     state.q = '';
//     state.category = 'all';
//     state.day_type = 'weekday';
//     state.crowd = 'all';
//     state.min_rating = '1.0';
//     state.sort = 'popularity';

//     if (searchInput) searchInput.value = '';

//     tabButtons.forEach((b) => {
//       b.classList.toggle('is-active', b.dataset.category === 'all');
//       b.setAttribute('aria-selected', b.dataset.category === 'all' ? 'true' : 'false');
//     });
//     syncTabIndicator();

//     dayToggleBtns.forEach((b) => b.classList.toggle('is-active', b.dataset.daytype === 'weekday'));
//     syncDayToggleIndicator();

//     crowdPills.forEach((p) => p.classList.toggle('is-active', p.dataset.crowd === 'all'));

//     if (ratingSlider) {
//       ratingSlider.value = 1;
//       updateSliderFill();
//     }
//     if (sortSelect) sortSelect.value = 'popularity';

//     runSearch();
//   }

//   if (resetBtn) resetBtn.addEventListener('click', resetAll);

//   // ---- init -----------------------------------------------------------------
//   window.addEventListener('resize', () => {
//     syncTabIndicator();
//     syncDayToggleIndicator();
//   });

//   syncTabIndicator();
//   syncDayToggleIndicator();
// })();

//==============================================================
/**
 * destinations.js
 * Drives the Destinations page: debounced AJAX search, sliding category
 * tabs, an inline filter panel (Weekday/Weekend toggle, crowd pills,
 * rating slider, sort), and re-rendering the card grid from JSON —
 * all hitting the same search.php endpoint so state never gets out
 * of sync between search box, tabs, and filter panel.
 */
(function () {
  'use strict';

  const SEARCH_ENDPOINT = 'search.php';
  const DEBOUNCE_MS = 300;

  // ---- element refs -----------------------------------------------
  const searchInput   = document.getElementById('destSearchInput');
  const searchSpinner = document.getElementById('destSearchSpinner');

  const tabsWrap    = document.getElementById('destTabs');
  const tabsIndicator = document.getElementById('destTabsIndicator');
  const tabButtons  = Array.from(document.querySelectorAll('.dest-tab'));

  const filtersBtn   = document.getElementById('destFiltersBtn');
  const filterPanel  = document.getElementById('destFilterPanel');

  const dayToggle       = document.getElementById('destDayToggle');
  const dayToggleIndicator = document.getElementById('destDayToggleIndicator');
  const dayToggleBtns    = Array.from(document.querySelectorAll('.dest-daytoggle__opt'));

  const crowdPillsWrap = document.getElementById('destCrowdPills');
  const crowdPills     = Array.from(document.querySelectorAll('.dest-pill'));

  const ratingSlider = document.getElementById('destRatingSlider');
  const ratingValue   = document.getElementById('destRatingValue');

  const sortSelect = document.getElementById('destSortSelect');

  const applyBtn = document.getElementById('destApplyBtn');
  const resetBtn = document.getElementById('destResetBtn');

  const grid  = document.getElementById('destGrid');
  const count = document.getElementById('destCount');

  // ---- state (mirrors the params search.php expects) --------------
  const state = {
    q: '',
    category: 'all',
    day_type: 'weekday',
    crowd: 'all',
    min_rating: '1.0',
    sort: 'popularity',
  };

  let debounceTimer = null;
  let inFlightController = null;

  // ---- sliding indicators ------------------------------------------
  function moveIndicator(activeEl, indicatorEl, wrapEl) {
    if (!activeEl || !indicatorEl || !wrapEl) return;
    // Use layout-space offsets (offsetLeft/offsetWidth), not
    // getBoundingClientRect(): the tabs row scrolls horizontally on
    // mobile, and viewport-relative rects go stale the moment the user
    // swipes, detaching the indicator from the active tab. offsetLeft
    // is relative to wrapEl (its offsetParent) and is scroll-independent,
    // so the indicator — itself a child of the scrollable row — tracks
    // correctly at any scroll position without extra listeners.
    indicatorEl.style.width = activeEl.offsetWidth + 'px';
    indicatorEl.style.transform = `translateX(${activeEl.offsetLeft - 5}px)`;
  }

  function syncTabIndicator() {
    const active = tabsWrap.querySelector('.dest-tab.is-active');
    moveIndicator(active, tabsIndicator, tabsWrap);
  }

  function syncDayToggleIndicator() {
    const active = dayToggle.querySelector('.dest-daytoggle__opt.is-active');
    const isWeekend = active && active.dataset.daytype === 'weekend';
    dayToggleIndicator.style.transform = isWeekend ? 'translateX(100%)' : 'translateX(0)';
  }

  // ---- card rendering ------------------------------------------------
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function cardHtml(d) {
    const category = d.category || '';
    const crowd = (d.crowd_level || 'low').toLowerCase();
    const crowdLabel = crowd.charAt(0).toUpperCase() + crowd.slice(1);
    return `
      <a class="dest-card" href="place-details.php?id=${encodeURIComponent(d.id)}" data-id="${d.id}">
        <div class="dest-card__media">
          <img src="${escapeHtml(d.image)}" alt="${escapeHtml(d.name)}" loading="lazy">
          <span class="dest-card__tag">${escapeHtml(category)}</span>
          <span class="dest-card__crowd dest-card__crowd--${crowd}">
            <span class="dest-card__crowd-dot"></span>${escapeHtml(crowdLabel)}
          </span>
        </div>
        <div class="dest-card__body">
          <h3 class="dest-card__title">${escapeHtml(d.name)}</h3>
          <p class="dest-card__location"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>${escapeHtml(d.location)}</p>
          <div class="dest-card__stats">
            <span><i class="bi bi-star-fill" aria-hidden="true"></i>${Number(d.rating).toFixed(1)}</span>
            <span class="dest-card__stat-sep"></span>
            <span><i class="bi bi-calendar-event" aria-hidden="true"></i>${escapeHtml(d.season || '—')}</span>
          </div>
        </div>
      </a>`;
  }

  function emptyStateHtml() {
    return `
      <div class="dest-empty">
        <i class="bi bi-map" aria-hidden="true"></i>
        <h3>No destinations match that</h3>
        <p>Try a different search term, or reset the filters.</p>
        <button class="dest-btn dest-btn--primary" type="button" id="destEmptyResetBtn">Reset Filters</button>
      </div>`;
  }

  function renderResults(results) {
    grid.innerHTML = results.length
      ? results.map(cardHtml).join('')
      : emptyStateHtml();

    if (count) count.textContent = results.length;

    const emptyReset = document.getElementById('destEmptyResetBtn');
    if (emptyReset) emptyReset.addEventListener('click', resetAll);
  }

  // ---- AJAX fetch (debounced + abortable) ----------------------------
  function runSearch() {
    if (searchSpinner) searchSpinner.hidden = false;
    if (inFlightController) inFlightController.abort();
    inFlightController = new AbortController();

    const params = new URLSearchParams(state);

    fetch(`${SEARCH_ENDPOINT}?${params.toString()}`, { signal: inFlightController.signal })
      .then((res) => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
      })
      .then((data) => {
        renderResults(data.results || []);
      })
      .catch((err) => {
        if (err.name !== 'AbortError') {
          console.error('Destination search failed:', err);
        }
      })
      .finally(() => {
        if (searchSpinner) searchSpinner.hidden = true;
      });
  }

  function queueSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
  }

  // ---- search box -----------------------------------------------------
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      state.q = e.target.value.trim();
      queueSearch();
    });
  }

  // ---- category tabs ---------------------------------------------------
  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      tabButtons.forEach((b) => {
        b.classList.remove('is-active');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('is-active');
      btn.setAttribute('aria-selected', 'true');
      state.category = btn.dataset.category;
      syncTabIndicator();
      // If the tapped tab is off-screen in the scrollable row (mobile),
      // bring it fully into view instead of leaving the indicator
      // to jump to a tab the user can't see.
      btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      runSearch();
    });
  });

  // ---- Filters panel open/close (inline, not a modal) -------------------
  if (filtersBtn && filterPanel) {
    filtersBtn.addEventListener('click', () => {
      const isOpen = filtersBtn.getAttribute('aria-expanded') === 'true';

      if (isOpen) {
        // Closing: play the collapse transition first, then set [hidden]
        // once it finishes so the panel is fully removed from layout/tab order.
        filtersBtn.setAttribute('aria-expanded', 'false');
        filterPanel.classList.remove('is-open');
        const onCollapseEnd = (e) => {
          if (e.target !== filterPanel || e.propertyName !== 'max-height') return;
          filterPanel.hidden = true;
          filterPanel.removeEventListener('transitionend', onCollapseEnd);
        };
        filterPanel.addEventListener('transitionend', onCollapseEnd);
      } else {
        // Opening: unhide first, force a reflow so the browser registers the
        // collapsed state, then add is-open so the transition actually runs.
        filterPanel.hidden = false;
        void filterPanel.offsetHeight;
        filterPanel.classList.add('is-open');
        filtersBtn.setAttribute('aria-expanded', 'true');
        syncDayToggleIndicator();
      }
    });
  }

  // ---- Weekday / Weekend toggle -----------------------------------------
  dayToggleBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      dayToggleBtns.forEach((b) => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      state.day_type = btn.dataset.daytype; // 'weekday' | 'weekend'
      syncDayToggleIndicator();
      runSearch(); // crowd_level on every card updates immediately
    });
  });

  // ---- Crowd level pills -------------------------------------------------
  crowdPills.forEach((pill) => {
    pill.addEventListener('click', () => {
      crowdPills.forEach((p) => p.classList.remove('is-active'));
      pill.classList.add('is-active');
      state.crowd = pill.dataset.crowd;
    });
  });

  // ---- Rating slider -------------------------------------------------------
  function updateSliderFill() {
    const min = Number(ratingSlider.min);
    const max = Number(ratingSlider.max);
    const val = Number(ratingSlider.value);
    const pct = ((val - min) / (max - min)) * 100;
    ratingSlider.style.background =
      `linear-gradient(to right, var(--primary-color, #1f8a54) 0%, var(--primary-color, #1f8a54) ${pct}%, var(--primary-light, #e6f5ec) ${pct}%, var(--primary-light, #e6f5ec) 100%)`;
    ratingValue.textContent = `${val.toFixed(1)} ★`;
  }

  if (ratingSlider) {
    ratingSlider.addEventListener('input', () => {
      state.min_rating = ratingSlider.value;
      updateSliderFill();
    });
    updateSliderFill(); // initial paint
  }

  // ---- Sort dropdown --------------------------------------------------------
  if (sortSelect) {
    sortSelect.addEventListener('change', () => {
      state.sort = sortSelect.value;
    });
  }

  // ---- Apply / Reset ------------------------------------------------------
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      runSearch();
    });
  }

  function resetAll() {
    state.q = '';
    state.category = 'all';
    state.day_type = 'weekday';
    state.crowd = 'all';
    state.min_rating = '1.0';
    state.sort = 'popularity';

    if (searchInput) searchInput.value = '';

    tabButtons.forEach((b) => {
      b.classList.toggle('is-active', b.dataset.category === 'all');
      b.setAttribute('aria-selected', b.dataset.category === 'all' ? 'true' : 'false');
    });
    syncTabIndicator();

    dayToggleBtns.forEach((b) => b.classList.toggle('is-active', b.dataset.daytype === 'weekday'));
    syncDayToggleIndicator();

    crowdPills.forEach((p) => p.classList.toggle('is-active', p.dataset.crowd === 'all'));

    if (ratingSlider) {
      ratingSlider.value = 1;
      updateSliderFill();
    }
    if (sortSelect) sortSelect.value = 'popularity';

    runSearch();
  }

  if (resetBtn) resetBtn.addEventListener('click', resetAll);

  // ---- init -----------------------------------------------------------------
  window.addEventListener('resize', () => {
    syncTabIndicator();
    syncDayToggleIndicator();
  });

  syncTabIndicator();
  syncDayToggleIndicator();
})();