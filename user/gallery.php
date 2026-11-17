<?php
$page_title = 'Liked Gallery';
$page_subtitle = 'Photos you have liked from the gallery';
require_once __DIR__ . '/includes/auth.php';

/* Remove a like from this page */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'remove') {
    user_ajax_guard();
    $id = require_post_id();
    q("DELETE FROM gallery_favorites WHERE user_id = $user_id AND gallery_photo_id = $id");
    json_out(['success' => true, 'message' => 'Photo removed from your likes.']);
}

/* All photos this traveller has liked (matches the rows saved by ajax/gallery_favorite_toggle.php) */
$list = rows("SELECT gp.gallery_photo_id, gp.image_path, gp.caption, d.destination_name, gf.created_at AS liked_at
              FROM gallery_favorites gf
              JOIN gallery_photos gp ON gp.gallery_photo_id = gf.gallery_photo_id
              LEFT JOIN destinations d ON d.destination_id = gp.destination_id
              WHERE gf.user_id = $user_id
              ORDER BY gf.created_at DESC");

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<section class="card anim" style="--d:0">
  <div class="card-head">
    <div>
      <h3><?= count($list) ?> liked photo<?= count($list) === 1 ? '' : 's' ?></h3>
      <p>Click a photo to view it larger.</p>
    </div>
  </div>
</section>

<?php if (!$list): ?>
  <section class="card" style="margin-top:22px"><div class="empty">
    <i class="fa-regular fa-images"></i>
    <h4>No liked photos yet</h4>
    <p>Tap the heart on a gallery photo and it will appear here.</p>
    <a class="btn btn-primary" href="../gallery.php" style="margin-top:12px"><i class="fa-solid fa-images"></i> Open gallery</a>
  </div></section>
<?php else: ?>
<section class="ud-gallery" style="margin-top:22px">
  <?php foreach ($list as $i => $g):
    $src   = asset_url($g['image_path']);
    $label = $g['caption'] ?: ($g['destination_name'] ?: 'Photo');
  ?>
  <figure class="ud-photo" data-card style="--i: <?= $i ?>; margin:0">
    <img src="<?= h($src) ?>" alt="<?= h($label) ?>" loading="lazy"
         data-lightbox data-src="<?= h($src) ?>" data-title="<?= h($label) ?>">
    <button class="ud-heart" type="button" title="Remove like" aria-label="Remove like"
            data-unfav data-url="gallery.php?ajax=remove" data-id="<?= (int) $g['gallery_photo_id'] ?>">
      <i class="fa-solid fa-heart"></i>
    </button>
    <figcaption class="ud-photo-cap"><?= h($label) ?><span><?= h($g['destination_name'] ?: 'NextTrip') ?></span></figcaption>
  </figure>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<?php include __DIR__ . '/includes/page-end.php'; ?>
