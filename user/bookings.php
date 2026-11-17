<?php
$page_title = 'My Bookings';
$page_subtitle = 'Track requests, pay for accepted trips, and review trips you have completed.';
require_once __DIR__ . '/includes/auth.php';

$tab = in_array($_GET['tab'] ?? '', ['Pending', 'Accepted', 'Confirmed', 'Rejected', 'All'], true) ? $_GET['tab'] : 'All';
$cond = $tab === 'All' ? '' : " AND b.booking_status = '$tab'";
$counts = [];
foreach (rows("SELECT booking_status s, COUNT(*) c FROM bookings WHERE user_id = $user_id GROUP BY booking_status") as $r) $counts[$r['s']] = (int) $r['c'];

$list = rows("SELECT b.booking_id, b.travel_date, b.adult_count, b.child_count, b.total_amount, b.booking_status, b.agent_message,
                     p.package_name, p.duration_days, ag.full_name AS agent_name,
                     (SELECT pm.razorpay_payment_id FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS pay_id,
                     (SELECT pm.paid_at FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS paid_at,
                     (SELECT pm.payment_method FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS pay_method,
                     (SELECT COUNT(*) FROM package_reviews pr WHERE pr.booking_id = b.booking_id) AS reviewed
              FROM bookings b
              JOIN packages p ON p.package_id = b.package_id
              JOIN agents ag ON ag.agent_id = b.agent_id
              WHERE b.user_id = $user_id $cond
              ORDER BY FIELD(b.booking_status, 'Accepted', 'Pending', 'Confirmed', 'Rejected', 'Cancelled'), b.travel_date");

$pill = ['Pending' => ['pending', 'Waiting for agent'], 'Accepted' => ['accepted', 'Accepted · pay to confirm'],
         'Confirmed' => ['confirmed', 'Confirmed'], 'Rejected' => ['rejected', 'Not accepted'], 'Cancelled' => ['cancelled', 'Cancelled']];
$methodLabel = ['upi' => 'UPI', 'gpay' => 'Google Pay', 'card' => 'Card', 'netbanking' => 'Net banking', 'all' => 'Online'];
$pref = $me['preferred_payment'] ?? 'all';

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<style>
  .bk-review-btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 800; font-size: 14px; color: #fff; padding: 13px 20px; border-radius: 14px; background: linear-gradient(135deg, #f4a627, #e08a0b); box-shadow: 0 14px 24px -14px rgba(224,138,11,.9); transition: transform .2s, box-shadow .2s; }
  .bk-review-btn:hover { transform: translateY(-2px); box-shadow: 0 20px 30px -14px rgba(224,138,11,.95); color: #fff; }
  .bk-reviewed { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; color: #146339; background: #e8f6ee; border: 1px solid #cfe9d9; padding: 9px 14px; border-radius: 12px; text-decoration: none; }
  .bk-reviewed:hover { background: #d3eedd; color: #146339; }
  .bk-upcoming { font-size: 12.5px; color: #6b7a72; max-width: 220px; text-align: right; line-height: 1.5; }
  .bk-banner { margin-top: 18px; padding: 14px 18px; border-radius: 14px; background: #e8f6ee; color: #146339; font-weight: 700; display: flex; gap: 10px; align-items: center; animation: bk-rise .4s ease; }
  .bk-banner.bad { background: #fbe9e9; color: #a52d2d; }
  @keyframes bk-rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.bk-receipt-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:13px; text-decoration:none; font-weight:800; font-size:13.5px; color:#146339; background:#fff; border:1.5px solid #cfe9d9; transition: background .25s, transform .25s; }
  .bk-receipt-btn:hover { background:#e8f6ee; transform: translateY(-2px); color:#146339; }
  </style>

<section class="card anim" style="--d:0">
  <div class="card-head ud-tabs-wrap">
    <div class="tabs">
      <?php foreach (['All' => array_sum($counts), 'Accepted' => $counts['Accepted'] ?? 0, 'Pending' => $counts['Pending'] ?? 0, 'Confirmed' => $counts['Confirmed'] ?? 0, 'Rejected' => $counts['Rejected'] ?? 0] as $t => $n): ?>
        <a class="tab <?= $tab === $t ? 'active' : '' ?>" href="?tab=<?= $t ?>"><?= $t === 'Accepted' ? 'To pay' : $t ?> <span class="tab-count"><?= $n ?></span></a>
      <?php endforeach; ?>
    </div>
    <span class="muted" style="font-size:12.5px"><?= count($list) ?> booking<?= count($list) === 1 ? '' : 's' ?></span>
  </div>
</section>

<?php if (isset($_GET['reviewed'])): ?><div class="bk-banner"><i class="fa-solid fa-circle-check"></i> Thank you! Your reviews are now published.</div><?php endif; ?>
<?php if (isset($_GET['msg'])): ?><div class="bk-banner <?= ($_GET['type'] ?? '') === 'error' ? 'bad' : '' ?>"><?= h($_GET['msg']) ?></div><?php endif; ?>
<div id="payBanner" hidden></div>

<?php if (!$list): ?>
  <section class="card" style="margin-top:22px"><div class="empty">
    <i class="fa-solid fa-ticket"></i><h4>No bookings here</h4>
    <p>Book a package and your trips will show up in this list.</p>
    <a class="btn btn-primary" href="../packages.php" style="margin-top:12px"><i class="fa-solid fa-box-open"></i> Browse packages</a>
  </div></section>
<?php else: ?>
<section style="display:flex;flex-direction:column;gap:18px;margin-top:22px">
  <?php foreach ($list as $i => $b):
    [$cls, $label] = $pill[$b['booking_status']] ?? ['pending', $b['booking_status']];
    $st = $b['booking_status'];
    $accDone  = in_array($st, ['Accepted', 'Confirmed'], true);
    $paidDone = $st === 'Confirmed';
    $endDate  = date('Y-m-d', strtotime($b['travel_date'] . ' +' . (int) $b['duration_days'] . ' days'));
    $tripEnded = $paidDone && $endDate <= date('Y-m-d');
    $guests = (int) $b['adult_count'] . ' adult' . ((int) $b['adult_count'] === 1 ? '' : 's') . ((int) $b['child_count'] ? ', ' . (int) $b['child_count'] . ' child' . ((int) $b['child_count'] === 1 ? '' : 'ren') : '');
  ?>
<article class="card ud-card ud-booking" data-card style="--i: <?= $i ?>">
    <div>
      <span class="ud-pill <?= $cls ?>"><?= h($label) ?></span>
      <h3><?= h($b['package_name']) ?></h3>
      <div class="ud-meta" style="margin-top:8px">
        <span><i class="fa-solid fa-hashtag"></i>Booking #<?= (int) $b['booking_id'] ?></span>
        <span><i class="fa-regular fa-calendar"></i><?= fmt_date($b['travel_date']) ?> – <?= fmt_date($endDate) ?></span>
        <span><i class="fa-solid fa-users"></i><?= h($guests) ?></span>
        <span><i class="fa-solid fa-user-tie"></i><?= h($b['agent_name']) ?></span>
      </div>

      <div class="ud-timeline">
        <div class="ud-step done"><i class="fa-solid fa-paper-plane"></i> Requested</div>
        <div class="ud-line <?= $accDone ? 'done' : '' ?>"></div>
        <div class="ud-step <?= $accDone ? 'done' : '' ?>"><i class="fa-solid fa-circle-check"></i> Accepted</div>
        <div class="ud-line <?= $paidDone ? 'done' : '' ?>"></div>
        <div class="ud-step <?= $paidDone ? 'done' : '' ?>"><i class="fa-solid fa-ticket"></i> Paid &amp; confirmed</div>
      </div>

      <?php if ($st === 'Pending'): ?>
        <div class="ud-note"><i class="fa-solid fa-hourglass-half"></i> Your request is with <?= h($b['agent_name']) ?>. You'll get an email when it's reviewed.</div>
      <?php elseif ($st === 'Accepted'): ?>
        <div class="ud-note"><i class="fa-solid fa-circle-check" style="color:var(--primary)"></i> Your request was accepted. Choose how to pay to confirm this trip.</div>
      <?php elseif ($st === 'Rejected' && $b['agent_message']): ?>
        <div class="ud-note"><strong>Reason:</strong> <?= h($b['agent_message']) ?></div>
      <?php endif; ?>
    </div>

    <div class="ud-side">
      <div class="ud-total"><?= money($b['total_amount']) ?></div>

      <?php if ($st === 'Accepted'): ?>
        <button class="ud-pay" type="button" data-pay="<?= (int) $b['booking_id'] ?>"><i class="fa-solid fa-lock"></i> Pay securely</button>

      <?php elseif ($paidDone): ?>
        <div class="ud-receipt">Paid <?= $b['paid_at'] ? fmt_date($b['paid_at']) : '' ?> via <?= h($methodLabel[$b['pay_method']] ?? 'Online') ?><br>Payment ID <code><?= h($b['pay_id']) ?></code></div>

        <a class="bk-receipt-btn" href="receipt.php?booking_id=<?= (int) $b['booking_id'] ?>"><i class="fa-solid fa-receipt"></i> View receipt</a>

        <?php if ($tripEnded && $b['reviewed']): ?>
          <a class="bk-reviewed" href="review.php?booking_id=<?= (int) $b['booking_id'] ?>"><i class="fa-solid fa-star"></i> Reviewed · view</a>
        <?php elseif ($tripEnded): ?>
          <a class="bk-review-btn" href="review.php?booking_id=<?= (int) $b['booking_id'] ?>"><i class="fa-solid fa-star"></i> Write review</a>
        <?php else: ?>
          <div class="bk-upcoming"><i class="fa-regular fa-clock"></i> Review opens after your trip ends on <?= fmt_date($endDate) ?>.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
  const PREF = <?= json_encode($pref) ?>;
  const METHODS = [
    { id: 'upi',        icon: 'fa-solid fa-mobile-screen-button', title: 'UPI',                 text: 'Any UPI app or your UPI ID' },
    { id: 'gpay',       icon: 'fa-brands fa-google-pay',          title: 'Google Pay',          text: 'Opens the Google Pay app on your phone' },
    { id: 'card',       icon: 'fa-solid fa-credit-card',          title: 'Debit / credit card', text: 'Visa, Mastercard, RuPay' },
    { id: 'netbanking', icon: 'fa-solid fa-building-columns',     title: 'Net banking',         text: 'All major Indian banks' },
    { id: 'all',        icon: 'fa-solid fa-layer-group',          title: 'All options',         text: 'Show every available method' }
  ];
  const banner = document.getElementById('payBanner');
  const busy = (msg) => {
    let el = document.getElementById('payBusy');
    if (!el) {
      el = document.createElement('div');
      el.id = 'payBusy';
      el.className = 'pay-busy';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.innerHTML = `<div class="pay-busy__card"><span class="pay-busy__spin"></span><strong>${msg}</strong><small>Please keep this page open.</small></div>`;
    el.hidden = false;
  };
  const idle = () => { const el = document.getElementById('payBusy'); if (el) el.hidden = true; };
  const note = (msg, ok) => {
    banner.hidden = false;
    banner.innerHTML = `<div class="bk-banner ${ok ? '' : 'bad'}">${msg}</div>`;
  };
  const post = (url, data) => fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })
    .then(r => r.json().then(j => ({ ok: r.ok, data: j })));

  function chooseMethod(bookingId, btn) {
    const opts = METHODS.map(m => `
      <label class="pm-opt">
        <input type="radio" name="pm" value="${m.id}" ${m.id === PREF ? 'checked' : ''}>
        <span class="pm-ic"><i class="${m.icon}"></i></span>
        <span class="pm-txt"><strong>${m.title}</strong><small>${m.text}</small></span>
        <span class="pm-tick"><i class="fa-solid fa-check"></i></span>
      </label>`).join('');
    NT.openModal('Choose how to pay',
      `<p class="pm-intro">Pick a payment method. You'll complete the payment securely on the next screen.</p>
       <div class="pm-list">${opts}</div>
       <label class="pm-remember"><input type="checkbox" id="pmRemember"> Save this as my default method</label>`,
      `<button class="btn btn-ghost" type="button" data-close>Cancel</button>
       <button class="btn btn-primary" type="button" id="pmGo"><i class="fa-solid fa-lock"></i> Continue to pay</button>`,
      'sm');
    document.getElementById('pmGo').addEventListener('click', () => {
      const method = document.querySelector('input[name=pm]:checked').value;
      const remember = document.getElementById('pmRemember').checked;
      NT.closeModal();
      startPayment(bookingId, method, remember, btn);
    });
  }

  async function startPayment(bookingId, method, remember, btn) {
    banner.hidden = true;
    busy('Preparing your payment…');
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = 'Preparing payment…';
    let opened = false;
    try {
      const fd = new FormData();
      fd.append('booking_id', bookingId);
      fd.append('method', method);
      fd.append('remember', remember ? '1' : '0');
      const { ok, data } = await post('../ajax/payment_create.php', fd);
      if (!ok) throw new Error(data.message || 'Payment could not be started.');

      const checkout = data.checkout || {};
      const options = {
        key: data.key, amount: data.amount, currency: data.currency, name: data.name,
        description: data.description, order_id: data.order_id,
        prefill: Object.assign({}, data.prefill, checkout.prefill_method ? { method: checkout.prefill_method } : {}),
        method: checkout.method || undefined,
        theme: { color: '#1f8a54' },
        handler: async (resp) => {
          busy('Confirming your payment…');
          const v = new FormData();
          v.append('booking_id', data.booking_id);
          v.append('razorpay_order_id', resp.razorpay_order_id);
          v.append('razorpay_payment_id', resp.razorpay_payment_id);
          v.append('razorpay_signature', resp.razorpay_signature);
          const out = await post('../ajax/payment_verify.php', v);
          if (out.ok) { NT.toast('Payment successful. Opening your receipt…'); setTimeout(() => { location.href = 'receipt.php?booking_id=' + data.booking_id; }, 900); }
          else { idle(); note(out.data.message || 'Payment could not be verified.', false); }
        },
        modal: { ondismiss: () => { idle(); btn.disabled = false; btn.innerHTML = original; } }
      };
      if (checkout.display && Object.keys(checkout.display).length) options.config = { display: checkout.display };

      const rzp = new Razorpay(options);
      rzp.on('payment.failed', (r) => note('Payment failed: ' + (r.error?.description || 'please try again.'), false));
      opened = true;
      idle();
      rzp.open();
    } catch (e) {
      note(e.message, false);
    } finally {
      if (!opened) { idle(); btn.disabled = false; btn.innerHTML = original; }
    }
  }

  document.querySelectorAll('[data-pay]').forEach((btn) => btn.addEventListener('click', () => chooseMethod(btn.dataset.pay, btn)));
})();
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>