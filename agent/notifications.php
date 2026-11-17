<?php
$page_title = 'Notifications';
$page_subtitle = 'Admin decisions, deletions and new booking requests. Close any item with the X.';
require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'read_all') {
    agent_ajax_guard();
    q("UPDATE notifications SET is_read = 1 WHERE account_id = $agent_acc");
    json_out(['success' => true, 'message' => 'All notifications marked as read.']);
}

$hidden = "SELECT item_key FROM dismissed_notifications WHERE account_id = $agent_acc";
$items = rows("SELECT CONCAT('n:', n.notification_id) AS k, n.type, n.title, n.message, n.link, n.is_read, n.created_at
               FROM notifications n
               WHERE n.account_id = $agent_acc AND CONCAT('n:', n.notification_id) NOT IN ($hidden)
               ORDER BY n.created_at DESC LIMIT 100");
$requests = rows("SELECT CONCAT('b:', b.booking_id) AS k, 'booking_request' AS type, 'Booking request waiting' AS title,
                         CONCAT(CONCAT(u.first_name, ' ', u.last_name), ' · ', p.package_name) AS message,
                         'bookings.php' AS link, 0 AS is_read, b.created_at
                  FROM bookings b
                  JOIN users u ON u.user_id = b.user_id
                  JOIN packages p ON p.package_id = b.package_id
                  WHERE b.agent_id = $agent_id AND b.booking_status = 'Pending'
                    AND CONCAT('b:', b.booking_id) NOT IN ($hidden)
                  ORDER BY b.created_at DESC LIMIT 50");
$items = array_merge($items, $requests);
usort($items, fn($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

$icons = ['destination_submitted' => ['fa-map-location-dot', 'g'], 'package_submitted' => ['fa-box-open', 'g'],
          'destination_approved' => ['fa-circle-check', 'g'], 'destination_rejected' => ['fa-circle-xmark', 'r'],
          'destination_deleted' => ['fa-trash-can', 'r'], 'package_approved' => ['fa-circle-check', 'g'],
          'package_rejected' => ['fa-circle-xmark', 'r'], 'package_deleted' => ['fa-trash-can', 'r'],
          'package_created' => ['fa-box-open', 'g'], 'account_approved' => ['fa-user-check', 'g'],
          'booking_request' => ['fa-bell-concierge', 'a'], 'booking_paid' => ['fa-ticket', 'g'],
          'coupon_submitted' => ['fa-ticket', 'a'], 'coupon_approved' => ['fa-circle-check', 'g'],
          'coupon_rejected' => ['fa-circle-xmark', 'r']];

include __DIR__ . '/includes/page-start.php';
?>
<section class="card anim" style="--d:0">
  <div class="card-head">
    <div><h3><?= count($items) ?> notification<?= count($items) === 1 ? '' : 's' ?></h3><p>Close an item with the X when you no longer need it.</p></div>
    <button class="btn btn-ghost btn-sm" type="button" id="markAllRead"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
  </div>

  <div style="padding:10px 12px 14px">
  <?php if (!$items): ?>
    <div class="nt-empty"><i class="fa-regular fa-bell"></i><h4>You're all caught up</h4><p>New updates about your listings and bookings will appear here.</p></div>
  <?php else: ?>
    <ul class="nt-list">
      <?php foreach ($items as $n):
        [$ic, $tone] = $icons[$n['type']] ?? ['fa-bell', 'g'];
        $link = $n['link'] ?: 'notifications.php';
      ?>
      <li class="nt-item <?= $n['is_read'] ? '' : 'is-unread' ?>">
        <a href="<?= h($link) ?>">
          <span class="nt-icon <?= $tone ?>"><i class="fa-solid <?= $ic ?>"></i></span>
          <span class="nt-body"><strong><?= h($n['title']) ?></strong><span><?= h($n['message']) ?></span></span>
          <span class="nt-time"><?= time_ago($n['created_at']) ?></span>
        </a>
        <button class="nt-dismiss" type="button" aria-label="Remove notification" title="Remove"
                data-dismiss="<?= h($n['k']) ?>" data-url="../ajax/notification_dismiss.php">&times;</button>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  </div>
</section>
<script>
document.getElementById('markAllRead')?.addEventListener('click', async () => {
  const res = await NT.post('notifications.php?ajax=read_all', {});
  NT.toast(res.message, res.success ? 'success' : 'error');
  setTimeout(() => location.reload(), 600);
});
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
