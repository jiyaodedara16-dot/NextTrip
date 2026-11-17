// ==========================================================================
// SMART TOURISM MANAGEMENT SYSTEM — navbar.js
// Handles: scroll opacity change + mobile hamburger menu toggle
// Pairs with: assets/css/navbar.css
// ==========================================================================

document.addEventListener('DOMContentLoaded', function () {
  var navbar = document.querySelector('.navbar');
  var toggleBtn = document.querySelector('.navbar-toggle');
  var mobileMenu = document.querySelector('.navbar-mobile-menu');
  var closeBtn = document.querySelector('.navbar-mobile-close');
  var backdrop = document.querySelector('.navbar-backdrop');
  var scrollThreshold = 40; // px scrolled before navbar becomes more opaque

  // ---- 1. Scroll opacity effect ----
  function handleScroll() {
    if (window.scrollY > scrollThreshold) {
      navbar.classList.add('navbar-scrolled');
    } else {
      navbar.classList.remove('navbar-scrolled');
    }
  }
  window.addEventListener('scroll', handleScroll);
  handleScroll(); // run once on load

  // ---- 2. Hamburger / mobile menu toggle ----
  function openMenu() {
    toggleBtn.classList.add('active');
    mobileMenu.classList.add('active');
    if (backdrop) backdrop.classList.add('active');
    document.body.classList.add('navbar-open');
    toggleBtn.setAttribute('aria-expanded', 'true');
  }

  function closeMenu() {
    toggleBtn.classList.remove('active');
    mobileMenu.classList.remove('active');
    if (backdrop) backdrop.classList.remove('active');
    document.body.classList.remove('navbar-open');
    toggleBtn.setAttribute('aria-expanded', 'false');
  }

  if (toggleBtn && mobileMenu) {
    toggleBtn.addEventListener('click', function () {
      var isOpen = mobileMenu.classList.contains('active');
      isOpen ? closeMenu() : openMenu();
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeMenu);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeMenu);
  }

  // Close menu automatically if window is resized back to desktop width
  window.addEventListener('resize', function () {
    if (window.innerWidth > 991.98) {
      closeMenu();
    }
  });
});

