<?php
$page_title = 'Favourite Packages';
$page_subtitle = 'Packages you have saved. Open one to book it.';
require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'remove') {
    user_ajax_guard();
    $id = require_post_id();
    q("DELETE FROM package_favorites WHERE user_id = $user_id AND package_id = $id");
    json_out(['success' => true, 'message' => 'Removed from your saved packages.']);
}

$list = rows("SELECT p.package_id, p.package_name, p.duration_days, p.duration_nights, p.price_per_person, pf.created_at AS saved_at, ag.full_name AS agent_name,
                (SELECT pi.image_path FROM package_images pi WHERE pi.package_id = p.package_id ORDER BY (pi.image_type = 'Cover') DESC, pi.display_order LIMIT 1) AS img
              FROM package_favorites pf
              JOIN packages p ON p.package_id = pf.package_id
              LEFT JOIN agents ag ON ag.agent_id = p.agent_id
              WHERE pf.user_id = $user_id AND p.status = 'Active'
              ORDER BY pf.created_at DESC");

// Use the saved package's cover image only if the file really exists on disk;
// otherwise use a destination photo from one of its destinations, then the placeholder.
if (!function_exists('fav_pick_image')) {
    function fav_pick_image(?string $path, int $pkgId): string {
        $placeholder = 'assets/images/destinations/placeholder.jpg';
        $root = dirname(__DIR__);
        $usable = function (?string $p) use ($root): ?string {
            if (empty($p)) return null;
            if (preg_match('#^https?://#i', $p)) return $p;
            $rel = ltrim($p, '/');
            return is_file($root . '/' . $rel) ? $rel : null;
        };
        if ($found = $usable($path)) return $found;
        $dest = rows("SELECT di.image_path FROM package_destinations pd
                      JOIN destination_images di ON di.destination_id = pd.destination_id AND di.status = 'Active'
                      WHERE pd.package_id = $pkgId
                      ORDER BY pd.display_order, di.is_primary DESC, di.display_order LIMIT 1");
        if (!empty($dest) && ($found = $usable($dest[0]['image_path']))) return $found;
        return $placeholder;
    }
}
foreach ($list as &$fav) {
    $fav['img'] = fav_pick_image($fav['img'] ?? null, (int) $fav['package_id']);
}
unset($fav);
include __DIR__ . '/includes/page-start.php';
?>
<section class="card anim" style="--d:0">
  <div class="card-head">
    <div><h3><?= count($list) ?> saved package<?= count($list) === 1 ? '' : 's' ?></h3><p>Compare prices and book when you're ready.</p></div>
  </div>
</section>

<?php if (!$list): ?>
  <section class="card" style="margin-top:22px"><div class="empty">
    <i class="fa-regular fa-heart"></i><h4>No saved packages yet</h4>
    <p>Save packages you like and they will appear here.</p>
    <a class="btn btn-primary" href="../packages.php" style="margin-top:12px"><i class="fa-solid fa-box-open"></i> Browse packages</a>
  </div></section>
<?php else: ?>
<section class="ud-grid" style="margin-top:22px">
  <?php foreach ($list as $i => $p): ?>
  <article class="ud-card" data-card style="--i: <?= $i ?>">
    <div class="ud-media">
      <img src="<?= h(asset_url($p['img'], 'assets/images/destinations/placeholder.jpg')) ?>" alt="<?= h($p['package_name']) ?>" loading="lazy">
      <span class="ud-badge-top"><?= (int) $p['duration_days'] ?>D / <?= (int) $p['duration_nights'] ?>N</span>
      <button class="ud-heart" type="button" title="Remove from saved" aria-label="Remove from saved"
              data-unfav data-url="packages.php?ajax=remove" data-id="<?= (int) $p['package_id'] ?>"><i class="fa-solid fa-heart"></i></button>
    </div>
    <div class="ud-body">
      <h3><?= h($p['package_name']) ?></h3>
      <div class="ud-meta">
        <span><i class="fa-solid fa-user-tie"></i><?= h($p['agent_name'] ?: 'Travel partner') ?></span>
        <span><i class="fa-regular fa-clock"></i>Saved <?= time_ago($p['saved_at']) ?></span>
      </div>
    </div>
    <div class="ud-foot">
      <div class="ud-price"><?= money($p['price_per_person']) ?><small> / person</small></div>
      <a class="ud-link" href="../package-details.php?id=<?= (int) $p['package_id'] ?>">View <i class="fa-solid fa-arrow-right"></i></a>
    </div>
  </article>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/page-end.php'; ?>
