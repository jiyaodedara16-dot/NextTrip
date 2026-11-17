<?php
$current = basename($_SERVER['PHP_SELF']);
$isActive = function ($files) use ($current) { return in_array($current, (array) $files, true) ? 'active' : ''; };
$unreadCount = (int) scalar("SELECT COUNT(*) FROM notifications WHERE account_id = $agent_acc AND is_read = 0");
$pendingCount = (int) scalar("SELECT COUNT(*) FROM bookings WHERE agent_id = $agent_id AND booking_status = 'Pending'");
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-mark"><i class="fa-solid fa-briefcase"></i></div>
    <div class="brand-text">NextTrip<span>Agent Console</span></div>
  </div>

  <div class="nav-group-label">Overview</div>
  <a href="dashboard.php" class="nav-link <?= $isActive('dashboard.php') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
  <a href="notifications.php" class="nav-link <?= $isActive('notifications.php') ?>">
    <i class="fa-solid fa-bell"></i> Notifications
    <?php if ($unreadCount): ?><span class="nav-count"><?= $unreadCount ?></span><?php endif; ?>
  </a>

  <div class="nav-group-label">Catalog</div>
  <a href="destinations.php" class="nav-link <?= $isActive(['destinations.php', 'destination-view.php', 'destination-add.php']) ?>"><i class="fa-solid fa-map-location-dot"></i> Destinations</a>
  <a href="packages.php" class="nav-link <?= $isActive(['packages.php', 'package-add.php']) ?>"><i class="fa-solid fa-box-open"></i> Packages</a>
    <a href="coupons.php" class="nav-link <?= $isActive(['coupons.php', 'coupon-add.php']) ?>"><i class="fa-solid fa-ticket"></i> Coupons</a>

  <div class="nav-group-label">Bookings</div>
  <a href="bookings.php" class="nav-link <?= $isActive('bookings.php') ?>">
    <i class="fa-solid fa-bell-concierge"></i> Booking Requests
    <?php if ($pendingCount): ?><span class="nav-count"><?= $pendingCount ?></span><?php endif; ?>
  </a>

  <div class="nav-group-label">System</div>
  <a href="settings.php" class="nav-link <?= $isActive('settings.php') ?>"><i class="fa-solid fa-gear"></i> Settings</a>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <span>Signed in as</span>
      <strong><?= h($agent_name) ?></strong>
      <em><?= h($agency_name) ?></em>
    </div>
    <a href="logout.php" class="nav-link logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</aside>
