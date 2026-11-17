<?php
$page_title = 'User Bookings';
$page_subtitle = 'Search bookings by traveller, package or agent';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$statuses = ['Pending', 'Accepted', 'Confirmed', 'Rejected', 'Cancelled'];
$where = "WHERE 1=1";
if ($search !== '') {
    $t = esc($search);
    $where .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE '%$t%' OR p.package_name LIKE '%$t%' OR ag.full_name LIKE '%$t%')";
}
if (in_array($status, $statuses, true)) $where .= " AND b.booking_status = '$status'";

$joins = "FROM bookings b
          JOIN users u ON u.user_id = b.user_id
          JOIN packages p ON p.package_id = b.package_id
          JOIN agents ag ON ag.agent_id = b.agent_id";
$total = (int) scalar("SELECT COUNT(*) $joins $where");
$pg = paginate($total, 10, $_GET['page'] ?? 1);
$list = rows("SELECT b.booking_id, b.travel_date, b.adult_count, b.child_count, b.total_amount, b.booking_status, b.created_at,
                     CONCAT(u.first_name, ' ', u.last_name) AS user_name, p.package_name, ag.full_name AS agent_name
              $joins $where ORDER BY b.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>Bookings</h3><p>Matches on traveller name, package name or agent name.</p></div>
    <form class="toolbar" method="GET" data-autosubmit>
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search by user, package or agent">
      </div>
      <select name="status" aria-label="Filter by booking status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>#</th><th>Traveller</th><th>Package</th><th>Agent</th><th>Travel date</th><th>Guests</th><th>Total</th><th>Status</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="9"><div class="empty"><i class="fa-solid fa-ticket"></i><h4>No bookings found</h4><p>Try a different search or clear the filters.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $b): ?>
        <tr>
          <td class="num muted">#<?= (int) $b['booking_id'] ?></td>
          <td><div class="cell-person"><div class="mini-avatar"><?= h(initials($b['user_name'])) ?></div><strong><?= h($b['user_name']) ?></strong></div></td>
          <td style="max-width:240px"><span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($b['package_name']) ?></span></td>
          <td class="muted"><?= h($b['agent_name']) ?></td>
          <td class="muted"><?= fmt_date($b['travel_date']) ?></td>
          <td class="muted"><?= (int) $b['adult_count'] ?> adult<?= $b['adult_count'] == 1 ? '' : 's' ?><?= (int) $b['child_count'] ? ', ' . (int) $b['child_count'] . ' child' : '' ?></td>
          <td class="num"><?= money($b['total_amount']) ?></td>
          <td><?= badge($b['booking_status']) ?></td>
          <td><div class="row-actions">
            <button class="btn btn-soft btn-sm" type="button" data-action="view" data-url="ajax/booking_details.php" data-id="<?= (int) $b['booking_id'] ?>" data-title="Booking #<?= (int) $b['booking_id'] ?>" data-size="lg"><i class="fa-solid fa-eye"></i> View</button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($pg, array_filter(['q' => $search, 'status' => $status])) ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
