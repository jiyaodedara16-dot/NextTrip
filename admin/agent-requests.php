<?php
$page_title = 'Agent Requests';
$page_subtitle = 'Review partner applications. Approved agents move to Agents.';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$tab    = in_array($_GET['tab'] ?? '', ['Pending', 'Rejected', 'All'], true) ? $_GET['tab'] : 'Pending';
$where  = "WHERE ag.status " . ($tab === 'All' ? "IN ('Pending','Rejected')" : "= '$tab'") . like_sql('ag.full_name', $search);

$counts = [
    'Pending'  => (int) scalar("SELECT COUNT(*) FROM agents WHERE status = 'Pending'"),
    'Rejected' => (int) scalar("SELECT COUNT(*) FROM agents WHERE status = 'Rejected'"),
];
$total = (int) scalar("SELECT COUNT(*) FROM agents ag JOIN accounts a ON a.account_id = ag.account_id $where");
$p = paginate($total, 10, $_GET['page'] ?? 1);
$list = rows("SELECT ag.agent_id, ag.full_name, ag.phone, ag.city, ag.status, ag.rejection_reason, ag.created_at, ag.reviewed_at, a.email
              FROM agents ag JOIN accounts a ON a.account_id = ag.account_id
              $where ORDER BY ag.created_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>Applications</h3><p>Pending and rejected applications stay here for admin records.</p></div>
    <div class="toolbar">
      <div class="tabs">
        <?php foreach (['Pending' => $counts['Pending'], 'Rejected' => $counts['Rejected'], 'All' => $counts['Pending'] + $counts['Rejected']] as $t => $n): ?>
          <a class="tab <?= $tab === $t ? 'active' : '' ?>" href="?tab=<?= $t ?>&q=<?= urlencode($search) ?>"><?= $t ?> <span class="muted">(<?= $n ?>)</span></a>
        <?php endforeach; ?>
      </div>
      <form class="toolbar" method="GET" data-autosubmit>
        <input type="hidden" name="tab" value="<?= h($tab) ?>">
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search applicant by name">
        </div>
      </form>
    </div>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Applicant</th><th>Phone</th><th>Location</th><th>Applied</th><th>Status</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="6"><div class="empty"><i class="fa-solid fa-inbox"></i><h4>Nothing to show</h4><p>No <?= strtolower($tab) === 'all' ? 'open' : strtolower($tab) ?> applications match this view.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $ag): ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar <?= avatar_class($ag['status']) ?>"><?= h(initials($ag['full_name'])) ?></div>
            <div><strong><?= h($ag['full_name']) ?></strong><span><?= h($ag['email']) ?></span></div></div></td>
          <td class="muted"><?= h($ag['phone'] ?: '—') ?></td>
          <td class="muted"><?= h($ag['city'] ?: '—') ?></td>
          <td class="muted"><?= fmt_date($ag['created_at']) ?></td>
          <td><?= badge($ag['status']) ?></td>
          <td><div class="row-actions">
            <button class="btn btn-soft btn-sm" type="button" data-action="view" data-url="ajax/agent_details.php" data-id="<?= (int) $ag['agent_id'] ?>" data-title="Application details" data-size="lg"><i class="fa-solid fa-eye"></i> View</button>
            <?php if ($ag['status'] === 'Pending'): ?>
              <button class="btn btn-primary btn-sm" type="button" data-action="approve" data-url="ajax/agent_request_action.php" data-id="<?= (int) $ag['agent_id'] ?>" data-name="<?= h($ag['full_name']) ?>" data-title="Approve agent"><i class="fa-solid fa-check"></i> Approve</button>
              <button class="btn btn-danger btn-sm btn-text" type="button" data-action="reject" data-url="ajax/agent_request_action.php" data-id="<?= (int) $ag['agent_id'] ?>" data-name="<?= h($ag['full_name']) ?>"><i class="fa-solid fa-xmark"></i> Reject</button>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($p, array_filter(['q' => $search, 'tab' => $tab])) ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
