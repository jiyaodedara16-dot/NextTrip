/* Package booking page: live pricing, steppers, coupon apply/remove, submit.
   Every network call reports errors on the page, so nothing fails silently. */
(function () {
  'use strict';
  var cfg = window.BKP;
  var form = document.getElementById('bkpForm');
  if (!cfg || !form) return;

  var $ = function (id) { return document.getElementById(id); };
  var money = function (n) { return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var state = { adults: 1, children: 0, coupon: null };
  var lastTotal = null;

  /* ---------- pricing (display only; the server recalculates on submit) ---------- */
  function totals() {
    var adultAmt = cfg.price * state.adults;
    var childAmt = cfg.price * 0.5 * state.children;
    var base = adultAmt + childAmt;
    var discount = state.coupon ? Math.min(state.coupon.discount, base) : 0;
    var sub = Math.max(0, base - discount);
    var gst = Math.round(sub * 0.05 * 100) / 100;
    return { adultAmt: adultAmt, childAmt: childAmt, base: base, discount: discount, sub: sub, gst: gst, total: sub + gst };
  }

  function render(animate) {
    var t = totals();
    $('bkpAdults').textContent = state.adults;
    $('bkpChildren').textContent = state.children;
    $('bkpLineA').textContent = state.adults + (state.adults === 1 ? ' Adult' : ' Adults') + ' × ' + money(cfg.price);
    $('bkpAdultAmt').textContent = money(t.adultAmt);
    $('bkpRowC').hidden = state.children === 0;
    $('bkpLineC').textContent = state.children + (state.children === 1 ? ' Child' : ' Children') + ' × ' + money(cfg.price * 0.5) + ' (50%)';
    $('bkpChildAmt').textContent = money(t.childAmt);
    $('bkpBase').textContent = money(t.base);
    $('bkpRowD').hidden = !state.coupon;
    if (state.coupon) {
      $('bkpCodeTag').textContent = state.coupon.code;
      $('bkpDiscount').textContent = '− ' + money(t.discount);
    }
    $('bkpGst').textContent = money(t.gst);
    $('bkpTotal').textContent = money(t.total);
    $('bkpSave').hidden = !state.coupon;
    if (state.coupon) $('bkpSave').textContent = 'You save ' + money(t.discount) + ' with this coupon';

    var stepDown = document.querySelectorAll('[data-kind="adult"][data-d="-1"]')[0];
    if (stepDown) stepDown.disabled = state.adults <= 1;
    var addAdult = document.querySelectorAll('[data-kind="adult"][data-d="1"]')[0];
    if (addAdult) addAdult.disabled = !!cfg.maxA && state.adults >= cfg.maxA;
    var remChild = document.querySelectorAll('[data-kind="child"][data-d="-1"]')[0];
    if (remChild) remChild.disabled = state.children <= 0;
    var addChild = document.querySelectorAll('[data-kind="child"][data-d="1"]')[0];
    if (addChild) addChild.disabled = !!cfg.maxC && state.children >= cfg.maxC;

    if (animate && lastTotal !== null && Math.abs(lastTotal - t.total) > 0.001) {
      var el = $('bkpTotal');
      el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump');
    }
    lastTotal = t.total;
  }

  /* ---------- steppers ---------- */
  document.querySelectorAll('[data-kind]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var kind = btn.getAttribute('data-kind');
      var d = parseInt(btn.getAttribute('data-d'), 10);
      if (kind === 'adult') {
        var a = state.adults + d;
        state.adults = Math.max(1, cfg.maxA ? Math.min(cfg.maxA, a) : a);
      } else {
        var c = state.children + d;
        state.children = Math.max(0, cfg.maxC ? Math.min(cfg.maxC, c) : c);
      }
      render(true);
      if (state.coupon) reapplyCoupon();
    });
  });

  /* ---------- coupon ---------- */
  function setCouponMsg(text, kind) {
    var el = $('bkpCouponMsg');
    el.textContent = text || '';
    el.className = 'bkp-coupon-msg' + (kind ? ' ' + kind : '');
  }
  function showCoupon() {
    $('bkpApplied').hidden = !state.coupon;
    $('bkpCouponRow').hidden = !!state.coupon;
    if (state.coupon) $('bkpAppliedCode').textContent = state.coupon.code;
  }
  function removeCoupon(message) {
    state.coupon = null;
    $('bkpCoupon').value = '';
    showCoupon();
    render(true);
    setCouponMsg(message || '', message ? 'bad' : '');
  }

  function checkCoupon(code, silent) {
    var btn = $('bkpApply');
    var body = new FormData();
    body.append('code', code);
    body.append('package_id', cfg.packageId);
    body.append('adult_count', state.adults);
    body.append('child_count', state.children);
    if (!silent) { btn.disabled = true; btn.textContent = 'Checking…'; }
    return fetch('ajax/coupon_validate.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unexpected response from the server.' }; }); })
      .then(function (data) {
        if (data && data.ok) {
          state.coupon = { code: data.code, percent: data.percent, discount: data.totals.discount, capped: data.capped };
          showCoupon();
          render(true);
          setCouponMsg(data.message, 'ok');
        } else {
          var msg = (data && data.message) || 'This coupon cannot be used.';
          if (silent) removeCoupon(msg); else setCouponMsg(msg, 'bad');
        }
      })
      .catch(function () {
        setCouponMsg('Network problem. Please try again.', 'bad');
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = 'Apply';
      });
  }
  function reapplyCoupon() { if (state.coupon) checkCoupon(state.coupon.code, true); }

  $('bkpApply').addEventListener('click', function () {
    var code = $('bkpCoupon').value.trim().toUpperCase();
    if (!code) { setCouponMsg('Enter a promo code first.', 'bad'); return; }
    checkCoupon(code, false);
  });
  $('bkpCoupon').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); $('bkpApply').click(); }
  });
  $('bkpRemove').addEventListener('click', function () { removeCoupon('Coupon removed.'); });

  /* ---------- validation + submit ---------- */
  function showAlert(msg) {
    var a = $('bkpAlert');
    a.textContent = msg;
    a.hidden = false;
    a.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  function hideAlert() { $('bkpAlert').hidden = true; }
  function markInvalid(el, bad) {
    var box = el.closest('.bkp-input');
    if (box) box.classList.toggle('is-invalid', bad);
    return bad;
  }

  function validate() {
    var name = $('bkpName'), email = $('bkpEmail'), phone = $('bkpPhone'), date = $('bkpDate');
    if (markInvalid(name, !name.value.trim())) return 'Please enter your full name.';
    if (markInvalid(email, !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()))) return 'Please enter a valid email address.';
    if (markInvalid(phone, !/^[0-9 ]{10,14}$/.test(phone.value.trim()))) return 'Please enter a valid 10-digit mobile number.';
    if (markInvalid(date, !date.value || date.value < date.min)) return 'Please choose a travel date from tomorrow onwards.';
    if (!$('bkpTerms').checked) return 'Please accept the booking terms to continue.';
    return null;
  }

  $('bkpSubmit').addEventListener('click', function () {
    hideAlert();
    var problem = validate();
    if (problem) { showAlert(problem); return; }

    var btn = $('bkpSubmit');
    var label = btn.querySelector('span');
    btn.disabled = true;
    label.textContent = 'Sending your request…';

    var body = new FormData(form);
    body.set('adult_count', state.adults);
    body.set('child_count', state.children);
    body.set('terms', '1');
    body.set('coupon_code', state.coupon ? state.coupon.code : '');

    fetch('ajax/book_package.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().catch(function () { return { success: false, message: 'Unexpected response from the server.' }; })
          .then(function (data) { return { ok: r.ok, data: data }; });
      })
      .then(function (res) {
        if (res.ok && res.data.success) {
          form.hidden = true;
          $('bkpShell').hidden = true;
          $('bkpSteps').classList.add('at-3');
          var steps = document.querySelectorAll('#bkpSteps li');
          if (steps[1]) { steps[1].classList.remove('is-current'); steps[1].classList.add('is-done'); }
          if (steps[2]) steps[2].classList.add('is-current');
          $('bkpDoneId').textContent = '#' + res.data.booking.booking_id;
          $('bkpDone').hidden = false;
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
        }
        var msg = res.data.message ||
          (Array.isArray(res.data.details) ? res.data.details.join(' ') : 'We could not send your request. Please try again.');
        showAlert(msg);
        if (res.data.error === 'coupon_invalid') removeCoupon('');
      })
      .catch(function () { showAlert('Network problem. Please check your connection and try again.'); })
      .finally(function () {
        btn.disabled = false;
        label.textContent = 'Submit Booking Request';
      });
  });

  /* Steps: move the progress line to step 2 when the form is first used */
  $('bkpSteps').classList.add('at-2');

  render(false);
})();
