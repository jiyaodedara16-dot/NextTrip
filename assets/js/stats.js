/* ==========================================================================
   SMART TOURISM MANAGEMENT SYSTEM
   Stats Count-Up Script (stats.js)
   Animates each .stats-item's number from 0 to its data-target value the
   first time it scrolls into view. Runs once per item, then stops.
   ========================================================================== */

(function () {
  "use strict";

  var items = document.querySelectorAll(".stats-item[data-target]");
  if (!items.length) return;

  var DURATION_MS = 1600;

  function animateItem(item) {
    var target = parseFloat(item.getAttribute("data-target")) || 0;
    var decimals = parseInt(item.getAttribute("data-decimals"), 10) || 0;
    var countEl = item.querySelector(".stats-count");
    if (!countEl) return;

    var startTime = null;

    function formatValue(value) {
      if (decimals > 0) {
        return value.toFixed(decimals);
      }
      return Math.floor(value).toLocaleString("en-IN");
    }

    function step(timestamp) {
      if (startTime === null) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / DURATION_MS, 1);
      var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
      countEl.textContent = formatValue(target * eased);

      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        countEl.textContent = formatValue(target);
      }
    }

    window.requestAnimationFrame(step);
  }

  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateItem(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 }
    );

    items.forEach(function (item) {
      observer.observe(item);
    });
  } else {
    // Fallback for browsers without IntersectionObserver: animate immediately
    items.forEach(animateItem);
  }
})();
