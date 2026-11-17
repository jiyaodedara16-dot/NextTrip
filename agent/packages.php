<?php
$page_title = 'My Packages';
$page_subtitle = 'Tour packages you created. New packages go to admin for approval.';
require_once __DIR__ . '/includes/auth.php';

/* ---- AJAX: delete a package (blocked when it has bookings) ---- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete') {
    agent_ajax_guard();
    $id = require_post_id();
    if (!row("SELECT package_id FROM packages WHERE package_id = $id AND agent_id = $agent_id")) json_fail('Package not found.', 404);
    if ((int) scalar("SELECT COUNT(*) FROM bookings WHERE package_id = $id") > 0) {
        json_fail('This package has bookings, so it cannot be deleted. Ask admin to deactivate it instead.');
    }
    begin_tx();
    $ok = q("DELETE FROM package_destinations WHERE package_id = $id")
       && q("DELETE FROM package_categories WHERE package_id = $id")
       && q("DELETE FROM package_images WHERE package_id = $id")
       && q("DELETE FROM package_inclusions WHERE package_id = $id")
       && q("DELETE FROM package_exclusions WHERE package_id = $id")
       && q("DELETE FROM package_itinerary WHERE package_id = $id")
       && q("DELETE FROM package_seasons WHERE package_id = $id")
       && q("DELETE FROM package_favorites WHERE package_id = $id")
       && q("DELETE FROM package_reviews WHERE package_id = $id")
       && q("DELETE FROM packages WHERE package_id = $id");
    if (!$ok) { rollback_tx(); json_fail('Could not delete this package.', 500); }
    commit_tx();
    json_out(['success' => true, 'message' => 'Package deleted.']);
}

$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? '';
$list = rows("SELECT p.package_id, p.package_name, p.duration_days, p.duration_nights, p.price_per_person, p.status, p.created_at,
                (SELECT pi.image_path FROM package_images pi WHERE pi.package_id = p.package_id ORDER BY (pi.image_type = 'Cover') DESC, pi.display_order LIMIT 1) AS img,
                (SELECT COUNT(*) FROM package_destinations pd WHERE pd.package_id = p.package_id) AS dest_count,
                (SELECT COUNT(*) FROM bookings b WHERE b.package_id = p.package_id) AS booking_count
              FROM packages p
              WHERE p.agent_id = $agent_id" . like_sql('p.package_name', $search) . "
              ORDER BY p.created_at DESC LIMIT 200");
foreach ($list as &$p) $p['state'] = $p['status'] === 'Active' ? 'Live' : 'Pending';
unset($p);
if (in_array($filter, ['Live', 'Pending'], true)) $list = array_values(array_filter($list, fn($p) => $p['state'] === $filter));

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>Package list</h3><p>Search by package name.</p></div>
    <div class="toolbar">
      <form class="toolbar" method="GET" data-autosubmit>
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search package by name"></div>
        <select name="status" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option value="Live" <?= $filter === 'Live' ? 'selected' : '' ?>>Live</option>
          <option value="Pending" <?= $filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
        </select>
      </form>
      <a class="btn btn-primary" href="package-add.php"><i class="fa-solid fa-plus"></i> Add package</a>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Package</th><th>Duration</th><th>Price / person</th><th>Destinations</th><th>Bookings</th><th>Status</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="7"><div class="empty"><i class="fa-solid fa-box-open"></i><h4>No packages yet</h4>
          <p>Build a package from your live destinations. It is sent to admin for approval.</p>
          <a class="btn btn-primary" href="package-add.php" style="margin-top:12px"><i class="fa-solid fa-plus"></i> Add package</a></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $p): ?>
        <tr>
          <td><div class="cell-person">
            <img class="thumb" src="<?= h(asset_url($p['img'], 'assets/images/destinations/placeholder.jpg')) ?>" alt="">
            <div><strong><?= h($p['package_name']) ?></strong><span>Created <?= fmt_date($p['created_at']) ?></span></div></div></td>
          <td><span class="chip"><?= (int) $p['duration_days'] ?>D / <?= (int) $p['duration_nights'] ?>N</span></td>
          <td class="num"><?= money($p['price_per_person']) ?></td>
          <td class="num"><?= (int) $p['dest_count'] ?></td>
          <td class="num"><?= (int) $p['booking_count'] ?></td>
          <td><?= badge($p['state']) ?></td>
          <td><div class="row-actions">
            <button class="btn btn-danger btn-sm" type="button" data-action="delete" data-url="packages.php?ajax=delete"
                    data-id="<?= (int) $p['package_id'] ?>" data-name="<?= h($p['package_name']) ?>" data-title="Delete package">
              <i class="fa-solid fa-trash-can"></i></button>
            <a class="btn btn-soft btn-sm" href="package-view.php?id=<?= (int) $p['package_id'] ?>"><i class="fa-solid fa-eye"></i> View</a>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
