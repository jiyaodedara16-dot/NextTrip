<?php
$page_title = 'Destination Details';
$page_subtitle = 'Your destination page as travellers will see it';
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$d = row("SELECT d.*, (SELECT dr.status FROM destination_requests dr WHERE dr.linked_destination_id = d.destination_id
                        ORDER BY dr.request_id DESC LIMIT 1) AS req_status
          FROM destinations d WHERE d.destination_id = $id AND d.created_by = $agent_acc");
if (!$d) { header('Location: destinations.php?msg=' . urlencode('Destination not found.') . '&type=error'); exit; }

$state    = $d['status'] === 'Active' ? 'Live' : ($d['req_status'] === 'Rejected' ? 'Rejected' : 'Pending');
$images   = rows("SELECT image_path, alt_text FROM destination_images WHERE destination_id = $id AND status = 'Active' ORDER BY is_primary DESC, display_order");
$sections = rows("SELECT section_type, title, content FROM destination_sections WHERE destination_id = $id AND status = 'Active'");
$seasons  = rows("SELECT s.season_name, s.start_month, s.end_month FROM destination_seasons ds JOIN seasons s ON s.season_id = ds.season_id
                  WHERE ds.destination_id = $id AND ds.is_best_time = 1 ORDER BY s.start_month");
$hours    = row("SELECT open_time, close_time, is_closed FROM destination_opening_hours WHERE destination_id = $id ORDER BY day_of_week LIMIT 1");
$tips     = rows("SELECT tip_text FROM destination_tips WHERE destination_id = $id AND status = 'Active' ORDER BY display_order");
$acts     = rows("SELECT a.activity_name, COALESCE(da.description, a.description) AS description FROM destination_activities da
                  JOIN activities a ON a.activity_id = da.activity_id WHERE da.destination_id = $id ORDER BY da.display_order");
$hotels   = rows("SELECT hotel_name, address, price_per_night, rating, image_path FROM hotels WHERE destination_id = $id AND status = 'Active'");
$rests    = rows("SELECT restaurant_name, cuisine, rating, address, image_path FROM restaurants WHERE destination_id = $id AND status = 'Active'");
$gallery  = $images ?: [['image_path' => null, 'alt_text' => $d['destination_name']]];

$hoursText = !$hours ? 'Not set'
    : ((int) $hours['is_closed'] ? 'Closed'
    : date('g:i A', strtotime($hours['open_time'])) . ' – ' . date('g:i A', strtotime($hours['close_time'])));
$bestText = $seasons
    ? implode(', ', array_map(fn($s) => date('M', mktime(0, 0, 0, (int) $s['start_month'], 1)) . ' – ' . date('M', mktime(0, 0, 0, (int) $s['end_month'], 1)), $seasons))
    : 'Any time';
$byType = ['About' => null, 'History' => null, 'Importance' => null];
foreach ($sections as $s) $byType[$s['section_type']] = $s;
$hasCoords = $d['latitude'] !== null && $d['longitude'] !== null;

$page_title = $d['destination_name'];
include __DIR__ . '/includes/page-start.php';
?>
<style>
  .dd { --g1:#0f4a30; --g2:#146339; --g3:#1f8a54; --gs:#e6f5ec; --gb:#cfe9d9; --amb:#f4a627; --amb2:#cf8a17;
        --ink:#1d2a24; --mut:#6b7a72; --line:#e3ebe6; --bg:#f7faf8; font-family: 'Manrope', system-ui, sans-serif; color: var(--ink); }
  .dd * { box-sizing: border-box; }
  .dd h1, .dd h2, .dd h3 { font-family: 'Fraunces', Georgia, serif; margin: 0; }
  .dd p { margin: 0; }
  .dd-back { display: inline-flex; align-items: center; gap: 8px; color: var(--g2); font-weight: 700; font-size: 13.5px; text-decoration: none; }
  .dd-back:hover { text-decoration: underline; }

  /* Hero */
  .dd-hero { position: relative; height: 420px; border-radius: 24px; overflow: hidden; background: #0c2a1d; box-shadow: 0 24px 50px -28px rgba(15,74,48,.7); }
  .dd-hero img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity .8s ease; }
  .dd-hero img.on { opacity: 1; }
  .dd-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(8,30,20,.05) 35%, rgba(8,30,20,.85)); pointer-events: none; }
  .dd-hero-text { position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; padding: 30px 34px; color: #fff; }
  .dd-badge { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; padding: 5px 12px; border-radius: 999px; background: rgba(255,255,255,.16); backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,.3); }
  .dd-badge i { width: 7px; height: 7px; border-radius: 50%; background: #7ee2a8; }
  .dd-badge.pending i { background: var(--amb); }
  .dd-badge.rejected i { background: #ff8c8c; }
  .dd-hero h1 { font-size: 44px; font-weight: 800; line-height: 1.1; margin-top: 12px; text-shadow: 0 2px 16px rgba(0,0,0,.35); }
  .dd-loc { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 18px; font-size: 14.5px; opacity: .95; }
  .dd-loc span { display: inline-flex; align-items: center; gap: 7px; }
  .dd-nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 3; width: 46px; height: 46px; border-radius: 50%; border: 0; background: rgba(255,255,255,.9); color: var(--ink); cursor: pointer; display: grid; place-items: center; box-shadow: 0 8px 22px -8px rgba(0,0,0,.4); transition: transform .2s; }
  .dd-nav:hover { transform: translateY(-50%) scale(1.07); }
  .dd-prev { left: 18px; } .dd-next { right: 18px; }
  .dd-dots { position: absolute; top: 18px; right: 22px; z-index: 3; display: flex; gap: 7px; }
  .dd-dots button { width: 8px; height: 8px; border-radius: 999px; border: 0; padding: 0; background: rgba(255,255,255,.5); cursor: pointer; transition: all .3s; }
  .dd-dots button.on { width: 24px; background: #fff; }

  /* Fact strip */
  .dd-facts { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; margin-top: 18px; }
  .dd-fact { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 8px 22px -16px rgba(16,24,20,.25); }
  .dd-fact-ic { width: 44px; height: 44px; border-radius: 14px; background: var(--gs); color: var(--g3); display: grid; place-items: center; font-size: 17px; flex-shrink: 0; }
  .dd-fact small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--mut); font-weight: 800; }
  .dd-fact strong { display: block; font-size: 15px; margin-top: 2px; }

  /* Layout */
  .dd-grid { display: grid; grid-template-columns: minmax(0,1.65fr) minmax(0,1fr); gap: 22px; margin-top: 22px; align-items: start; }
  .dd-col { display: flex; flex-direction: column; gap: 22px; min-width: 0; }
  .dd-card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px -18px rgba(16,24,20,.2); }
  .dd-card-h { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
  .dd-card-h h3 { font-size: 19px; font-weight: 700; }
  .dd-card-h i { color: var(--g3); font-size: 16px; }

  /* Tabs & text */
  .dd-tabs { display: inline-flex; gap: 4px; padding: 5px; background: var(--bg); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 18px; }
  .dd-tab { border: 0; background: transparent; font: inherit; font-size: 13.5px; font-weight: 700; color: var(--mut); padding: 9px 18px; border-radius: 10px; cursor: pointer; transition: .2s; }
  .dd-tab.on { background: var(--g3); color: #fff; box-shadow: 0 8px 18px -10px rgba(31,138,84,.9); }
  .dd-pane { display: none; animation: dd-fade .4s ease; }
  .dd-pane.on { display: block; }
  .dd-pane h4 { font-family: 'Fraunces', Georgia, serif; font-size: 18px; margin-bottom: 10px; }
  .dd-pane p { font-size: 14.8px; line-height: 1.8; color: #3f5148; white-space: pre-line; }
  .dd-empty { color: var(--mut); font-size: 13.5px; font-style: italic; }
  @keyframes dd-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

  /* Activities & tips */
  .dd-acts { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
  .dd-act { display: flex; gap: 12px; padding: 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--bg); transition: transform .25s, box-shadow .25s; }
  .dd-act:hover { transform: translateY(-2px); box-shadow: 0 14px 26px -18px rgba(16,24,20,.35); }
  .dd-act-ic { width: 38px; height: 38px; border-radius: 12px; background: #fff; color: var(--g3); display: grid; place-items: center; flex-shrink: 0; border: 1px solid var(--line); }
  .dd-act strong { display: block; font-size: 14.5px; }
  .dd-act span { display: block; font-size: 12.8px; color: var(--mut); margin-top: 3px; line-height: 1.5; }
  .dd-tips { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .dd-tips li { display: flex; gap: 12px; padding: 12px 14px; border-radius: 14px; background: #fffaf0; border: 1px solid #f6e4bd; font-size: 14px; line-height: 1.6; }
  .dd-tips i { color: var(--amb2); margin-top: 3px; }

  /* Hotels & restaurants */
  .dd-place { display: grid; grid-template-columns: 96px minmax(0,1fr) auto; gap: 16px; align-items: center; padding: 14px; border: 1px solid var(--line); border-radius: 18px; background: #fff; margin-bottom: 12px; transition: box-shadow .25s, transform .25s; }
  .dd-place:hover { box-shadow: 0 16px 30px -20px rgba(16,24,20,.35); transform: translateY(-2px); }
  .dd-place:last-child { margin-bottom: 0; }
  .dd-place img { width: 96px; height: 80px; border-radius: 14px; object-fit: cover; }
  .dd-place-body strong { display: block; font-size: 15.5px; }
  .dd-place-body span { display: block; font-size: 12.8px; color: var(--mut); margin-top: 3px; }
  .dd-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
  .dd-chip { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: var(--gs); color: var(--g2); border: 1px solid var(--gb); }
  .dd-chip.amb { background: #fdf3dd; color: #8a5d05; border-color: #f6e4bd; }
  .dd-place-end { text-align: right; }
  .dd-price { font-family: 'Fraunces', Georgia, serif; font-size: 18px; font-weight: 800; color: var(--g2); }
  .dd-price small { display: block; font-family: 'Manrope', sans-serif; font-size: 11px; color: var(--mut); font-weight: 600; }
  .dd-star { color: var(--amb2); font-weight: 800; font-size: 13px; }

  /* Sidebar */
  .dd-map { width: 100%; height: 250px; border: 0; border-radius: 16px; border: 1px solid var(--line); }
  .dd-coords { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 12px; font-size: 12.8px; color: var(--mut); flex-wrap: wrap; }
  .dd-coords code { background: var(--bg); border: 1px solid var(--line); padding: 4px 8px; border-radius: 8px; font-family: ui-monospace, Consolas, monospace; color: var(--ink); font-size: 12px; }
  .dd-rows { display: flex; flex-direction: column; }
  .dd-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px dashed var(--line); font-size: 14px; }
  .dd-row:last-child { border-bottom: 0; padding-bottom: 0; }
  .dd-row:first-child { padding-top: 0; }
  .dd-row span { color: var(--mut); font-weight: 600; }
  .dd-row b { font-weight: 800; text-align: right; }
  .dd-season { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-radius: 12px; background: var(--bg); border: 1px solid var(--line); font-size: 13.5px; }
  .dd-season + .dd-season { margin-top: 8px; }
  .dd-season b { color: var(--g2); }

  .dd-section-empty { padding: 26px; text-align: center; color: var(--mut); font-size: 13.5px; border: 1px dashed #cbd9d1; border-radius: 16px; background: var(--bg); }

  @media (max-width: 1100px) { .dd-grid { grid-template-columns: 1fr; } .dd-facts { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 720px) { .dd-hero { height: 300px; } .dd-hero h1 { font-size: 30px; } .dd-acts { grid-template-columns: 1fr; } .dd-place { grid-template-columns: 72px minmax(0,1fr); } .dd-place img { width: 72px; height: 64px; } .dd-place-end { grid-column: 1 / -1; text-align: left; } .dd-facts { grid-template-columns: 1fr; } }
</style>

<div class="dd">
  <a class="dd-back" href="destinations.php"><i class="fa-solid fa-arrow-left"></i> Back to my destinations</a>

  <!-- Hero with image slider -->
  <section class="dd-hero" id="ddHero">
    <?php foreach ($gallery as $i => $img): ?>
      <img class="<?= $i === 0 ? 'on' : '' ?>" src="<?= h(asset_url($img['image_path'])) ?>" alt="<?= h($img['alt_text'] ?? $d['destination_name']) ?>">
    <?php endforeach; ?>
    <?php if (count($gallery) > 1): ?>
      <div class="dd-dots"><?php foreach ($gallery as $i => $img): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" aria-label="Photo <?= $i + 1 ?>"></button><?php endforeach; ?></div>
      <button type="button" class="dd-nav dd-prev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>
      <button type="button" class="dd-nav dd-next" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
    <?php endif; ?>
    <div class="dd-hero-text">
      <?php $badgeCls = strtolower($state); ?>
      <span class="dd-badge <?= h($badgeCls) ?>"><i></i><?= h($state) ?></span>
      <h1><?= h($d['destination_name']) ?></h1>
      <div class="dd-loc">
        <span><i class="fa-solid fa-location-dot"></i><?= h($d['city']) ?><?= $d['district'] ? ', ' . h($d['district']) : '' ?>, <?= h($d['state']) ?>, <?= h($d['country']) ?></span>
        <span><i class="fa-solid fa-ticket"></i>Entry <?= h(entry_label($d['entry_fee'])) ?></span>
      </div>
    </div>
  </section>

  <!-- Quick facts -->
  <section class="dd-facts">
    <div class="dd-fact"><div class="dd-fact-ic"><i class="fa-regular fa-calendar"></i></div><div><small>Best time</small><strong><?= h($bestText) ?></strong></div></div>
    <div class="dd-fact"><div class="dd-fact-ic"><i class="fa-regular fa-clock"></i></div><div><small>Opening hours</small><strong><?= h($hoursText) ?></strong></div></div>
    <div class="dd-fact"><div class="dd-fact-ic"><i class="fa-solid fa-ticket"></i></div><div><small>Entry fee</small><strong><?= h(entry_label($d['entry_fee'])) ?></strong></div></div>
    <div class="dd-fact"><div class="dd-fact-ic"><i class="fa-solid fa-circle-check"></i></div><div><small>Status</small><strong><?= h($state) ?></strong></div></div>
  </section>

  <div class="dd-grid">
    <!-- Main column -->
    <div class="dd-col">
      <section class="dd-card">
        <div class="dd-tabs" role="tablist">
          <?php foreach (['About', 'History', 'Importance'] as $i => $t): ?>
            <button type="button" class="dd-tab <?= $i === 0 ? 'on' : '' ?>" data-tab="<?= $t ?>"><?= $t ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($byType as $type => $sec): ?>
          <div class="dd-pane <?= $type === 'About' ? 'on' : '' ?>" data-pane="<?= $type ?>">
            <?php if ($sec): ?><h4><?= h($sec['title']) ?></h4><p><?= h($sec['content']) ?></p>
            <?php else: ?><p class="dd-empty">No <?= strtolower($type) ?> text has been added.</p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="dd-card">
        <div class="dd-card-h"><h3>Activities</h3><i class="fa-solid fa-person-hiking"></i></div>
        <?php if (!$acts): ?><p class="dd-empty">No activities added.</p><?php endif; ?>
        <div class="dd-acts">
          <?php foreach ($acts as $a): ?>
            <div class="dd-act"><div class="dd-act-ic"><i class="fa-solid fa-star"></i></div>
              <div><strong><?= h($a['activity_name']) ?></strong><?php if ($a['description']): ?><span><?= h($a['description']) ?></span><?php endif; ?></div></div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="dd-card">
        <div class="dd-card-h"><h3>Hotels</h3><i class="fa-solid fa-hotel"></i></div>
        <?php if (!$hotels): ?><div class="dd-section-empty">No hotels added yet.</div><?php endif; ?>
        <?php foreach ($hotels as $ht): ?>
          <div class="dd-place">
            <img src="<?= h(asset_url($ht['image_path'], 'assets/images/hotels/hotel-placeholder.jpg')) ?>" alt="<?= h($ht['hotel_name']) ?>">
            <div class="dd-place-body">
              <strong><?= h($ht['hotel_name']) ?></strong>
              <span><?= h($ht['address'] ?: 'Address not provided') ?></span>
              <div class="dd-meta"><?php if ($ht['rating']): ?><span class="dd-star">★ <?= h($ht['rating']) ?></span><?php endif; ?></div>
            </div>
            <div class="dd-place-end"><div class="dd-price"><?= money($ht['price_per_night']) ?><small>per night</small></div></div>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="dd-card">
        <div class="dd-card-h"><h3>Restaurants</h3><i class="fa-solid fa-utensils"></i></div>
        <?php if (!$rests): ?><div class="dd-section-empty">No restaurants added yet.</div><?php endif; ?>
        <?php foreach ($rests as $r): ?>
          <div class="dd-place">
            <img src="<?= h(asset_url($r['image_path'], 'assets/images/hotels/hotel-placeholder.jpg')) ?>" alt="<?= h($r['restaurant_name']) ?>">
            <div class="dd-place-body">
              <strong><?= h($r['restaurant_name']) ?></strong>
              <span><?= h($r['address'] ?: 'Address not provided') ?></span>
              <div class="dd-meta"><?php if ($r['cuisine']): ?><span class="dd-chip"><?= h($r['cuisine']) ?></span><?php endif; ?></div>
            </div>
            <div class="dd-place-end"><?php if ($r['rating']): ?><span class="dd-star">★ <?= h($r['rating']) ?></span><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </section>
    </div>

    <!-- Side column -->
    <aside class="dd-col">
      <section class="dd-card">
        <div class="dd-card-h"><h3>Travel tips</h3><i class="fa-regular fa-lightbulb"></i></div>
        <?php if (!$tips): ?><p class="dd-empty">No tips added.</p><?php endif; ?>
        <ul class="dd-tips"><?php foreach ($tips as $t): ?><li><i class="fa-solid fa-lightbulb"></i><span><?= h($t['tip_text']) ?></span></li><?php endforeach; ?></ul>
      </section>

      <section class="dd-card">
        <div class="dd-card-h"><h3>Best time to visit</h3><i class="fa-regular fa-calendar"></i></div>
        <?php if (!$seasons): ?><p class="dd-empty">No season selected.</p><?php endif; ?>
        <?php foreach ($seasons as $s): ?>
          <div class="dd-season"><b><?= h($s['season_name']) ?></b><span><?= date('M', mktime(0,0,0,(int)$s['start_month'],1)) ?> – <?= date('M', mktime(0,0,0,(int)$s['end_month'],1)) ?></span></div>
        <?php endforeach; ?>
      </section>

      <section class="dd-card">
        <div class="dd-card-h"><h3>Location</h3><i class="fa-solid fa-map-location-dot"></i></div>
        <?php if ($hasCoords): ?>
          <iframe class="dd-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            src="https://maps.google.com/maps?q=<?= h($d['latitude']) ?>,<?= h($d['longitude']) ?>&z=11&output=embed" title="Map"></iframe>
          <div class="dd-coords">
            <span>Latitude <code><?= h($d['latitude']) ?></code> Longitude <code><?= h($d['longitude']) ?></code></span>
          </div>
        <?php else: ?><p class="dd-empty">No coordinates have been set.</p><?php endif; ?>
      </section>

      <section class="dd-card">
        <div class="dd-card-h"><h3>Record</h3><i class="fa-solid fa-file-lines"></i></div>
        <div class="dd-rows">
          <div class="dd-row"><span>Destination ID</span><b>#<?= (int) $d['destination_id'] ?></b></div>
          <div class="dd-row"><span>Added</span><b><?= fmt_date($d['created_at']) ?></b></div>
          <div class="dd-row"><span>Last updated</span><b><?= $d['updated_at'] ? fmt_date($d['updated_at']) : '—' ?></b></div>
          <div class="dd-row"><span>Status</span><b><?= h($state) ?></b></div>
        </div>
      </section>
    </aside>
  </div>
</div>

<script>
(function () {
  const hero = document.getElementById('ddHero');
  if (hero) {
    const slides = hero.querySelectorAll('img'), dots = hero.querySelectorAll('.dd-dots button');
    let i = 0, timer;
    const show = (n) => {
      slides[i].classList.remove('on'); dots[i]?.classList.remove('on');
      i = (n + slides.length) % slides.length;
      slides[i].classList.add('on'); dots[i]?.classList.add('on');
    };
    const restart = () => { clearInterval(timer); if (slides.length > 1) timer = setInterval(() => show(i + 1), 6000); };
    hero.querySelector('.dd-prev')?.addEventListener('click', () => { show(i - 1); restart(); });
    hero.querySelector('.dd-next')?.addEventListener('click', () => { show(i + 1); restart(); });
    dots.forEach((d, n) => d.addEventListener('click', () => { show(n); restart(); }));
    restart();
  }
  document.querySelectorAll('.dd-tab').forEach((tab) => tab.addEventListener('click', () => {
    document.querySelectorAll('.dd-tab').forEach((t) => t.classList.toggle('on', t === tab));
    document.querySelectorAll('.dd-pane').forEach((p) => p.classList.toggle('on', p.dataset.pane === tab.dataset.tab));
  }));
})();
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
