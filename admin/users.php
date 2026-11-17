<?php
$page_title = 'Users';
$page_subtitle = 'Registered tourists on the platform';
require_once __DIR__ . '/includes/auth.php';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$where  = "WHERE 1=1" . like_sql("CONCAT(u.first_name, ' ', u.last_name)", $search);
if (in_array($status, ['Active', 'Inactive', 'Blocked'], true)) $where .= " AND a.status = '$status'";

$total = (int) scalar("SELECT COUNT(*) FROM users u JOIN accounts a ON a.account_id = u.account_id $where");
$p = paginate($total, 10, $_GET['page'] ?? 1);
$list = rows("SELECT u.user_id, u.first_name, u.last_name, u.phone, u.city, a.email, a.status, a.created_at, a.last_login
              FROM users u JOIN accounts a ON a.account_id = u.account_id
              $where ORDER BY a.created_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>All users</h3><p>Search by name, then open a profile to review it.</p></div>
    <form class="toolbar" method="GET" data-autosubmit>
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search user by name">
      </div>
      <select name="status" aria-label="Filter by status">
        <option value="">All statuses</option>
        <?php foreach (['Active', 'Inactive', 'Blocked'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>User</th><th>Phone</th><th>City</th><th>Joined</th><th>Last login</th><th>Status</th><th style="text-align:right">Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="7"><div class="empty"><i class="fa-solid fa-user-slash"></i><h4>No users found</h4><p>Try a different name or clear the filters.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $u): $name = trim($u['first_name'] . ' ' . $u['last_name']); ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar"><?= h(initials($name)) ?></div>
            <div><strong><?= h($name) ?></strong><span><?= h($u['email']) ?></span></div></div></td>
          <td class="muted"><?= h($u['phone'] ?: '—') ?></td>
          <td class="muted"><?= h($u['city'] ?: '—') ?></td>
          <td class="muted"><?= fmt_date($u['created_at']) ?></td>
          <td class="muted"><?= time_ago($u['last_login']) ?></td>
          <td><?= badge($u['status']) ?></td>
          <td><div class="row-actions">
            <button class="btn btn-soft btn-sm" type="button" data-action="view" data-url="ajax/user_details.php" data-id="<?= (int) $u['user_id'] ?>" data-title="User details" data-size="lg"><i class="fa-solid fa-eye"></i> View</button>
            <button class="btn btn-danger btn-sm" type="button" data-action="delete" data-url="ajax/user_delete.php" data-id="<?= (int) $u['user_id'] ?>" data-name="<?= h($name) ?>" data-title="Delete user"><i class="fa-solid fa-trash-can"></i></button>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($p, array_filter(['q' => $search, 'status' => $status])) ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
