(function () {
  'use strict';

  /* -----------------------------------------------------------------------
     1. IMAGE CAROUSEL — same behaviour as place-details.js (autoplay every
        3.5s, pause on hover/touch/focus, swipe support, wraps around).
     ------------------------------------------------------------------- */
  var track = document.getElementById('pkgdCarouselTrack');
  var prevBtn = document.getElementById('pkgdCarouselPrev');
  var nextBtn = document.getElementById('pkgdCarouselNext');
  var dots = document.querySelectorAll('#pkgdCarouselDots .pd-carousel__dot');
  var slideCount = track ? track.children.length : 0;
  var current = 0;

  function goToSlide(index) {
    if (!track) return;
    current = Math.max(0, Math.min(index, slideCount - 1));
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    dots.forEach(function (dot, i) { dot.classList.toggle('is-active', i === current); });
    if (prevBtn) prevBtn.disabled = current === 0;
    if (nextBtn) nextBtn.disabled = current === slideCount - 1;
  }
  function goToNextSlide() {
    goToSlide(current + 1 >= slideCount ? 0 : current + 1);
  }

  if (prevBtn) prevBtn.addEventListener('click', function () { goToSlide(current - 1); restartAutoplay(); });
  if (nextBtn) nextBtn.addEventListener('click', function () { goToSlide(current + 1); restartAutoplay(); });
  dots.forEach(function (dot, i) {
    dot.addEventListener('click', function () { goToSlide(i); restartAutoplay(); });
  });
  goToSlide(0);

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

  var carouselViewport = document.querySelector('#pkgdCarousel .pd-carousel__viewport');
  if (carouselViewport) {
    carouselViewport.addEventListener('mouseenter', stopAutoplay);
    carouselViewport.addEventListener('mouseleave', startAutoplay);
    carouselViewport.addEventListener('focusin', stopAutoplay);
    carouselViewport.addEventListener('focusout', startAutoplay);
    carouselViewport.addEventListener('touchstart', stopAutoplay, { passive: true });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopAutoplay(); else startAutoplay();
  });
  startAutoplay();

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
     2. FAVOURITE (heart) BUTTON — real AJAX to ajax/package_favorite_toggle.php
     ------------------------------------------------------------------- */
  var likeBtn = document.getElementById('pkgdLikeBtn');
  if (likeBtn) {
    likeBtn.addEventListener('click', function () {
      if (!window.PKGD_DATA || !window.PKGD_DATA.isLoggedIn) {
        window.location.href = (window.PKGD_DATA && window.PKGD_DATA.loginUrl) || 'auth/login.php';
        return;
      }
      if (likeBtn.disabled) return;
      likeBtn.disabled = true;

      var icon = likeBtn.querySelector('i');
      var body = new URLSearchParams({ package_id: window.PKGD_DATA.packageId });

      fetch('ajax/package_favorite_toggle.php', { method: 'POST', body: body })
        .then(function (res) {
          if (res.status === 401) { window.location.href = window.PKGD_DATA.loginUrl; throw new Error('not_logged_in'); }
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
        })
        .catch(function (err) {
          if (err.message !== 'not_logged_in') console.error('Favourite toggle failed:', err);
        })
        .finally(function () { likeBtn.disabled = false; });
    });
  }

  /* -----------------------------------------------------------------------
     3. PLACES COVERED — horizontal-scroll strip; native touch scroll works
        out of the box, this just adds click-and-drag support for mouse users.
     ------------------------------------------------------------------- */
  var placesStrip = document.getElementById('pkgdPlacesStrip');
  if (placesStrip) {
    var isDown = false, startX = 0, scrollLeft = 0, dragged = false;
    placesStrip.addEventListener('mousedown', function (e) {
      isDown = true;
      dragged = false;
      startX = e.pageX - placesStrip.offsetLeft;
      scrollLeft = placesStrip.scrollLeft;
    });
    ['mouseleave', 'mouseup'].forEach(function (evt) {
      placesStrip.addEventListener(evt, function () { isDown = false; });
    });
    placesStrip.addEventListener('mousemove', function (e) {
      if (!isDown) return;
      e.preventDefault();
      var x = e.pageX - placesStrip.offsetLeft;
      var walk = x - startX;
      if (Math.abs(walk) > 5) dragged = true;
      placesStrip.scrollLeft = scrollLeft - walk;
    });
    // A drag that moved the strip shouldn't also trigger the link underneath.
    placesStrip.addEventListener('click', function (e) {
      if (dragged) { e.preventDefault(); e.stopPropagation(); }
    }, true);
  }

  /* -----------------------------------------------------------------------
     4. BOOK PACKAGE — live estimated total + AJAX submit to
        ajax/book_package.php (item #8)
     ------------------------------------------------------------------- */
  var bookForm = document.getElementById('pkgdBookForm');
  if (bookForm) {
    var adultsInput = document.getElementById('pkgdAdults');
    var childrenInput = document.getElementById('pkgdChildren');
    var estTotalEl = document.getElementById('pkgdEstTotal');
    var pricePerPerson = (window.PKGD_DATA && window.PKGD_DATA.pricePerPerson) || 0;

    function updateEstimate() {
      var adults = Math.max(1, parseInt(adultsInput.value, 10) || 1);
      var children = Math.max(0, parseInt(childrenInput.value, 10) || 0);
      // Mirrors the server-side pricing in ajax/book_package.php: children
      // at 50% + 5% GST — kept in sync so the estimate never surprises
      // the traveller once the real booking is priced server-side.
      var subtotal = (pricePerPerson * adults) + (pricePerPerson * 0.5 * children);
      var total = subtotal * 1.05;
      if (estTotalEl) estTotalEl.textContent = '₹' + Math.round(total).toLocaleString('en-IN');
    }
    if (adultsInput) adultsInput.addEventListener('input', updateEstimate);
    if (childrenInput) childrenInput.addEventListener('input', updateEstimate);
    updateEstimate();

    bookForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var submitBtn = document.getElementById('pkgdBookBtn');
      var travelDate = document.getElementById('pkgdTravelDate').value;

      if (!travelDate) {
        alert('Please choose a travel date.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Booking…';

      var body = new URLSearchParams({
        package_id: bookForm.dataset.packageId,
        travel_date: travelDate,
        adult_count: adultsInput.value,
        child_count: childrenInput.value,
      });

      fetch('ajax/book_package.php', { method: 'POST', body: body })
        .then(function (res) {
          if (res.status === 401) { window.location.href = window.PKGD_DATA.loginUrl; throw new Error('not_logged_in'); }
          return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        })
        .then(function (result) {
          if (!result.ok) {
            alert('Could not complete your booking — please check the details and try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Book Package';
            return;
          }
          bookForm.hidden = true;
          var successEl = document.getElementById('pkgdBookSuccess');
          if (successEl) successEl.hidden = false;
        })
        .catch(function (err) {
          if (err.message !== 'not_logged_in') {
            console.error('Booking failed:', err);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Book Package';
          }
        });
    });
  }
})();
