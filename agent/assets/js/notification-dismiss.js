/* Close (X) buttons on notification items. Needs NT (agent.js or user.js) loaded first. */
(function () {
  'use strict';
  if (!window.NT) return;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-dismiss]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    var item = btn.closest('.nt-item');
    btn.disabled = true;

    NT.post(btn.getAttribute('data-url'), { key: btn.getAttribute('data-dismiss') }).then(function (res) {
      if (res && res.success) {
        item.classList.add('is-removing');
        setTimeout(function () {
          item.remove();
          if (!document.querySelector('.nt-item')) location.reload();
        }, 320);
        NT.toast(res.message || 'Notification removed.');
      } else {
        btn.disabled = false;
        NT.toast((res && res.message) || 'Could not remove this notification.', 'error');
      }
    });
  }, true);
})();
