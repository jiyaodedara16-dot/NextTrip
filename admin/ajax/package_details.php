<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();

$id = (int) ($_GET['id'] ?? 0);
$pk = row("SELECT p.*, ag.full_name AS agent_name FROM packages p LEFT JOIN agents ag ON ag.agent_id = p.agent_id WHERE p.package_id = $id");
if (!$pk) { echo '<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span>This package could not be found.</span></div>'; exit; }

$dests      = rows("SELECT d.destination_name, d.city, d.state FROM package_destinations pd JOIN destinations d ON d.destination_id = pd.destination_id WHERE pd.package_id = $id ORDER BY pd.display_order");
$inclusions = rows("SELECT item_name FROM package_inclusions WHERE package_id = $id AND status = 'Active' ORDER BY display_order");
$exclusions = rows("SELECT item_name FROM package_exclusions WHERE package_id = $id AND status = 'Active' ORDER BY display_order");
$itinerary  = rows("SELECT day_number, title, description FROM package_itinerary WHERE package_id = $id ORDER BY day_number, display_order");
$bookings   = (int) scalar("SELECT COUNT(*) FROM bookings WHERE package_id = $id");
$revenue    = (float) scalar("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE package_id = $id AND booking_status IN ('Accepted','Confirmed')");
$rating     = scalar("SELECT ROUND(AVG(rating),1) FROM package_reviews WHERE package_id = $id AND status = 'Approved'");
?>
<div class="detail-head">
  <div class="mini-avatar amber" style="width:58px;height:58px;border-radius:18px"><i class="fa-solid fa-suitcase-rolling"></i></div>
  <div>
    <h4><?= h($pk['package_name']) ?></h4>
    <p>By <?= h($pk['agent_name'] ?: 'Unknown agent') ?> · <?= badge($pk['status']) ?></p>
  </div>
</div>

<div class="metrics">
  <div class="metric"><b><?= (int) $pk['duration_days'] ?>D/<?= (int) $pk['duration_nights'] ?>N</b><span>Duration</span></div>
  <div class="metric"><b><?= money($pk['price_per_person']) ?></b><span>Per person</span></div>
  <div class="metric"><b><?= $bookings ?></b><span>Bookings</span></div>
  <div class="metric"><b><?= $rating ? h($rating) . ' ★' : '—' ?></b><span>Avg. rating</span></div>
</div>

<div class="section-title">Overview</div>
<p class="prose"><?= nl2br(h($pk['description'] ?: 'No description provided.')) ?></p>
<dl class="kv" style="margin-top:14px">
  <div><dt>Max adults</dt><dd><?= h($pk['max_adults'] ?: '—') ?></dd></div>
  <div><dt>Max children</dt><dd><?= h($pk['max_children'] ?: '—') ?></dd></div>
  <div><dt>Revenue (confirmed)</dt><dd><?= money($revenue) ?></dd></div>
  <div><dt>Created</dt><dd><?= fmt_date($pk['created_at']) ?></dd></div>
</dl>

<div class="section-title">Destinations covered</div>
<div class="chip-list">
  <?php foreach ($dests as $d): ?><span class="chip green"><?= h($d['destination_name']) ?> · <?= h($d['city']) ?></span><?php endforeach; ?>
  <?php if (!$dests): ?><span class="muted">No destinations linked.</span><?php endif; ?>
</div>

<?php if ($itinerary): ?>
<div class="section-title">Itinerary</div>
<div>
  <?php foreach ($itinerary as $it): ?>
    <div class="tip"><i class="fa-solid fa-calendar-day"></i><span><strong>Day <?= (int) $it['day_number'] ?> · <?= h($it['title']) ?></strong><br><span class="muted"><?= h($it['description']) ?></span></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="two-col" style="grid-template-columns:1fr 1fr;gap:18px;margin-top:4px">
  <div>
    <div class="section-title">Inclusions</div>
    <ul class="bullets"><?php foreach ($inclusions as $i) echo '<li>' . h($i['item_name']) . '</li>'; ?><?php if (!$inclusions) echo '<li class="muted">None listed</li>'; ?></ul>
  </div>
  <div>
    <div class="section-title">Exclusions</div>
    <ul class="bullets"><?php foreach ($exclusions as $e) echo '<li>' . h($e['item_name']) . '</li>'; ?><?php if (!$exclusions) echo '<li class="muted">None listed</li>'; ?></ul>
  </div>
</div>
