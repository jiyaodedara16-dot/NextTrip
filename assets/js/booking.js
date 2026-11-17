/* Booking modal: steps, validation, live price, traveller rows, submit. */
(function () {
  'use strict';
  const modal = document.getElementById('bkModal');
  if (!modal) return;

  const dialog = modal.querySelector('.bk-dialog');
  const form = document.getElementById('bkForm');
  const price = parseFloat(dialog.dataset.price) || 0;
  const maxA = parseInt(dialog.dataset.maxAdults, 10) || 0;
  const maxC = parseInt(dialog.dataset.maxChildren, 10) || 0;
  const steps = [...form.querySelectorAll('.bk-step')];
  const inds = [...modal.querySelectorAll('.bk-steps li')];
  const alertBox = document.getElementById('bkAlert');
  const btnBack = modal.querySelector('[data-bk-back]');
  const btnNext = modal.querySelector('[data-bk-next]');
  const btnSubmit = modal.querySelector('[data-bk-submit]');
  let cur = 0;

  const money = (n) => '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const $ = (id) => document.getElementById(id);
  const adults = () => parseInt($('bkAdults').value, 10) || 0;
  const children = () => parseInt($('bkChildren').value, 10) || 0;

  /* ---------- open / close ---------- */
  function openModal() {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('bk-lock');
    syncTravellers();
    updateSummary();
    showStep(0);
    setTimeout(() => $('bkDate').focus(), 200);
  }
  function closeModal() {
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('bk-lock');
  }
  document.querySelectorAll('[data-bk-open]').forEach((b) => b.addEventListener('click', (e) => { e.preventDefault(); openModal(); }));
  modal.querySelectorAll('[data-bk-close]').forEach((b) => b.addEventListener('click', closeModal));
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

  /* ---------- steppers ---------- */
  modal.querySelectorAll('[data-stepper]').forEach((box) => {
    const input = box.querySelector('input');
    box.querySelectorAll('[data-delta]').forEach((btn) => btn.addEventListener('click', () => {
      const delta = parseInt(btn.dataset.delta, 10);
      let v = (parseInt(input.value, 10) || 0) + delta;
      const isAdult = input.id === 'bkAdults';
      const min = isAdult ? 1 : 0;
      const max = isAdult ? maxA : maxC;
      v = Math.max(min, v);
      if (max) v = Math.min(max, v);
      input.value = v;
      syncTravellers();
      updateSummary();
    }));
  });

  /* ---------- travellers: one name per guest, first defaults to the contact ---------- */
  function syncTravellers() {
    const wrap = $('bkTravellers');
    const total = adults() + children();
    const existing = [...wrap.querySelectorAll('input')].map((i) => i.value);
    wrap.innerHTML = '';
    for (let i = 0; i < total; i++) {
      const row = document.createElement('div');
      row.className = 'bk-trav';
      const value = existing[i] !== undefined ? existing[i] : (i === 0 ? $('bkCName').value : '');
      row.innerHTML = `<span class="bk-trav-no">${i + 1}</span>
        <input type="text" name="travellers[]" placeholder="${i === 0 ? 'Full name (contact)' : (i < adults() ? 'Adult ' : 'Child ') + (i + 1) + ' — full name'}" value="">`;
      row.querySelector('input').value = value;
      wrap.appendChild(row);
    }
    $('bkPaxCount').textContent = total ? `(${total})` : '';
  }
  $('bkCName').addEventListener('input', () => {
    const first = $('bkTravellers').querySelector('input');
    if (first && !first.value.trim()) first.value = $('bkCName').value;
  });

  /* ---------- live price ---------- */
  function totals() {
    const sub = price * adults() + price * 0.5 * children();
    const gst = Math.round(sub * 0.05 * 100) / 100;
    return { sub, gst, total: sub + gst };
  }
  function updateSummary() {
    const t = totals();
    $('bkSumSub').textContent = money(t.sub);
    $('bkSumGst').textContent = money(t.gst);
    $('bkSumTotal').textContent = money(t.total);
    $('bkFootTotal').textContent = money(t.total);
    const d = $('bkDate').value;
    $('bkSumDate').textContent = d ? new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const parts = [`${adults()} adult${adults() === 1 ? '' : 's'}`];
    if (children()) parts.push(`${children()} child${children() === 1 ? '' : 'ren'}`);
    $('bkSumGuests').textContent = parts.join(', ');
  }
  ['bkDate'].forEach((id) => $(id).addEventListener('change', updateSummary));

  /* ---------- validation per step ---------- */
  function markInvalid(el, bad) { el.classList.toggle('is-invalid', bad); return bad; }
  function showError(msg) { alertBox.textContent = msg; alertBox.hidden = false; }
  function clearError() { alertBox.hidden = true; alertBox.textContent = ''; }

  function validate(step) {
    clearError();
    if (step === 0) {
      const d = $('bkDate');
      const minDate = d.min;
      if (markInvalid(d, !d.value || d.value < minDate)) return showError('Please choose a travel date from tomorrow onwards.'), false;
      if (markInvalid($('bkAdults'), adults() < 1)) return showError('At least one adult is required.'), false;
      if (maxA && adults() > maxA) return showError(`This package allows up to ${maxA} adults.`), false;
      if (maxC && children() > maxC) return showError(`This package allows up to ${maxC} children.`), false;
    }
    if (step === 1) {
      const name = $('bkCName'), phone = $('bkCPhone'), email = $('bkCEmail');
      if (markInvalid(name, !name.value.trim())) return showError('Please enter the contact name.'), false;
      if (markInvalid(phone, !/^[0-9+\- ]{7,20}$/.test(phone.value.trim()))) return showError('Please enter a valid phone number.'), false;
      if (markInvalid(email, !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()))) return showError('Please enter a valid email address.'), false;
      const names = [...$('bkTravellers').querySelectorAll('input')];
      const missing = names.find((n) => !n.value.trim());
      if (missing) { missing.classList.add('is-invalid'); return showError('Please enter a name for every traveller.'), false; }
      names.forEach((n) => n.classList.remove('is-invalid'));
    }
    if (step === 2) {
      if (!$('bkTerms').checked) return showError('Please accept the terms to send your request.'), false;
    }
    return true;
  }

  /* ---------- navigation ---------- */
  function showStep(i) {
    cur = i;
    steps.forEach((s, n) => s.classList.toggle('is-active', n === i));
    inds.forEach((li, n) => { li.classList.toggle('is-active', n === i); li.classList.toggle('is-done', n < i); });
    btnBack.hidden = i === 0;
    btnNext.hidden = i === steps.length - 1;
    btnSubmit.hidden = i !== steps.length - 1;
    form.scrollTop = 0;
    updateSummary();
  }
  btnNext.addEventListener('click', () => { if (validate(cur)) showStep(cur + 1); });
  btnBack.addEventListener('click', () => { clearError(); showStep(cur - 1); });

  /* ---------- submit ---------- */
  btnSubmit.addEventListener('click', async () => {
    if (!validate(2)) return;
    if (!validate(0) || !validate(1)) { showStep(!validate(0) ? 0 : 1); return; }
    btnSubmit.disabled = true;
    const original = btnSubmit.textContent;
    btnSubmit.textContent = 'Sending…';
    try {
      const body = new FormData(form);
      body.set('terms', $('bkTerms').checked ? '1' : '0');
      const res = await fetch('ajax/book_package.php', { method: 'POST', body, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.success) {
        form.hidden = true;
        document.querySelector('.bk-steps').hidden = true;
        document.querySelector('.bk-foot').hidden = true;
        $('bkDoneId').textContent = '#' + data.booking.booking_id;
        $('bkDone').hidden = false;
        clearError();
      } else {
        const details = Array.isArray(data.details) ? data.details.join(' ') : '';
        showError(data.message || details || 'We could not send your request. Please check the details and try again.');
      }
    } catch (err) {
      showError('Network problem. Please check your connection and try again.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmit.textContent = original;
    }
  });

  showStep(0);
})();