(function () {
  'use strict';

  /* -----------------------------------------------------------------------
     1. IMAGE CAROUSEL
     ------------------------------------------------------------------- */
  var track = document.getElementById('pdCarouselTrack');
  var prevBtn = document.getElementById('pdCarouselPrev');
  var nextBtn = document.getElementById('pdCarouselNext');
  var dots = document.querySelectorAll('.pd-carousel__dot');
  var slideCount = track ? track.children.length : 0;
  var current = 0;

  function goToSlide(index) {
    if (!track) return;
    current = Math.max(0, Math.min(index, slideCount - 1));
    track.style.transform = 'translateX(-' + (current * 100) + '%)';

    dots.forEach(function (dot, i) {
      dot.classList.toggle('is-active', i === current);
    });
    if (prevBtn) prevBtn.disabled = current === 0;
    if (nextBtn) nextBtn.disabled = current === slideCount - 1;
  }

  function goToNextSlide() {
    // Wraps around so autoplay loops continuously instead of stopping dead
    // at the last image.
    goToSlide(current + 1 >= slideCount ? 0 : current + 1);
  }

  if (prevBtn) prevBtn.addEventListener('click', function () { goToSlide(current - 1); restartAutoplay(); });
  if (nextBtn) nextBtn.addEventListener('click', function () { goToSlide(current + 1); restartAutoplay(); });
  dots.forEach(function (dot, i) {
    dot.addEventListener('click', function () { goToSlide(i); restartAutoplay(); });
  });
  goToSlide(0);

  // Autoplay — advances every 3.5s, pauses on hover/touch/focus so a
  // visitor reading or interacting with the carousel isn't fighting it.
  var AUTOPLAY_MS = 3500;
  var autoplayTimer = null;

  function startAutoplay() {
    if (slideCount <= 1) return;
    stopAutoplay();
    autoplayTimer = setInterval(goToNextSlide, AUTOPLAY_MS);
  }
  function stopAutoplay() {
    if (autoplayTimer) { clearInterval(autoplayTimer); autoplayTimer = null; }
  }
  function restartAutoplay() { startAutoplay(); }

  var carouselViewport = document.querySelector('.pd-carousel__viewport');
  if (carouselViewport) {
    carouselViewport.addEventListener('mouseenter', stopAutoplay);
    carouselViewport.addEventListener('mouseleave', startAutoplay);
    carouselViewport.addEventListener('focusin', stopAutoplay);
    carouselViewport.addEventListener('focusout', startAutoplay);
    carouselViewport.addEventListener('touchstart', stopAutoplay, { passive: true });
  }
  // Pause while the page/tab isn't visible so it doesn't silently rack up
  // slide changes in a background tab.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopAutoplay(); else startAutoplay();
  });
  startAutoplay();

  // Swipe support on touch devices
  (function () {
    var viewport = carouselViewport;
    if (!viewport) return;
    var startX = null;
    viewport.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    viewport.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) { goToSlide(current + (dx < 0 ? 1 : -1)); restartAutoplay(); }
      startX = null;
    });
  })();

  /* -----------------------------------------------------------------------
     2. ABOUT / HISTORY / IMPORTANCE TABS — blur + fade cross-transition
     ------------------------------------------------------------------- */
  var tabs = document.querySelectorAll('.pd-tab');
  var panels = document.querySelectorAll('.pd-tab-panel');
  var tabContent = document.querySelector('.pd-tab-content');

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      if (tab.classList.contains('is-active')) return;
      var target = tab.dataset.tab;

      tabs.forEach(function (t) {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
      });

      var current = document.querySelector('.pd-tab-panel.is-active');
      var next = document.querySelector('.pd-tab-panel[data-panel="' + target + '"]');
      if (!next || next === current) return;

      // Freeze the container at its current height so pulling the
      // outgoing panel out of flow (to overlap the incoming one)
      // doesn't also collapse/jump the surrounding layout.
      if (tabContent && current) {
        tabContent.style.height = current.offsetHeight + 'px';
      }

      if (current) {
        current.classList.remove('is-active');
        current.classList.add('is-leaving');
        current.addEventListener('animationend', function handler() {
          current.classList.remove('is-leaving');
          current.removeEventListener('animationend', handler);
        });
      }
      next.classList.add('is-active');

      // Once the incoming panel has laid itself out, ease the container
      // to its natural height, then release the fixed height so the
      // panel can resize freely afterwards (e.g. on window resize).
      requestAnimationFrame(function () {
        if (tabContent) tabContent.style.height = next.offsetHeight + 'px';
      });
      setTimeout(function () {
        if (tabContent) tabContent.style.height = '';
      }, 400);
    });
  });

  /* -----------------------------------------------------------------------
     3. CROWD LEVEL — compact animated arc in the sidebar. All calculation
        happens server-side (ajax/crowd_level.php -> get_crowd_result_for_hour()
        in place-details-db.php); this just fetches a result for the picked
        hour and animates the arc fill + percentage count-up + label.
     ------------------------------------------------------------------- */
  (function () {
    var root = document.getElementById('pdCrowdMini');
    if (!root || !window.PD_DATA) return;

    var destinationId = window.PD_DATA.destinationId;
    var selectEl  = document.getElementById('pdCrowdMiniTimeSelect');
    var arcEl     = document.getElementById('pdCrowdMiniArc');
    var pctEl     = document.getElementById('pdCrowdMiniPct');
    var labelEl   = document.getElementById('pdCrowdMiniLabel');
    var statusEl  = document.getElementById('pdCrowdMiniStatus');
    var forecastBars = root.querySelectorAll('.pd-crowd-mini__forecast-bar');

    var LEVEL_COLORS = {
      'Very Low': '#1fa96a', 'Low': '#7bc96f', 'Medium': '#f4b23f',
      'High': '#f08a3c', 'Very High': '#e2513f'
    };
    var REDUCE_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var currentPct = parseInt((pctEl && pctEl.textContent) || '0', 10) || 0;
    var fetchToken = 0; // guards against an older response landing after a newer one

    function animatePct(from, to, duration) {
      if (!pctEl) return;
      if (REDUCE_MOTION) { pctEl.textContent = to + '%'; return; }
      var start = null;
      function step(ts) {
        if (start === null) start = ts;
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
        pctEl.textContent = Math.round(from + (to - from) * eased) + '%';
        if (progress < 1) window.requestAnimationFrame(step);
      }
      window.requestAnimationFrame(step);
    }

    function setLabel(text) {
      if (!labelEl) return;
      labelEl.style.opacity = '0';
      setTimeout(function () {
        labelEl.textContent = text;
        labelEl.style.opacity = '1';
      }, REDUCE_MOTION ? 0 : 150);
    }

    function applyResult(result, hour) {
      forecastBars.forEach(function (bar) {
        bar.classList.toggle('is-active', parseInt(bar.dataset.hour, 10) === hour);
      });

      if (result.status !== 'ok') {
        if (arcEl) { arcEl.style.strokeDashoffset = '100'; arcEl.style.stroke = '#d8dee3'; }
        animatePct(currentPct, 0, 500);
        currentPct = 0;
        setLabel('No data');
        if (statusEl) {
          statusEl.textContent = 'No visitor reports available for this time';
          statusEl.classList.add('is-empty');
        }
        return;
      }

      var pct = result.percentage;
      if (arcEl) {
        arcEl.style.strokeDashoffset = String(100 - pct);
        arcEl.style.stroke = LEVEL_COLORS[result.level] || '#1fa96a';
      }
      animatePct(currentPct, pct, 700);
      currentPct = pct;
      setLabel(result.level);
      if (statusEl) {
        statusEl.textContent = result.estimated
          ? 'Estimated from nearby reports'
          : 'Based on ' + result.count + ' visitor review' + (result.count === 1 ? '' : 's');
        statusEl.classList.remove('is-empty');
      }
    }

    function loadHour(hour) {
      var token = ++fetchToken;
      fetch('ajax/crowd_level.php?destination_id=' + encodeURIComponent(destinationId) + '&hour=' + encodeURIComponent(hour))
        .then(function (res) { return res.json(); })
        .then(function (result) {
          if (token !== fetchToken) return; // a newer request already answered first
          applyResult(result, hour);
        })
        .catch(function () {
          if (token !== fetchToken) return;
          if (statusEl) {
            statusEl.textContent = 'Could not load crowd data — please try again.';
            statusEl.classList.add('is-empty');
          }
        });
    }

    if (selectEl) {
      selectEl.addEventListener('change', function () {
        loadHour(parseInt(selectEl.value, 10));
      });
    }
  })();

  /* -----------------------------------------------------------------------
     4. INTERACTIVE MAP (Leaflet + OpenStreetMap — no API key needed)
     ------------------------------------------------------------------- */
  var mapEl = document.getElementById('pdMap');
  if (mapEl && window.L) {
    var lat = parseFloat(mapEl.dataset.lat);
    var lng = parseFloat(mapEl.dataset.lng);
    var name = mapEl.dataset.name;

    var map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 18
    }).addTo(map);

    var destIcon = L.divIcon({
      className: 'pd-map-pin',
      html: '<i class="bi bi-geo-alt-fill"></i>',
      iconSize: [28, 28],
      iconAnchor: [14, 28]
    });
    L.marker([lat, lng], { icon: destIcon }).addTo(map).bindPopup(name);

    var myLocationBtn = document.getElementById('pdMyLocationBtn');
    if (myLocationBtn) {
      myLocationBtn.addEventListener('click', function () {
        if (!navigator.geolocation) {
          alert('Location access isn\'t available in this browser.');
          return;
        }
        myLocationBtn.textContent = 'Locating…';
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            myLocationBtn.textContent = 'My Location';
            var userLat = pos.coords.latitude;
            var userLng = pos.coords.longitude;
            var userIcon = L.divIcon({
              className: 'pd-map-pin pd-map-pin--user',
              html: '<i class="bi bi-person-fill"></i>',
              iconSize: [26, 26],
              iconAnchor: [13, 26]
            });
            L.marker([userLat, userLng], { icon: userIcon }).addTo(map).bindPopup('You are here').openPopup();
            var bounds = L.latLngBounds([lat, lng], [userLat, userLng]);
            map.fitBounds(bounds, { padding: [40, 40] });
          },
          function () {
            myLocationBtn.textContent = 'My Location';
            alert('Couldn\'t get your location — check your browser\'s location permission.');
          }
        );
      });
    }
  }

  /* -----------------------------------------------------------------------
     5. FAVOURITE (heart) BUTTON — real AJAX to ajax/favorite_toggle.php
     ------------------------------------------------------------------- */
  var likeBtn = document.getElementById('pdLikeBtn');
  if (likeBtn) {
    likeBtn.addEventListener('click', function () {
      if (!window.PD_DATA || !window.PD_DATA.isLoggedIn) {
        window.location.href = (window.PD_DATA && window.PD_DATA.loginUrl) || 'auth/login.php';
        return;
      }
      if (likeBtn.disabled) return;
      likeBtn.disabled = true;

      var icon = likeBtn.querySelector('i');
      var wasLiked = likeBtn.classList.contains('liked');

      var body = new URLSearchParams({ destination_id: window.PD_DATA.destinationId });

      fetch('ajax/favorite_toggle.php', { method: 'POST', body: body })
        .then(function (res) {
          if (res.status === 401) {
            window.location.href = window.PD_DATA.loginUrl;
            throw new Error('not_logged_in');
          }
          if (!res.ok) throw new Error('request_failed');
          return res.json();
        })
        .then(function (data) {
          likeBtn.classList.toggle('liked', data.liked);
          likeBtn.setAttribute('aria-pressed', String(data.liked));
          if (icon) {
            icon.classList.toggle('bi-heart', !data.liked);
            icon.classList.toggle('bi-heart-fill', data.liked);
          }
          if (data.liked) {
            likeBtn.classList.add('pd-like-pulse');
            setTimeout(function () { likeBtn.classList.remove('pd-like-pulse'); }, 400);
          }
        })
        .catch(function (err) {
          if (err.message !== 'not_logged_in') {
            console.error('Favourite toggle failed:', err);
          }
        })
        .finally(function () {
          likeBtn.disabled = false;
        });
    });
  }

  /* -----------------------------------------------------------------------
     6. REVIEW FORM — star picker + real AJAX submit (rating, text,
        visit_time, crowd_level) to ajax/submit_review.php
     ------------------------------------------------------------------- */
  var reviewStars = document.querySelectorAll('.pd-review-form__star');
  var selectedRating = 0;

  reviewStars.forEach(function (star) {
    var value = parseInt(star.dataset.value, 10);
    star.addEventListener('mouseenter', function () { paintStars(value, true); });
    star.addEventListener('mouseleave', function () { paintStars(selectedRating, false); });
    star.addEventListener('click', function () {
      selectedRating = value;
      paintStars(selectedRating, false);
    });
  });

  function paintStars(value, hover) {
    reviewStars.forEach(function (s) {
      var v = parseInt(s.dataset.value, 10);
      s.classList.toggle(hover ? 'is-hovered' : 'is-selected', v <= value);
      if (!hover) s.classList.remove('is-hovered');
    });
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function starsHtml(rating) {
    var out = '';
    for (var i = 1; i <= 5; i++) {
      out += '<i class="bi ' + (i <= rating ? 'bi-star-fill' : 'bi-star') + '"></i>';
    }
    return out;
  }

  function formatTimeLabel(hhmmss) {
    if (!hhmmss) return '';
    var parts = hhmmss.split(':');
    var d = new Date();
    d.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0);
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function crowdTagClass(level) {
    return 'pd-review__crowd-tag--' + level.toLowerCase().replace(/\s+/g, '-');
  }

  function prependReview(review) {
    var list = document.getElementById('pdReviewList');
    if (!list) return;
    var emptyMsg = document.getElementById('pdReviewsEmpty');
    if (emptyMsg) emptyMsg.remove();

    var card = document.createElement('div');
    card.className = 'pd-review pd-review--new';
    var metaHtml = '';
    if (review.visit_time || review.crowd_level) {
      metaHtml = '<div class="pd-review__crowd-meta">' +
        (review.visit_time ? '<span><i class="bi bi-clock"></i> Visited around ' + formatTimeLabel(review.visit_time) + '</span>' : '') +
        (review.crowd_level ? '<span class="pd-review__crowd-tag ' + crowdTagClass(review.crowd_level) + '"><i class="bi bi-people-fill"></i> ' + escapeHtml(review.crowd_level) + ' crowd</span>' : '') +
        '</div>';
    }
    card.innerHTML =
      '<div class="pd-review__top">' +
        '<div class="pd-review__avatar">' + escapeHtml(review.reviewer_name.charAt(0)) + '</div>' +
        '<div><div class="pd-review__name">' + escapeHtml(review.reviewer_name) + '</div>' +
        '<div class="pd-review__date">Just now</div></div>' +
        '<span class="pd-stars pd-review__stars">' + starsHtml(review.rating) + '</span>' +
      '</div>' +
      '<p class="pd-review__text">' + escapeHtml(review.review_text) + '</p>' +
      metaHtml;

    list.insertBefore(card, list.firstChild);
  }

  var reviewForm = document.getElementById('pdReviewForm');
  if (reviewForm) {
    reviewForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var textarea = reviewForm.querySelector('.pd-review-form__text');
      var timeSelect = document.getElementById('pdVisitTime');
      var crowdSelect = document.getElementById('pdCrowdLevel');

      if (!selectedRating || !textarea.value.trim()) {
        alert('Please add a star rating and a short review before submitting.');
        return;
      }

      var submitBtn = reviewForm.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting…';

      var body = new URLSearchParams({
        destination_id: reviewForm.dataset.destinationId,
        rating: selectedRating,
        review_text: textarea.value.trim(),
        visit_time: timeSelect ? timeSelect.value : '',
        crowd_level: crowdSelect ? crowdSelect.value : ''
      });

      fetch('ajax/submit_review.php', { method: 'POST', body: body })
        .then(function (res) {
          if (res.status === 401) {
            window.location.href = window.PD_DATA.loginUrl;
            throw new Error('not_logged_in');
          }
          return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
          if (!result.ok) {
            alert('Could not submit your review — please check your input and try again.');
            return;
          }
          prependReview(result.data.review);
          textarea.value = '';
          selectedRating = 0;
          paintStars(0, false);
          if (timeSelect) timeSelect.value = '';
          if (crowdSelect) crowdSelect.value = '';
          submitBtn.textContent = 'Thanks for your review!';
          setTimeout(function () {
            submitBtn.textContent = 'Submit Review';
            submitBtn.disabled = false;
          }, 1800);
        })
        .catch(function (err) {
          if (err.message !== 'not_logged_in') {
            console.error('Review submit failed:', err);
            submitBtn.textContent = 'Submit Review';
            submitBtn.disabled = false;
          }
        });
    });
  }

})();