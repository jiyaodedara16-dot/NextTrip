/* Gallery likes: saves a liked photo to the logged-in user's favourites. */
(function () {
  'use strict';

  var ENDPOINT = 'ajax/gallery_favorite_toggle.php';
  var LOGIN_URL = 'auth/login.php?redirect=' + encodeURIComponent('gallery.php');
  var busy = {};

  function toast(message, ok) {
    var t = document.createElement('div');
    t.textContent = message;
    t.setAttribute('role', 'status');
    t.style.cssText =
      'position:fixed;left:50%;bottom:28px;transform:translateX(-50%) translateY(16px);opacity:0;' +
      'background:' + (ok ? '#146339' : '#a52d2d') + ';color:#fff;padding:12px 20px;border-radius:14px;' +
      'font:700 14px Manrope,system-ui,sans-serif;box-shadow:0 18px 36px -14px rgba(0,0,0,.35);' +
      'z-index:3000;transition:opacity .3s ease,transform .3s cubic-bezier(.22,1,.36,1);max-width:90vw;text-align:center;';
    document.body.appendChild(t);
    requestAnimationFrame(function () {
      t.style.opacity = '1';
      t.style.transform = 'translateX(-50%) translateY(0)';
    });
    setTimeout(function () {
      t.style.opacity = '0';
      setTimeout(function () { t.remove(); }, 300);
    }, 3000);
  }

  function paint(imageId, liked) {
    document.querySelectorAll('.gallery-item[data-image-id="' + imageId + '"] .gallery-like-btn').forEach(function (btn) {
      btn.classList.toggle('liked', liked);
      btn.setAttribute('aria-pressed', liked ? 'true' : 'false');
      btn.setAttribute('aria-label', liked ? 'Remove from favourites' : 'Add to favourites');
      var icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-heart-fill', liked);
        icon.classList.toggle('bi-heart', !liked);
      }
    });
  }

  function toggle(imageId, btn) {
    if (busy[imageId]) return;
    busy[imageId] = true;
    btn.disabled = true;

    var body = new URLSearchParams();
    body.set('image_id', imageId);

    fetch(ENDPOINT, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) {
        return res.text().then(function (text) {
          var data;
          try { data = JSON.parse(text); }
          catch (e) { data = { message: 'Unexpected server response.', detail: text.slice(0, 300) }; }
          return { status: res.status, data: data };
        });
      })
      .then(function (r) {
        if (r.status === 401) {
          toast('Log in to save photos to your favourites.', false);
          setTimeout(function () { location.href = LOGIN_URL; }, 1000);
          return;
        }
        if (r.status >= 300 || typeof r.data.liked === 'undefined') {
          console.error('[gallery likes]', r.status, r.data);
          toast(r.data.message || 'Could not update your favourites.', false);
          return;
        }
        paint(imageId, !!r.data.liked);
        toast(r.data.liked ? 'Added to your liked photos.' : 'Removed from your liked photos.', true);
      })
      .catch(function (err) {
        console.error('[gallery likes] network error', err);
        toast('Network problem. Please try again.', false);
      })
      .finally(function () {
        busy[imageId] = false;
        btn.disabled = false;
      });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.gallery-like-btn');
    if (!btn) return;
    var item = btn.closest('.gallery-item');
    var imageId = item && item.getAttribute('data-image-id');
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    if (!imageId) {
      toast('This photo could not be identified.', false);
      return;
    }
    toggle(imageId, btn);
  }, true);
})();
