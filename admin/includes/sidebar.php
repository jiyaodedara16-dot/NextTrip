<?php
$current = basename($_SERVER['PHP_SELF']);
$nav = function ($file) use ($current) { return $current === $file ? 'active' : ''; };
$pendingAgents = (int) scalar("SELECT COUNT(*) FROM agents WHERE status = 'Pending'");
$pendingDest   = (int) scalar("SELECT COUNT(*) FROM destination_requests WHERE status = 'Pending'");
?>
<aside class="sidebar" id="adminSidebar" data-lenis-prevent>
  <div class="sidebar-brand">
    <div class="logo-mark"><i class="fa-solid fa-compass"></i></div>
    <div class="brand-text">NextTrip<span>Admin Console</span></div>
  </div>

  <div class="nav-group-label">Overview</div>
  <a href="dashboard.php" class="nav-link <?= $nav('dashboard.php') ?>"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>

  <div class="nav-group-label">People</div>
  <a href="users.php" class="nav-link <?= $nav('users.php') ?>"><i class="fa-solid fa-users"></i> Users</a>
  <a href="agents.php" class="nav-link <?= $nav('agents.php') ?>"><i class="fa-solid fa-user-tie"></i> Agents</a>
  <a href="agent-requests.php" class="nav-link <?= $nav('agent-requests.php') ?>">
    <i class="fa-solid fa-file-signature"></i> Agent Requests
    <?php if ($pendingAgents): ?><span class="nav-count"><?= $pendingAgents ?></span><?php endif; ?>
  </a>

  <div class="nav-group-label">Catalog</div>
  <a href="destinations.php" class="nav-link <?= $nav('destinations.php') ?> <?= $nav('destination-view.php') ?>"><i class="fa-solid fa-map-location-dot"></i> Destinations</a>
  <a href="destination-requests.php" class="nav-link <?= $nav('destination-requests.php') ?>">
    <i class="fa-solid fa-inbox"></i> Destination Requests
    <?php if ($pendingDest): ?><span class="nav-count"><?= $pendingDest ?></span><?php endif; ?>
  </a>
  <a href="packages.php" class="nav-link <?= $nav('packages.php') ?>"><i class="fa-solid fa-box-open"></i> Packages</a>
  <a href="coupons.php" class="nav-link <?= $nav('coupons.php') ?>"><i class="fa-solid fa-ticket"></i> Coupons</a>
    
    <div class="nav-group-label">Support</div>
  <a href="enquiries.php" class="nav-link <?= $nav('enquiries.php') ?>">
    <i class="fa-solid fa-envelope-open-text"></i> Contact Enquiries
    <?php if ($newEnquiries): ?><span class="nav-count"><?= $newEnquiries ?></span><?php endif; ?>
  </a>

  <div class="nav-group-label">System</div>
  <a href="settings.php" class="nav-link <?= $nav('settings.php') ?>"><i class="fa-solid fa-gear"></i> Website Settings</a>

  <div class="nav-group-label">Operations</div>
  <a href="bookings.php" class="nav-link <?= $nav('bookings.php') ?>"><i class="fa-solid fa-ticket"></i> User Bookings</a>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-link logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</aside>
