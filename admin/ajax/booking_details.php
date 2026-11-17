<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();

$id = (int) ($_GET['id'] ?? 0);
$b = row("SELECT b.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name, u.phone AS user_phone, p.package_name, ag.full_name AS agent_name
          FROM bookings b
          JOIN users u ON u.user_id = b.user_id
          JOIN packages p ON p.package_id = b.package_id
          JOIN agents ag ON ag.agent_id = b.agent_id
          WHERE b.booking_id = $id");
if (!$b) { echo '<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span>This booking could not be found.</span></div>'; exit; }

$members = rows("SELECT full_name, phone, email FROM booking_members WHERE booking_id = $id");
$history = rows("SELECT status, message, changed_at FROM booking_status_history WHERE booking_id = $id ORDER BY changed_at DESC");
?>
<div class="detail-head">
  <div class="mini-avatar"><?= h(initials($b['user_name'])) ?></div>
  <div><h4><?= h($b['user_name']) ?></h4><p><?= h($b['package_name']) ?> · <?= badge($b['booking_status']) ?></p></div>
</div>

<div class="metrics">
  <div class="metric"><b><?= money($b['total_amount']) ?></b><span>Total paid</span></div>
  <div class="metric"><b><?= (int) $b['adult_count'] + (int) $b['child_count'] ?></b><span>Travellers</span></div>
  <div class="metric"><b><?= fmt_date($b['travel_date']) ?></b><span>Travel date</span></div>
</div>

<div class="section-title">Pricing</div>
<dl class="kv">
  <div><dt>Package price</dt><dd><?= money($b['package_price']) ?></dd></div>
  <div><dt>Subtotal</dt><dd><?= money($b['subtotal']) ?></dd></div>
  <div><dt>GST</dt><dd><?= money($b['gst_amount']) ?></dd></div>
  <div><dt>Discount</dt><dd><?= money($b['discount_amount']) ?></dd></div>
</dl>

<div class="section-title">Traveller contact</div>
<dl class="kv">
  <div><dt>Phone</dt><dd><?= h($b['user_phone'] ?: '—') ?></dd></div>
  <div><dt>Agent</dt><dd><?= h($b['agent_name']) ?></dd></div>
  <div class="wide"><dt>Agent message</dt><dd><?= h($b['agent_message'] ?: '—') ?></dd></div>
</dl>

<?php if ($members): ?>
<div class="section-title">Travellers</div>
<ul class="list-plain"><?php foreach ($members as $m): ?>
  <li><div><?= h($m['full_name']) ?><small><?= h($m['email'] ?: '') ?></small></div><span class="muted"><?= h($m['phone'] ?: '') ?></span></li>
<?php endforeach; ?></ul>
<?php endif; ?>

<div class="section-title">Status history</div>
<?php if (!$history): ?><p class="empty-note">No status changes recorded.</p><?php endif; ?>
<ul class="list-plain"><?php foreach ($history as $hs): ?>
  <li><div><?= badge($hs['status']) ?><?php if ($hs['message']): ?><small><?= h($hs['message']) ?></small><?php endif; ?></div><span class="muted"><?= fmt_datetime($hs['changed_at']) ?></span></li>
<?php endforeach; ?></ul>
