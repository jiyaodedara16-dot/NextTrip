<?php
$page_title = 'Destination Details';
$page_subtitle = 'Read-only view of the public destination page';
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$d = row("SELECT d.*, COALESCE((SELECT ag.full_name FROM agents ag WHERE ag.account_id = d.created_by LIMIT 1), 'NextTrip Admin') AS agent_name
          FROM destinations d WHERE d.destination_id = $id");
if (!$d) {
    header('Location: destinations.php?msg=' . urlencode('That destination no longer exists.') . '&type=error');
    exit;
}

$images = rows("SELECT * FROM destination_images WHERE destination_id = $id AND status = 'Active' ORDER BY is_primary DESC, display_order ASC");
$sections = rows("SELECT * FROM destination_sections WHERE destination_id = $id AND status = 'Active' ORDER BY display_order");
$seasons = rows("SELECT s.season_name, s.start_month, s.end_month, ds.is_best_time, ds.description
                 FROM destination_seasons ds JOIN seasons s ON s.season_id = ds.season_id
                 WHERE ds.destination_id = $id ORDER BY ds.is_best_time DESC, s.start_month");
$hours = rows("SELECT * FROM destination_opening_hours WHERE destination_id = $id ORDER BY day_of_week");
$tips = rows("SELECT tip_text FROM destination_tips WHERE destination_id = $id AND status = 'Active' ORDER BY display_order");
$activities = rows("SELECT a.activity_name, COALESCE(da.description, a.description) AS description
                    FROM destination_activities da JOIN activities a ON a.activity_id = da.activity_id
                    WHERE da.destination_id = $id ORDER BY da.display_order");
$hotels = rows("SELECT ht.hotel_name, ht.address, ht.price_per_night, ht.rating, ht.image_path
                FROM destination_hotels dh JOIN hotels ht ON ht.hotel_id = dh.hotel_id WHERE dh.destination_id = $id");
$restaurants = rows("SELECT r.restaurant_name, r.cuisine, r.rating, r.image_path
                     FROM destination_restaurants dr JOIN restaurants r ON r.restaurant_id = dr.restaurant_id WHERE dr.destination_id = $id");
$categories = rows("SELECT c.category_name FROM destination_categories dc JOIN categories c ON c.category_id = dc.category_id WHERE dc.destination_id = $id");

$hoursByDay = [];
foreach ($hours as $hr) $hoursByDay[(int) $hr['day_of_week']] = $hr;
$todayN = (int) date('N');
// Use today's row if one exists, otherwise the generic row (day_of_week = 0) that applies to every day
$todayHr = $hoursByDay[$todayN] ?? ($hoursByDay[0] ?? null);

$sectionsByType = ['About' => null, 'History' => null, 'Importance' => null];
foreach ($sections as $sec) if (!$sectionsByType[$sec['section_type']]) $sectionsByType[$sec['section_type']] = $sec;

$bestSeasons = array_filter($seasons, fn($s) => (int) $s['is_best_time'] === 1);
$bestText = $bestSeasons
    ? implode(', ', array_map(fn($s) => date('M', mktime(0, 0, 0, (int) $s['start_month'], 1)) . ' – ' . date('M', mktime(0, 0, 0, (int) $s['end_month'], 1)), $bestSeasons))
    : 'Not set';
$hasCoords = $d['latitude'] !== null && $d['longitude'] !== null;
$entry = entry_label($d['entry_fee']);

$gallery = $images ?: [['image_path' => null, 'alt_text' => $d['destination_name']]];

include __DIR__ . '/includes/page-start.php';
?>

<a class="dv-back" href="destinations.php"><i class="fa-solid fa-arrow-left"></i> Back to destinations</a>

<header class="dv-head">
  <div>
    <div class="dv-tags">
      <?php foreach ($categories as $c): ?><span class="dv-tag"><?= h($c['category_name']) ?></span><?php endforeach; ?>
      <span class="dv-tag dv-tag-live"><span class="dv-dot"></span><?= h($d['status']) ?></span>
    </div>
    <h1 class="dv-title"><?= h($d['destination_name']) ?></h1>
    <p class="dv-location"><i class="fa-solid fa-location-dot"></i>
      <?= h($d['city']) ?><?= $d['district'] ? ', ' . h($d['district']) : '' ?>, <?= h($d['state']) ?>, <?= h($d['country']) ?>
    </p>
  </div>
  <div class="dv-head-side">
    <div class="dv-price"><span>Entry fee</span><strong><?= h($entry) ?></strong></div>
    <div class="dv-lister"><span>Listed by</span><strong><?= h($d['agent_name']) ?></strong></div>
  </div>
</header>

<section class="dv-slider" id="dvSlider">
  <div class="dv-slides">
    <?php foreach ($gallery as $i => $img): ?>
      <div class="dv-slide <?= $i === 0 ? 'is-active' : '' ?>">
        <img src="<?= h(asset_url($img['image_path'])) ?>" alt="<?= h($img['alt_text'] ?: $d['destination_name']) ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($gallery) > 1): ?>
    <button type="button" class="dv-nav dv-prev" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>
    <button type="button" class="dv-nav dv-next" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="dv-dots">
      <?php foreach ($gallery as $i => $img): ?><button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" aria-label="Image <?= $i + 1 ?>"></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<div class="dv-layout">
  <div class="dv-main">

    <section class="dv-card">
      <div class="dv-tabs" role="tablist">
        <?php foreach (['About', 'History', 'Importance'] as $i => $t): ?>
          <button type="button" class="dv-tab <?= $i === 0 ? 'is-active' : '' ?>" data-tab="<?= $t ?>"><?= $t ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($sectionsByType as $type => $sec): ?>
        <div class="dv-panel <?= $type === 'About' ? 'is-active' : '' ?>" data-panel="<?= $type ?>">
          <?php if ($sec): ?>
            <h3><?= h($sec['title']) ?></h3>
            <p><?= nl2br(h($sec['content'])) ?></p>
          <?php else: ?>
            <p class="dv-empty">No <?= strtolower($type) ?> information has been added yet.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="dv-info3">
      <div class="dv-info">
        <i class="fa-regular fa-calendar"></i>
        <span>Best time</span>
        <strong><?= h($bestText) ?></strong>
      </div>
      <?php
      $hr = $todayHr;
      if (!$hr) {
          $hoursText = 'Not available';
      } elseif ((int) $hr['is_closed'] === 1) {
          $hoursText = 'Closed';
      } elseif ($hr['open_time'] === '00:00:00' && $hr['close_time'] >= '23:59:00') {
          $hoursText = 'Open 24 hours';
      } else {
          $hoursText = date('g:i A', strtotime($hr['open_time'])) . ' – ' . date('g:i A', strtotime($hr['close_time']));
      }
      ?>
      <div class="dv-info">
        <i class="fa-regular fa-clock"></i>
        <span>Opening hours</span>
        <strong><?= h($hoursText) ?></strong>
      </div>
      <div class="dv-info">
        <i class="fa-solid fa-ticket"></i>
        <span>Entry fee</span>
        <strong><?= h($entry) ?></strong>
      </div>
    </section>

    <div class="dv-two">
      <section class="dv-card">
        <h3 class="dv-h">Activities</h3>
        <?php if (!$activities): ?><p class="dv-empty">No activities listed.</p><?php endif; ?>
        <div class="dv-chips">
          <?php foreach ($activities as $a): ?>
            <div class="dv-chip" title="<?= h($a['description']) ?>"><i class="fa-solid fa-location-dot"></i><span><?= h($a['activity_name']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="dv-card">
        <h3 class="dv-h">Travel tips</h3>
        <?php if (!$tips): ?><p class="dv-empty">No tips added yet.</p><?php endif; ?>
        <ul class="dv-tips">
          <?php foreach ($tips as $t): ?>
            <li><i class="fa-regular fa-lightbulb"></i><span><?= h($t['tip_text']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>

    <section class="dv-card">
      <h3 class="dv-h">Nearby hotels</h3>
      <?php if (!$hotels): ?><p class="dv-empty">No hotels linked.</p><?php endif; ?>
      <?php foreach ($hotels as $ht): ?>
        <div class="dv-row">
          <img class="dv-thumb" src="<?= h(asset_url($ht['image_path'], 'assets/images/hotels/hotel-placeholder.jpg')) ?>" alt="">
          <div class="dv-row-body">
            <strong><?= h($ht['hotel_name']) ?></strong>
            <span><?= h($ht['address'] ?: '') ?></span>
            <em>★ <?= h($ht['rating'] ?: '—') ?></em>
          </div>
          <div class="dv-row-end"><strong><?= money($ht['price_per_night']) ?></strong><span>per night</span></div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="dv-card">
      <h3 class="dv-h">Nearby restaurants</h3>
      <?php if (!$restaurants): ?><p class="dv-empty">No restaurants linked.</p><?php endif; ?>
      <?php foreach ($restaurants as $r): ?>
        <div class="dv-row">
          <img class="dv-thumb" src="<?= h(asset_url($r['image_path'], 'assets/images/hotels/hotel-placeholder.jpg')) ?>" alt="">
          <div class="dv-row-body">
            <strong><?= h($r['restaurant_name']) ?></strong>
            <span><?= h($r['cuisine'] ?: '') ?></span>
          </div>
          <div class="dv-row-end"><em>★ <?= h($r['rating'] ?: '—') ?></em></div>
        </div>
      <?php endforeach; ?>
    </section>
  </div>

  <aside class="dv-side">
    <section class="dv-card">
      <h3 class="dv-h">Location</h3>
      <?php if ($hasCoords): ?>
        <iframe class="dv-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
          src="https://maps.google.com/maps?q=<?= h($d['latitude']) ?>,<?= h($d['longitude']) ?>&z=11&output=embed"
          title="Map of <?= h($d['destination_name']) ?>"></iframe>
        <div class="dv-coords">
          <span><?= h($d['latitude']) ?>° N, <?= h($d['longitude']) ?>° E</span>
          <a href="https://www.google.com/maps?q=<?= h($d['latitude']) ?>,<?= h($d['longitude']) ?>" target="_blank" rel="noopener">Open in Maps</a>
        </div>
      <?php else: ?>
        <p class="dv-empty">No coordinates set for this destination.</p>
      <?php endif; ?>
    </section>

    <?php
    $isOpenToday = $todayHr && (int) $todayHr['is_closed'] === 0;
    $isOpenNow   = $isOpenToday && date('H:i:s') >= $todayHr['open_time'] && date('H:i:s') <= $todayHr['close_time'];
    ?>
    <div class="dv-info">
      <i class="fa-regular fa-clock"></i>
      <span>Opening hours</span>
      <?php if ($isOpenToday): ?>
        <strong class="<?= $isOpenNow ? 'dv-open' : 'dv-closed' ?>"><?= $isOpenNow ? 'Open now' : 'Closed now' ?></strong>
        <small><?= h(date('h:i A', strtotime($todayHr['open_time']))) ?> – <?= h(date('h:i A', strtotime($todayHr['close_time']))) ?></small>
      <?php else: ?>
        <strong class="dv-closed">Closed today</strong>
      <?php endif; ?>
    </div>

    <section class="dv-card">
      <h3 class="dv-h">Season guide</h3>
      <?php if (!$seasons): ?><p class="dv-empty">Season details not added.</p><?php endif; ?>
      <?php foreach ($seasons as $s): ?>
        <div class="dv-season">
          <div>
            <strong><?= h($s['season_name']) ?></strong>
            <?php if ($s['is_best_time']): ?><span class="dv-best">Best</span><?php endif; ?>
            <small><?= h($s['description'] ?: '') ?></small>
          </div>
          <span><?= date('M', mktime(0, 0, 0, (int) $s['start_month'], 1)) ?> – <?= date('M', mktime(0, 0, 0, (int) $s['end_month'], 1)) ?></span>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="dv-card dv-meta-card">
      <h3 class="dv-h">Record</h3>
      <div><span>Added</span><strong><?= fmt_date($d['created_at']) ?></strong></div>
      <div><span>Last updated</span><strong><?= fmt_date($d['updated_at']) ?></strong></div>
      <div><span>Listed by</span><strong><?= h($d['agent_name']) ?></strong></div>
      <div><span>Status</span><?= badge($d['status']) ?></div>
    </section>
  </aside>
</div>

<script>
(function () {
  /* Image slider */
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
  /* Tabs */
  document.querySelectorAll('.dv-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.dv-tab').forEach((t) => t.classList.toggle('is-active', t === tab));
      document.querySelectorAll('.dv-panel').forEach((p) => p.classList.toggle('is-active', p.dataset.panel === tab.dataset.tab));
    });
  });
})();
</script>

<?php include __DIR__ . '/includes/page-end.php'; ?>