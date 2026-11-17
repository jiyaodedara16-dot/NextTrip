<?php
$page_title = 'Coupon Approvals';
$page_subtitle = 'Review agent coupons. Only approved coupons can be used.';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/coupon-lib.php';

$tab = in_array($_GET['tab'] ?? '', ['Pending', 'Approved', 'Rejected', 'All'], true) ? $_GET['tab'] : 'Pending';
$cond = $tab === 'All' ? '' : " AND c.approval_status = '$tab'";
$counts = [];
foreach (['Pending', 'Approved', 'Rejected'] as $s) $counts[$s] = (int) scalar("SELECT COUNT(*) FROM coupons WHERE approval_status = '$s'");
$list = rows("SELECT c.*, p.package_name, ag.full_name AS agent_name,
                (SELECT COUNT(*) FROM coupon_users cu WHERE cu.coupon_id = c.coupon_id) AS eligible
              FROM coupons c JOIN packages p ON p.package_id = c.package_id JOIN agents ag ON ag.agent_id = c.agent_id
              WHERE 1=1 $cond" . like_sql('c.code', trim($_GET['q'] ?? '')) . " ORDER BY c.created_at DESC LIMIT 200");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div class="tabs">
      <?php foreach (['Pending' => $counts['Pending'], 'Approved' => $counts['Approved'], 'Rejected' => $counts['Rejected'], 'All' => array_sum($counts)] as $t => $n): ?>
        <a class="tab <?= $tab === $t ? 'active' : '' ?>" href="?tab=<?= $t ?>"><?= $t ?> <span class="muted">(<?= $n ?>)</span></a>
      <?php endforeach; ?>
    </div>
    <form class="toolbar" method="GET" data-autosubmit>
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Search by code"></div>
    </form>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Code</th><th>Agent</th><th>Package</th><th>Discount</th><th>Usage</th><th>Valid</th><th>Eligible</th><th>Status</th><th>Decision</th></tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="9"><div class="empty"><i class="fa-solid fa-ticket"></i><h4>Nothing to review</h4><p>Coupons submitted by agents appear here.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $c): $st = coupon_state($c); ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar amber"><i class="fa-solid fa-ticket"></i></div>
            <div><strong style="font-family:monospace;letter-spacing:.06em"><?= h($c['code']) ?></strong><span><?= h($c['title'] ?: 'Untitled') ?></span></div></div></td>
          <td class="muted"><?= h($c['agent_name']) ?></td>
          <td class="muted"><?= h($c['package_name']) ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $c['discount_percent'], 2), '0'), '.') ?>%</td>
          <td class="num"><?= (int) $c['usage_count'] ?> / <?= (int) $c['usage_limit'] ?></td>
          <td class="muted"><?= fmt_date($c['start_date']) ?> – <?= fmt_date($c['expiry_date']) ?></td>
          <td class="num"><?= (int) $c['eligible'] ?></td>
          <td><?= badge($st) ?></td>
          <td><div class="row-actions">
            <?php if ($c['approval_status'] === 'Pending'): ?>
              <button class="btn btn-primary btn-sm btn-text" type="button" data-action="approve" data-url="ajax/coupon_action.php" data-id="<?= (int) $c['coupon_id'] ?>" data-name="<?= h($c['code']) ?>" data-title="Approve coupon"><i class="fa-solid fa-check"></i> Approve</button>
              <button class="btn btn-danger btn-sm btn-text" type="button" data-action="reject" data-url="ajax/coupon_action.php" data-id="<?= (int) $c['coupon_id'] ?>" data-name="<?= h($c['code']) ?>"><i class="fa-solid fa-xmark"></i> Reject</button>
            <?php else: ?><span class="muted" style="font-size:12.5px">Decided</span><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
