document.addEventListener('DOMContentLoaded', function () {

  var REDUCE_MOTION = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* -----------------------------------------------------------------------
     HELPERS — smooth scroll (clears the fixed navbar) + toasts
     ------------------------------------------------------------------- */
  function scrollToEl(el, extra) {
    if (!el) return;
    var nav = document.querySelector('.navbar');
    var offset = (nav ? nav.offsetHeight : 72) + (extra === undefined ? 24 : extra);
    var top = el.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top: Math.max(top, 0), behavior: REDUCE_MOTION ? 'auto' : 'smooth' });
  }

  function toast(type, title, message) {
    var stack = document.getElementById('contactToasts');
    if (!stack) return;

    while (stack.children.length >= 3) stack.removeChild(stack.firstChild);

    var el = document.createElement('div');
    el.className = 'contact-toast contact-toast--' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML =
      '<span class="contact-toast__icon"><i class="bi ' +
        (type === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill') + '"></i></span>' +
      '<div><span class="contact-toast__title"></span><span class="contact-toast__msg"></span></div>' +
      '<button type="button" class="contact-toast__close" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>' +
      '<span class="contact-toast__bar"></span>';
    el.querySelector('.contact-toast__title').textContent = title;
    el.querySelector('.contact-toast__msg').textContent = message || '';
    stack.appendChild(el);

    var closed = false;
    function dismiss() {
      if (closed) return;
      closed = true;
      el.classList.add('is-leaving');
      window.setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 320);
    }
    el.querySelector('.contact-toast__close').addEventListener('click', dismiss);
    window.setTimeout(dismiss, 4500);
  }

  /* -----------------------------------------------------------------------
     INLINE VALIDATION — every error is shown right under its own field
     ------------------------------------------------------------------- */
  var FIELD_MESSAGES = {
    name: 'Please enter your name.',
    email: 'Please enter your email address.',
    destination_name: 'Please enter the destination name.',
    destination_state: 'Please enter the state.',
    rating: 'Please choose a star rating.'
  };
  var MESSAGE_BY_FORM = {
    main: 'Please write a short message.',
    feature: 'Please describe the feature you have in mind.',
    destination: 'Please tell us what makes this place special.',
    feedback: 'Please tell us a little about your experience.',
    problem: 'Please describe what went wrong.'
  };
  var SERVER_CODES = {
    name_required:              ['name', null],
    invalid_email:              ['email', 'That email address doesn\u2019t look right.'],
    invalid_phone:              ['phone', 'Please enter a valid phone number.'],
    message_required:           ['message', null],
    message_too_long:           ['message', 'That\u2019s a little too long \u2014 please shorten it.'],
    destination_name_required:  ['destination_name', null],
    destination_state_required: ['destination_state', null],
    invalid_rating:             ['rating', null]
  };

  function fieldFor(form, name) {
    if (name === 'rating') return form.querySelector('.contact-field--stars');
    var input = form.querySelector('[name="' + name + '"]');
    return input ? input.closest('.contact-field') : null;
  }

  function clearFieldError(field) {
    if (!field) return;
    field.classList.remove('is-invalid');
    var err = field.querySelector('.contact-field__error');
    if (err) err.parentNode.removeChild(err);
  }

  function clearErrors(form) {
    form.querySelectorAll('.contact-field').forEach(clearFieldError);
  }

  function setFieldError(form, name, message) {
    var field = fieldFor(form, name);
    if (!field) return false;
    clearFieldError(field);
    field.classList.add('is-invalid');
    var err = document.createElement('small');
    err.className = 'contact-field__error';
    err.setAttribute('role', 'alert');
    err.textContent = message;
    field.appendChild(err);
    return true;
  }

  function focusFirstInvalid(form) {
    var first = form.querySelector('.contact-field.is-invalid input, .contact-field.is-invalid select, .contact-field.is-invalid textarea, .contact-field.is-invalid .contact-fb-star');
    if (first) first.focus({ preventScroll: false });
  }

  // Client-side checks first, so people see every problem at once, each in place.
  function validateForm(form, type) {
    var problems = [];
    form.querySelectorAll('input[required], textarea[required]').forEach(function (el) {
      if (el.value.trim() === '') {
        var msg = el.name === 'message' ? MESSAGE_BY_FORM[type] : FIELD_MESSAGES[el.name];
        problems.push([el.name, msg || 'This field is required.']);
      }
    });

    var email = form.querySelector('input[name="email"]');
    if (email && email.value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      problems.push(['email', 'That email address doesn\u2019t look right.']);
    }

    var phone = form.querySelector('input[name="phone"]');
    if (phone && phone.value.trim() !== '' && !/^[+\d][\d\s\-()]{6,19}$/.test(phone.value.trim())) {
      problems.push(['phone', 'Please enter a valid phone number.']);
    }

    var rating = form.querySelector('input[name="rating"]');
    if (rating && parseInt(rating.value, 10) < 1) {
      problems.push(['rating', FIELD_MESSAGES.rating]);
    }
    return problems;
  }

  function showProblems(form, problems) {
    var unplaced = false;
    problems.forEach(function (p) {
      if (!setFieldError(form, p[0], p[1])) unplaced = true;
    });
    focusFirstInvalid(form);
    return !unplaced;
  }

  function showServerErrors(form, type, codes) {
    var problems = [];
    (codes || []).forEach(function (code) {
      var map = SERVER_CODES[code];
      if (!map) return;
      var name = map[0];
      var msg = map[1] || (name === 'message' ? MESSAGE_BY_FORM[type] : FIELD_MESSAGES[name]) || 'Please check this field.';
      problems.push([name, msg]);
    });
    return problems.length ? showProblems(form, problems) : false;
  }

  // Typing (or picking a star) clears that field's error straight away.
  function watchFormForClearing(form) {
    form.addEventListener('input', function (e) {
      clearFieldError(e.target.closest('.contact-field'));
    });
    form.addEventListener('change', function (e) {
      clearFieldError(e.target.closest('.contact-field'));
    });
  }

  function setLoading(btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-loading', on);
    btn.disabled = on;
  }

  // Shared submit flow for all 5 forms.
  function submitForm(form, type, onSuccess) {
    var btn = form.querySelector('button[type="submit"]');
    clearErrors(form);

    var problems = validateForm(form, type);
    if (problems.length) { showProblems(form, problems); return; }

    setLoading(btn, true);
    var data = new FormData(form);
    data.set('type', type);

    fetch('ajax/submit_enquiry.php', { method: 'POST', body: data })
      .then(function (res) { return res.json(); })
      .then(function (result) {
        if (result.success) {
          onSuccess(result);
        } else if (!showServerErrors(form, type, result.details)) {
          toast('error', 'Something went wrong', 'We couldn\u2019t send that just now. Please try again.');
        }
      })
      .catch(function () {
        toast('error', 'Connection problem', 'Please check your internet and try again.');
      })
      .then(function () { setLoading(btn, false); });
  }

  /* -----------------------------------------------------------------------
     1. QUICK CARDS + QUICK-HELP LINKS — set the type, then glide to the form
     ------------------------------------------------------------------- */
  var typeEl = document.getElementById('contactEnquiryType');
  var quickCards = document.querySelectorAll('.contact-quick-card');
  var formCard = document.getElementById('contactFormCard');

  function selectEnquiryType(type) {
    if (typeEl) typeEl.value = type;
    quickCards.forEach(function (card) {
      card.classList.toggle('is-active', card.dataset.enquiryType === type);
    });
  }

  document.querySelectorAll('[data-enquiry-type]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault(); // cards are buttons; help links carry an #anchor we replace with a smooth scroll
      selectEnquiryType(el.dataset.enquiryType);

      // If the "thank you" panel is showing from an earlier send, bring the form back.
      var formPanel = document.getElementById('contactFormPanel');
      var successPanel = document.getElementById('contactSuccessPanel');
      if (formPanel && formPanel.hidden) { formPanel.hidden = false; successPanel.hidden = true; }

      scrollToEl(formCard);
      var messageField = document.querySelector('#contactMainForm textarea[name="message"]');
      window.setTimeout(function () {
        if (messageField) messageField.focus({ preventScroll: true });
      }, REDUCE_MOTION ? 0 : 650);
    });
  });

  /* -----------------------------------------------------------------------
     2. MAIN CONTACT FORM
     ------------------------------------------------------------------- */
  (function () {
    var form = document.getElementById('contactMainForm');
    if (!form) return;
    var formPanel = document.getElementById('contactFormPanel');
    var successPanel = document.getElementById('contactSuccessPanel');
    var sendAnother = document.getElementById('contactSendAnother');

    watchFormForClearing(form);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitForm(form, 'main', function () {
        formPanel.hidden = true;
        successPanel.hidden = false;
        scrollToEl(formCard);
        toast('success', 'Message sent', 'We\u2019ve received your enquiry and will be in touch soon.');
      });
    });

    if (sendAnother) {
      sendAnother.addEventListener('click', function () {
        form.reset();
        selectEnquiryType('general');
        successPanel.hidden = true;
        formPanel.hidden = false;
      });
    }
  })();

  /* -----------------------------------------------------------------------
     3. FEEDBACK SECTION — tabs, star picker, and the 4 mini-forms
     ------------------------------------------------------------------- */
  var fbButtons = document.querySelectorAll('.contact-fb-btn');
  var fbPanels = document.querySelectorAll('.contact-fb-form-wrap');

  function showFbPanel(type) {
    fbButtons.forEach(function (b) { b.classList.toggle('is-active', b.dataset.fbType === type); });
    fbPanels.forEach(function (p) { p.hidden = p.dataset.fbPanel !== type; });
  }
  fbButtons.forEach(function (btn) {
    btn.addEventListener('click', function () { showFbPanel(btn.dataset.fbType); });
  });

  // Star picker
  var starWrap = document.querySelector('.contact-fb-stars');
  var stars = starWrap ? starWrap.querySelectorAll('.contact-fb-star') : [];
  var starInput = starWrap ? starWrap.querySelector('input[name="rating"]') : null;
  var starSelected = 0;

  function paintStars(value) {
    stars.forEach(function (s) {
      s.classList.toggle('is-filled', parseInt(s.dataset.value, 10) <= value);
    });
  }
  function resetStars() {
    starSelected = 0;
    if (starInput) starInput.value = '0';
    paintStars(0);
  }
  stars.forEach(function (star) {
    var value = parseInt(star.dataset.value, 10);
    star.addEventListener('mouseenter', function () { paintStars(value); });
    star.addEventListener('mouseleave', function () { paintStars(starSelected); });
    star.addEventListener('click', function () {
      starSelected = value;
      if (starInput) starInput.value = String(value);
      paintStars(value);
      clearFieldError(star.closest('.contact-field'));
    });
  });

  var FB_TOASTS = {
    feature:     ['Thanks for the idea!', 'Your feature suggestion is with our team.'],
    destination: ['Destination suggested', 'Thanks \u2014 we\u2019ll take a look at it.'],
    feedback:    ['Thanks for your review!', 'It\u2019s now on the reviews board below.'],
    problem:     ['Report received', 'Our team will look into it.']
  };

  document.querySelectorAll('.contact-fb-form').forEach(function (form) {
    var type = form.dataset.fbType;
    watchFormForClearing(form);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitForm(form, type, function (result) {
        form.reset();
        if (type === 'feedback') {
          resetStars();
          if (result.review && board) board.addReview(result.review, result.stats);
        }
        var t = FB_TOASTS[type];
        toast('success', t[0], t[1]);
      });
    });
  });

  var writeBtn = document.getElementById('reviewsWriteBtn');
  if (writeBtn) {
    writeBtn.addEventListener('click', function () {
      showFbPanel('feedback');
      scrollToEl(document.getElementById('contactFbButtons'), 40);
    });
  }

  /* -----------------------------------------------------------------------
     4. REVIEWS — 3D board (coverflow). Server renders the cards; this
        positions them in 3D space, handles nav/drag/autoplay, and can
        slot in a brand-new review without a page reload.
     ------------------------------------------------------------------- */
  var board = (function () {
    var root = document.getElementById('contactBoard');
    var stage = document.getElementById('boardStage');
    var dotsEl = document.getElementById('boardDots');
    var prevBtn = document.getElementById('boardPrev');
    var nextBtn = document.getElementById('boardNext');
    if (!root || !stage) return null;

    var cards = [];
    var current = 0;
    var timer = null;
    var paused = false;
    var dragStartX = null;
    var dragged = false;

    function esc(s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }
    function starsHtml(n) {
      var out = '';
      for (var i = 1; i <= 5; i++) out += '<i class="bi ' + (i <= n ? 'bi-star-fill' : 'bi-star') + '"></i>';
      return out;
    }

    function collect() { cards = Array.prototype.slice.call(stage.querySelectorAll('.contact-review-card')); }

    function layout() {
      var n = cards.length;
      if (!n) return;
      var cardW = cards[0].offsetWidth || 340;
      var step = cardW * (window.innerWidth < 640 ? 0.62 : 0.78);

      cards.forEach(function (card, i) {
        var o = i - current;
        if (o > n / 2) o -= n;
        if (o < -n / 2) o += n;
        var abs = Math.abs(o);

        card.style.transform =
          'translateX(-50%) translateX(' + (o * step) + 'px) translateZ(' + (-abs * 150) + 'px) ' +
          'rotateY(' + (-o * 36) + 'deg) scale(' + (1 - abs * 0.05) + ')';
        card.style.zIndex = String(10 - abs);
        card.style.opacity = abs === 0 ? '1' : abs === 1 ? '0.92' : abs === 2 ? '0.4' : '0';
        card.style.pointerEvents = abs > 2 ? 'none' : 'auto';
        card.classList.toggle('is-center', abs === 0);
        card.setAttribute('aria-hidden', abs === 0 ? 'false' : 'true');
      });

      var dots = dotsEl ? dotsEl.querySelectorAll('.contact-board__dot') : [];
      dots.forEach(function (d, i) { d.classList.toggle('is-active', i === current); });
    }

    function buildDots() {
      if (!dotsEl) return;
      dotsEl.innerHTML = '';
      cards.forEach(function (_, i) {
        var d = document.createElement('button');
        d.type = 'button';
        d.className = 'contact-board__dot';
        d.setAttribute('aria-label', 'Show review ' + (i + 1));
        d.addEventListener('click', function () { go(i); });
        dotsEl.appendChild(d);
      });
    }

    function refreshChrome() {
      var many = cards.length > 1;
      if (prevBtn) prevBtn.hidden = !many;
      if (nextBtn) nextBtn.hidden = !many;
      if (dotsEl) dotsEl.hidden = !many;
    }

    function go(i) {
      var n = cards.length;
      if (!n) return;
      current = ((i % n) + n) % n;
      layout();
      restart();
    }

    function restart() {
      if (timer) window.clearInterval(timer);
      if (REDUCE_MOTION || cards.length < 2) return;
      timer = window.setInterval(function () {
        if (!paused && !document.hidden) go(current + 1);
      }, 5500);
    }

    function attachCard(card) {
      card.addEventListener('click', function () {
        if (dragged) return;
        var i = cards.indexOf(card);
        if (i !== current) go(i);
      });
    }

    function addReview(review, stats) {
      var el = document.createElement('article');
      el.className = 'contact-review-card';
      el.innerHTML =
        '<i class="bi bi-quote contact-review-card__quote" aria-hidden="true"></i>' +
        '<div class="contact-review-card__stars">' + starsHtml(review.rating) + '</div>' +
        '<p class="contact-review-card__text">' + esc(review.review_text) + '</p>' +
        '<footer class="contact-review-card__who">' +
          '<span class="contact-review-card__avatar">' + esc(review.display_name.charAt(0).toUpperCase()) + '</span>' +
          '<span><strong>' + esc(review.display_name) + '</strong><small>' + esc(review.date_label) + '</small></span>' +
        '</footer>';

      // Start it a little off-stage so it glides in to the centre.
      el.style.opacity = '0';
      stage.insertBefore(el, stage.firstChild);

      root.hidden = false;
      var emptyEl = document.getElementById('reviewsEmpty');
      if (emptyEl) emptyEl.hidden = true;

      collect();
      cards.forEach(function (c) { if (!c.__wired) { attachCard(c); c.__wired = true; } });
      buildDots();
      refreshChrome();
      current = 0;
      layout();
      restart();

      if (stats) updateSummary(stats);
      window.setTimeout(function () { scrollToEl(document.getElementById('reviewsSection'), 12); }, 500);
    }

    function updateSummary(stats) {
      var summary = document.getElementById('reviewsSummary');
      var avg = document.getElementById('reviewsAvg');
      var avgStars = document.getElementById('reviewsAvgStars');
      var count = document.getElementById('reviewsCount');
      if (summary) summary.hidden = false;
      if (avg) avg.textContent = Number(stats.avg).toFixed(1);
      if (avgStars) avgStars.innerHTML = starsHtml(Math.round(stats.avg));
      if (count) count.textContent = stats.count;
    }

    // --- wiring ---
    collect();
    cards.forEach(function (c) { attachCard(c); c.__wired = true; });
    buildDots();
    refreshChrome();
    layout();
    restart();

    if (prevBtn) prevBtn.addEventListener('click', function () { go(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { go(current + 1); });

    root.addEventListener('mouseenter', function () { paused = true; });
    root.addEventListener('mouseleave', function () { paused = false; });
    stage.addEventListener('focus', function () { paused = true; });
    stage.addEventListener('blur', function () { paused = false; });
    stage.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(current - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); go(current + 1); }
    });

    // Swipe / drag
    stage.addEventListener('pointerdown', function (e) { dragStartX = e.clientX; dragged = false; });
    stage.addEventListener('pointermove', function (e) {
      if (dragStartX !== null && Math.abs(e.clientX - dragStartX) > 8) dragged = true;
    });
    function endDrag(e) {
      if (dragStartX === null) return;
      var dx = e.clientX - dragStartX;
      dragStartX = null;
      if (Math.abs(dx) > 45) go(dx < 0 ? current + 1 : current - 1);
      window.setTimeout(function () { dragged = false; }, 0);
    }
    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', function () { dragStartX = null; });

    window.addEventListener('resize', layout);

    return { addReview: addReview };
  })();

});
