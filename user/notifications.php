<?php
$page_title = 'Notifications';
$page_subtitle = 'Updates on your booking requests and payments. Close any item with the X.';
require_once __DIR__ . '/includes/auth.php';

$hidden = "SELECT item_key FROM dismissed_notifications WHERE account_id = $user_acc";
$items = rows("SELECT CONCAT('h:', h.status_history_id) AS k, h.status, h.message, h.changed_at AS created_at,
                      b.booking_id, p.package_name
               FROM booking_status_history h
               JOIN bookings b ON b.booking_id = h.booking_id
               JOIN packages p ON p.package_id = b.package_id
               WHERE b.user_id = $user_id AND CONCAT('h:', h.status_history_id) NOT IN ($hidden)
               ORDER BY h.changed_at DESC LIMIT 100");

$text = [
    'Pending'   => ['Request sent', 'Waiting for the agent to review it.', 'fa-paper-plane', 'b'],
    'Accepted'  => ['Request accepted', 'Pay now to confirm your trip.', 'fa-circle-check', 'a'],
    'Confirmed' => ['Trip confirmed', 'Payment received. Enjoy your trip!', 'fa-ticket', 'g'],
    'Rejected'  => ['Request not accepted', 'The agent could not take this booking.', 'fa-circle-xmark', 'r'],
    'Cancelled' => ['Booking cancelled', 'This booking was cancelled.', 'fa-ban', 'r'],
];

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<section class="card anim" style="--d:0">
  <div class="card-head">
    <div><h3><?= count($items) ?> notification<?= count($items) === 1 ? '' : 's' ?></h3><p>Booking updates from your agents.</p></div>
    <a class="btn btn-ghost btn-sm" href="bookings.php"><i class="fa-solid fa-ticket"></i> My bookings</a>
  </div>

  <div style="padding:10px 12px 14px">
  <?php if (!$items): ?>
    <div class="nt-empty"><i class="fa-regular fa-bell"></i><h4>You're all caught up</h4><p>Updates about your bookings will appear here.</p></div>
  <?php else: ?>
    <ul class="nt-list">
      <?php foreach ($items as $n):
        [$title, $sub, $ic, $tone] = $text[$n['status']] ?? [$n['status'], '', 'fa-bell', 'b'];
      ?>
      <li class="nt-item">
        <a href="bookings.php">
          <span class="nt-icon <?= $tone ?>"><i class="fa-solid <?= $ic ?>"></i></span>
          <span class="nt-body">
            <strong><?= h($title) ?></strong>
            <span><?= h($n['package_name']) ?> — <?= h($n['message'] ?: $sub) ?></span>
          </span>
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
<?php include __DIR__ . '/includes/page-end.php'; ?>
