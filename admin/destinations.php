<?php
$page_title = 'Destinations';
$page_subtitle = 'Live destinations on the platform. Open a destination to see its full page.';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$state  = trim($_GET['state'] ?? '');
$status = $_GET['status'] ?? '';
$where  = "WHERE 1=1" . like_sql('d.destination_name', $search);
if ($state !== '') $where .= " AND d.state = '" . esc($state) . "'";
if (in_array($status, ['Active', 'Inactive'], true)) $where .= " AND d.status = '$status'";

$states = rows("SELECT DISTINCT state FROM destinations WHERE state IS NOT NULL AND state <> '' ORDER BY state");
$total  = (int) scalar("SELECT COUNT(*) FROM destinations d $where");
$p = paginate($total, 10, $_GET['page'] ?? 1);
$list = rows("SELECT d.destination_id, d.destination_name, d.city, d.state, d.entry_fee, d.status, d.created_at,
                     (SELECT di.image_path FROM destination_images di WHERE di.destination_id = d.destination_id
                        ORDER BY di.is_primary DESC, di.display_order ASC LIMIT 1) AS img,
                     COALESCE((SELECT ag.full_name FROM agents ag WHERE ag.account_id = d.created_by LIMIT 1), 'NextTrip Admin') AS agent_name,
                     (SELECT COUNT(*) FROM package_destinations pd WHERE pd.destination_id = d.destination_id) AS pkg_count
              FROM destinations d $where ORDER BY d.destination_name ASC LIMIT {$p['per']} OFFSET {$p['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>Destination list</h3><p>Search by name or filter by state and status.</p></div>
    <form class="toolbar" method="GET" data-autosubmit>
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search destination by name">
      </div>
      <select name="state" aria-label="Filter by state">
        <option value="">All states</option>
        <?php foreach ($states as $s): ?>
          <option value="<?= h($s['state']) ?>" <?= $state === $s['state'] ? 'selected' : '' ?>><?= h($s['state']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" aria-label="Filter by status">
        <option value="">Any status</option>
        <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
        <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Destination</th><th>Location</th><th>Entry fee</th><th>Added by</th><th>Packages</th><th>Status</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="7"><div class="empty"><i class="fa-solid fa-map-location-dot"></i><h4>No destinations found</h4><p>Try another name or clear the filters.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $d): ?>
        <tr>
          <td><div class="cell-person">
            <img class="thumb" src="<?= h(asset_url($d['img'])) ?>" alt="">
            <div><strong><?= h($d['destination_name']) ?></strong><span>Added <?= fmt_date($d['created_at']) ?></span></div></div></td>
          <td class="muted"><?= h($d['city']) ?>, <?= h($d['state']) ?></td>
          <td class="num"><?= h(entry_label($d['entry_fee'])) ?></td>
          <td class="muted"><?= h($d['agent_name']) ?></td>
          <td class="num"><?= (int) $d['pkg_count'] ?></td>
          <td><?= badge($d['status']) ?></td>
          <td><div class="row-actions">
            <a class="btn btn-soft btn-sm" href="destination-view.php?id=<?= (int) $d['destination_id'] ?>"><i class="fa-solid fa-eye"></i> View</a>
            <button class="btn btn-danger btn-sm" type="button" data-action="delete" data-reason="required" data-url="ajax/destination_delete.php" data-id="<?= (int) $d['destination_id'] ?>" data-name="<?= h($d['destination_name']) ?>" data-title="Delete destination"><i class="fa-solid fa-trash-can"></i></button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($p, array_filter(['q' => $search, 'state' => $state, 'status' => $status])) ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
