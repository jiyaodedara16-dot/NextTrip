<?php
$page_title = 'Packages';
$page_subtitle = 'Tour packages created by partner agents';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$where  = "WHERE 1=1" . like_sql('p.package_name', $search);
if (in_array($status, ['Active', 'Inactive'], true)) $where .= " AND p.status = '$status'";

$total = (int) scalar("SELECT COUNT(*) FROM packages p $where");
$p = paginate($total, 10, $_GET['page'] ?? 1);
$list = rows("SELECT p.package_id, p.package_name, p.description, p.duration_days, p.duration_nights, p.price_per_person, p.status,
                     ag.full_name AS agent_name,
                     (SELECT GROUP_CONCAT(d.destination_name ORDER BY pd.display_order SEPARATOR '||')
                        FROM package_destinations pd JOIN destinations d ON d.destination_id = pd.destination_id
                        WHERE pd.package_id = p.package_id) AS destinations_covered
              FROM packages p
              LEFT JOIN agents ag ON ag.agent_id = p.agent_id
              $where ORDER BY p.created_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>All packages</h3><p>Search by package name. View opens the full package summary.</p></div>
    <form class="toolbar" method="GET" data-autosubmit>
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search package by name">
      </div>
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
        <th>Package</th><th>Agent</th><th>Price / person</th><th>Destinations covered</th><th>Status</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="7"><div class="empty"><i class="fa-solid fa-box-open"></i><h4>No packages found</h4><p>Try another name or clear the filters.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $pk):
        $dests = $pk['destinations_covered'] ? explode('||', $pk['destinations_covered']) : [];
      ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar amber"><i class="fa-solid fa-suitcase-rolling"></i></div>
            <div style="min-width:0"><strong><?= h($pk['package_name']) ?></strong>
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:260px"><?= h($pk['description'] ?: '—') ?></span></div></div></td>
          <td class="muted"><?= h($pk['agent_name'] ?: '—') ?></td>
          <td class="num"><?= money($pk['price_per_person']) ?></td>
          <td>
            <div class="chip-list">
              <?php foreach (array_slice($dests, 0, 2) as $dn): ?><span class="chip green"><?= h($dn) ?></span><?php endforeach; ?>
              <?php if (count($dests) > 2): ?><span class="chip">+<?= count($dests) - 2 ?></span><?php endif; ?>
              <?php if (!$dests): ?><span class="muted">None</span><?php endif; ?>
            </div>
          </td>
          <td><?= badge($pk['status']) ?></td>
          <td><div class="row-actions">
            <a class="btn btn-soft btn-sm" href="package-view.php?id=<?= (int) $pk['package_id'] ?>"><i class="fa-solid fa-eye"></i> View</a>
            <button class="btn btn-danger btn-sm" type="button" data-action="delete" data-reason="required" data-url="ajax/package_delete.php" data-id="<?= (int) $pk['package_id'] ?>" data-name="<?= h($pk['package_name']) ?>" data-title="Delete package"><i class="fa-solid fa-trash-can"></i></button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($p, array_filter(['q' => $search, 'status' => $status])) ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
