// ==========================================================================
// SMART TOURISM MANAGEMENT SYSTEM — gallery.js
// Handles: filter pills (sliding indicator + filtering),
//          like/favourite toggle, lightbox modal
// Pairs with: assets/css/gallery.css, pages/gallery.php
// ==========================================================================

document.addEventListener('DOMContentLoaded', function () {

  /* ---------------------------------------------------------------------
     0. MASONRY GRID SIZING
     CSS Grid gives each .gallery-item a tiny 8px row unit (grid-auto-rows).
     Here we measure each image's actual rendered height and set the
     item's grid-row-end: span N so it "spans" the right number of those
     tiny rows — producing a true Pinterest-style masonry that (unlike
     CSS columns) reflows cleanly when items are filtered in/out.
     --------------------------------------------------------------------- */
  var galleryGrid = document.getElementById('galleryGrid');

  function resizeGridItem(item) {
    if (!galleryGrid) return;
    var rowHeight = parseInt(window.getComputedStyle(galleryGrid).getPropertyValue('grid-auto-rows'), 10) || 8;
    var rowGap = parseInt(window.getComputedStyle(galleryGrid).getPropertyValue('gap'), 10) || 0;
    var img = item.querySelector('img');
    if (!img) return;
    var contentHeight = img.getBoundingClientRect().height;
    if (contentHeight === 0) return; // image not loaded/measured yet
    var rowSpan = Math.ceil((contentHeight + rowGap) / (rowHeight + rowGap));
    item.style.gridRowEnd = 'span ' + rowSpan;
  }

  function resizeAllGridItems() {
    document.querySelectorAll('.gallery-item').forEach(resizeGridItem);
  }

  // Size each item once its image has actually loaded (natural height known)
  document.querySelectorAll('.gallery-item img').forEach(function (img) {
    if (img.complete) {
      resizeGridItem(img.closest('.gallery-item'));
    } else {
      img.addEventListener('load', function () {
        resizeGridItem(img.closest('.gallery-item'));
      });
    }
  });

  // Re-measure on resize (tile width changes -> image height changes)
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resizeAllGridItems, 150);
  });

  /* ---------------------------------------------------------------------
     1. FILTER PILLS
     --------------------------------------------------------------------- */
  var filtersWrap = document.getElementById('galleryFilters');
  var indicator = document.getElementById('filterIndicator');
  var pills = document.querySelectorAll('.gallery-filter-pill');
  var galleryItems = document.querySelectorAll('.gallery-item');

  function moveIndicatorTo(pill) {
    if (!indicator || !pill) return;
    indicator.style.width = pill.offsetWidth + 'px';
    indicator.style.transform = 'translateX(' + pill.offsetLeft + 'px)';
  }

  function applyFilter(filterValue) {
    galleryItems.forEach(function (item) {
      // item.dataset.category is a comma-separated list of category_ids, e.g. "4,6"
      var itemCategoryIds = (item.dataset.category || '').split(',').map(function (id) {
        return id.trim();
      });
      var matches = filterValue === 'all' || itemCategoryIds.indexOf(String(filterValue)) !== -1;
      item.classList.toggle('is-hidden', !matches);
    });
    // Grid reflows automatically when items are hidden, but re-measuring
    // catches any items whose images finished loading after initial paint.
    resizeAllGridItems();
  }

  pills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      pills.forEach(function (p) { p.classList.remove('active'); });
      pill.classList.add('active');
      moveIndicatorTo(pill);
      applyFilter(pill.dataset.filter);
    });
  });

  // Position indicator under the initially-active pill ("All")
  var initialActive = document.querySelector('.gallery-filter-pill.active');
  if (initialActive) {
    // slight delay so layout/fonts have settled before measuring widths
    window.requestAnimationFrame(function () {
      moveIndicatorTo(initialActive);
    });
  }

  // Re-measure on resize (pill widths can change on wrap/breakpoints)
  window.addEventListener('resize', function () {
    var current = document.querySelector('.gallery-filter-pill.active');
    moveIndicatorTo(current);
  });

  /* ---------------------------------------------------------------------
     2. LIKE / FAVOURITE TOGGLE (grid cards)
     --------------------------------------------------------------------- */
  function setLiked(btn, liked) {
    btn.classList.toggle('liked', liked);
    btn.setAttribute('aria-pressed', String(liked));
    var icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-heart', !liked);
      icon.classList.toggle('bi-heart-fill', liked);
    }
  }

  document.querySelectorAll('.gallery-like-btn').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation(); // don't open the lightbox when liking
      setLiked(btn, !btn.classList.contains('liked'));

      // Hook point: send a like/unlike request to the server here, e.g.
      // fetch('/api/favourites.php', {
      //   method: 'POST',
      //   headers: { 'Content-Type': 'application/json' },
      //   body: JSON.stringify({
      //     image_id: btn.closest('.gallery-item').dataset.imageId,
      //     liked: btn.classList.contains('liked')
      //   })
      // });
    });
  });

  /* ---------------------------------------------------------------------
     3. LIGHTBOX MODAL
     --------------------------------------------------------------------- */
  var modalBackdrop = document.getElementById('galleryModalBackdrop');
  var modalImage = document.getElementById('galleryModalImage');
  var modalBadge = document.getElementById('galleryModalBadge');
  var modalLocationText = document.getElementById('galleryModalLocationText');
  var modalClose = document.getElementById('galleryModalClose');
  var modalLike = document.getElementById('galleryModalLike');

  function openModal(item) {
    modalImage.src = item.dataset.image;
    modalImage.alt = item.dataset.title;
    modalBadge.textContent = item.dataset.categoryLabel;
    modalLocationText.textContent = item.dataset.title;

    // reflect this card's current liked state in the modal
    var cardLikeBtn = item.querySelector('.gallery-like-btn');
    setLiked(modalLike, !!(cardLikeBtn && cardLikeBtn.classList.contains('liked')));
    modalLike.onclick = function () {
      setLiked(modalLike, !modalLike.classList.contains('liked'));
      if (cardLikeBtn) {
        setLiked(cardLikeBtn, modalLike.classList.contains('liked'));
      }
    };

    modalBackdrop.classList.add('active');
    document.body.classList.add('navbar-open'); // reuse scroll-lock utility from navbar.js
  }

  function closeModal() {
    modalBackdrop.classList.remove('active');
    document.body.classList.remove('navbar-open');
  }

  galleryItems.forEach(function (item) {
    item.addEventListener('click', function () {
      openModal(item);
    });
  });

  if (modalClose) modalClose.addEventListener('click', closeModal);

  if (modalBackdrop) {
    modalBackdrop.addEventListener('click', function (e) {
      if (e.target === modalBackdrop) closeModal();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });

});

/**
 * Gallery Marquee — continuous auto-scroll with hover ease-down.
 *
 * Markup expected:
 *   <section class="gallery-marquee" data-speed="0.55" data-hover-speed="0.12">
 *     <div class="gallery-marquee__viewport">
 *       <div class="gallery-marquee__track" id="galleryTrack">
 *         <div class="gallery-marquee__item"><img ...><span class="gallery-marquee__caption">...</span></div>
 *         ... one <div class="gallery-marquee__item"> per image, rendered from the DB ...
 *       </div>
 *     </div>
 *   </section>
 *
 * The script duplicates the rendered items once so the strip can loop
 * seamlessly, then drives the scroll with requestAnimationFrame so the
 * speed can be eased smoothly on hover (a CSS keyframe animation can only
 * jump between speeds, not glide between them).
 */
// 

/**
 * Gallery Marquee — continuous auto-scroll with hover ease-down.
 */
(function () {
  "use strict";

  document.querySelectorAll(".gallery-marquee").forEach(initMarquee);

  function initMarquee(section) {
    const track = section.querySelector(".gallery-marquee__track");

    if (!track || track.dataset.marqueeReady) return;

    track.dataset.marqueeReady = "true";

    const items = Array.from(track.children);

    if (items.length === 0) return;

    // Reduced motion
    const prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)"
    ).matches;

    if (prefersReducedMotion) return;

    // Duplicate items for seamless loop
    items.forEach((node) => {
      track.appendChild(node.cloneNode(true));
    });

    const baseSpeed =
      parseFloat(section.dataset.speed) || 1.5;

    const hoverSpeed =
      parseFloat(section.dataset.hoverSpeed) || 0.12;

    const easing = 0.06;

    let position = 0;
    let currentSpeed = baseSpeed;
    let targetSpeed = baseSpeed;
    let halfWidth = 0;
    let rafId = null;
    let paused = false;

    function measure() {
      halfWidth = track.scrollWidth / 2;
    }

    function setTarget(speed) {
      targetSpeed = speed;
    }

    function frame() {
      if (!paused) {
        // Smoothly change speed
        currentSpeed +=
          (targetSpeed - currentSpeed) * easing;

        position -= currentSpeed;

        // Seamless loop
        if (
          halfWidth > 0 &&
          Math.abs(position) >= halfWidth
        ) {
          position += halfWidth;
        }

        track.style.transform =
          "translate3d(" + position + "px, 0, 0)";
      }

      rafId = requestAnimationFrame(frame);
    }

    /*
     * -----------------------------------------
     * ONLY IMAGE/CARD HOVER SLOWS THE MARQUEE
     * -----------------------------------------
     */

    const galleryItems = track.querySelectorAll(
      ".gallery-marquee__item"
    );

    galleryItems.forEach((item) => {
      item.addEventListener("mouseenter", () => {
        setTarget(hoverSpeed);
      });

      item.addEventListener("mouseleave", () => {
        setTarget(baseSpeed);
      });
    });

    // Touch interaction
    galleryItems.forEach((item) => {
      item.addEventListener(
        "touchstart",
        () => setTarget(hoverSpeed),
        { passive: true }
      );

      item.addEventListener("touchend", () => {
        setTarget(baseSpeed);
      });
    });

    // Keyboard focus on image/card
    track.addEventListener("focusin", (event) => {
      if (
        event.target.closest(".gallery-marquee__item")
      ) {
        setTarget(hoverSpeed);
      }
    });

    track.addEventListener("focusout", (event) => {
      if (
        event.target.closest(".gallery-marquee__item")
      ) {
        setTarget(baseSpeed);
      }
    });

    // Pause when section is outside viewport
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          paused = !entry.isIntersecting;
        });
      },
      { threshold: 0 }
    );

    observer.observe(section);

    // Pause when browser tab is hidden
    document.addEventListener(
      "visibilitychange",
      () => {
        if (document.hidden) {
          paused = true;
        } else {
          paused = false;
        }
      }
    );

    // Re-measure after images load
    const imgs = track.querySelectorAll("img");

    imgs.forEach((img) => {
      if (!img.complete) {
        img.addEventListener("load", measure);
        img.addEventListener("error", measure);
      }
    });

    measure();

    window.addEventListener("resize", measure);

    rafId = requestAnimationFrame(frame);

    // Cleanup
    section._marqueeDestroy = () => {
      cancelAnimationFrame(rafId);
      observer.disconnect();
      window.removeEventListener("resize", measure);
    };
  }
})();