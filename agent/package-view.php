<?php
$page_title = 'Package Details';
$page_subtitle = 'Your package as travellers will see it';
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$p = row("SELECT p.*, ag.full_name AS agent_name, ag.city AS agent_city FROM packages p
          JOIN agents ag ON ag.agent_id = p.agent_id WHERE p.package_id = $id AND p.agent_id = $agent_id");
if (!$p) { header('Location: packages.php?msg=' . urlencode('Package not found.') . '&type=error'); exit; }

$live      = $p['status'] === 'Active';
$dests     = rows("SELECT d.destination_name, d.city, d.state, d.status FROM package_destinations pd
                   JOIN destinations d ON d.destination_id = pd.destination_id WHERE pd.package_id = $id ORDER BY pd.display_order");
$cats      = rows("SELECT c.category_name FROM package_categories pc JOIN categories c ON c.category_id = pc.category_id WHERE pc.package_id = $id");
$images    = rows("SELECT image_path, alt_text FROM package_images WHERE package_id = $id AND status = 'Active' ORDER BY (image_type = 'Cover') DESC, display_order");
$itinerary = rows("SELECT day_number, title, description FROM package_itinerary WHERE package_id = $id ORDER BY day_number, display_order");
$incl      = rows("SELECT item_name FROM package_inclusions WHERE package_id = $id AND status = 'Active' ORDER BY display_order");
$excl      = rows("SELECT item_name FROM package_exclusions WHERE package_id = $id AND status = 'Active' ORDER BY display_order");
$bookings  = (int) scalar("SELECT COUNT(*) FROM bookings WHERE package_id = $id");
$confirmed = (int) scalar("SELECT COUNT(*) FROM bookings WHERE package_id = $id AND booking_status = 'Confirmed'");
$revenue   = (float) scalar("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE package_id = $id AND booking_status IN ('Accepted','Confirmed')");
$avg       = scalar("SELECT ROUND(AVG(rating),1) FROM package_reviews WHERE package_id = $id AND status = 'Approved'");
$reviewN   = (int) scalar("SELECT COUNT(*) FROM package_reviews WHERE package_id = $id AND status = 'Approved'");
$gallery   = $images ?: [['image_path' => null, 'alt_text' => $p['package_name']]];
$days      = (int) $p['duration_days'];
$nights    = (int) $p['duration_nights'];

$page_title = $p['package_name'];
include __DIR__ . '/includes/page-start.php';
?>
<style>
  .pv { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; --g1:#0f4a30; --g2:#146339; --g3:#1f8a54; --gs:#e6f5ec; --gb:#cfe9d9; --mut:#6b7a72; --line:#e3ebe6; --bg:#f7faf8; --amb:#f4a627; --amb2:#cf8a17; --red:#d64545; }
  .pv * { box-sizing: border-box; }
  .pv h1, .pv h2, .pv h3, .pv h4 { font-family: 'Fraunces', Georgia, serif; margin: 0; letter-spacing: -.01em; }
  .pv p { margin: 0; }
  .pv-back { display: inline-flex; align-items: center; gap: 8px; color: var(--g2); font-weight: 700; font-size: 13.5px; text-decoration: none; }
  .pv-back:hover { text-decoration: underline; }

  /* Header */
  .pv-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-top: 14px; }
  .pv-title-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .pv-title { font-size: 34px; font-weight: 800; line-height: 1.15; }
  .pv-status { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; padding: 6px 13px; border-radius: 999px; }
  .pv-status i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
  .pv-status.live { background: var(--gs); color: var(--g2); border: 1px solid var(--gb); }
  .pv-status.live i { animation: pv-pulse 2.2s ease-out infinite; }
  .pv-status.pending { background: #fdf3dd; color: #8a5d05; border: 1px solid #f6e4bd; }
  .pv-sub { margin-top: 8px; color: var(--mut); font-size: 14px; display: flex; flex-wrap: wrap; gap: 6px 18px; }
  .pv-sub span { display: inline-flex; align-items: center; gap: 7px; }
  .pv-sub i { color: var(--g3); }
  .pv-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .pv-btn { display: inline-flex; align-items: center; gap: 8px; font: inherit; font-weight: 700; font-size: 13.5px; padding: 11px 18px; border-radius: 13px; border: 1.5px solid var(--line); background: #fff; color: #1d2a24; text-decoration: none; transition: transform .2s, box-shadow .2s, background .2s; cursor: pointer; }
  .pv-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 22px -14px rgba(16,24,20,.4); }
  .pv-btn.primary { background: linear-gradient(135deg, var(--g1), var(--g3)); color: #fff; border-color: transparent; }

  /* Gallery */
  .pv-gallery { display: grid; grid-template-columns: minmax(0,1fr) 150px; gap: 12px; margin-top: 20px; }
  .pv-stage { position: relative; height: 400px; border-radius: 24px; overflow: hidden; background: #0c2a1d; box-shadow: 0 24px 50px -28px rgba(15,74,48,.7); }
  .pv-stage img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transform: scale(1.03); transition: opacity .7s ease, transform 1.2s ease; }
  .pv-stage img.on { opacity: 1; transform: scale(1); }
  .pv-stage::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 60%, rgba(8,30,20,.55)); pointer-events: none; }
  .pv-stage-count { position: absolute; right: 16px; bottom: 14px; z-index: 2; color: #fff; font-size: 12px; font-weight: 800; background: rgba(0,0,0,.35); padding: 5px 11px; border-radius: 999px; backdrop-filter: blur(6px); }
  .pv-thumbs { display: flex; flex-direction: column; gap: 10px; max-height: 400px; overflow-y: auto; padding-right: 2px; }
  .pv-thumb { border: 2px solid transparent; border-radius: 14px; overflow: hidden; padding: 0; cursor: pointer; height: 84px; flex-shrink: 0; background: #e9f1ed; transition: border-color .25s, transform .25s; }
  .pv-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .pv-thumb:hover { transform: translateX(-3px); }
  .pv-thumb.on { border-color: var(--g3); }

  /* Fact strip */
  .pv-facts { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; margin-top: 18px; }
  .pv-fact { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 8px 22px -16px rgba(16,24,20,.25); transition: transform .3s, box-shadow .3s; }
  .pv-fact:hover { transform: translateY(-3px); box-shadow: 0 16px 30px -18px rgba(16,24,20,.35); }
  .pv-fact-ic { width: 44px; height: 44px; border-radius: 14px; background: var(--gs); color: var(--g3); display: grid; place-items: center; font-size: 17px; flex-shrink: 0; }
  .pv-fact small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--mut); font-weight: 800; }
  .pv-fact strong { display: block; font-size: 15px; margin-top: 2px; }

  /* Layout */
  .pv-grid { display: grid; grid-template-columns: minmax(0,1.65fr) minmax(0,1fr); gap: 22px; margin-top: 22px; align-items: start; }
  .pv-card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px -18px rgba(16,24,20,.2); animation: pv-rise .6s cubic-bezier(.22,1,.36,1) both; }
  .pv-card + .pv-card { margin-top: 22px; }
  .pv-card-h { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
  .pv-card-h h3 { font-size: 19px; font-weight: 700; }
  .pv-card-h i { color: var(--g3); }
  .pv-side { position: sticky; top: 90px; display: flex; flex-direction: column; gap: 22px; }

  /* Tabs */
  .pv-tabs { display: inline-flex; gap: 4px; padding: 5px; background: var(--bg); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 18px; flex-wrap: wrap; }
  .pv-tab { border: 0; background: transparent; font: inherit; font-size: 13.5px; font-weight: 700; color: var(--mut); padding: 9px 16px; border-radius: 10px; cursor: pointer; transition: .2s; }
  .pv-tab.on { background: var(--g3); color: #fff; box-shadow: 0 8px 18px -10px rgba(31,138,84,.9); }
  .pv-pane { display: none; }
  .pv-pane.on { display: block; animation: pv-fade .4s ease; }
  .pv-pane p { font-size: 14.8px; line-height: 1.8; color: #3f5148; white-space: pre-line; }
  .pv-empty { color: var(--mut); font-size: 13.5px; font-style: italic; }

  /* Itinerary */
  .pv-day { display: grid; grid-template-columns: 96px minmax(0,1fr); gap: 18px; position: relative; padding-bottom: 20px; }
  .pv-day:last-child { padding-bottom: 0; }
  .pv-day::before { content: ''; position: absolute; left: 47px; top: 40px; bottom: 0; width: 2px; background: var(--line); }
  .pv-day:last-child::before { display: none; }
  .pv-day-no { position: relative; z-index: 1; height: 34px; border-radius: 999px; background: var(--g3); color: #fff; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 16px -8px rgba(31,138,84,.9); }
  .pv-day strong { font-size: 15.5px; display: block; padding-top: 4px; }
  .pv-day p { font-size: 13.8px; color: var(--mut); margin-top: 6px; line-height: 1.7; }

  /* Inclusions */
  .pv-inc { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
  .pv-inc h4 { font-family: 'Manrope', sans-serif; font-size: 12px; text-transform: uppercase; letter-spacing: .1em; font-weight: 800; margin-bottom: 12px; }
  .pv-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .pv-list li { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.5; }
  .pv-list li i { margin-top: 3px; font-size: 13px; }
  .pv-list .ok i { color: var(--g3); }
  .pv-list .no i { color: var(--red); }

  /* Places */
  .pv-place { display: flex; align-items: center; gap: 13px; padding: 13px 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--bg); margin-bottom: 10px; transition: transform .25s, box-shadow .25s; }
  .pv-place:hover { transform: translateY(-2px); box-shadow: 0 12px 22px -16px rgba(16,24,20,.35); }
  .pv-place:last-child { margin-bottom: 0; }
  .pv-place-ic { width: 40px; height: 40px; border-radius: 13px; background: #fff; color: var(--g3); border: 1px solid var(--line); display: grid; place-items: center; flex-shrink: 0; }
  .pv-place strong { display: block; font-size: 14.5px; }
  .pv-place span { font-size: 12.5px; color: var(--mut); }
  .pv-chip { margin-left: auto; font-size: 11px; font-weight: 800; padding: 4px 9px; border-radius: 999px; }
  .pv-chip.live { background: var(--gs); color: var(--g2); }
  .pv-chip.wait { background: #fdf3dd; color: #8a5d05; }

  /* Stats & record */
  .pv-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .pv-stat { border-radius: 16px; padding: 16px 12px; text-align: center; background: linear-gradient(160deg, var(--gs), #fff); border: 1px solid var(--gb); transition: transform .25s; }
  .pv-stat:hover { transform: translateY(-2px); }
  .pv-stat b { display: block; font-family: 'Fraunces', Georgia, serif; font-size: 22px; color: var(--g2); }
  .pv-stat span { font-size: 11.5px; color: var(--mut); font-weight: 700; }
  .pv-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px dashed var(--line); font-size: 14px; }
  .pv-row:first-child { padding-top: 0; }
  .pv-row:last-child { border-bottom: 0; padding-bottom: 0; }
  .pv-row span { color: var(--mut); font-weight: 600; }
  .pv-row b { font-weight: 800; text-align: right; }
  .pv-cats { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
  .pv-cat { font-size: 12px; font-weight: 700; padding: 5px 11px; border-radius: 999px; background: var(--gs); color: var(--g2); border: 1px solid var(--gb); }

  @keyframes pv-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  @keyframes pv-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
  @keyframes pv-pulse { 0% { box-shadow: 0 0 0 0 rgba(31,138,84,.4); } 70% { box-shadow: 0 0 0 7px rgba(31,138,84,0); } 100% { box-shadow: 0 0 0 0 rgba(31,138,84,0); } }
  @media (max-width: 1180px) { .pv-grid { grid-template-columns: 1fr; } .pv-side { position: static; } .pv-facts { grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 760px) { .pv-gallery { grid-template-columns: 1fr; } .pv-thumbs { flex-direction: row; max-height: none; overflow-x: auto; } .pv-thumb { width: 110px; height: 72px; } .pv-stage { height: 280px; } .pv-title { font-size: 26px; } .pv-inc { grid-template-columns: 1fr; } }
  @media (prefers-reduced-motion: reduce) { .pv *, .pv *::before, .pv *::after { animation: none !important; transition: none !important; } }
</style>

<div class="pv">
  <a class="pv-back" href="packages.php"><i class="fa-solid fa-arrow-left"></i> Back to my packages</a>

  <header class="pv-head">
    <div>
      <div class="pv-title-row">
        <h1 class="pv-title"><?= h($p['package_name']) ?></h1>
        <span class="pv-status <?= $live ? 'live' : 'pending' ?>"><i></i><?= $live ? 'Live' : 'Pending approval' ?></span>
      </div>
      <div class="pv-sub">
        <span><i class="fa-regular fa-calendar"></i><?= $days ?> days / <?= $nights ?> nights</span>
        <span><i class="fa-solid fa-location-dot"></i><?= h($p['agent_city'] ?: 'Your city') ?></span>
        <span><i class="fa-solid fa-user-tie"></i><?= h($p['agent_name']) ?></span>
      </div>
    </div>
    <div class="pv-actions">
      <a class="pv-btn" href="bookings.php"><i class="fa-solid fa-bell-concierge"></i> Booking requests</a>
      <a class="pv-btn primary" href="packages.php"><i class="fa-solid fa-box-open"></i> All packages</a>
    </div>
  </header>

  <section class="pv-gallery">
    <div class="pv-stage" id="pvStage">
      <?php foreach ($gallery as $i => $img): ?>
        <img class="<?= $i === 0 ? 'on' : '' ?>" src="<?= h(asset_url($img['image_path'], 'assets/images/destinations/placeholder.jpg')) ?>" alt="<?= h($img['alt_text'] ?? $p['package_name']) ?>">
      <?php endforeach; ?>
      <span class="pv-stage-count" id="pvCount">1 / <?= count($gallery) ?></span>
    </div>
    <?php if (count($gallery) > 1): ?>
    <div class="pv-thumbs" id="pvThumbs">
      <?php foreach ($gallery as $i => $img): ?>
        <button type="button" class="pv-thumb <?= $i === 0 ? 'on' : '' ?>" aria-label="Photo <?= $i + 1 ?>">
          <img src="<?= h(asset_url($img['image_path'], 'assets/images/destinations/placeholder.jpg')) ?>" alt="">
        </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="pv-facts">
    <div class="pv-fact"><div class="pv-fact-ic"><i class="fa-regular fa-calendar"></i></div><div><small>Duration</small><strong><?= $days ?>D / <?= $nights ?>N</strong></div></div>
    <div class="pv-fact"><div class="pv-fact-ic"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><small>Per person</small><strong><?= money($p['price_per_person']) ?></strong></div></div>
    <div class="pv-fact"><div class="pv-fact-ic"><i class="fa-solid fa-users"></i></div><div><small>Group limit</small><strong><?= (int) $p['max_adults'] ?> adults<?= (int) $p['max_children'] ? ' · ' . (int) $p['max_children'] . ' children' : '' ?></strong></div></div>
    <div class="pv-fact"><div class="pv-fact-ic"><i class="fa-solid fa-ticket"></i></div><div><small>Confirmed trips</small><strong><?= $confirmed ?> of <?= $bookings ?></strong></div></div>
  </section>

  <div class="pv-grid">
    <!-- Main column -->
    <div>
      <section class="pv-card">
        <div class="pv-tabs" role="tablist">
          <button type="button" class="pv-tab on" data-tab="overview">Overview</button>
          <button type="button" class="pv-tab" data-tab="itinerary">Itinerary</button>
          <button type="button" class="pv-tab" data-tab="included">Inclusions</button>
          <button type="button" class="pv-tab" data-tab="places">Places</button>
        </div>

        <div class="pv-pane on" data-pane="overview">
          <h3 style="font-size:18px;margin-bottom:12px">About this package</h3>
          <?php if (trim((string) $p['description']) !== ''): ?><p><?= h($p['description']) ?></p>
          <?php else: ?><p class="pv-empty">No description has been added.</p><?php endif; ?>
          <?php if ($cats): ?>
            <div class="pv-cats"><?php foreach ($cats as $c): ?><span class="pv-cat"><?= h($c['category_name']) ?></span><?php endforeach; ?></div>
          <?php endif; ?>
        </div>

        <div class="pv-pane" data-pane="itinerary">
          <?php if (!$itinerary): ?><p class="pv-empty">No itinerary has been added.</p><?php endif; ?>
          <?php foreach ($itinerary as $it): ?>
            <div class="pv-day">
              <div class="pv-day-no">Day <?= (int) $it['day_number'] ?></div>
              <div><strong><?= h($it['title']) ?></strong><p><?= h($it['description']) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="pv-pane" data-pane="included">
          <div class="pv-inc">
            <div>
              <h4 style="color:var(--g2)">Included</h4>
              <ul class="pv-list">
                <?php foreach ($incl as $i): ?><li class="ok"><i class="fa-solid fa-circle-check"></i><span><?= h($i['item_name']) ?></span></li><?php endforeach; ?>
                <?php if (!$incl): ?><li class="pv-empty">Nothing listed</li><?php endif; ?>
              </ul>
            </div>
            <div>
              <h4 style="color:var(--red)">Excluded</h4>
              <ul class="pv-list">
                <?php foreach ($excl as $e): ?><li class="no"><i class="fa-solid fa-circle-xmark"></i><span><?= h($e['item_name']) ?></span></li><?php endforeach; ?>
                <?php if (!$excl): ?><li class="pv-empty">Nothing listed</li><?php endif; ?>
              </ul>
            </div>
          </div>
        </div>

        <div class="pv-pane" data-pane="places">
          <?php if (!$dests): ?><p class="pv-empty">No destinations are linked to this package.</p><?php endif; ?>
          <?php foreach ($dests as $d): ?>
            <div class="pv-place">
              <div class="pv-place-ic"><i class="fa-solid fa-location-dot"></i></div>
              <div><strong><?= h($d['destination_name']) ?></strong><span><?= h($d['city']) ?>, <?= h($d['state']) ?></span></div>
              <span class="pv-chip <?= $d['status'] === 'Active' ? 'live' : 'wait' ?>"><?= $d['status'] === 'Active' ? 'Live' : 'Pending' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <!-- Side column -->
    <aside class="pv-side">
      <section class="pv-card" style="margin-top:0">
        <div class="pv-card-h"><h3>Performance</h3><i class="fa-solid fa-chart-simple"></i></div>
        <div class="pv-stats">
          <div class="pv-stat"><b><?= $bookings ?></b><span>Bookings</span></div>
          <div class="pv-stat"><b><?= money($revenue) ?></b><span>Revenue</span></div>
          <div class="pv-stat"><b><?= $avg ? h($avg) . ' ★' : '—' ?></b><span>Avg. rating</span></div>
          <div class="pv-stat"><b><?= $reviewN ?></b><span>Reviews</span></div>
        </div>
      </section>

      <section class="pv-card" style="margin-top:0">
        <div class="pv-card-h"><h3>Package record</h3><i class="fa-regular fa-id-card"></i></div>
        <div class="pv-row"><span>Package ID</span><b>#<?= (int) $p['package_id'] ?></b></div>
        <div class="pv-row"><span>Status</span><b><?= $live ? 'Live' : 'Pending approval' ?></b></div>
        <div class="pv-row"><span>Created</span><b><?= fmt_date($p['created_at']) ?></b></div>
        <div class="pv-row"><span>Last updated</span><b><?= $p['updated_at'] ? fmt_date($p['updated_at']) : '—' ?></b></div>
      </section>
    </aside>
  </div>
</div>

<script>
(function () {
  const stage = document.getElementById('pvStage');
  if (stage) {
    const slides = stage.querySelectorAll('img');
    const thumbs = document.querySelectorAll('#pvThumbs .pv-thumb');
    const count = document.getElementById('pvCount');
    let i = 0, timer;
    const show = (n) => {
      slides[i].classList.remove('on'); thumbs[i]?.classList.remove('on');
      i = (n + slides.length) % slides.length;
      slides[i].classList.add('on'); thumbs[i]?.classList.add('on');
      if (count) count.textContent = (i + 1) + ' / ' + slides.length;
    };
    const restart = () => { clearInterval(timer); if (slides.length > 1) timer = setInterval(() => show(i + 1), 6000); };
    thumbs.forEach((t, n) => t.addEventListener('click', () => { show(n); restart(); }));
    restart();
  }
  document.querySelectorAll('.pv-tab').forEach((tab) => tab.addEventListener('click', () => {
    document.querySelectorAll('.pv-tab').forEach((t) => t.classList.toggle('on', t === tab));
    document.querySelectorAll('.pv-pane').forEach((p) => p.classList.toggle('on', p.dataset.pane === tab.dataset.tab));
  }));
})();
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
