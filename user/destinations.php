<?php
$page_title = 'Favourite Destinations';
$page_subtitle = 'Places you have liked. Tap the heart to remove one.';
require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'remove') {
    user_ajax_guard();
    $id = require_post_id();
    q("DELETE FROM favorites WHERE user_id = $user_id AND destination_id = $id");
    json_out(['success' => true, 'message' => 'Removed from your favourites.']);
}

$search = trim($_GET['q'] ?? '');
$list = rows("SELECT d.destination_id, d.destination_name, d.city, d.state, d.entry_fee, f.created_at AS saved_at,
                (SELECT di.image_path FROM destination_images di WHERE di.destination_id = d.destination_id ORDER BY di.is_primary DESC, di.display_order LIMIT 1) AS img,
                (SELECT c.category_name FROM destination_categories dc JOIN categories c ON c.category_id = dc.category_id WHERE dc.destination_id = d.destination_id LIMIT 1) AS category
              FROM favorites f JOIN destinations d ON d.destination_id = f.destination_id
              WHERE f.user_id = $user_id AND d.status = 'Active'" . like_sql('d.destination_name', $search) . "
              ORDER BY f.created_at DESC");

include __DIR__ . '/includes/page-start.php';
?>
<section class="card anim" style="--d:0">
  <div class="card-head">
    <div><h3><?= count($list) ?> saved destination<?= count($list) === 1 ? '' : 's' ?></h3><p>Revisit any place and plan your next trip.</p></div>
    <form class="toolbar" method="GET" data-autosubmit>
      <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= h($search) ?>" placeholder="Search your favourites"></div>
    </form>
  </div>
</section>

<?php if (!$list): ?>
  <section class="card" style="margin-top:22px"><div class="empty">
    <i class="fa-regular fa-heart"></i><h4>No favourite destinations yet</h4>
    <p>Tap the heart on any destination to save it here.</p>
    <a class="btn btn-primary" href="../destinations.php" style="margin-top:12px"><i class="fa-solid fa-compass"></i> Explore destinations</a>
  </div></section>
<?php else: ?>
<section class="ud-grid" style="margin-top:22px">
  <?php foreach ($list as $i => $d): ?>
  <article class="ud-card" data-card style="--i: <?= $i ?>">
    <div class="ud-media">
      <img src="<?= h(asset_url($d['img'])) ?>" alt="<?= h($d['destination_name']) ?>" loading="lazy">
      <?php if ($d['category']): ?><span class="ud-badge-top"><?= h($d['category']) ?></span><?php endif; ?>
      <button class="ud-heart" type="button" title="Remove from favourites" aria-label="Remove from favourites"
              data-unfav data-url="destinations.php?ajax=remove" data-id="<?= (int) $d['destination_id'] ?>"><i class="fa-solid fa-heart"></i></button>
    </div>
    <div class="ud-body">
      <h3><?= h($d['destination_name']) ?></h3>
      <div class="ud-meta">
        <span><i class="fa-solid fa-location-dot"></i><?= h($d['city']) ?>, <?= h($d['state']) ?></span>
        <span><i class="fa-solid fa-ticket"></i><?= h(entry_label($d['entry_fee'])) ?></span>
      </div>
      <div class="ud-meta"><span><i class="fa-regular fa-clock"></i>Saved <?= time_ago($d['saved_at']) ?></span></div>
    </div>
    <div class="ud-foot">
      <span class="muted" style="font-size:12.5px">Destination</span>
      <a class="ud-link" href="../place-details.php?id=<?= (int) $d['destination_id'] ?>">View <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/page-end.php'; ?>
