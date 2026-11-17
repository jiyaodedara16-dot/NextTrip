<?php
$page_title = 'Agents';
$page_subtitle = 'Approved travel partners and their verification records';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$where  = "WHERE ag.status = 'Approved'" . like_sql('ag.full_name', $search);

$total = (int) scalar("SELECT COUNT(*) FROM agents ag JOIN accounts a ON a.account_id = ag.account_id $where");
$p = paginate($total, 10, $_GET['page'] ?? 1);
$list = rows("SELECT ag.agent_id, ag.full_name, ag.phone, ag.city, ag.created_at, ag.reviewed_at, a.email
              FROM agents ag JOIN accounts a ON a.account_id = ag.account_id
              $where ORDER BY ag.reviewed_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>Approved agents</h3><p>Each row is an agent approved from the Agent Requests queue.</p></div>
    <form class="toolbar" method="GET" data-autosubmit>
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search agent by name">
      </div>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Agent</th><th>Phone</th><th>Location</th><th>Joined</th><th>Approved</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="6"><div class="empty"><i class="fa-solid fa-user-tie"></i><h4>No approved agents</h4><p>Approved agents from the Agent Requests page appear here.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $ag): ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar"><?= h(initials($ag['full_name'])) ?></div>
            <div><strong><?= h($ag['full_name']) ?></strong><span><?= h($ag['email']) ?></span></div></div></td>
          <td class="muted"><?= h($ag['phone'] ?: '—') ?></td>
          <td class="muted"><?= h($ag['city'] ?: '—') ?></td>
          <td class="muted"><?= fmt_date($ag['created_at']) ?></td>
          <td class="muted"><?= fmt_date($ag['reviewed_at']) ?></td>
          <td><div class="row-actions">
            <button class="btn btn-soft btn-sm" type="button" data-action="view" data-url="ajax/agent_details.php" data-id="<?= (int) $ag['agent_id'] ?>" data-title="Agent details" data-size="lg"><i class="fa-solid fa-eye"></i> View</button>
            <button class="btn btn-danger btn-sm" type="button" data-action="delete" data-url="ajax/agent_delete.php" data-id="<?= (int) $ag['agent_id'] ?>" data-name="<?= h($ag['full_name']) ?>" data-title="Delete agent"><i class="fa-solid fa-trash-can"></i></button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($p, array_filter(['q' => $search])) ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
