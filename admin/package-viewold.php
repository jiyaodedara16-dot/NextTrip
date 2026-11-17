<?php
$page_title = 'Package Details';
$page_subtitle = 'Read-only view of the public package page';
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$p = row("SELECT p.*, ag.full_name AS agent_name, ag.phone AS agent_phone, ag.city AS agent_city, a.email AS agent_email
          FROM packages p
          LEFT JOIN agents ag ON ag.agent_id = p.agent_id
          LEFT JOIN accounts a ON a.account_id = ag.account_id
          WHERE p.package_id = $id");
if (!$p) {
    header('Location: packages.php?msg=' . urlencode('That package no longer exists.') . '&type=error');
    exit;
}

$places = rows("SELECT d.destination_id, d.destination_name, d.city, d.state,
                       (SELECT di.image_path FROM destination_images di WHERE di.destination_id = d.destination_id
                          ORDER BY di.is_primary DESC, di.display_order LIMIT 1) AS img
                FROM package_destinations pd JOIN destinations d ON d.destination_id = pd.destination_id
                WHERE pd.package_id = $id ORDER BY pd.display_order");
$categories = rows("SELECT c.category_name FROM package_categories pc JOIN categories c ON c.category_id = pc.category_id WHERE pc.package_id = $id");
$itinerary  = rows("SELECT day_number, title, description FROM package_itinerary WHERE package_id = $id ORDER BY day_number, display_order");
$inclusions = rows("SELECT item_name FROM package_inclusions WHERE package_id = $id AND status = 'Active' ORDER BY display_order");
$exclusions = rows("SELECT item_name FROM package_exclusions WHERE package_id = $id AND status = 'Active' ORDER BY display_order");
$seasons    = rows("SELECT s.season_name, s.start_month, s.end_month, ps.is_best_time
                    FROM package_seasons ps JOIN seasons s ON s.season_id = ps.season_id
                    WHERE ps.package_id = $id ORDER BY ps.is_best_time DESC, s.start_month");
$reviews    = rows("SELECT pr.rating, pr.review_title, pr.review_text, pr.review_date, CONCAT(u.first_name, ' ', u.last_name) AS reviewer
                    FROM package_reviews pr JOIN users u ON u.user_id = pr.user_id
                    WHERE pr.package_id = $id AND pr.status = 'Approved' ORDER BY pr.review_date DESC");
$cover = rows("SELECT image_path, alt_text FROM package_images WHERE package_id = $id AND status = 'Active'
               ORDER BY (image_type = 'Cover') DESC, display_order");


/* Slider: package images first, then the photos of the destinations it covers, then a placeholder.
   Only files that really exist on disk are used, same as the public package page. */
$slides = [];
foreach ($cover as $c) {
    if ($img = existing_image($c['image_path'])) $slides[] = ['image_path' => $img, 'alt_text' => $c['alt_text'] ?? $p['package_name']];
}
if (!$slides) {
    foreach ($places as $pl) {
        if ($img = existing_image($pl['img'])) $slides[] = ['image_path' => $img, 'alt_text' => $pl['destination_name']];
    }
}
if (!$slides) $slides = [['image_path' => 'assets/images/destinations/placeholder.jpg', 'alt_text' => $p['package_name']]];
$avg      = $reviews ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : null;
$bookings = (int) scalar("SELECT COUNT(*) FROM bookings WHERE package_id = $id");
$revenue  = (float) scalar("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE package_id = $id AND booking_status IN ('Accepted', 'Confirmed')");

$bestSeasons = array_filter($seasons, fn($s) => (int) $s['is_best_time'] === 1);
$bestText = $bestSeasons
    ? implode(', ', array_map(fn($s) => date('M', mktime(0, 0, 0, (int) $s['start_month'], 1)) . ' – ' . date('M', mktime(0, 0, 0, (int) $s['end_month'], 1)), $bestSeasons))
    : 'Any time';

$duration = (int) $p['duration_days'] . ' Days / ' . (int) $p['duration_nights'] . ' Nights';

include __DIR__ . '/includes/page-start.php';
$extra_css = ['assets/css/package-view.css'];
?>


<a class="dv-back" href="packages.php"><i class="fa-solid fa-arrow-left"></i> Back to packages</a>

<header class="dv-head">
  <div>
    <div class="dv-tags">
      <?php foreach ($categories as $c): ?><span class="dv-tag"><?= h($c['category_name']) ?></span><?php endforeach; ?>
      <span class="dv-tag"><i class="fa-regular fa-calendar"></i> <?= h($duration) ?></span>
      <span class="dv-tag dv-tag-live"><span class="dv-dot"></span><?= h($p['status']) ?></span>
    </div>
    <h1 class="dv-title"><?= h($p['package_name']) ?></h1>
    <p class="dv-location"><i class="fa-solid fa-user-tie"></i>
      By <?= h($p['agent_name'] ?: 'Unknown agent') ?><?= $p['agent_city'] ? ' · ' . h($p['agent_city']) : '' ?>
      <?php if ($avg): ?> · <span class="dv-stars">★ <?= $avg ?></span> <span class="muted">(<?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?>)</span><?php endif; ?>
    </p>
  </div>
  <div class="dv-head-side">
    <div class="dv-price"><span>Price per person</span><strong><?= money($p['price_per_person']) ?></strong></div>
  </div>
</header>

<section class="dv-slider" id="dvSlider">
  <div class="dv-slides">
    <?php foreach ($slides as $i => $img): ?>
      <div class="dv-slide <?= $i === 0 ? 'is-active' : '' ?>">
        <img src="<?= h(asset_url($img['image_path'])) ?>" alt="<?= h($img['alt_text'] ?? $p['package_name']) ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($slides) > 1): ?>
    <button type="button" class="dv-nav dv-prev" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>
    <button type="button" class="dv-nav dv-next" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="dv-dots">
      <?php foreach ($slides as $i => $img): ?><button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" aria-label="Image <?= $i + 1 ?>"></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<div class="dv-layout">
  <div class="dv-main">

    <section class="dv-card">
      <h3 class="dv-h">About this package</h3>
      <p><?= nl2br(h($p['description'] ?: 'No description has been added yet.')) ?></p>
    </section>

    <section class="dv-info3">
      <div class="dv-info"><i class="fa-regular fa-calendar"></i><span>Duration</span><strong><?= h($duration) ?></strong></div>
      <div class="dv-info"><i class="fa-regular fa-sun"></i><span>Best time</span><strong><?= h($bestText) ?></strong></div>
      <div class="dv-info"><i class="fa-solid fa-indian-rupee-sign"></i><span>Per person</span><strong><?= money($p['price_per_person']) ?></strong></div>
    </section>

    <section class="dv-card">
      <h3 class="dv-h">Places covered</h3>
      <?php if (!$places): ?><p class="dv-empty">No destinations linked to this package.</p><?php endif; ?>
      <div class="dv-places">
        <?php foreach ($places as $pl): ?>
          <div class="dv-place">
            <img src="<?= h(asset_url(existing_image($pl['img']))) ?>" alt="<?= h($pl['destination_name']) ?>">
            <div><strong><?= h($pl['destination_name']) ?></strong><span><?= h($pl['city']) ?>, <?= h($pl['state']) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="dv-card">
      <h3 class="dv-h">Itinerary</h3>
      <?php if (!$itinerary): ?><p class="dv-empty">No itinerary added yet.</p><?php endif; ?>
      <div class="dv-itin">
        <?php foreach ($itinerary as $it): ?>
          <div class="dv-day">
            <div class="dv-day-badge">Day <?= (int) $it['day_number'] ?></div>
            <div><strong><?= h($it['title']) ?></strong><p><?= h($it['description']) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="dv-card">
      <h3 class="dv-h">What's included & excluded</h3>
      <div class="dv-inc">
        <div>
          <h4 class="dv-inc-title ok"><i class="fa-solid fa-circle-check"></i> Included</h4>
          <ul>
            <?php foreach ($inclusions as $i): ?><li><i class="fa-solid fa-check"></i><?= h($i['item_name']) ?></li><?php endforeach; ?>
            <?php if (!$inclusions): ?><li class="dv-empty">None listed</li><?php endif; ?>
          </ul>
        </div>
        <div>
          <h4 class="dv-inc-title no"><i class="fa-solid fa-circle-xmark"></i> Excluded</h4>
          <ul>
            <?php foreach ($exclusions as $e): ?><li><i class="fa-solid fa-xmark"></i><?= h($e['item_name']) ?></li><?php endforeach; ?>
            <?php if (!$exclusions): ?><li class="dv-empty">None listed</li><?php endif; ?>
          </ul>
        </div>
      </div>
    </section>

    <section class="dv-card">
      <div class="dv-reviews-head">
        <h3 class="dv-h" style="margin:0">Traveller reviews</h3>
        <?php if ($avg): ?><div class="dv-avg"><strong><?= $avg ?></strong><span>★ · <?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?></span></div><?php endif; ?>
      </div>
      <?php if (!$reviews): ?><p class="dv-empty" style="margin-top:14px">No approved reviews yet.</p><?php endif; ?>
      <?php foreach ($reviews as $rv): ?>
        <div class="dv-review">
          <div class="dv-review-top">
            <div class="mini-avatar"><?= h(initials($rv['reviewer'])) ?></div>
            <div><strong><?= h($rv['reviewer']) ?></strong><span><?= fmt_date($rv['review_date']) ?></span></div>
            <span class="dv-stars"><?= str_repeat('★', (int) $rv['rating']) ?></span>
          </div>
          <?php if ($rv['review_title']): ?><h4><?= h($rv['review_title']) ?></h4><?php endif; ?>
          <p><?= h($rv['review_text']) ?></p>
        </div>
      <?php endforeach; ?>
    </section>
  </div>

  <aside class="dv-side">
    <section class="dv-card">
      <h3 class="dv-h">Pricing & capacity</h3>
      <dl class="dv-kv">
        <div><span>Price per person</span><strong><?= money($p['price_per_person']) ?></strong></div>
        <div><span>Max adults</span><strong><?= h($p['max_adults'] ?: '—') ?></strong></div>
        <div><span>Max children</span><strong><?= h($p['max_children'] ?: '—') ?></strong></div>
        <div><span>Status</span><?= badge($p['status']) ?></div>
      </dl>
    </section>

    <section class="dv-card">
      <h3 class="dv-h">Tour operator</h3>
      <div class="dv-operator">
        <div class="dv-op-avatar"><?= h(initials($p['agent_name'] ?: '?')) ?></div>
        <div><strong><?= h($p['agent_name'] ?: 'Unknown agent') ?></strong><span><?= h($p['agent_city'] ?: '') ?></span></div>
      </div>
      <ul class="dv-contact">
        <li><i class="fa-solid fa-phone"></i><?= h($p['agent_phone'] ?: '—') ?></li>
        <li><i class="fa-solid fa-envelope"></i><?= h($p['agent_email'] ?: '—') ?></li>
      </ul>
    </section>

    <section class="dv-card">
      <h3 class="dv-h">Performance</h3>
      <div class="dv-metrics">
        <div><b><?= $bookings ?></b><span>Bookings</span></div>
        <div><b><?= money($revenue) ?></b><span>Revenue</span></div>
        <div><b><?= $avg ?: '—' ?></b><span>Avg. rating</span></div>
        <div><b><?= count($reviews) ?></b><span>Reviews</span></div>
      </div>
    </section>

    <section class="dv-card dv-meta-card">
      <h3 class="dv-h">Record</h3>
      <div><span>Created</span><strong><?= fmt_date($p['created_at']) ?></strong></div>
      <div><span>Last updated</span><strong><?= fmt_date($p['updated_at']) ?></strong></div>
      <div><span>Season guide</span><strong><?= h($bestText) ?></strong></div>
    </section>
  </aside>
</div>

<script>
(function () {
  const slider = document.getElementById('dvSlider');
  if (slider) {
    const slides = slider.querySelectorAll('.dv-slide');
    const dots = slider.querySelectorAll('.dv-dots button');
    let i = 0;
    const go = (n) => {
      slides[i].classList.remove('is-active'); dots[i]?.classList.remove('is-active');
      i = (n + slides.length) % slides.length;
      slides[i].classList.add('is-active'); dots[i]?.classList.add('is-active');
    };
    slider.querySelector('.dv-prev')?.addEventListener('click', () => go(i - 1));
    slider.querySelector('.dv-next')?.addEventListener('click', () => go(i + 1));
    dots.forEach((dot, n) => dot.addEventListener('click', () => go(n)));
  }
})();
</script>

<?php include __DIR__ . '/includes/page-end.php'; ?>