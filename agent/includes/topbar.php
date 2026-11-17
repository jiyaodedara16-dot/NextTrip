<header class="topbar">
  <div class="topbar-left">
    <button class="icon-btn menu-toggle" id="menuToggle" type="button" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
    <div class="topbar-title">
      <h2><?= h($page_title ?? 'Dashboard') ?></h2>
      <?php if (!empty($page_subtitle)): ?><p><?= h($page_subtitle) ?></p><?php endif; ?>
    </div>
  </div>
  <div class="topbar-right">
    <a class="icon-btn bell" href="notifications.php" aria-label="Notifications">
      <i class="fa-regular fa-bell"></i>
      <?php if ($unreadCount): ?><span class="bell-dot"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <span class="today-chip"><i class="fa-regular fa-calendar"></i> <?= date('D, d M Y') ?></span>
    <div class="admin-chip">
      <div class="avatar"><?= h(initials($agent_name)) ?></div>
      <div class="who"><strong><?= h($agent_name) ?></strong><span><?= h($agent_email) ?></span></div>
    </div>
  </div>
</header>
