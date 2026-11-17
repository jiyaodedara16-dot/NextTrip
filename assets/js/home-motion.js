/* Light motion for the existing home page: progress bar and scroll reveal.
   Does not change content. If this script fails, every element stays visible. */
(function () {
  'use strict';
  var root = document.documentElement;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  /* Progress bar at the top of the page */
  var bar = document.createElement('div');
  bar.className = 'mo-progress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);
  function onScroll() {
    var max = root.scrollHeight - window.innerHeight;
    bar.style.transform = 'scaleX(' + (max > 0 ? window.scrollY / max : 0) + ')';
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* Elements that rise in as they scroll into view, staggered within their group */
  var selectors = [
    '.section-header',
    '.category-grid > *',
    '.features-grid > *',
    '.smart-features-grid > *',
    '.why-grid > *',
    '.why-image-wrapper',
    '.why-content',
    '.stats-item',
    '.cta-block'
  ];
  var targets = [];
  selectors.forEach(function (sel) {
    var groupIndex = 0;
    document.querySelectorAll(sel).forEach(function (el) {
      if (el.closest('.hero')) return;
      el.style.setProperty('--mo-i', (groupIndex % 6));
      groupIndex++;
      el.classList.add('mo-reveal');
      targets.push(el);
    });
  });

  if (!targets.length || !('IntersectionObserver' in window)) {
    targets.forEach(function (el) { el.classList.add('mo-in'); });
    return;
  }

  root.classList.add('mo-ready');
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) {
        e.target.classList.add('mo-in');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  targets.forEach(function (el) { io.observe(el); });

  /* Safety: reveal anything still hidden after a short delay */
  setTimeout(function () {
    targets.forEach(function (el) { el.classList.add('mo-in'); });
  }, 3000);
})();