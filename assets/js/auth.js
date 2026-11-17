/* ==========================================================================
   NextTrip — Sign in / Create account behaviour
   Used by: auth/login.php
   Server endpoints (relative to auth/login.php):
     ajax/send_otp.php    — validates + stages signup, emails the code
     ajax/verify_otp.php  — checks the code, creates the account
     ajax/resend_otp.php  — resends a fresh code
   ========================================================================== */
(function () {
  'use strict';

  var card = document.getElementById('authCard');
  var toast = document.getElementById('toast');
  var toastTimer = null;

  var ICON_EYE = '<svg class="ico" aria-hidden="true"><use href="#i-eye"/></svg>';
  var ICON_EYE_OFF = '<svg class="ico" aria-hidden="true"><use href="#i-eye-off"/></svg>';

  function $(id) { return document.getElementById(id); }

  function showToast(message) {
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.add('is-visible');
    toastTimer = setTimeout(function () { toast.classList.remove('is-visible'); }, 3800);
  }

  function setLoading(btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-loading', on);
    btn.disabled = on;
  }

  /* ---------- sliding between Sign in and Create account ---------- */
  function showSignUp() {
    card.classList.add('right-panel-active');
    history.replaceState(null, '', '?mode=signup');
  }
  function showSignIn() {
    card.classList.remove('right-panel-active');
    history.replaceState(null, '', location.pathname);
  }
  ['toSignUp', 'toSignUpMobile'].forEach(function (id) {
    var el = $(id);
    if (el) el.addEventListener('click', showSignUp);
  });
  ['toSignIn', 'toSignInMobile'].forEach(function (id) {
    var el = $(id);
    if (el) el.addEventListener('click', showSignIn);
  });

  /* ---------- show / hide password ---------- */
  document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
    btn.innerHTML = ICON_EYE;
    btn.addEventListener('click', function () {
      var input = $(btn.getAttribute('data-target'));
      var reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      btn.innerHTML = reveal ? ICON_EYE_OFF : ICON_EYE;
      btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
  });

  /* ---------- field error helpers ---------- */
  // Maps a logical field name to its input id. Ids are unique on the page,
  // so the sign-in password and the signup password never collide.
  var FIELD_IDS = {
    first_name: 're-first',
    last_name: 're-last',
    full_name: 're-agent-name',
    email: 're-email',
    phone: 're-phone',
    address: 're-address',
    city: 're-city',
    agent_address: 're-agent-address',
    agent_city: 're-agent-city',
    aadhaar_file: 're-aadhaar',
    pan_file: 're-pan',
    business_document_file: 're-business-doc',
    password: 're-pass',
    password_confirm: 're-confirm'
  };
  var STEP1_FIELDS = ['first_name', 'last_name', 'full_name', 'email', 'phone'];

  var FIELD_MESSAGES = {
    first_name_required: 'Please enter your first name.',
    last_name_required: 'Please enter your last name.',
    full_name_required: 'Please enter your full name.',
    address_required: 'Please enter your address.',
    city_required: 'Please enter your city.',
    invalid_email: 'Please enter a valid email address.',
    email_taken: 'An account with this email already exists.',
    invalid_phone: 'Please enter a valid phone number.',
    weak_password: 'Use at least 8 characters, with a letter and a number.',
    password_mismatch: 'Passwords don\u2019t match.',
    aadhaar_file_required: 'Please upload your Aadhaar card.',
    pan_file_required: 'Please upload your PAN card.',
    business_document_invalid: 'That file couldn\u2019t be used. Try a JPG, PNG or PDF under 5 MB.',
    terms_required: 'Please accept the terms to continue.'
  };

  function fieldWrap(name) {
    var input = $(FIELD_IDS[name]);
    return input ? input.closest('.field') : null;
  }

  function clearFieldError(field) {
    if (!field) return;
    field.classList.remove('is-invalid');
    var err = field.querySelector(':scope > .field__error');
    if (err) err.parentNode.removeChild(err);
  }

  function setFieldError(name, message) {
    var field = fieldWrap(name);
    if (!field) return false;
    clearFieldError(field);
    field.classList.add('is-invalid');
    var err = document.createElement('span');
    err.className = 'field__error';
    err.setAttribute('role', 'alert');
    err.textContent = message;
    field.appendChild(err);
    return true;
  }

  function clearAllStepErrors() {
    document.querySelectorAll('#signupInner .field').forEach(clearFieldError);
    $('registerFormError').hidden = true;
    $('termsError').hidden = true;
  }

  // Any edit to a field clears its own error straight away.
  document.querySelectorAll('#signupInner input').forEach(function (el) {
    el.addEventListener('input', function () { clearFieldError(el.closest('.field')); });
    el.addEventListener('change', function () { clearFieldError(el.closest('.field')); });
  });
  $('re-terms').addEventListener('change', function () { $('termsError').hidden = true; });

  /* ---------- role segmented control ---------- */
  var roleTabs = $('register-role-tabs');
  var currentRole = 'user';

  function selectRole(role) {
    var changed = role !== currentRole;
    currentRole = role;

    roleTabs.querySelectorAll('button').forEach(function (btn) {
      btn.setAttribute('aria-selected', btn.getAttribute('data-role') === role ? 'true' : 'false');
    });

    $('userNameFields').hidden = role !== 'user';
    $('agentNameField').hidden = role !== 'agent';
    $('userDetailFields').hidden = role !== 'user';
    $('agentDetailFields').hidden = role !== 'agent';
    $('step2Title').textContent = role === 'user' ? 'Where are you based?' : 'Verify your business';
    $('step2Sub').textContent = role === 'user'
      ? 'Helps us tailor recommendations to you.'
      : 'Required to list packages and destinations.';

    // Different role = different fields, so drop errors from the previous role.
    if (changed) clearAllStepErrors();
  }

  roleTabs.querySelectorAll('button').forEach(function (btn) {
    btn.addEventListener('click', function () { selectRole(btn.getAttribute('data-role')); });
  });

  /* ---------- document upload boxes ---------- */
  document.querySelectorAll('.doc input[type="file"]').forEach(function (input) {
    input.addEventListener('change', function () {
      var doc = input.closest('.doc');
      var name = doc.querySelector('.doc__name');
      if (input.files && input.files[0]) {
        doc.classList.add('is-filled');
        name.textContent = input.files[0].name;
      } else {
        doc.classList.remove('is-filled');
        name.textContent = name.getAttribute('data-default');
      }
      clearFieldError(input.closest('.field'));
    });
  });

  /* ---------- step navigation ---------- */
  var PANELS = {
    '1': $('step1'),
    '2': $('step2'),
    '3': $('form-register'),
    'otp': $('stepOtp')
  };

  function updateProgress(step) {
    var num = step === 'otp' ? 4 : parseInt(step, 10);
    document.querySelectorAll('#signupInner .steps').forEach(function (bar) {
      bar.querySelectorAll('span').forEach(function (seg, i) {
        seg.classList.toggle('is-done', i + 1 < num);
        seg.classList.toggle('is-active', i + 1 === num);
      });
    });
  }

  function goStep(step) {
    Object.keys(PANELS).forEach(function (key) {
      PANELS[key].hidden = key !== String(step);
    });
    updateProgress(step);
    var first = PANELS[String(step)].querySelector('input:not([type="file"])');
    if (first && step !== 'otp') window.setTimeout(function () { first.focus({ preventScroll: true }); }, 60);
  }

  function validateStep1() {
    var problems = [];
    if (currentRole === 'user') {
      if ($('re-first').value.trim() === '') problems.push(['first_name', FIELD_MESSAGES.first_name_required]);
      if ($('re-last').value.trim() === '') problems.push(['last_name', FIELD_MESSAGES.last_name_required]);
    } else {
      if ($('re-agent-name').value.trim() === '') problems.push(['full_name', FIELD_MESSAGES.full_name_required]);
    }
    var email = $('re-email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) problems.push(['email', FIELD_MESSAGES.invalid_email]);
    var phone = $('re-phone').value.trim();
    if (!/^[+\d][\d\s\-()]{6,19}$/.test(phone)) problems.push(['phone', FIELD_MESSAGES.invalid_phone]);
    return problems;
  }

  function validateStep2() {
    var problems = [];
    if (currentRole === 'agent') {
      if ($('re-agent-address').value.trim() === '') problems.push(['agent_address', FIELD_MESSAGES.address_required]);
      if ($('re-agent-city').value.trim() === '') problems.push(['agent_city', FIELD_MESSAGES.city_required]);
      if (!$('re-aadhaar').files.length) problems.push(['aadhaar_file', FIELD_MESSAGES.aadhaar_file_required]);
      if (!$('re-pan').files.length) problems.push(['pan_file', FIELD_MESSAGES.pan_file_required]);
    }
    return problems;
  }

  function showProblems(problems) {
    problems.forEach(function (p) { setFieldError(p[0], p[1]); });
    if (problems.length) {
      var firstField = fieldWrap(problems[0][0]);
      var input = firstField && firstField.querySelector('input:not([type="file"])');
      if (input) input.focus();
    }
    return problems.length === 0;
  }

  document.querySelectorAll('[data-next]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = btn.getAttribute('data-next');
      var ok = target === '2' ? showProblems(validateStep1()) : showProblems(validateStep2());
      if (ok) goStep(target);
    });
  });

  document.querySelectorAll('[data-back]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      clearAllStepErrors();
      goStep(btn.getAttribute('data-back'));
    });
  });

  /* ---------- step 3: submit → send_otp.php ---------- */
  var registerForm = $('form-register');
  var otpEmailDisplay = $('otpEmailDisplay');
  var otpMailWarning = $('otpMailWarning');

  // Server error code → field it belongs to (role decides address/city).
  function serverFieldFor(code) {
    if (code === 'address_required') return currentRole === 'agent' ? 'agent_address' : 'address';
    if (code === 'city_required') return currentRole === 'agent' ? 'agent_city' : 'city';
    var map = {
      first_name_required: 'first_name',
      last_name_required: 'last_name',
      full_name_required: 'full_name',
      invalid_email: 'email',
      email_taken: 'email',
      invalid_phone: 'phone',
      weak_password: 'password',
      password_mismatch: 'password_confirm',
      aadhaar_file_required: 'aadhaar_file',
      pan_file_required: 'pan_file',
      business_document_invalid: 'business_document_file'
    };
    return map[code] || null;
  }

  registerForm.addEventListener('submit', function (e) {
    e.preventDefault();
    $('registerFormError').hidden = true;
    $('termsError').hidden = true;
    document.querySelectorAll('#form-register .field').forEach(clearFieldError);

    var problems = [];
    var pass = $('re-pass').value;
    if (pass.length < 8 || !/[A-Za-z]/.test(pass) || !/\d/.test(pass)) problems.push(['password', FIELD_MESSAGES.weak_password]);
    if (pass !== $('re-confirm').value) problems.push(['password_confirm', FIELD_MESSAGES.password_mismatch]);

    var termsOk = $('re-terms').checked;
    if (!termsOk) {
      $('termsError').textContent = FIELD_MESSAGES.terms_required;
      $('termsError').hidden = false;
    }
    if (problems.length) showProblems(problems);
    if (problems.length || !termsOk) return;

    var btn = $('signupContinueBtn');
    setLoading(btn, true);

    var data = new FormData();
    data.set('kind', currentRole);
    data.set('email', $('re-email').value.trim());
    data.set('phone', $('re-phone').value.trim());
    data.set('password', pass);
    data.set('password_confirm', $('re-confirm').value);
    data.set('terms', '1');

    if (currentRole === 'user') {
      data.set('first_name', $('re-first').value.trim());
      data.set('last_name', $('re-last').value.trim());
      data.set('address', $('re-address').value.trim());
      data.set('city', $('re-city').value.trim());
    } else {
      data.set('full_name', $('re-agent-name').value.trim());
      data.set('address', $('re-agent-address').value.trim());
      data.set('city', $('re-agent-city').value.trim());
      if ($('re-aadhaar').files[0]) data.set('aadhaar_file', $('re-aadhaar').files[0]);
      if ($('re-pan').files[0]) data.set('pan_file', $('re-pan').files[0]);
      if ($('re-business-doc').files[0]) data.set('business_document_file', $('re-business-doc').files[0]);
    }

    fetch('ajax/send_otp.php', { method: 'POST', body: data })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.success) {
          otpEmailDisplay.textContent = json.email;
          otpMailWarning.hidden = !!json.mail_sent;
          clearOtpBoxes();
          goStep('otp');
          startResendCountdown(20);
          window.setTimeout(function () { otpBoxes[0].focus(); }, 150);
          return;
        }
        if (json.error === 'cooldown') {
          showToast('Please wait a few seconds before trying again.');
          return;
        }

        var codes = json.details || [];
        var inStep1 = false;
        var inStep2 = false;
        codes.forEach(function (code) {
          var field = serverFieldFor(code);
          if (!field) return;
          setFieldError(field, FIELD_MESSAGES[code] || 'Please check this field.');
          if (STEP1_FIELDS.indexOf(field) !== -1) inStep1 = true;
          else if (field !== 'password' && field !== 'password_confirm') inStep2 = true;
        });
        if (inStep1) { goStep(1); showToast('Please check the highlighted fields.'); }
        else if (inStep2) { goStep(2); showToast('Please check the highlighted fields.'); }
        else { $('registerFormError').textContent = 'Something went wrong. Please try again.'; $('registerFormError').hidden = false; }
      })
      .catch(function () { showToast('Connection problem. Please try again.'); })
      .then(function () { setLoading(btn, false); });
  });

  /* ---------- step 4: OTP ---------- */
  var otpForm = $('form-otp');
  var otpBoxesWrap = $('otpBoxes');
  var otpBoxes = Array.prototype.slice.call(otpBoxesWrap.querySelectorAll('input'));
  var otpError = $('otpError');
  var resendBtn = $('otpResendBtn');
  var resendHint = $('otpResendHint');
  var countdownEl = $('otpCountdown');
  var countdownTimer = null;

  function clearOtpBoxes() {
    otpBoxes.forEach(function (b) { b.value = ''; });
    otpBoxesWrap.classList.remove('is-invalid');
    otpError.hidden = true;
  }

  otpBoxes.forEach(function (box, i) {
    box.addEventListener('input', function () {
      box.value = box.value.replace(/\D/g, '').slice(0, 1);
      if (box.value && otpBoxes[i + 1]) otpBoxes[i + 1].focus();
      otpBoxesWrap.classList.remove('is-invalid');
      otpError.hidden = true;
    });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !box.value && otpBoxes[i - 1]) otpBoxes[i - 1].focus();
    });
    box.addEventListener('paste', function (e) {
      var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      if (!text) return;
      e.preventDefault();
      text.slice(0, 6).split('').forEach(function (ch, idx) { if (otpBoxes[idx]) otpBoxes[idx].value = ch; });
      var next = otpBoxes[Math.min(text.length, 5)];
      if (next) next.focus();
    });
  });

  function otpCode() {
    return otpBoxes.map(function (b) { return b.value; }).join('');
  }

  otpForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var code = otpCode();
    if (code.length !== 6) {
      otpBoxesWrap.classList.add('is-invalid');
      otpError.textContent = 'Enter all 6 digits.';
      otpError.hidden = false;
      return;
    }

    var btn = $('otpVerifyBtn');
    setLoading(btn, true);
    otpError.hidden = true;
    otpBoxesWrap.classList.remove('is-invalid');

    fetch('ajax/verify_otp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'otp=' + encodeURIComponent(code)
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (json.success) {
          showToast('Account created. Welcome to NextTrip!');
          window.setTimeout(function () { window.location.href = json.redirect; }, 700);
          return;
        }
        var messages = {
          otp_invalid: 'That code isn\u2019t right. Please try again.',
          otp_expired: 'That code has expired. Request a new one below.',
          otp_session_expired: 'Your session expired. Please start again.',
          otp_too_many_attempts: 'Too many attempts. Please request a new code.'
        };
        var first = (json.details || [])[0];
        otpBoxesWrap.classList.add('is-invalid');
        otpBoxes.forEach(function (b) { b.value = ''; });
        otpBoxes[0].focus();
        otpError.textContent = messages[first] || 'Something went wrong. Please try again.';
        otpError.hidden = false;
        if (first === 'otp_session_expired' || first === 'otp_too_many_attempts') {
          window.setTimeout(function () { goStep(1); }, 1600);
        }
      })
      .catch(function () { showToast('Connection problem. Please try again.'); })
      .then(function () { setLoading(btn, false); });
  });

  $('otpBackBtn').addEventListener('click', function () {
    clearInterval(countdownTimer);
    goStep(3);
  });

  function startResendCountdown(seconds) {
    resendBtn.hidden = true;
    resendHint.hidden = false;
    var remaining = seconds;
    countdownEl.textContent = remaining;
    clearInterval(countdownTimer);
    countdownTimer = setInterval(function () {
      remaining -= 1;
      countdownEl.textContent = remaining;
      if (remaining <= 0) {
        clearInterval(countdownTimer);
        resendHint.hidden = true;
        resendBtn.hidden = false;
      }
    }, 1000);
  }

  resendBtn.addEventListener('click', function () {
    resendBtn.disabled = true;
    fetch('ajax/resend_otp.php', { method: 'POST' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        resendBtn.disabled = false;
        if (json.success) {
          otpMailWarning.hidden = !!json.mail_sent;
          clearOtpBoxes();
          showToast('New code sent.');
          startResendCountdown(20);
        } else if (json.error === 'cooldown') {
          showToast('Please wait a little longer before resending.');
        } else {
          showToast('Your session expired. Please start again.');
          goStep(1);
        }
      })
      .catch(function () {
        resendBtn.disabled = false;
        showToast('Connection problem. Please try again.');
      });
  });

  /* ---------- init ---------- */
  selectRole('user');
  goStep(1);
})();
