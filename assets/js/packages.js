/**
 * packages.js
 * Drives the Packages page: debounced AJAX search, sliding category
 * tabs, an inline filter panel (Budget pills, Duration pills, rating
 * slider, sort), favourite-heart toggling on cards, and re-rendering
 * the card grid from JSON — mirrors destinations.js's pattern.
 */
(function () {
  'use strict';

  const SEARCH_ENDPOINT = 'package-search.php';
  const DEBOUNCE_MS = 300;

  const searchInput   = document.getElementById('pkgSearchInput');
  const searchSpinner = document.getElementById('pkgSearchSpinner');

  const tabsWrap      = document.getElementById('pkgTabs');
  const tabsIndicator = document.getElementById('pkgTabsIndicator');
  const tabButtons    = Array.from(document.querySelectorAll('#pkgTabs .dest-tab'));

  const filtersBtn  = document.getElementById('pkgFiltersBtn');
  const filterPanel = document.getElementById('pkgFilterPanel');

  const budgetPills   = Array.from(document.querySelectorAll('#pkgBudgetPills .dest-pill'));
  const durationPills = Array.from(document.querySelectorAll('#pkgDurationPills .dest-pill'));

  const ratingSlider = document.getElementById('pkgRatingSlider');
  const ratingValue  = document.getElementById('pkgRatingValue');

  const sortSelect = document.getElementById('pkgSortSelect');

  const applyBtn = document.getElementById('pkgApplyBtn');
  const resetBtn = document.getElementById('pkgResetBtn');

  const grid  = document.getElementById('pkgGrid');
  const count = document.getElementById('pkgCount');

  const state = {
    q: '',
    category: 'all',
    min_budget: '0',
    max_budget: '999999',
    duration: 'all',
    min_rating: '1.0',
    sort: 'popularity',
    destination_id: new URLSearchParams(window.location.search).get('destination') || '0',
  };

  let debounceTimer = null;
  let inFlightController = null;

  // ---- sliding tab indicator ------------------------------------------
  function moveIndicator(activeEl, indicatorEl, wrapEl) {
    if (!activeEl || !indicatorEl || !wrapEl) return;
    indicatorEl.style.width = activeEl.offsetWidth + 'px';
    indicatorEl.style.transform = `translateX(${activeEl.offsetLeft - 5}px)`;
  }
  function syncTabIndicator() {
    const active = tabsWrap.querySelector('.dest-tab.is-active');
    moveIndicator(active, tabsIndicator, tabsWrap);
  }

  // ---- card rendering ---------------------------------------------------
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  // Mirrors pkg_itinerary_preview() in packages-db.php: parses the
  // 'day::title||day::title' raw string into up to 3 preview days + a
  // remaining count, for the card's hover "Itinerary Preview".
  function itineraryPreview(raw) {
    if (!raw) return { days: [], more: 0 };
    const days = raw.split('||').map((pair) => {
      const idx = pair.indexOf('::');
      return { day_number: parseInt(pair.slice(0, idx), 10), title: pair.slice(idx + 2) };
    });
    return { days: days.slice(0, 3), more: Math.max(0, days.length - 3) };
  }

  function cardHtml(p) {
    const category = p.category || 'Package';
    const isFav = !!p.is_favorited;
    const destNames = (p.destinations_label || '').split(',').map((s) => s.trim()).filter(Boolean);
    const itinerary = itineraryPreview(p.itinerary_raw);

    const destPillsHtml = destNames.length ? `
        <div class="pkg-card__dest-pills">
          ${destNames.map((dn) => `<span class="pkg-card__dest-pill"><i class="bi bi-geo-alt-fill"></i>${escapeHtml(dn)}</span>`).join('')}
        </div>` : '';

    const itineraryHtml = itinerary.days.length ? `
        <div class="pkg-card__itinerary">
          <div class="pkg-card__itinerary-title">Itinerary Preview</div>
          ${itinerary.days.map((d) => `
          <div class="pkg-card__itinerary-row">
            <span class="pkg-card__itinerary-num">${d.day_number}</span>
            <span>Day ${d.day_number}: ${escapeHtml(d.title)}</span>
          </div>`).join('')}
          ${itinerary.more > 0 ? `<div class="pkg-card__itinerary-more">+${itinerary.more} more days</div>` : ''}
        </div>` : '';

    return `
      <a class="pkg-card" href="package-details.php?id=${encodeURIComponent(p.id)}" data-id="${p.id}">
        <div class="pkg-card__media">
          <img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" loading="lazy">
          <span class="pkg-card__tag">${escapeHtml(category)}</span>
          <span class="pkg-card__rating"><i class="bi bi-star-fill"></i> ${Number(p.rating).toFixed(1)}</span>
          <button type="button" class="pkg-card__fav${isFav ? ' liked' : ''}" data-pkg-fav="${p.id}" aria-label="Add to favourites" aria-pressed="${isFav}" onclick="event.preventDefault(); event.stopPropagation();">
            <i class="bi ${isFav ? 'bi-heart-fill' : 'bi-heart'}"></i>
          </button>
          <div class="pkg-card__price">
            <span class="pkg-card__price-amount">₹${Number(p.price_per_person).toLocaleString('en-IN')}</span><span class="pkg-card__price-unit">/person</span>
          </div>
        </div>
        <div class="pkg-card__body">
          <h3 class="pkg-card__title">${escapeHtml(p.name)}</h3>
          <div class="pkg-card__meta">
            <span><i class="bi bi-clock"></i> ${Number(p.duration_days)}D/${Number(p.duration_nights)}N</span>
            <span><i class="bi bi-person"></i> ${escapeHtml(p.agent_name || 'NextTrip')}</span>
            <span><i class="bi bi-tag"></i> ${escapeHtml(p.best_time || 'All Season')}</span>
          </div>
          ${destPillsHtml}
          ${itineraryHtml}
          <span class="pkg-card__book-btn">Book Now <i class="bi bi-arrow-right"></i></span>
        </div>
      </a>`;
  }

  function emptyStateHtml() {
    return `
      <div class="dest-empty">
        <i class="bi bi-suitcase-lg" aria-hidden="true"></i>
        <h3>No packages match that</h3>
        <p>Try a different search term, or reset the filters.</p>
        <button class="dest-btn dest-btn--primary" type="button" id="pkgEmptyResetBtn">Reset Filters</button>
      </div>`;
  }

  function renderResults(results) {
    grid.innerHTML = results.length ? results.map(cardHtml).join('') : emptyStateHtml();
    if (count) count.textContent = results.length;
    const emptyReset = document.getElementById('pkgEmptyResetBtn');
    if (emptyReset) emptyReset.addEventListener('click', resetAll);
  }

  // ---- AJAX fetch (debounced + abortable) ------------------------------
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
      .then((data) => renderResults(data.results || []))
      .catch((err) => {
        if (err.name !== 'AbortError') console.error('Package search failed:', err);
      })
      .finally(() => {
        if (searchSpinner) searchSpinner.hidden = true;
      });
  }

  function queueSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
  }

  // ---- search box -------------------------------------------------------
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      state.q = e.target.value.trim();
      queueSearch();
    });
  }

  // ---- category tabs -----------------------------------------------------
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
      btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      runSearch();
    });
  });

  // ---- Filters panel open/close ------------------------------------------
  if (filtersBtn && filterPanel) {
    filtersBtn.addEventListener('click', () => {
      const isOpen = filtersBtn.getAttribute('aria-expanded') === 'true';
      if (isOpen) {
        filtersBtn.setAttribute('aria-expanded', 'false');
        filterPanel.classList.remove('is-open');
        const onCollapseEnd = (e) => {
          if (e.target !== filterPanel || e.propertyName !== 'max-height') return;
          filterPanel.hidden = true;
          filterPanel.removeEventListener('transitionend', onCollapseEnd);
        };
        filterPanel.addEventListener('transitionend', onCollapseEnd);
      } else {
        filterPanel.hidden = false;
        void filterPanel.offsetHeight;
        filterPanel.classList.add('is-open');
        filtersBtn.setAttribute('aria-expanded', 'true');
      }
    });
  }

  // ---- Budget pills -------------------------------------------------------
  budgetPills.forEach((pill) => {
    pill.addEventListener('click', () => {
      budgetPills.forEach((p) => p.classList.remove('is-active'));
      pill.classList.add('is-active');
      const [min, max] = (pill.dataset.budget === 'all' ? '0-999999' : pill.dataset.budget).split('-');
      state.min_budget = min;
      state.max_budget = max;
    });
  });

  // ---- Duration pills -----------------------------------------------------
  durationPills.forEach((pill) => {
    pill.addEventListener('click', () => {
      durationPills.forEach((p) => p.classList.remove('is-active'));
      pill.classList.add('is-active');
      state.duration = pill.dataset.duration;
    });
  });

  // ---- Rating slider --------------------------------------------------------
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
    updateSliderFill();
  }

  // ---- Sort dropdown ----------------------------------------------------------
  if (sortSelect) {
    sortSelect.addEventListener('change', () => { state.sort = sortSelect.value; });
  }

  // ---- Apply / Reset --------------------------------------------------------
  if (applyBtn) applyBtn.addEventListener('click', runSearch);

  function resetAll() {
    state.q = '';
    state.category = 'all';
    state.min_budget = '0';
    state.max_budget = '999999';
    state.duration = 'all';
    state.min_rating = '1.0';
    state.sort = 'popularity';

    if (searchInput) searchInput.value = '';

    tabButtons.forEach((b) => {
      b.classList.toggle('is-active', b.dataset.category === 'all');
      b.setAttribute('aria-selected', b.dataset.category === 'all' ? 'true' : 'false');
    });
    syncTabIndicator();

    budgetPills.forEach((p) => p.classList.toggle('is-active', p.dataset.budget === 'all'));
    durationPills.forEach((p) => p.classList.toggle('is-active', p.dataset.duration === 'all'));

    if (ratingSlider) { ratingSlider.value = 1; updateSliderFill(); }
    if (sortSelect) sortSelect.value = 'popularity';

    runSearch();
  }
  if (resetBtn) resetBtn.addEventListener('click', resetAll);

  // ---- Favourite heart (event delegation — works for PHP-rendered AND
  //      AJAX-rebuilt cards alike, since the grid container never changes) --
  grid.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-pkg-fav]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    if (!window.PKG_IS_LOGGED_IN) {
      window.location.href = window.PKG_LOGIN_URL || 'auth/login.php';
      return;
    }
    if (btn.disabled) return;
    btn.disabled = true;

    const pkgId = btn.dataset.pkgFav;
    const icon = btn.querySelector('i');

    fetch('ajax/package_favorite_toggle.php', {
      method: 'POST',
      body: new URLSearchParams({ package_id: pkgId }),
    })
      .then((res) => {
        if (res.status === 401) { window.location.href = window.PKG_LOGIN_URL; throw new Error('not_logged_in'); }
        if (!res.ok) throw new Error('request_failed');
        return res.json();
      })
      .then((data) => {
        btn.classList.toggle('liked', data.liked);
        btn.setAttribute('aria-pressed', String(data.liked));
        if (icon) {
          icon.classList.toggle('bi-heart', !data.liked);
          icon.classList.toggle('bi-heart-fill', data.liked);
        }
      })
      .catch((err) => {
        if (err.message !== 'not_logged_in') console.error('Favourite toggle failed:', err);
      })
      .finally(() => { btn.disabled = false; });
  });

  // ---- init -----------------------------------------------------------------
  window.addEventListener('resize', syncTabIndicator);
  syncTabIndicator();
})();
