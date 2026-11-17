<?php
  if (session_status() === PHP_SESSION_NONE) session_start();

  $currentPage = basename($_SERVER['PHP_SELF']);
  function navActive($page, $current) {
    return ($page === $current) ? 'active' : '';
  }

  $navIsLoggedIn = !empty($_SESSION['user_id']);
  $navUserName   = trim($_SESSION['user_name'] ?? '');
  $navParts      = $navUserName !== '' ? preg_split('/\s+/', $navUserName) : [];
  $navFirst      = $navParts[0] ?? 'Traveller';
  $navInitials   = mb_strtoupper(mb_substr($navParts[0] ?? 'T', 0, 1)) . mb_strtoupper(mb_substr($navParts[1] ?? '', 0, 1));
  $navToPay      = 0;
  if ($navIsLoggedIn && isset($conn)) {
      $res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE user_id = " . (int) $_SESSION['user_id'] . " AND booking_status = 'Accepted'");
      if ($res) $navToPay = (int) (mysqli_fetch_assoc($res)['c'] ?? 0);
  }
?>
<script>window.NT_LOGGED_IN = <?= $navIsLoggedIn ? 'true' : 'false' ?>;</script>
<script src="assets/js/login-gate.js"></script>
<nav class="navbar">
  <div class="navbar-container">
    <a href="index.php" class="navbar-brand">
      <span class="navbar-brand-text"><span class="brand-dark">Next</span><span class="brand-light">Trip</span></span>
    </a>

    <div class="navbar-menu">
      <a href="index.php" class="navbar-link <?= navActive('index.php', $currentPage) ?>">Home</a>
      <a href="destinations.php" data-login-required class="navbar-link <?= navActive('destinations.php', $currentPage) ?>">Destinations</a>
      <a href="packages.php" data-login-required class="navbar-link <?= navActive('packages.php', $currentPage) ?>">Packages</a>
      <a href="gallery.php" class="navbar-link <?= navActive('gallery.php', $currentPage) ?>">Gallery</a>
      <a href="about.php" class="navbar-link <?= navActive('about.php', $currentPage) ?>">About</a>
      <a href="contact.php" class="navbar-link <?= navActive('contact.php', $currentPage) ?>">Contact</a>
    </div>

    <div class="navbar-actions">
      <?php if ($navIsLoggedIn): ?>
      
        <details class="nav-account">
          <summary class="nav-account-trigger" aria-label="Account menu">
            <span class="nav-avatar"><?= htmlspecialchars($navInitials) ?></span>
            <span class="nav-account-name"><?= htmlspecialchars($navFirst) ?></span>
            <i class="bi bi-chevron-down nav-chevron"></i>
          </summary>
          <div class="nav-account-menu">
            <div class="nav-account-head">
              <span class="nav-avatar lg"><?= htmlspecialchars($navInitials) ?></span>
              <div>
                <strong><?= htmlspecialchars($navUserName ?: 'Traveller') ?></strong>
                <small>Traveller account</small>
              </div>
            </div>
            <a href="user/index.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <a href="user/destinations.php"><i class="bi bi-heart-fill"></i> Favourite places</a>
            <a href="user/gallery.php"><i class="bi bi-images"></i> Liked photos</a>
            <a href="user/packages.php"><i class="bi bi-box-seam"></i> Saved packages</a>
            <a href="user/bookings.php"><i class="bi bi-ticket-perforated"></i> My bookings
              <?php if ($navToPay): ?><span class="nav-mini-badge"><?= $navToPay ?></span><?php endif; ?></a>
            <a href="user/settings.php"><i class="bi bi-gear"></i> Settings</a>
            <a href="auth/logout.php" class="nav-account-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
          </div>
        </details>
      <?php else: ?>
        <a href="auth/login.php" class="navbar-login-btn">Login / Register</a>
      <?php endif; ?>
    </div>

    <button class="navbar-toggle" aria-label="Toggle menu" aria-expanded="false">
      <span class="navbar-toggle-bar"></span>
      <span class="navbar-toggle-bar"></span>
      <span class="navbar-toggle-bar"></span>
    </button>
  </div>
</nav>

<div class="navbar-backdrop"></div>

<div class="navbar-mobile-menu">
  <div class="navbar-mobile-header">
    <span class="navbar-brand-text"><span class="brand-dark">Next</span><span class="brand-light">Trip</span></span>
    <button class="navbar-mobile-close" aria-label="Close menu">&times;</button>
  </div>

  <div class="navbar-mobile-links">
    <a href="index.php" class="navbar-mobile-link <?= navActive('index.php', $currentPage) ?>">Home</a>
    <a href="destinations.php" class="navbar-mobile-link <?= navActive('destinations.php', $currentPage) ?>">Destinations</a>
    <a href="packages.php" class="navbar-mobile-link <?= navActive('packages.php', $currentPage) ?>">Packages</a>
    <a href="gallery.php" class="navbar-mobile-link <?= navActive('gallery.php', $currentPage) ?>">Gallery</a>
    <a href="about.php" class="navbar-mobile-link <?= navActive('about.php', $currentPage) ?>">About</a>
    <a href="contact.php" class="navbar-mobile-link <?= navActive('contact.php', $currentPage) ?>">Contact</a>
  </div>

  <?php if ($navIsLoggedIn): ?>
    <div class="nav-mobile-account">
      <a href="user/destinations.php"><i class="bi bi-heart-fill"></i> Favourite places</a>
      <a href="user/gallery.php"><i class="bi bi-images"></i> Liked photos</a>
      <a href="user/packages.php"><i class="bi bi-box-seam"></i> Saved packages</a>
      <a href="user/bookings.php"><i class="bi bi-ticket-perforated"></i> My bookings<?php if ($navToPay): ?> <span class="nav-mini-badge"><?= $navToPay ?></span><?php endif; ?></a>
      <a href="user/settings.php"><i class="bi bi-gear"></i> Settings</a>
      <a href="auth/logout.php" class="nav-account-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  <?php else: ?>
    <a href="auth/login.php" class="navbar-mobile-login-btn">Login / Register</a>
  <?php endif; ?>
</div>