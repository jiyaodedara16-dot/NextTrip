<?php
$page_title = 'Notifications';
$page_subtitle = 'Admin decisions, deletions and updates about your listings';
require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'read_all') {
    agent_ajax_guard();
    q("UPDATE notifications SET is_read = 1 WHERE account_id = $agent_acc");
    json_out(['success' => true, 'message' => 'All notifications marked as read.']);
}

$items = rows("SELECT notification_id, type, title, message, link, is_read, created_at
               FROM notifications WHERE account_id = $agent_acc ORDER BY created_at DESC LIMIT 100");
$icons = ['destination_submitted' => ['fa-map-location-dot', 'g'], 'package_submitted' => ['fa-box-open', 'g'],
          'destination_approved' => ['fa-circle-check', 'g'], 'destination_rejected' => ['fa-circle-xmark', 'r'],
          'destination_deleted' => ['fa-trash-can', 'r'], 'package_approved' => ['fa-circle-check', 'g'],
          'package_rejected' => ['fa-circle-xmark', 'r'], 'package_deleted' => ['fa-trash-can', 'r'],
          'account_approved' => ['fa-user-check', 'g']];

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div><h3>All notifications</h3><p><?= count($items) ?> recent update<?= count($items) === 1 ? '' : 's' ?></p></div>
    <button class="btn btn-ghost btn-sm" type="button" id="markAllRead"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
  </div>
  <?php if (!$items): ?>
    <div class="empty"><i class="fa-regular fa-bell"></i><h4>You're all up to date</h4><p>Updates about your destinations and packages will appear here.</p></div>
  <?php else: ?>
  <ul class="feed feed-full">
    <?php foreach ($items as $n):
      [$ic, $tone] = $icons[$n['type']] ?? ['fa-bell', 'g']; ?>
      <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
        <a href="<?= h($n['link'] ?: 'notifications.php') ?>">
          <span class="feed-ic <?= $tone ?>"><i class="fa-solid <?= $ic ?>"></i></span>
          <div class="feed-body"><strong><?= h($n['title']) ?></strong><span><?= h($n['message']) ?></span></div>
          <em><?= fmt_datetime($n['created_at']) ?></em>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<script>
document.getElementById('markAllRead')?.addEventListener('click', async () => {
  const res = await NT.post('notifications.php?ajax=read_all', {});
  NT.toast(res.message, res.success ? 'success' : 'error');
  setTimeout(() => location.reload(), 600);
});
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
