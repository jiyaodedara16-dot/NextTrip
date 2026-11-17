/* Login gate: visitors can browse freely. Any link marked data-login-required (or a
   Destinations / Packages link) sends signed-out visitors to login and back again. */
(function () {
  'use strict';

  var self = document.currentScript;
  var base = self ? self.src : window.location.href;
  // This script lives in assets/js/, so go up two folders to reach the site root.
  var LOGIN = new URL('../../auth/login.php', base).href;
  var PROTECTED = ['destinations', 'packages', 'place-details', 'package-details'];

  function pageName(url) {
    var last = url.pathname.split('/').filter(Boolean).pop() || 'index.php';
    if (last.indexOf('.') === -1) last += '.php';
    return last;
  }

  document.addEventListener('click', function (e) {
    if (window.NT_LOGGED_IN) return;
    var a = e.target.closest && e.target.closest('a[href], [data-login-required]');
    if (!a) return;

    var marked = a.hasAttribute('data-login-required');
    var explicit = a.getAttribute('data-login-target');
    var href = a.getAttribute('href') || '';

    var url;
    try { url = new URL(href || '#', window.location.href); } catch (err) { return; }
    var name = pageName(url).replace(/\.php$/, '');

    if (!marked && PROTECTED.indexOf(name) === -1) return;

    e.preventDefault();
    e.stopPropagation();

    var target = explicit
      ? explicit
      : (href && href.charAt(0) !== '#' ? pageName(url) + url.search : 'packages.php');
    window.location.href = LOGIN + '?redirect=' + encodeURIComponent(target);
  }, true);
})();