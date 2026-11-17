/* ==========================================================================
   NextTrip Admin — interactions
   Modals (viewport-centred), confirmations, toasts, AJAX actions, live search.
   ========================================================================== */
(function () {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const backdrop = document.getElementById('appModal');
  const modalEl = backdrop.querySelector('.modal');
  const mTitle = document.getElementById('modalTitle');
  const mBody = document.getElementById('modalBody');
  const mFoot = document.getElementById('modalFoot');
  const stack = document.getElementById('toastStack');

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
    mBody.scrollTop = 0;
  }
  function closeModal() {
    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
  }

  /* ---------- Toast ---------- */
  function toast(message, type = 'success', title) {
    while (stack.children.length >= 3) stack.removeChild(stack.firstChild);
    const isErr = type === 'error';
    const el = document.createElement('div');
    el.className = 'admin-toast' + (isErr ? ' admin-toast--error' : '');
    el.setAttribute('role', isErr ? 'alert' : 'status');
    el.innerHTML =
      `<span class="admin-toast__icon"><i class="fa-solid ${isErr ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i></span>` +
      `<div><span class="admin-toast__title">${esc(title || (isErr ? 'Something went wrong' : 'Success'))}</span>` +
      `<span class="admin-toast__msg">${esc(message)}</span></div>` +
      `<button type="button" class="admin-toast__close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>` +
      `<span class="admin-toast__bar"></span>`;
    stack.appendChild(el);
    let closed = false;
    const dismiss = () => {
      if (closed) return;
      closed = true;
      el.classList.add('is-leaving');
      setTimeout(() => el.remove(), 320);
    };
    el.querySelector('.admin-toast__close').addEventListener('click', dismiss);
    setTimeout(dismiss, 4500);
  }

  /* ---------- POST helper ---------- */
  async function post(url, data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    fd.append('csrf', csrf);
    try {
      const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } });
      const json = await res.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));
      return json;
    } catch (err) {
      return { success: false, message: 'Network error. Please try again.' };
    }
  }

  function setBusy(btn, busy, label) {
    if (!btn) return;
    btn.disabled = busy;
    btn.innerHTML = busy ? '<span class="spinner"></span>' : label;
  }

  function reloadSoon(msg, type = 'success') {
    if (msg) sessionStorage.setItem('ntToast', JSON.stringify({ msg, type }));
    setTimeout(() => location.reload(), 300);
  }

  /* ---------- Confirm dialog ---------- */
  function confirmBox({ title, message, okText, danger, onOk }) {
    openModal(
      title,
      `<div class="confirm">
         <div class="confirm-icon ${danger ? 'danger' : ''}"><i class="fa-solid ${danger ? 'fa-triangle-exclamation' : 'fa-circle-question'}"></i></div>
         <p>${message}</p>
       </div>`,
      `<button class="btn btn-ghost" type="button" data-close>Cancel</button>
       <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" type="button" id="confirmOk">${esc(okText)}</button>`,
      'sm'
    );
    document.getElementById('confirmOk').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      setBusy(btn, true);
      const res = await onOk();
      if (res && res.success) {
        closeModal();
        reloadSoon(res.message || 'Done.');
      } else {
        setBusy(btn, false, esc(okText));
        toast(res?.message || 'Something went wrong.', 'error');
      }
    });
  }

  /* ---------- Reject dialog (asks for a reason) ---------- */
  function rejectBox(btn) {
    const { url, id, name } = btn.dataset;
    openModal(
      'Reject application',
      `<p class="muted" style="margin-bottom:14px">You are rejecting <strong>${esc(name)}</strong>. The reason is saved and emailed to the applicant.</p>
       <label class="field-label" for="rejectReason">Rejection reason</label>
       <textarea id="rejectReason" maxlength="255" placeholder="For example: Aadhaar copy is unreadable. Please re-upload a clear scan."></textarea>
       <div class="form-error" id="rejectErr">Please enter a reason of at least 5 characters.</div>`,
      `<button class="btn btn-ghost" type="button" data-close>Cancel</button>
       <button class="btn btn-danger" type="button" id="rejectOk"><i class="fa-solid fa-ban"></i> Reject application</button>`,
      'sm'
    );
    document.getElementById('rejectOk').addEventListener('click', async (e) => {
      const submitBtn = e.currentTarget;
      const reason = document.getElementById('rejectReason').value.trim();
      const err = document.getElementById('rejectErr');
      if (reason.length < 5) { err.classList.add('show'); return; }
      err.classList.remove('show');
      setBusy(submitBtn, true);
      const res = await post(url, { id, action: 'reject', reason });
      if (res.success) {
        closeModal();
        reloadSoon(res.message);
        return;
      }
      // Already decided by an earlier action: the list is stale, so refresh it
      if (/already been (decided|reviewed)/i.test(res.message || '')) {
        closeModal();
        reloadSoon(res.message);
        return;
      }
      setBusy(submitBtn, false, '<i class="fa-solid fa-ban"></i> Reject application');
      toast(res.message, 'error');
    });
  }

  /* ---------- Delete dialog that requires a reason (emailed to the owner) ---------- */
  function deleteBox(btn) {
    const { url, id, name, title } = btn.dataset;
    openModal(
      title || 'Delete',
      `<div class="confirm">
         <div class="confirm-icon danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
         <p>You are deleting <strong>${esc(name)}</strong>. This cannot be undone. The owning agent will be emailed the reason below.</p>
       </div>
       <label class="field-label" for="delReason">Reason for deletion</label>
       <textarea id="delReason" maxlength="500" placeholder="For example: Duplicate listing, or details are outdated and no longer valid."></textarea>
       <div class="form-error" id="delErr">Please enter a reason of at least 5 characters.</div>`,
      `<button class="btn btn-ghost" type="button" data-close>Cancel</button>
       <button class="btn btn-danger" type="button" id="delOk"><i class="fa-solid fa-trash-can"></i> Delete</button>`,
      'sm'
    );
    document.getElementById('delOk').addEventListener('click', async (e) => {
      const reason = document.getElementById('delReason').value.trim();
      const err = document.getElementById('delErr');
      if (reason.length < 5) { err.classList.add('show'); return; }
      err.classList.remove('show');
      setBusy(e.currentTarget, true);
      const res = await post(url, { id, reason });
      if (res.success) { closeModal(); reloadSoon(res.message); }
      else { setBusy(e.currentTarget, false, '<i class="fa-solid fa-trash-can"></i> Delete'); toast(res.message, 'error'); }
    });
  }

  /* ---------- Click delegation ---------- */
  document.addEventListener('click', async (e) => {
    if (e.target.closest('[data-close]') || e.target === backdrop) { closeModal(); return; }

    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const { action, url, id, name, title, size } = btn.dataset;
    e.preventDefault();

    if (action === 'view') {
      openModal(title || 'Details', '<div class="loader"><span class="spinner" style="border-color:#cfe3d7;border-top-color:var(--primary)"></span></div>', '', size || 'lg');
      try {
        const res = await fetch(`${url}?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        mBody.innerHTML = await res.text();
      } catch (err) {
        mBody.innerHTML = '<div class="alert alert-danger">Could not load details. Please try again.</div>';
      }
      mBody.scrollTop = 0;
    }

    if (action === 'delete' && btn.dataset.reason === 'required') { deleteBox(btn); return; }
    if (action === 'delete') {
      confirmBox({
        title: title || 'Delete record',
        message: `This will permanently delete <strong>${esc(name)}</strong>. This action cannot be undone.`,
        okText: 'Yes, delete',
        danger: true,
        onOk: () => post(url, { id })
      });
    }

    if (action === 'approve') {
      confirmBox({
        title: title || 'Approve',
        message: `Approve <strong>${esc(name)}</strong>? The applicant will be notified by email.`,
        okText: 'Approve',
        danger: false,
        onOk: () => post(url, { id, action: 'approve' })
      });
    }

    if (action === 'reject') rejectBox(btn);
  });

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && backdrop.classList.contains('open')) closeModal(); });

  /* ---------- Live search / filters: submit the GET form automatically ---------- */
  document.querySelectorAll('form[data-autosubmit]').forEach((form) => {
    let timer;
    form.querySelectorAll('input[type=search], input[type=text]').forEach((input) => {
      input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => form.requestSubmit(), 450); });
    });
    form.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => form.requestSubmit()));
  });

  /* ---------- Mobile sidebar ---------- */
  const shell = document.querySelector('.admin-shell');
  document.getElementById('menuToggle')?.addEventListener('click', () => shell.classList.toggle('nav-open'));
  document.getElementById('scrim')?.addEventListener('click', () => shell.classList.remove('nav-open'));

  /* ---------- Expose a tiny API for inline scripts ---------- */
  window.NT = { openModal, closeModal, toast, post, confirmBox, esc };

  /* ---------- Flash message from the query string (e.g. after a redirect) ---------- */
  const params = new URLSearchParams(location.search);
  if (params.get('msg')) toast(params.get('msg'), params.get('type') || 'success');

  const pending = sessionStorage.getItem('ntToast');
  if (pending) {
    sessionStorage.removeItem('ntToast');
    const t = JSON.parse(pending);
    toast(t.msg, t.type);
  }

})();