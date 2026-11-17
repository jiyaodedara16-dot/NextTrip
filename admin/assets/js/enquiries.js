/* Contact Enquiries: view modal (opening marks New as Seen), custom reply modal, filters and live badges.
   Uses NT (admin.js) for modals, toasts, and CSRF-protected POST requests. */
(function () {
  'use strict';

  const table = document.getElementById('enqTable');
  if (!table || !window.NT) return;
  const esc = window.NT.esc;
  const FROM_EMAIL = table.closest('.enq-wrap').dataset.from || '';
  const STATUS_CLASS = { 'New': 'enq-new', 'Seen': 'enq-seen', 'In Progress': 'enq-progress', 'Resolved': 'enq-resolved', 'Closed': 'enq-closed' };
  const newCount = document.getElementById('enqNewCount');

  /* ---- Filters: search waits until typing stops; selects apply at once ---- */
  const form = document.getElementById('enqFilters');
  if (form) {
    let timer;
    const q = form.querySelector('input[name="q"]');
    if (q) q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => form.submit(), 450); });
    form.querySelectorAll('select').forEach((s) => s.addEventListener('change', () => form.submit()));
  }

  table.addEventListener('click', (e) => {
    const view = e.target.closest('[data-enq-view]');
    if (view) return openView(view.dataset.enqView);
    const reply = e.target.closest('[data-enq-reply]');
    if (reply) openReply(reply.dataset.enqReply);
  });

  function getJson(url) {
    return fetch(url, { credentials: 'same-origin' })
      .then((r) => r.json())
      .catch(() => ({ success: false, message: 'Network error. Please try again.' }));
  }

  /* ---------------- View ---------------- */
  function openView(id) {
    window.NT.openModal('Enquiry #' + id, loader(), '', 'lg');
    getJson('ajax/enquiry_details.php?id=' + encodeURIComponent(id)).then((d) => {
      if (!d.success) {
        window.NT.closeModal();
        window.NT.toast(d.message || 'The enquiry could not be loaded.', 'error');
        return;
      }
      renderView(d.enquiry, d.replies || []);
      // Only an explicit View marks New as Seen. Loading the table never does.
      if (d.enquiry.status === 'New') markSeen(d.enquiry.enquiry_id);
    });
  }

  function renderView(en, replies) {
    const phone = en.phone ? esc(en.phone) : '—';
    const history = replies.length
      ? replies.map((r) => '<div class="enq-hist-item"><div><strong>' + esc(r.subject) + '</strong> · ' +
          '<span class="' + (r.send_status === 'Sent' ? 'enq-hist-ok' : 'enq-hist-fail') + '">' + esc(r.send_status) + '</span></div>' +
          '<div class="enq-hist-meta">' + esc(r.created_label) + ' · to ' + esc(r.to_email) + '</div></div>').join('')
      : '<p class="enq-muted">No replies sent yet.</p>';

    const body =
      '<dl class="kv">' +
        '<div><dt>User</dt><dd>' + esc(en.full_name) + '</dd></div>' +
        '<div><dt>Email</dt><dd>' + esc(en.email) + '</dd></div>' +
        '<div><dt>Phone</dt><dd>' + phone + '</dd></div>' +
        '<div><dt>Enquiry type</dt><dd>' + esc(en.type_label) + '</dd></div>' +
        '<div class="wide"><dt>Submitted on</dt><dd>' + esc(en.created_label) + '</dd></div>' +
        '<div class="wide"><dt>Status</dt><dd><span class="enq-badge ' + (STATUS_CLASS[en.status] || 'enq-new') + '" data-modal-status>' + esc(en.status) + '</span></dd></div>' +
      '</dl>' +
      '<div class="enq-message">' + esc(en.message) + '</div>' +
      '<div class="enq-history"><h4>Reply history</h4>' + history + '</div>';

    const foot =
      '<button class="btn btn-ghost" type="button" data-close>Close</button>' +
      '<button class="btn btn-primary" type="button" id="enqOpenReply"><i class="fa-solid fa-reply"></i> Reply to customer</button>';

    window.NT.openModal('Enquiry #' + en.enquiry_id, body, foot, 'lg');
    document.getElementById('enqOpenReply').addEventListener('click', () => {
      window.NT.closeModal();
      openReply(en.enquiry_id);
    });
  }

  function markSeen(id) {
    window.NT.post('ajax/enquiry_seen.php', { id: id }).then((res) => {
      if (res && res.success && res.changed) {
        updateStatus(id, res.status);
      }
    });
  }

  function updateStatus(id, status) {
    const row = table.querySelector('tr[data-id="' + id + '"]');
    const cell = row && row.querySelector('[data-status-cell]');
    if (cell) {
      cell.textContent = status;
      cell.className = 'enq-badge ' + (STATUS_CLASS[status] || 'enq-new');
    }
    const modalStatus = document.querySelector('[data-modal-status]');
    if (modalStatus) {
      modalStatus.textContent = status;
      modalStatus.className = 'enq-badge ' + (STATUS_CLASS[status] || 'enq-new');
    }
    if (newCount && status !== 'New') {
      const n = Math.max(0, parseInt(newCount.textContent, 10) - 1);
      newCount.textContent = n + ' new';
    }
  }

  /* ---------------- Reply ---------------- */
  function openReply(id) {
    window.NT.openModal('Reply to enquiry', loader(), '', 'md');
    getJson('ajax/enquiry_details.php?id=' + encodeURIComponent(id)).then((d) => {
      if (!d.success) {
        window.NT.closeModal();
        window.NT.toast(d.message || 'The enquiry could not be loaded.', 'error');
        return;
      }
      buildReplyModal(d.enquiry);
    });
  }

  function buildReplyModal(en) {
    const base = en.subject || 'Your NextTrip enquiry';
    const subject = /^re:/i.test(base) ? base : 'Re: ' + base;
    const idem = makeKey();
    let sending = false;

    const body =
      '<div class="enq-reply">' +
        '<p class="enq-reply-to">Replying to <strong>' + esc(en.full_name) + '</strong></p>' +
        '<div class="enq-reply-grid">' +
          '<div class="field"><label for="rpFrom">From</label><input id="rpFrom" type="text" value="' + esc(FROM_EMAIL) + '" readonly></div>' +
          '<div class="field"><label for="rpTo">To</label><input id="rpTo" type="text" value="' + esc(en.email) + '" readonly></div>' +
        '</div>' +
        '<div class="field"><label for="rpSubject">Subject</label><input id="rpSubject" type="text" maxlength="150" value="' + esc(subject) + '"></div>' +
        '<div class="field"><label for="rpMessage">Message</label>' +
          '<textarea id="rpMessage" rows="9" maxlength="5000" placeholder="Write your reply to the customer..."></textarea>' +
          '<small class="enq-count"><span id="rpCount">0</span> / 5000</small></div>' +
        '<p class="enq-reply-error" id="rpError" hidden></p>' +
      '</div>';

    const foot =
      '<button class="btn btn-ghost" type="button" data-close>Cancel</button>' +
      '<button class="btn btn-primary" type="button" id="rpSend"><i class="fa-solid fa-paper-plane"></i> Send reply</button>';

    window.NT.openModal('Reply to enquiry #' + en.enquiry_id, body, foot, 'md');

    const msg = document.getElementById('rpMessage');
    const count = document.getElementById('rpCount');
    const sendBtn = document.getElementById('rpSend');
    const errBox = document.getElementById('rpError');
    msg.addEventListener('input', () => { count.textContent = msg.value.length; });
    msg.focus();

    function showError(text) {
      errBox.textContent = text;
      errBox.hidden = false;
    }

    sendBtn.addEventListener('click', () => {
      if (sending) return;
      errBox.hidden = true;
      const subj = document.getElementById('rpSubject').value.trim();
      const text = msg.value.replace(/\r\n/g, '\n').trim();
      if (!subj) return showError('Please enter a subject.');
      if (text.length < 10) return showError('Please write a reply of at least 10 characters.');

      sending = true;
      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner enq-spin"></span> Sending…';

      window.NT.post('ajax/enquiry_reply.php', { id: en.enquiry_id, subject: subj, message: text, idem: idem })
        .then((res) => {
          sending = false;
          if (res && res.success) {
            window.NT.closeModal();
            window.NT.toast(res.message || 'Reply sent.', 'success');
            const row = table.querySelector('tr[data-id="' + en.enquiry_id + '"]');
            const pill = row && row.querySelector('[data-reply-cell]');
            if (pill) pill.hidden = false;
            return;
          }
          // Failed or already sent: keep the typed message so nothing is lost
          sendBtn.disabled = false;
          sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send reply';
          showError((res && res.message) || 'The reply could not be sent.');
        });
    });
  }

  /* ---------------- helpers ---------------- */
  function loader() {
    return '<div class="loader"><span class="spinner" style="border-color:#cfe3d7;border-top-color:var(--primary)"></span></div>';
  }
  function makeKey() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    let s = '';
    for (let i = 0; i < 32; i++) s += Math.floor(Math.random() * 16).toString(16);
    return s;
  }
})();