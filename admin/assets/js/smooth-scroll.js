/* Smooth scrolling for the whole admin panel (Lenis).
   Skipped automatically for users who prefer reduced motion. */
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !window.Lenis) return;

    const lenis = new Lenis({
    lerp: 0.085,            // lower = softer and floatier, higher = snappier
    smoothWheel: true,
    wheelMultiplier: 1.0,   // normal scroll distance per wheel tick
    touchMultiplier: 1.0,
    syncTouch: false
    });

  function raf(time) {
    lenis.raf(time);
    requestAnimationFrame(raf);
  }
  requestAnimationFrame(raf);

  window.ntLenis = lenis; // available to other scripts if needed
})();