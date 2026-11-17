<?php
$current = basename($_SERVER['PHP_SELF']);
$isActive = function ($files) use ($current) { return in_array($current, (array) $files, true) ? 'active' : ''; };
$actionCount = (int) scalar("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_status = 'Accepted'");
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-mark"><i class="fa-solid fa-compass"></i></div>
    <div class="brand-text">NextTrip<span>My Account</span></div>
  </div>

  <div class="nav-group-label">Overview</div>
  <a href="index.php" class="nav-link <?= $isActive('index.php') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>

  <div class="nav-group-label">Favourites</div>
  <a href="destinations.php" class="nav-link <?= $isActive('destinations.php') ?>"><i class="fa-solid fa-heart"></i> Destinations</a>
  <a href="gallery.php" class="nav-link <?= $isActive('gallery.php') ?>"><i class="fa-solid fa-images"></i> Gallery</a>
  <a href="packages.php" class="nav-link <?= $isActive('packages.php') ?>"><i class="fa-solid fa-box-open"></i> Packages</a>

  <div class="nav-group-label">Travel</div>
  <a href="notifications.php" class="nav-link <?= $isActive('notifications.php') ?>"><i class="fa-solid fa-bell"></i> Notifications</a>
  <a href="bookings.php" class="nav-link <?= $isActive('bookings.php') ?>">
    <i class="fa-solid fa-ticket"></i> My Bookings
    <?php if ($actionCount): ?><span class="nav-count"><?= $actionCount ?></span><?php endif; ?>
  </a>

  <div class="nav-group-label">Account</div>
  <a href="settings.php" class="nav-link <?= $isActive('settings.php') ?>"><i class="fa-solid fa-user-gear"></i> Settings</a>
  <a href="../index.php" class="nav-link"><i class="fa-solid fa-arrow-up-right-from-square"></i> Back to website</a>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <span>Signed in as</span>
      <strong><?= h($user_name) ?></strong>
      <em><?= h($user_email) ?></em>
    </div>
    <a href="logout.php" class="nav-link logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</aside>