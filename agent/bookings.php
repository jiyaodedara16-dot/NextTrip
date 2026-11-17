<?php
$page_title = 'Booking Requests';
$page_subtitle = 'Review traveller requests. Accepting sends the traveller a payment link email; rejecting emails the reason.';
require_once __DIR__ . '/includes/auth.php';

/* ---- AJAX: accept (approve) or reject a request ---- */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'decide') {
    agent_ajax_guard();
    $id     = require_post_id();
    $action = $_POST['action'] ?? '';
    $b = row("SELECT b.booking_id, b.booking_status, b.total_amount, p.package_name, u.first_name, a.email, a.account_id AS traveller_acc,
                     CONCAT(u.first_name, ' ', u.last_name) AS traveller
              FROM bookings b
              JOIN packages p ON p.package_id = b.package_id
              JOIN users u ON u.user_id = b.user_id
              JOIN accounts a ON a.account_id = u.account_id
              WHERE b.booking_id = $id AND b.agent_id = $agent_id");
    if (!$b) json_fail('Booking request not found.', 404);
    if ($b['booking_status'] !== 'Pending') json_fail('This request has already been decided.');

    if ($action === 'approve') {
        begin_tx();
        $ok = q("UPDATE bookings SET booking_status = 'Accepted' WHERE booking_id = $id")
           && q("INSERT INTO booking_status_history (booking_id, status, changed_by, changed_at, message)
                 VALUES ($id, 'Accepted', $agent_acc, NOW(), 'Accepted by agent')");
        if (!$ok) { rollback_tx(); json_fail('Could not accept this request.', 500); }
        commit_tx();
        require_once __DIR__ . '/../includes/email-template.php';
        $bd = email_booking_data($id);
        $sent = email_send_booking($b['email'], $b['traveller'], 'Your booking was accepted — pay to confirm', 'Booking accepted',
            'Your request has been accepted',
            'Good news! ' . $agent_name . ' from ' . $agency_name . ' accepted your request. Pay the amount below to confirm your trip.',
            $bd, [EMAIL_SITE_URL . '/user/bookings.php', 'Pay now']);
        json_out(['success' => true, 'message' => 'Request accepted. The traveller can now pay to confirm.' . ($sent ? ' They were emailed.' : ' The email could not be sent.')]);
    }

    if ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        if (mb_strlen($reason) < 5) json_fail('Please enter a reason of at least 5 characters.');
        $reason = mb_substr($reason, 0, 500);
        begin_tx();
        $ok = q("UPDATE bookings SET booking_status = 'Rejected', agent_message = " . sql_val($reason) . " WHERE booking_id = $id")
           && q("INSERT INTO booking_status_history (booking_id, status, changed_by, changed_at, message)
                 VALUES ($id, 'Rejected', $agent_acc, NOW(), " . sql_val($reason) . ")");
        if (!$ok) { rollback_tx(); json_fail('Could not reject this request.', 500); }
        commit_tx();
        require_once __DIR__ . '/../includes/email-template.php';
        $bd = email_booking_data($id);
        $sent = email_send_booking($b['email'], $b['traveller'], 'Your booking was not accepted', 'Booking not accepted',
            'We could not accept your request',
            'Sorry, ' . $agent_name . ' from ' . $agency_name . ' could not accept your request. Reason: ' . $reason,
            $bd, [EMAIL_SITE_URL . '/packages.php', 'Browse other packages'], [], false);
        json_out(['success' => true, 'message' => 'Request rejected.' . ($sent ? ' The traveller was emailed the reason.' : ' The email could not be sent.')]);
    }
    json_fail('Unknown action.');
}

$tab = in_array($_GET['tab'] ?? '', ['Pending', 'Accepted', 'Confirmed', 'Rejected', 'All'], true) ? $_GET['tab'] : 'Pending';
$search = trim($_GET['q'] ?? '');
$cond = $tab === 'All' ? '' : " AND b.booking_status = '$tab'";
$counts = [];
foreach (['Pending', 'Accepted', 'Confirmed', 'Rejected'] as $s) {
    $counts[$s] = (int) scalar("SELECT COUNT(*) FROM bookings WHERE agent_id = $agent_id AND booking_status = '$s'");
}
$list = rows("SELECT b.booking_id, b.travel_date, b.adult_count, b.child_count, b.total_amount, b.booking_status, b.created_at,
                     CONCAT(u.first_name, ' ', u.last_name) AS traveller, a.email, p.package_name
              FROM bookings b
              JOIN users u ON u.user_id = b.user_id
              JOIN accounts a ON a.account_id = u.account_id
              JOIN packages p ON p.package_id = b.package_id
              WHERE b.agent_id = $agent_id $cond"
            . like_sql("CONCAT(u.first_name, ' ', u.last_name)", $search) . "
              ORDER BY b.created_at DESC LIMIT 200");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card">
  <div class="card-head">
    <div class="tabs">
      <?php foreach (['Pending' => 'Pending', 'Accepted' => 'Awaiting payment', 'Confirmed' => 'Confirmed', 'Rejected' => 'Rejected'] as $t => $label): ?>
        <a class="tab <?= $tab === $t ? 'active' : '' ?>" href="?tab=<?= $t ?>&q=<?= urlencode($search) ?>"><?= $label ?>
          <span class="muted">(<?= $counts[$t] ?>)</span></a>
      <?php endforeach; ?>
      <a class="tab <?= $tab === 'All' ? 'active' : '' ?>" href="?tab=All&q=<?= urlencode($search) ?>">All</a>
    </div>
    <form class="toolbar" method="GET" data-autosubmit>
      <input type="hidden" name="tab" value="<?= h($tab) ?>">
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search traveller name"></div>
    </form>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead><tr>
        <th>Traveller</th><th>Package</th><th>Travel date</th><th>Guests</th><th>Total</th><th>Requested</th><th>Status</th><th style="text-align:right">Decision</th>
      </tr></thead>
      <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="8"><div class="empty"><i class="fa-solid fa-inbox"></i><h4>Nothing here</h4><p>Requests matching this view will appear here.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($list as $b): ?>
        <tr>
          <td><div class="cell-person"><div class="mini-avatar"><?= h(initials($b['traveller'])) ?></div>
            <div><strong><?= h($b['traveller']) ?></strong><span><?= h($b['email']) ?></span></div></div></td>
          <td style="max-width:220px"><span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($b['package_name']) ?></span></td>
          <td class="muted"><?= fmt_date($b['travel_date']) ?></td>
          <td class="muted"><?= (int) $b['adult_count'] ?> adult<?= $b['adult_count'] == 1 ? '' : 's' ?><?= (int) $b['child_count'] ? ', ' . (int) $b['child_count'] . ' child' : '' ?></td>
          <td class="num"><?= money($b['total_amount']) ?></td>
          <td class="muted"><?= time_ago($b['created_at']) ?></td>
          <td><?= badge($b['booking_status'] === 'Accepted' ? 'Awaiting payment' : $b['booking_status']) ?></td>
          <td><div class="row-actions">
            <?php if ($b['booking_status'] === 'Pending'): ?>
              <button class="btn btn-primary btn-sm" type="button" data-action="approve" data-url="bookings.php?ajax=decide"
                      data-id="<?= (int) $b['booking_id'] ?>" data-name="<?= h($b['traveller']) ?>" data-title="Accept request">
                <i class="fa-solid fa-check"></i> Accept</button>
              <button class="btn btn-danger btn-sm btn-text" type="button" data-action="reject" data-url="bookings.php?ajax=decide"
                      data-id="<?= (int) $b['booking_id'] ?>" data-name="<?= h($b['traveller']) ?>">
                <i class="fa-solid fa-xmark"></i> Reject</button>
            <?php else: ?>
              <a class="btn btn-soft btn-sm" href="booking-view.php?id=<?= (int) $b['booking_id'] ?>"><i class="fa-regular fa-eye"></i> View details</a>
              <?php if ($b['booking_status'] === 'Confirmed'): ?>
                <a class="btn btn-soft btn-sm" href="receipt.php?booking_id=<?= (int) $b['booking_id'] ?>"><i class="fa-solid fa-file-pdf"></i> Receipt</a>
              <?php endif; ?>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>