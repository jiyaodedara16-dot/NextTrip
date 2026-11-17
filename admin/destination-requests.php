<?php
$page_title = 'Destination Requests';
$page_subtitle = 'New destinations suggested by agents. Approve to publish them.';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$tab    = in_array($_GET['tab'] ?? '', ['Pending', 'Approved', 'Rejected', 'All'], true) ? $_GET['tab'] : 'Pending';
$where  = "WHERE 1=1" . ($tab === 'All' ? '' : " AND dr.status = '$tab'") . like_sql('dr.destination_name', $search);

$counts = [];
foreach (['Pending', 'Approved', 'Rejected'] as $s) {
    $counts[$s] = (int) scalar("SELECT COUNT(*) FROM destination_requests WHERE status = '$s'");
}
$total = (int) scalar("SELECT COUNT(*) FROM destination_requests dr $where");
$p = paginate($total, 9, $_GET['page'] ?? 1);
$list = rows("SELECT dr.*, ag.full_name AS agent_name, c.category_name
              FROM destination_requests dr
              LEFT JOIN agents ag ON ag.agent_id = dr.agent_id
              LEFT JOIN categories c ON c.category_id = dr.category_id
              $where ORDER BY dr.requested_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="rq-toolbar anim" style="--d:0">
  <div class="tabs">
    <?php foreach (['Pending' => $counts['Pending'], 'Approved' => $counts['Approved'], 'Rejected' => $counts['Rejected'], 'All' => array_sum($counts)] as $t => $n): ?>
      <a class="tab <?= $tab === $t ? 'active' : '' ?>" href="?tab=<?= $t ?>&q=<?= urlencode($search) ?>"><?= $t ?> <span class="tab-count"><?= $n ?></span></a>
    <?php endforeach; ?>
  </div>
  <form class="toolbar" method="GET" data-autosubmit>
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search destination by name">
    </div>
  </form>
</section>

<?php if (!$list): ?>
  <section class="card"><div class="empty"><i class="fa-solid fa-inbox"></i><h4>No requests here</h4><p>Requests from agents appear in this queue.</p></div></section>
<?php else: ?>
<section class="rq-grid">
  <?php foreach ($list as $i => $r):
    $img = $r['image_path'] ? asset_url($r['image_path']) : null;
    $statusCls = strtolower($r['status']);
  ?>
  <article class="rq-card anim" style="--d:<?= $i + 1 ?>">
    <a class="rq-media" href="destination-request-view.php?id=<?= (int) $r['request_id'] ?>">
      <?php if ($img): ?>
        <img src="<?= h($img) ?>" alt="<?= h($r['destination_name']) ?>" loading="lazy">
      <?php else: ?>
        <div class="rq-placeholder"><i class="fa-solid fa-image"></i></div>
      <?php endif; ?>
      <span class="rq-shade"></span>
      <span class="rq-status rq-status-<?= h($statusCls) ?>"><i></i><?= h($r['status']) ?></span>
      <?php if ($r['category_name']): ?><span class="rq-cat"><i class="fa-solid fa-tag"></i><?= h($r['category_name']) ?></span><?php endif; ?>
    </a>

    <div class="rq-body">
      <h3 class="rq-title"><?= h($r['destination_name']) ?></h3>
      <div class="rq-meta">
        <span><i class="fa-solid fa-location-dot"></i><?= h($r['city']) ?>, <?= h($r['state']) ?></span>
        <span><i class="fa-solid fa-ticket"></i><?= h(entry_label($r['entry_fee'])) ?></span>
      </div>
      <p class="rq-desc"><?= h($r['description'] ?: 'No description provided.') ?></p>

      <?php if ($r['status'] === 'Rejected' && $r['rejection_reason']): ?>
        <div class="rq-reason"><i class="fa-solid fa-circle-info"></i><span><?= h($r['rejection_reason']) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="rq-foot">
      <div class="rq-agent">
        <span class="rq-avatar"><?= h(initials($r['agent_name'] ?: '?')) ?></span>
        <div><strong><?= h($r['agent_name'] ?: 'Unknown agent') ?></strong><small><i class="fa-regular fa-clock"></i> <?= time_ago($r['requested_at']) ?></small></div>
      </div>
      <div class="rq-actions">
        <a class="rq-btn rq-btn-view" href="destination-request-view.php?id=<?= (int) $r['request_id'] ?>" title="View full submission"><i class="fa-solid fa-eye"></i></a>
        <?php if ($r['status'] === 'Pending'): ?>
          <button class="rq-btn rq-btn-reject" type="button" title="Reject" data-action="reject" data-url="ajax/destination_request_action.php" data-id="<?= (int) $r['request_id'] ?>" data-name="<?= h($r['destination_name']) ?>"><i class="fa-solid fa-xmark"></i></button>
          <button class="rq-btn rq-btn-approve" type="button" title="Approve" data-action="approve" data-url="ajax/destination_request_action.php" data-id="<?= (int) $r['request_id'] ?>" data-name="<?= h($r['destination_name']) ?>" data-title="Approve destination"><i class="fa-solid fa-check"></i> Approve</button>
        <?php endif; ?>
      </div>
    </div>
  </article>
  <?php endforeach; ?>
</section>
<section class="card" style="margin-top:4px"><?= pager($p, array_filter(['q' => $search, 'tab' => $tab])) ?></section>
<?php endif; ?>
<?php include __DIR__ . '/includes/page-end.php'; ?>