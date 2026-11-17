<?php
$page_title = 'My Destinations';
$page_subtitle = 'Destinations you added. New ones go to admin for review before going live.';
require_once __DIR__ . '/includes/auth.php';

/* ---- AJAX: delete one of this agent's destinations ---- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete') {
    agent_ajax_guard();
    $id = require_post_id();
    if (!row("SELECT destination_id FROM destinations WHERE destination_id = $id AND created_by = $agent_acc")) json_fail('Destination not found.', 404);
    begin_tx();
    $ok = q("DELETE FROM destination_requests WHERE linked_destination_id = $id")
       && q("DELETE FROM destination_activities WHERE destination_id = $id")
       && q("DELETE FROM destination_hotels WHERE destination_id = $id")
       && q("DELETE FROM destination_restaurants WHERE destination_id = $id")
       && q("DELETE FROM destination_categories WHERE destination_id = $id")
       && q("DELETE FROM destination_seasons WHERE destination_id = $id")
       && q("DELETE FROM destinations WHERE destination_id = $id");
    if (!$ok) { rollback_tx(); json_fail('Could not delete this destination.', 500); }
    commit_tx();
    json_out(['success' => true, 'message' => 'Destination deleted.']);
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? '';
$list = rows("SELECT d.destination_id, d.destination_name, d.city, d.state, d.status, d.created_at,
                (SELECT di.image_path FROM destination_images di WHERE di.destination_id = d.destination_id
                   ORDER BY di.is_primary DESC, di.display_order LIMIT 1) AS img,
                (SELECT dr.status FROM destination_requests dr WHERE dr.linked_destination_id = d.destination_id
                   ORDER BY dr.request_id DESC LIMIT 1) AS req_status
              FROM destinations d
              WHERE d.created_by = $agent_acc" . like_sql('d.destination_name', $search) . "
              ORDER BY d.created_at DESC LIMIT 200");

/* Display state: Live (approved) / Pending (awaiting admin) / Rejected */
foreach ($list as &$d) {
    $d['state'] = $d['status'] === 'Active' ? 'Live' : ($d['req_status'] === 'Rejected' ? 'Rejected' : 'Pending');
}
unset($d);
if (in_array($filter, ['Live', 'Pending', 'Rejected'], true)) {
    $list = array_values(array_filter($list, fn($d) => $d['state'] === $filter));
}

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>Destination list</h3><p>Search by name. View opens the full destination page.</p></div>
    <div class="toolbar">
      <form class="toolbar" method="GET" data-autosubmit>
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search destination by name">
        </div>
        <select name="status" aria-label="Filter by status">
          <option value="">All statuses</option>
          <?php foreach (['Live', 'Pending', 'Rejected'] as $s): ?>
            <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <a class="btn btn-primary" href="destination-add.php"><i class="fa-solid fa-plus"></i> Add destination</a>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Destination</th><th>Location</th><th>Status</th><th>Date</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="5"><div class="empty"><i class="fa-solid fa-map-location-dot"></i>
          <h4>No destinations yet</h4><p>Add your first destination. It will be sent to admin for review.</p>
          <a class="btn btn-primary" href="destination-add.php" style="margin-top:12px"><i class="fa-solid fa-plus"></i> Add destination</a></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $d): ?>
        <tr>
          <td><div class="cell-person">
            <img class="thumb" src="<?= h(asset_url($d['img'])) ?>" alt="">
            <div><strong><?= h($d['destination_name']) ?></strong><span>ID #<?= (int) $d['destination_id'] ?></span></div></div></td>
          <td class="muted"><?= h($d['city']) ?>, <?= h($d['state']) ?></td>
          <td><?= badge($d['state']) ?></td>
          <td class="muted"><?= fmt_date($d['created_at']) ?></td>
          <td><div class="row-actions">
            <a class="btn btn-soft btn-sm" href="destination-view.php?id=<?= (int) $d['destination_id'] ?>"><i class="fa-solid fa-eye"></i> View</a>
            <button class="btn btn-danger btn-sm" type="button" data-action="delete" data-url="destinations.php?ajax=delete"
                    data-id="<?= (int) $d['destination_id'] ?>" data-name="<?= h($d['destination_name']) ?>" data-title="Delete destination">
              <i class="fa-solid fa-trash-can"></i></button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
