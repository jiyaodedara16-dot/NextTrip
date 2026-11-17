<header class="topbar">
  <div class="topbar-left">
    <button class="icon-btn menu-toggle" id="menuToggle" type="button" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
    <div class="topbar-title">
      <h2><?= h($page_title ?? 'Dashboard') ?></h2>
      <?php if (!empty($page_subtitle)): ?><p><?= h($page_subtitle) ?></p><?php endif; ?>
    </div>
  </div>
  <div class="topbar-right">
    <span class="today-chip"><i class="fa-regular fa-calendar"></i> <?= date('D, d M Y') ?></span>
    <div class="admin-chip">
      <div class="avatar"><?= h(initials($admin_name)) ?></div>
      <div class="who">
        <strong><?= h($admin_name) ?></strong>
        <span><?= h($admin_email) ?></span>
      </div>
    </div>
  </div>
</header>
