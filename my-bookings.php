<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
require_once 'includes/booking-lib.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php?redirect=' . urlencode('my-bookings.php'));
    exit;
}
$uid = (int) $_SESSION['user_id'];
$bookings = bk_rows("SELECT b.booking_id, b.travel_date, b.adult_count, b.child_count, b.total_amount, b.booking_status,
                            b.agent_message, b.created_at, p.package_name, p.slug, ag.full_name AS agent_name, ag.phone AS agent_phone
                     FROM bookings b
                     JOIN packages p ON p.package_id = b.package_id
                     JOIN agents ag ON ag.agent_id = b.agent_id
                     WHERE b.user_id = $uid ORDER BY b.created_at DESC");
$paid = isset($_GET['paid']);
$labels = ['Pending' => ['pending', 'Waiting for the agent'], 'Accepted' => ['accepted', 'Accepted — pay to confirm'],
           'Confirmed' => ['confirmed', 'Confirmed & paid'], 'Rejected' => ['rejected', 'Not accepted'], 'Cancelled' => ['cancelled', 'Cancelled']];
$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
include 'includes/navbar.php';
?>
<link rel="stylesheet" href="assets/css/booking.css">
<main class="mb-wrap">
  <h1 class="mb-title">My Bookings</h1>
  <p class="mb-sub">Track your requests. Once an agent accepts, pay securely here to confirm your trip.</p>

  <?php if ($paid): ?><div class="mb-banner"><i class="bi bi-check-circle-fill"></i> Payment received. Your booking is confirmed and a receipt is on its way to your email.</div><?php endif; ?>
  <div class="mb-banner error" id="mbError" hidden></div>

  <?php if (!$bookings): ?>
    <div class="mb-empty"><i class="bi bi-suitcase-lg" style="font-size:34px;display:block;margin-bottom:10px;color:#1f8a54"></i>
      <p>You have no bookings yet. <a href="packages.php" style="color:#146339;font-weight:700">Browse packages</a> to plan your next trip.</p></div>
  <?php else: ?>
  <div class="mb-list">
    <?php foreach ($bookings as $i => $b):
      [$cls, $label] = $labels[$b['booking_status']] ?? ['pending', $b['booking_status']];
      $guests = (int) $b['adult_count'] . ' adult' . ((int) $b['adult_count'] === 1 ? '' : 's') . ((int) $b['child_count'] ? ', ' . (int) $b['child_count'] . ' child' . ((int) $b['child_count'] === 1 ? '' : 'ren') : '');
    ?>
    <article class="mb-card" style="animation-delay: <?= $i * 80 ?>ms">
      <div>
        <span class="mb-pill <?= $cls ?>"><?= $e($label) ?></span>
        <h3 style="margin-top:10px"><?= $e($b['package_name']) ?></h3>
        <div class="mb-meta">
          <span><i class="bi bi-hash"></i>Booking #<?= (int) $b['booking_id'] ?></span>
          <span><i class="bi bi-calendar-event"></i><?= date('d M Y', strtotime($b['travel_date'])) ?></span>
          <span><i class="bi bi-people"></i><?= $e($guests) ?></span>
          <span><i class="bi bi-person-badge"></i><?= $e($b['agent_name']) ?></span>
        </div>
        <?php if ($b['booking_status'] === 'Pending'): ?>
          <div class="mb-note">Your request is with <?= $e($b['agent_name']) ?>. You'll get an email as soon as it's reviewed.</div>
        <?php elseif ($b['booking_status'] === 'Accepted'): ?>
          <div class="mb-note">Great news — your request was accepted. Pay now to confirm your trip.</div>
        <?php elseif ($b['booking_status'] === 'Rejected' && $b['agent_message']): ?>
          <div class="mb-note"><strong>Reason:</strong> <?= $e($b['agent_message']) ?></div>
        <?php endif; ?>
      </div>
      <div class="mb-side">
        <div class="mb-total"><?= bk_money($b['total_amount']) ?></div>
        <?php if ($b['booking_status'] === 'Accepted'): ?>
          <button class="mb-pay" type="button" data-pay="<?= (int) $b['booking_id'] ?>"><i class="bi bi-lock-fill"></i> Pay securely</button>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
  const err = document.getElementById('mbError');
  const showErr = (m) => { err.textContent = m; err.classList.add('error'); err.hidden = false; };
  const post = (url, data) => fetch(url, { method: 'POST', body: data, credentials: 'same-origin' }).then(r => r.json().then(j => ({ ok: r.ok, data: j })));

  document.querySelectorAll('[data-pay]').forEach((btn) => btn.addEventListener('click', async () => {
    err.hidden = true;
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = 'Preparing payment…';
    try {
      const fd = new FormData(); fd.append('booking_id', btn.dataset.pay);
      const { ok, data } = await post('ajax/payment_create.php', fd);
      if (!ok) throw new Error(data.message || 'Payment could not be started.');

      const rzp = new Razorpay({
        key: data.key,
        amount: data.amount,
        currency: data.currency,
        name: data.name,
        description: data.description,
        order_id: data.order_id,
        prefill: data.prefill,
        theme: { color: '#1f8a54' },
        handler: async (resp) => {
          const v = new FormData();
          v.append('booking_id', data.booking_id);
          v.append('razorpay_order_id', resp.razorpay_order_id);
          v.append('razorpay_payment_id', resp.razorpay_payment_id);
          v.append('razorpay_signature', resp.razorpay_signature);
          const out = await post('ajax/payment_verify.php', v);
          if (out.ok) location.href = 'my-bookings.php?paid=1';
          else showErr(out.data.message || 'Payment could not be verified.');
        },
        modal: { ondismiss: () => { btn.disabled = false; btn.innerHTML = original; } }
      });
      rzp.on('payment.failed', (r) => showErr('Payment failed: ' + (r.error && r.error.description ? r.error.description : 'please try again.')));
      rzp.open();
    } catch (e) {
      showErr(e.message);
    } finally {
      if (!document.querySelector('.razorpay-container')) { btn.disabled = false; btn.innerHTML = original; }
    }
  }));
})();
</script>
<?php include 'includes/footer.php'; ?>