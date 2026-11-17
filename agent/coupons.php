<?php
$page_title = 'Coupons';
$page_subtitle = 'Coupons you created. Admin approves them before travellers can use them.';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/coupon-lib.php';

$search = trim($_GET['q'] ?? '');
$list = rows("SELECT c.*, p.package_name,
                (SELECT COUNT(*) FROM coupon_users cu WHERE cu.coupon_id = c.coupon_id) AS eligible
              FROM coupons c JOIN packages p ON p.package_id = c.package_id
              WHERE c.agent_id = $agent_id" . like_sql('c.code', $search) . "
              ORDER BY c.created_at DESC LIMIT 200");
$stats = ['total' => count($list), 'active' => 0, 'pending' => 0, 'used' => 0];
foreach ($list as $c) {
    $st = coupon_state($c);
    if ($st === 'Active') $stats['active']++;
    if ($st === 'Pending approval') $stats['pending']++;
    $stats['used'] += (int) $c['usage_count'];
}

include __DIR__ . '/includes/page-start.php';
?>
<section class="kpi-grid kpi-5" style="grid-template-columns:repeat(4,minmax(0,1fr))">
  <article class="kpi anim" style="--d:1"><div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-ticket"></i></span><span class="kpi-tag">All</span></div>
    <div class="kpi-value"><?= $stats['total'] ?></div><div class="kpi-label">Coupons created</div></article>
  <article class="kpi anim" style="--d:2"><div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-circle-check"></i></span><span class="kpi-tag">Live</span></div>
    <div class="kpi-value"><?= $stats['active'] ?></div><div class="kpi-label">Active now</div></article>
  <article class="kpi anim" style="--d:3"><div class="kpi-top"><span class="kpi-icon a"><i class="fa-solid fa-hourglass-half"></i></span><span class="kpi-tag">Review</span></div>
    <div class="kpi-value"><?= $stats['pending'] ?></div><div class="kpi-label">Waiting for admin</div></article>
  <article class="kpi anim" style="--d:4"><div class="kpi-top"><span class="kpi-icon b"><i class="fa-solid fa-users"></i></span><span class="kpi-tag">Usage</span></div>
    <div class="kpi-value"><?= $stats['used'] ?></div><div class="kpi-label">Times redeemed</div></article>
</section>

<section class="card" style="margin-top:22px">
  <div class="card-head">
    <div><h3>Coupon list</h3><p>Discounts are deducted from the package price during booking.</p></div>
    <div class="toolbar">
      <form class="toolbar" method="GET" data-autosubmit>
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= h($search) ?>" placeholder="Search by code"></div>
      </form>
      <a class="btn btn-primary" href="coupon-add.php"><i class="fa-solid fa-plus"></i> Create coupon</a>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Code</th><th>Package</th><th>Discount</th><th>Min. amount</th><th>Usage</th><th>Valid</th><th>Eligible</th><th>Status</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="8"><div class="empty"><i class="fa-solid fa-ticket"></i><h4>No coupons yet</h4>
          <p>Create a coupon for one of your packages. Admin will review it first.</p>
          <a class="btn btn-primary" href="coupon-add.php" style="margin-top:12px"><i class="fa-solid fa-plus"></i> Create coupon</a></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $c): $st = coupon_state($c); ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar"><i class="fa-solid fa-ticket"></i></div>
            <div><strong style="font-family:monospace;letter-spacing:.06em"><?= h($c['code']) ?></strong><span><?= h($c['title'] ?: 'Untitled offer') ?></span></div></div></td>
          <td class="muted"><?= h($c['package_name']) ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $c['discount_percent'], 2), '0'), '.') ?>%</td>
          <td class="muted"><?= (float) $c['min_amount'] > 0 ? money($c['min_amount']) : 'None' ?></td>
          <td class="num"><?= (int) $c['usage_count'] ?> / <?= (int) $c['usage_limit'] ?></td>
          <td class="muted"><?= fmt_date($c['start_date']) ?> – <?= fmt_date($c['expiry_date']) ?></td>
          <td class="num"><?= (int) $c['eligible'] ?> user<?= (int) $c['eligible'] === 1 ? '' : 's' ?></td>
          <td>
            <?php
              $cls = ['Active' => 'live', 'Pending approval' => 'pending', 'Scheduled' => 'pending', 'Rejected' => 'rejected', 'Expired' => 'inactive', 'Used up' => 'inactive'][$st] ?? 'inactive';
            ?>
            <span class="badge badge-<?= $cls ?>"><?= h($st) ?></span>
            <?php if ($c['approval_status'] === 'Rejected' && $c['rejection_reason']): ?>
              <div class="muted" style="font-size:11.5px;margin-top:4px;max-width:220px"><?= h($c['rejection_reason']) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
