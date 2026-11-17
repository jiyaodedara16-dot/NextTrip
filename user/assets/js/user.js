/* NextTrip Agent Console — self-contained interactions.
   Modals, confirmations, toasts, AJAX actions, repeatable rows, image previews, live search. */
(function () {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const backdrop = document.getElementById('appModal');
  const modalEl = backdrop.querySelector('.modal');
  const mTitle = document.getElementById('modalTitle');
  const mBody = document.getElementById('modalBody');
  const mFoot = document.getElementById('modalFoot');
  const stack = document.getElementById('toastStack');
  const shell = document.querySelector('.admin-shell');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------- Modal ---------- */
  function openModal(title, bodyHTML, footHTML = '', size = '') {
    mTitle.textContent = title;
    mBody.innerHTML = bodyHTML;
    mFoot.innerHTML = footHTML;
    mFoot.style.display = footHTML ? 'flex' : 'none';
    modalEl.className = 'modal ' + size;
    backdrop.classList.add('open');
    backdrop.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
  }
  function closeModal() {
    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }

  /* ---------- Toast (slides in, drains a progress bar, then fades out) ---------- */
  function toast(message, type = 'success', title) {
    while (stack.children.length >= 3) stack.removeChild(stack.firstChild);
    const isErr = type === 'error';
    const el = document.createElement('div');
    el.className = 'toast' + (isErr ? ' toast--error' : '');
    el.setAttribute('role', isErr ? 'alert' : 'status');
    el.innerHTML =
      `<span class="toast__icon"><i class="fa-solid ${isErr ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i></span>` +
      `<div><span class="toast__title">${esc(title || (isErr ? 'Something went wrong' : 'Success'))}</span>` +
      `<span class="toast__msg">${esc(message)}</span></div>` +
      `<button type="button" class="toast__close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>` +
      `<span class="toast__bar"></span>`;
    stack.appendChild(el);
    let closed = false;
    const dismiss = () => {
      if (closed) return;
      closed = true;
      el.classList.add('is-leaving');
      setTimeout(() => el.remove(), 320);
    };
    el.querySelector('.toast__close').addEventListener('click', dismiss);
    setTimeout(dismiss, 4500);
  }

  /* ---------- POST helper (adds the CSRF token) ---------- */
  async function post(url, data) {
    const fd = new FormData();
    Object.entries(data || {}).forEach(([k, v]) => fd.append(k, v));
    fd.append('csrf', csrf);
    try {
      const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } });
      return await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));
    } catch (err) {
      return { success: false, message: 'Network error. Please try again.' };
    }
  }

  function setBusy(btn, busy, html) {
    if (!btn) return;
    btn.disabled = busy;
    btn.innerHTML = busy ? '<span class="spinner"></span>' : html;
  }

  /* Successful actions reload the page; the message is carried across the reload */
  function reloadWith(msg, type = 'success') {
    if (msg) sessionStorage.setItem('ntToast', JSON.stringify({ msg, type }));
    setTimeout(() => location.reload(), 300);
  }

  /* ---------- Confirm dialog ---------- */
  function confirmBox({ title, message, okText, danger, onOk }) {
    openModal(title,
      `<div class="confirm"><div class="confirm-icon ${danger ? 'danger' : ''}">
         <i class="fa-solid ${danger ? 'fa-triangle-exclamation' : 'fa-circle-question'}"></i></div>
         <p>${message}</p></div>`,
      `<button class="btn btn-ghost" type="button" data-close>Cancel</button>
       <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" type="button" id="confirmOk">${esc(okText)}</button>`,
      'sm');
    document.getElementById('confirmOk').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      setBusy(btn, true);
      const res = await onOk();
      if (res && res.success) { closeModal(); reloadWith(res.message); }
      else { setBusy(btn, false, esc(okText)); toast(res?.message || 'Something went wrong.', 'error'); }
    });
  }

  /* ---------- Reason dialog (reject) ---------- */
  function rejectBox(btn) {
    const { url, id, name } = btn.dataset;
    openModal('Reject request',
      `<p class="muted" style="margin-bottom:14px">You are rejecting <strong>${esc(name)}</strong>. The reason is saved and emailed.</p>
       <label class="field-label" for="rejectReason">Reason</label>
       <textarea id="rejectReason" maxlength="500" placeholder="Explain briefly why this is being rejected."></textarea>
       <div class="form-error" id="rejectErr">Please enter a reason of at least 5 characters.</div>`,
      `<button class="btn btn-ghost" type="button" data-close>Cancel</button>
       <button class="btn btn-danger" type="button" id="rejectOk"><i class="fa-solid fa-ban"></i> Reject</button>`,
      'sm');
    document.getElementById('rejectOk').addEventListener('click', async (e) => {
      const reason = document.getElementById('rejectReason').value.trim();
      const err = document.getElementById('rejectErr');
      if (reason.length < 5) { err.classList.add('show'); return; }
      err.classList.remove('show');
      setBusy(e.currentTarget, true);
      const res = await post(url, { id, action: 'reject', reason });
      if (res.success) { closeModal(); reloadWith(res.message); }
      else { setBusy(e.currentTarget, false, '<i class="fa-solid fa-ban"></i> Reject'); toast(res.message, 'error'); }
    });
  }

  /* ---------- Click handling ---------- */
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-close]') || e.target === backdrop) { closeModal(); return; }

    const rm = e.target.closest('.rc-remove');
    if (rm) { rm.closest('.repeat-card')?.remove(); return; }

    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    e.preventDefault();
    const { action, url, id, name, title } = btn.dataset;

    if (action === 'delete') {
      confirmBox({
        title: title || 'Delete',
        message: `This permanently deletes <strong>${esc(name)}</strong>. It cannot be undone.`,
        okText: 'Yes, delete', danger: true,
        onOk: () => post(url, { id })
      });
    }
    if (action === 'approve') {
      confirmBox({
        title: title || 'Confirm',
        message: `Accept the request from <strong>${esc(name)}</strong>? They will be emailed to complete payment.`,
        okText: 'Accept', danger: false,
        onOk: () => post(url, { id, action: 'approve' })
      });
    }
    if (action === 'reject') rejectBox(btn);
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal(); });

  /* ---------- Repeatable rows (activities, tips, hotels, restaurants, itinerary) ---------- */
  let uid = Date.now();
  function addRow(tplId, listId) {
    const tpl = document.getElementById(tplId), list = document.getElementById(listId);
    if (!tpl || !list) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = tpl.innerHTML.replace(/__i__/g, String(++uid));
    const node = wrap.firstElementChild;
    list.appendChild(node);
    node.querySelector('input, textarea')?.focus();
  }
  document.querySelectorAll('[data-add]').forEach((b) => b.addEventListener('click', () => addRow(b.dataset.add, b.dataset.target)));
  document.querySelectorAll('.repeat-list').forEach((list) => {
    const b = document.querySelector(`[data-target="${list.id}"]`);
    if (b && !list.children.length) addRow(b.dataset.add, list.id);
  });

  /* ---------- Image previews ---------- */
  document.querySelectorAll('input[data-gallery]').forEach((input) => {
    input.addEventListener('change', () => {
      const box = document.getElementById(input.dataset.gallery);
      box.innerHTML = '';
      [...input.files].forEach((file, i) => {
        const el = document.createElement('label');
        el.className = 'gp-item';
        el.innerHTML = `<img src="${URL.createObjectURL(file)}" alt=""><span class="gp-cover">
          <input type="radio" name="cover_index" value="${i}" ${i === 0 ? 'checked' : ''}> Cover image</span>`;
        box.appendChild(el);
      });
    });
  });
  document.querySelectorAll('input[data-single-preview]').forEach((input) => {
    input.addEventListener('change', () => {
      const box = document.getElementById(input.dataset.singlePreview);
      box.innerHTML = '';
      const file = input.files[0];
      if (file) box.innerHTML = `<div class="gp-item"><img src="${URL.createObjectURL(file)}" alt=""><span class="gp-cover"><i class="fa-solid fa-star" style="color:var(--amber-dark)"></i> Cover image</span></div>`;
    });
  });

  /* ---------- Entry-fee switch enables/disables its input ---------- */
  document.querySelectorAll('input[data-toggle-target]').forEach((sw) => {
    const target = document.getElementById(sw.dataset.toggleTarget);
    const sync = () => { if (target) { target.disabled = !sw.checked; if (!sw.checked) target.value = ''; } };
    sw.addEventListener('change', sync);
    sync();
  });

  /* ---------- Busy state on submit ---------- */
  document.querySelectorAll('form[data-busy]').forEach((form) => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('button[type=submit]').forEach((b) => { b.disabled = true; b.innerHTML = '<span class="spinner"></span> Please wait…'; });
    });
  });

  /* ---------- Live search / filters submit their GET form ---------- */
  document.querySelectorAll('form[data-autosubmit]').forEach((form) => {
    let t;
    form.querySelectorAll('input[type=search], input[type=text]').forEach((i) =>
      i.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit(), 450); }));
    form.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => form.requestSubmit()));
  });

  /* ---------- Mobile sidebar ---------- */
  document.getElementById('menuToggle')?.addEventListener('click', () => shell.classList.toggle('nav-open'));
  document.getElementById('scrim')?.addEventListener('click', () => shell.classList.remove('nav-open'));

  /* ---------- Show messages carried across reloads / redirects ---------- */
  const pending = sessionStorage.getItem('ntToast');
  if (pending) { sessionStorage.removeItem('ntToast'); const t = JSON.parse(pending); toast(t.msg, t.type); }
  const qs = new URLSearchParams(location.search);
  if (qs.get('msg')) toast(qs.get('msg'), qs.get('type') || 'success');

  window.NT = { openModal, closeModal, toast, post, confirmBox };
})();

/* ---------- User extras: remove favourites (animated), open gallery photos ---------- */
(function () {
  'use strict';
  document.addEventListener('click', async (e) => {
    const fav = e.target.closest('[data-unfav]');
    if (fav) {
      e.preventDefault();
      const card = fav.closest('[data-card]');
      fav.disabled = true;
      const res = await NT.post(fav.dataset.url, { id: fav.dataset.id });
      if (res.success) {
        NT.toast(res.message);
        card.classList.add('is-removing');
        setTimeout(() => {
          card.remove();
          if (!document.querySelector('[data-card]')) location.reload();
        }, 350);
      } else {
        fav.disabled = false;
        NT.toast(res.message || 'Could not update favourites.', 'error');
      }
      return;
    }
    const lb = e.target.closest('[data-lightbox]');
    if (lb) {
      NT.openModal(lb.dataset.title || 'Photo',
        `<img class="ud-lightbox" src="${lb.dataset.src}" alt="">`, '', 'lg');
    }
  });
})();
