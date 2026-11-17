<?php
$page_title = 'Booking Details';
$page_subtitle = 'Full details for this booking';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/email-template.php';

$bid = (int) ($_GET['id'] ?? 0);
$b = row("SELECT b.*, p.package_name, p.duration_days, p.price_per_person,
                 u.first_name, u.last_name, u.phone AS user_phone, a.email AS user_email
          FROM bookings b
          JOIN packages p ON p.package_id = b.package_id
          JOIN users u ON u.user_id = b.user_id
          JOIN accounts a ON a.account_id = u.account_id
          WHERE b.booking_id = $bid AND b.agent_id = $agent_id");
if (!$b) { header('Location: bookings.php?msg=' . urlencode('Booking not found.') . '&type=error'); exit; }

$pay     = row("SELECT payment_method, razorpay_order_id, razorpay_payment_id, paid_at FROM payments
                WHERE booking_id = $bid AND payment_status = 'Success' LIMIT 1");
$members = rows("SELECT full_name, phone, email FROM booking_members WHERE booking_id = $bid ORDER BY full_name");
$places  = rows("SELECT d.destination_name, d.city, d.state FROM package_destinations pd
                 JOIN destinations d ON d.destination_id = pd.destination_id
                 WHERE pd.package_id = " . (int) $b['package_id'] . " ORDER BY pd.display_order");
$history = rows("SELECT status, message, changed_at FROM booking_status_history WHERE booking_id = $bid ORDER BY changed_at, status_history_id");
$incl    = rows("SELECT item_name FROM package_inclusions WHERE package_id = " . (int) $b['package_id'] . " AND status = 'Active' ORDER BY display_order");

$adults   = (int) $b['adult_count'];
$children = (int) $b['child_count'];
$price    = (float) $b['price_per_person'];
$end      = date('d M Y', strtotime($b['travel_date'] . ' +' . (int) $b['duration_days'] . ' days'));
$confirmed = $b['booking_status'] === 'Confirmed';
$no = 'NT-' . str_pad((string) $bid, 6, '0', STR_PAD_LEFT);
$traveller = trim($b['first_name'] . ' ' . $b['last_name']);
$methodLabel = ['upi' => 'UPI', 'gpay' => 'Google Pay', 'card' => 'Card', 'netbanking' => 'Net banking', 'all' => 'Online'];

include __DIR__ . '/includes/page-start.php';
?>
<style>
  .bv { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; display: flex; flex-direction: column; gap: 18px; }
  .bv *, .bv *::before, .bv *::after { box-sizing: border-box; }
  .bv-top { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; }
  .bv-back { display: inline-flex; align-items: center; gap: 8px; color: #146339; font-weight: 700; font-size: 13.5px; text-decoration: none; }
  .bv-btns { display: flex; gap: 10px; flex-wrap: wrap; }
  .bv-btn { display: inline-flex; align-items: center; gap: 8px; font-weight: 800; font-size: 13.5px; padding: 11px 18px; border-radius: 12px; text-decoration: none; border: 1.5px solid #cfe9d9; background: #fff; color: #146339; }
  .bv-btn.primary { background: linear-gradient(135deg, #1f8a54, #146339); color: #fff; border-color: transparent; box-shadow: 0 12px 22px -14px rgba(31,138,84,.9); }
  .bv-btn:hover { transform: translateY(-1px); }

  .bv-hero { border-radius: 20px; padding: 24px; background: linear-gradient(135deg, #0f4a30, #1f8a54); color: #fff; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; align-items: center; }
  .bv-hero h2 { font-family: 'Fraunces', Georgia, serif; font-size: 26px; font-weight: 700; margin: 0; }
  .bv-hero p { margin: 6px 0 0; font-size: 13.5px; opacity: .9; }
  .bv-no { font-family: 'Fraunces', Georgia, serif; font-size: 18px; font-weight: 700; text-align: right; }
  .bv-no small { display: block; font-family: 'Manrope', sans-serif; font-size: 11px; letter-spacing: .1em; text-transform: uppercase; opacity: .8; }

  .bv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
  .bv-card { background: #fff; border: 1px solid #e3ebe6; border-radius: 18px; padding: 20px; }
  .bv-card.full { grid-column: 1 / -1; }
  .bv-card h3 { font-family: 'Fraunces', Georgia, serif; font-size: 17px; font-weight: 700; margin: 0 0 14px; display: flex; align-items: center; gap: 10px; }
  .bv-card h3 i { color: #1f8a54; font-size: 15px; }

  .bv-rows { display: flex; flex-direction: column; }
  .bv-row { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; border-bottom: 1px dashed #e3ebe6; font-size: 14px; }
  .bv-row:first-child { padding-top: 0; } .bv-row:last-child { border-bottom: 0; padding-bottom: 0; }
  .bv-row span { color: #6b7a72; font-weight: 600; flex-shrink: 0; }
  .bv-row b { font-weight: 700; text-align: right; word-break: break-word; }
  .bv-row.total b { color: #146339; font-size: 16px; font-family: 'Fraunces', Georgia, serif; }
  .bv-row.disc b { color: #146339; }

  .bv-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
  .bv-list li { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: #f8fbf9; border: 1px solid #e3ebe6; font-size: 14px; }
  .bv-list li i { color: #1f8a54; }
  .bv-list small { color: #6b7a72; margin-left: auto; font-size: 12.5px; }

  .bv-tl { display: flex; flex-direction: column; gap: 0; }
  .bv-tl-item { position: relative; padding: 0 0 16px 26px; border-left: 2px solid #e3ebe6; margin-left: 6px; }
  .bv-tl-item:last-child { border-left-color: transparent; padding-bottom: 0; }
  .bv-tl-item::before { content: ''; position: absolute; left: -7px; top: 3px; width: 12px; height: 12px; border-radius: 50%; background: #1f8a54; border: 2px solid #fff; box-shadow: 0 0 0 2px #cfe9d9; }
  .bv-tl-item strong { font-size: 14px; }
  .bv-tl-item p { margin: 2px 0 0; font-size: 12.5px; color: #6b7a72; }

  .bv-note { padding: 12px 14px; border-radius: 12px; background: #f8fbf9; border: 1px solid #e3ebe6; font-size: 13.5px; line-height: 1.6; color: #33443b; }
  .bv-empty { color: #9aa8a0; font-size: 13px; font-style: italic; }
  .bv-locked { padding: 16px; border-radius: 14px; background: #fdf7e8; border: 1px solid #f6e4bd; color: #8a5d05; font-size: 13.5px; line-height: 1.6; }

  @media (max-width: 860px) { .bv-grid { grid-template-columns: 1fr; } }
</style>

<div class="bv">
  <div class="bv-top">
    <a class="bv-back" href="bookings.php?tab=<?= h($b['booking_status']) ?>"><i class="fa-solid fa-arrow-left"></i> Back to bookings</a>
    <div class="bv-btns">
      <?php if ($confirmed): ?>
        <a class="bv-btn primary" href="receipt.php?booking_id=<?= $bid ?>"><i class="fa-solid fa-file-pdf"></i> Download receipt (PDF)</a>
      <?php endif; ?>
    </div>
  </div>

  <section class="bv-hero">
    <div>
      <h2><?= h($b['package_name']) ?></h2>
      <p><?= fmt_date($b['travel_date']) ?> – <?= h($end) ?> · <?= $adults + $children ?> traveller<?= ($adults + $children) === 1 ? '' : 's' ?></p>
    </div>
    <div class="bv-no"><small>Booking</small><?= h($no) ?> · <?= badge($b['booking_status']) ?></div>
  </section>

  <div class="bv-grid">
    <div class="bv-card">
      <h3><i class="fa-solid fa-user"></i> Traveller contact</h3>
      <div class="bv-rows">
        <div class="bv-row"><span>Name</span><b><?= h($traveller) ?></b></div>
        <div class="bv-row"><span>Email</span><b><?= h($b['user_email']) ?></b></div>
        <div class="bv-row"><span>Phone</span><b><?= $b['user_phone'] ? h($b['user_phone']) : '—' ?></b></div>
        <div class="bv-row"><span>Booked on</span><b><?= fmt_datetime($b['created_at']) ?></b></div>
      </div>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-calendar-days"></i> Trip</h3>
      <div class="bv-rows">
        <div class="bv-row"><span>Travel dates</span><b><?= fmt_date($b['travel_date']) ?> – <?= h($end) ?></b></div>
        <div class="bv-row"><span>Duration</span><b><?= (int) $b['duration_days'] ?> days</b></div>
        <div class="bv-row"><span>Adults</span><b><?= $adults ?></b></div>
        <div class="bv-row"><span>Children</span><b><?= $children ?></b></div>
      </div>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-users"></i> Travellers</h3>
      <?php if (!$members): ?><p class="bv-empty">No traveller names recorded.</p><?php endif; ?>
      <ul class="bv-list">
        <?php foreach ($members as $m): ?>
          <li><i class="fa-solid fa-user-check"></i> <?= h($m['full_name']) ?>
            <?php if ($m['phone'] || $m['email']): ?><small><?= h($m['phone'] ?: $m['email']) ?></small><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-location-dot"></i> Places covered</h3>
      <?php if (!$places): ?><p class="bv-empty">No places linked.</p><?php endif; ?>
      <ul class="bv-list">
        <?php foreach ($places as $pl): ?>
          <li><i class="fa-solid fa-map-pin"></i> <?= h($pl['destination_name']) ?><small><?= h($pl['city']) ?>, <?= h($pl['state']) ?></small></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-indian-rupee-sign"></i> Payment calculation</h3>
      <div class="bv-rows">
        <div class="bv-row"><span><?= $adults ?> adult<?= $adults === 1 ? '' : 's' ?> × <?= money($price) ?></span><b><?= money($price * $adults) ?></b></div>
        <?php if ($children > 0): ?>
        <div class="bv-row"><span><?= $children ?> child<?= $children === 1 ? '' : 'ren' ?> × <?= money($price * 0.5) ?></span><b><?= money($price * 0.5 * $children) ?></b></div>
        <?php endif; ?>
        <?php if ((float) $b['discount_amount'] > 0): ?>
        <div class="bv-row disc"><span>Coupon <?= h($b['coupon_code']) ?></span><b>− <?= money($b['discount_amount']) ?></b></div>
        <?php endif; ?>
        <div class="bv-row"><span>Subtotal</span><b><?= money($b['subtotal']) ?></b></div>
        <div class="bv-row"><span>GST (5%)</span><b><?= money($b['gst_amount']) ?></b></div>
        <div class="bv-row total"><span>Total</span><b><?= money($b['total_amount']) ?></b></div>
      </div>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-credit-card"></i> Payment</h3>
      <?php if (!$pay): ?>
        <p class="bv-empty">No successful payment yet.</p>
      <?php else: ?>
        <div class="bv-rows">
          <div class="bv-row"><span>Method</span><b><?= h($methodLabel[$pay['payment_method']] ?? 'Online') ?></b></div>
          <div class="bv-row"><span>Payment ID</span><b><?= h($pay['razorpay_payment_id']) ?></b></div>
          <div class="bv-row"><span>Order ID</span><b><?= h($pay['razorpay_order_id']) ?></b></div>
          <div class="bv-row"><span>Paid on</span><b><?= $pay['paid_at'] ? fmt_datetime($pay['paid_at']) : '—' ?></b></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-clock-rotate-left"></i> Status history</h3>
      <div class="bv-tl">
        <?php foreach ($history as $hs): ?>
          <div class="bv-tl-item">
            <strong><?= h($hs['status']) ?></strong>
            <p><?= fmt_datetime($hs['changed_at']) ?><?= $hs['message'] ? ' · ' . h($hs['message']) : '' ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="bv-card">
      <h3><i class="fa-solid fa-list-check"></i> Included in the package</h3>
      <?php if (!$incl): ?><p class="bv-empty">Nothing listed.</p><?php endif; ?>
      <ul class="bv-list">
        <?php foreach ($incl as $i): ?><li><i class="fa-solid fa-check"></i> <?= h($i['item_name']) ?></li><?php endforeach; ?>
      </ul>
    </div>

    <?php if ($b['special_requests'] || $b['agent_message']): ?>
    <div class="bv-card full">
      <h3><i class="fa-regular fa-comment-dots"></i> Notes</h3>
      <?php if ($b['special_requests']): ?><div class="bv-note"><strong>Traveller's notes:</strong><br><?= nl2br(h($b['special_requests'])) ?></div><?php endif; ?>
      <?php if ($b['agent_message']): ?><div class="bv-note" style="margin-top:10px"><strong>Your message:</strong><br><?= nl2br(h($b['agent_message'])) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$confirmed): ?>
    <div class="bv-card full">
      <div class="bv-locked"><i class="fa-solid fa-lock"></i> The receipt and payment details are available once the booking is confirmed and paid.</div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/page-end.php'; ?>