<?php
$page_title = 'Destination Request';
$page_subtitle = 'Full submission from the agent. Review everything before approving.';
require_once __DIR__ . '/includes/auth.php';

$id = (int) ($_GET['id'] ?? 0);
$r = row("SELECT dr.*, ag.full_name AS agent_name, ag.phone AS agent_phone, a.email AS agent_email, c.category_name
          FROM destination_requests dr
          LEFT JOIN agents ag ON ag.agent_id = dr.agent_id
          LEFT JOIN accounts a ON a.account_id = ag.account_id
          LEFT JOIN categories c ON c.category_id = dr.category_id
          WHERE dr.request_id = $id");
if (!$r) { header('Location: destination-requests.php?msg=' . urlencode('That request could not be found.') . '&type=error'); exit; }

$did = (int) ($r['linked_destination_id'] ?? 0);
$d = $did ? row("SELECT * FROM destinations WHERE destination_id = $did") : null;

$images   = $did ? rows("SELECT image_path, alt_text FROM destination_images WHERE destination_id = $did AND status = 'Active' ORDER BY is_primary DESC, display_order") : [];
$sections = $did ? rows("SELECT section_type, title, content FROM destination_sections WHERE destination_id = $did AND status = 'Active'") : [];
$seasons  = $did ? rows("SELECT s.season_name, s.start_month, s.end_month FROM destination_seasons ds JOIN seasons s ON s.season_id = ds.season_id WHERE ds.destination_id = $did AND ds.is_best_time = 1 ORDER BY s.start_month") : [];
$hours    = $did ? row("SELECT open_time, close_time, is_closed FROM destination_opening_hours WHERE destination_id = $did ORDER BY day_of_week LIMIT 1") : null;
$tips     = $did ? rows("SELECT tip_text FROM destination_tips WHERE destination_id = $did AND status = 'Active' ORDER BY display_order") : [];
$acts     = $did ? rows("SELECT a.activity_name, COALESCE(da.description, a.description) AS description FROM destination_activities da JOIN activities a ON a.activity_id = da.activity_id WHERE da.destination_id = $did ORDER BY da.display_order") : [];
$hotels   = $did ? rows("SELECT hotel_name, address, price_per_night, rating, image_path FROM hotels WHERE destination_id = $did AND status = 'Active'") : [];
$rests    = $did ? rows("SELECT restaurant_name, cuisine, rating, address, image_path FROM restaurants WHERE destination_id = $did AND status = 'Active'") : [];

$byType = ['About' => null, 'History' => null, 'Importance' => null];
foreach ($sections as $s) $byType[$s['section_type']] = $s;
$hoursText = !$hours ? 'Not set' : ((int) $hours['is_closed'] ? 'Closed' : date('g:i A', strtotime($hours['open_time'])) . ' – ' . date('g:i A', strtotime($hours['close_time'])));
$bestText  = $seasons ? implode(', ', array_map(fn($s) => date('M', mktime(0, 0, 0, (int) $s['start_month'], 1)) . ' – ' . date('M', mktime(0, 0, 0, (int) $s['end_month'], 1)), $seasons)) : 'Not set';
$hasCoords = $d && $d['latitude'] !== null && $d['longitude'] !== null;
$heroImg   = $images[0]['image_path'] ?? $r['image_path'];
$page_title = $r['destination_name'];

include __DIR__ . '/includes/page-start.php';
?>
<style>
  .dr { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; --g1:#0f4a30; --g2:#146339; --g3:#1f8a54; --gs:#e8f6ee; --gb:#cfe9d9; --mut:#6b7a72; --line:#e3ebe6; --bg:#f7faf8; --amb:#f4a627; --amb2:#cf8a17; --red:#d64545; }
  .dr *, .dr *::before, .dr *::after { box-sizing: border-box; }
  .dr h1, .dr h3, .dr h4 { font-family: 'Fraunces', Georgia, serif; margin: 0; letter-spacing: -.01em; }
  .dr p { margin: 0; }
  .dr-back { display: inline-flex; align-items: center; gap: 8px; color: var(--g2); font-weight: 700; font-size: 13.5px; text-decoration: none; }
  .dr-back:hover { text-decoration: underline; }

  /* Header */
  .dr-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; margin-top: 14px; }
  .dr-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; align-items: center; }
  .dr-tag { font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 999px; background: var(--gs); color: var(--g2); border: 1px solid var(--gb); }
  .dr-title { font-size: 38px; font-weight: 800; line-height: 1.12; }
  .dr-loc { margin-top: 8px; color: var(--mut); font-size: 14.5px; display: flex; align-items: center; gap: 8px; }
  .dr-loc i { color: #d6457a; }
  .dr-side { display: flex; gap: 12px; flex-wrap: wrap; }
  .dr-stat { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px 16px; box-shadow: 0 6px 16px -14px rgba(16,24,20,.3); min-width: 150px; }
  .dr-stat small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--mut); font-weight: 800; }
  .dr-stat strong { display: block; font-size: 15px; margin-top: 3px; }

  /* Decision bar */
  .dr-decide { margin-top: 18px; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; box-shadow: 0 10px 24px -20px rgba(16,24,20,.3); }
  .dr-decide strong { font-size: 15px; display: block; }
  .dr-decide span { font-size: 13px; color: var(--mut); }
  .dr-actions { display: flex; gap: 10px; flex-wrap: wrap; }
  .dr-btn { display: inline-flex; align-items: center; gap: 8px; font: inherit; font-weight: 700; font-size: 14px; padding: 11px 18px; border-radius: 12px; border: 1.5px solid transparent; cursor: pointer; text-decoration: none; transition: transform .2s, box-shadow .2s, background .2s; }
  .dr-btn:hover { transform: translateY(-2px); }
  .dr-btn-approve { background: linear-gradient(135deg, var(--g3), var(--g2)); color: #fff; box-shadow: 0 12px 22px -14px rgba(31,138,84,.9); }
  .dr-btn-reject { background: #fff; color: var(--red); border-color: #f2c9c9; }
  .dr-btn-reject:hover { background: var(--red); color: #fff; border-color: var(--red); }
  .dr-reason { margin-top: 18px; padding: 14px 18px; border-radius: 14px; background: #fbe9e9; color: #a52d2d; font-size: 14px; line-height: 1.6; border: 1px solid #f2c9c9; }

  /* Hero */
  .dr-hero { position: relative; height: 380px; border-radius: 24px; overflow: hidden; margin-top: 18px; background: linear-gradient(135deg, #e6f5ec, #cfe9d9); box-shadow: 0 24px 50px -28px rgba(15,74,48,.7); }
  .dr-hero img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 1.2s cubic-bezier(.22,1,.36,1); }
  .dr-hero:hover img { transform: scale(1.03); }
  .dr-hero::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 55%, rgba(8,30,20,.45)); pointer-events: none; }

  /* Layout */
  .dr-grid { display: grid; grid-template-columns: minmax(0,1.6fr) minmax(0,1fr); gap: 22px; margin-top: 22px; align-items: start; }
  .dr-col { display: flex; flex-direction: column; gap: 22px; min-width: 0; }
  .dr-card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 24px; box-shadow: 0 10px 30px -18px rgba(16,24,20,.2); animation: dr-rise .6s cubic-bezier(.22,1,.36,1) both; }
  .dr-card-h { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
  .dr-card-h h3 { font-size: 19px; font-weight: 700; }
  .dr-card-h i { color: var(--g3); }
  .dr-empty { color: var(--mut); font-size: 13.5px; font-style: italic; }
  .dr-none { padding: 22px; text-align: center; color: var(--mut); font-size: 13.5px; border: 1px dashed #cbd9d1; border-radius: 14px; background: var(--bg); }

  /* Tabs */
  .dr-tabs { display: inline-flex; gap: 4px; padding: 5px; background: var(--bg); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 18px; }
  .dr-tab { border: 0; background: transparent; font: inherit; font-size: 13.5px; font-weight: 700; color: var(--mut); padding: 9px 18px; border-radius: 10px; cursor: pointer; transition: .2s; }
  .dr-tab:hover { color: var(--ink, #1d2a24); }
  .dr-tab.on { background: var(--g3); color: #fff; box-shadow: 0 8px 18px -10px rgba(31,138,84,.9); }
  .dr-pane { display: none; }
  .dr-pane.on { display: block; animation: dr-fade .4s ease; }
  .dr-pane h4 { font-size: 18px; margin-bottom: 10px; }
  .dr-pane p { font-size: 14.8px; line-height: 1.8; color: #3f5148; white-space: pre-line; }

  /* Info strip */
  .dr-info { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; }
  .dr-info-item { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 6px; box-shadow: 0 8px 20px -16px rgba(16,24,20,.25); transition: transform .3s; }
  .dr-info-item:hover { transform: translateY(-3px); }
  .dr-info-item i { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; background: var(--gs); color: var(--g3); font-size: 16px; margin-bottom: 4px; }
  .dr-info-item span { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--mut); font-weight: 800; }
  .dr-info-item strong { font-size: 14.5px; }

  /* Two columns of chips */
  .dr-two { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
  .dr-chips { display: flex; flex-direction: column; gap: 10px; }
  .dr-chip { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border: 1px solid var(--line); border-radius: 13px; background: var(--bg); font-weight: 600; font-size: 13.5px; }
  .dr-chip i { color: #d6457a; font-size: 13px; }
  .dr-tips { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
  .dr-tips li { display: flex; gap: 12px; font-size: 13.5px; color: #3d4e46; line-height: 1.6; }
  .dr-tips i { color: var(--amb2); margin-top: 4px; }

  /* Hotels & restaurants */
  .dr-place { display: grid; grid-template-columns: 96px minmax(0,1fr) auto; gap: 16px; align-items: center; padding: 14px; border: 1px solid var(--line); border-radius: 18px; background: #fff; margin-bottom: 12px; transition: box-shadow .25s, transform .25s; }
  .dr-place:hover { box-shadow: 0 16px 30px -20px rgba(16,24,20,.35); transform: translateY(-2px); }
  .dr-place:last-child { margin-bottom: 0; }
  .dr-place img { width: 96px; height: 80px; border-radius: 14px; object-fit: cover; }
  .dr-place strong { display: block; font-size: 15.5px; }
  .dr-place span { display: block; font-size: 12.8px; color: var(--mut); margin-top: 3px; }
  .dr-price { font-family: 'Fraunces', Georgia, serif; font-size: 18px; font-weight: 800; color: var(--g2); text-align: right; }
  .dr-price small { display: block; font-family: 'Manrope', sans-serif; font-size: 11px; color: var(--mut); font-weight: 600; }
  .dr-star { color: var(--amb2); font-weight: 800; font-size: 13px; }

  /* Side */
  .dr-map { width: 100%; height: 250px; border: 1px solid var(--line); border-radius: 16px; }
  .dr-coords { margin-top: 12px; font-size: 12.8px; color: var(--mut); }
  .dr-coords code { background: var(--bg); border: 1px solid var(--line); padding: 3px 8px; border-radius: 8px; font-size: 12px; color: #1d2a24; }
  .dr-rows { display: flex; flex-direction: column; }
  .dr-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px dashed var(--line); font-size: 14px; }
  .dr-row:first-child { padding-top: 0; }
  .dr-row:last-child { border-bottom: 0; padding-bottom: 0; }
  .dr-row span { color: var(--mut); font-weight: 600; }
  .dr-row b { font-weight: 800; text-align: right; word-break: break-word; }

  @keyframes dr-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  @keyframes dr-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
  @media (max-width: 1100px) { .dr-grid { grid-template-columns: 1fr; } }
  @media (max-width: 720px) { .dr-title { font-size: 28px; } .dr-info, .dr-two { grid-template-columns: 1fr; } .dr-hero { height: 260px; } .dr-place { grid-template-columns: 72px minmax(0,1fr); } .dr-place img { width: 72px; height: 64px; } .dr-place .dr-price { grid-column: 1 / -1; text-align: left; } }
  @media (prefers-reduced-motion: reduce) { .dr *, .dr *::before, .dr *::after { animation: none !important; transition: none !important; } }
</style>

<div class="dr">
  <a class="dr-back" href="destination-requests.php"><i class="fa-solid fa-arrow-left"></i> Back to requests</a>

  <header class="dr-head">
    <div>
      <div class="dr-tags">
        <?php if ($r['category_name']): ?><span class="dr-tag"><?= h($r['category_name']) ?></span><?php endif; ?>
        <?= badge($r['status']) ?>
      </div>
      <h1 class="dr-title"><?= h($r['destination_name']) ?></h1>
      <p class="dr-loc"><i class="fa-solid fa-location-dot"></i>
        <?= h($r['city']) ?><?= $r['district'] ? ', ' . h($r['district']) : '' ?>, <?= h($r['state']) ?>, <?= h($r['country']) ?></p>
    </div>
    <div class="dr-side">
      <div class="dr-stat"><small>Entry fee</small><strong><?= h(entry_label($r['entry_fee'])) ?></strong></div>
      <div class="dr-stat"><small>Submitted by</small><strong><?= h($r['agent_name'] ?: 'Unknown') ?></strong></div>
    </div>
  </header>

  <?php if ($r['status'] === 'Pending'): ?>
  <section class="dr-decide">
    <div><strong>Decision needed</strong><span>Approve publishes this destination to travellers. Reject returns the reason to the agent.</span></div>
    <div class="dr-actions">
      <button class="dr-btn dr-btn-reject" type="button" data-action="reject" data-url="ajax/destination_request_action.php" data-id="<?= $id ?>" data-name="<?= h($r['destination_name']) ?>"><i class="fa-solid fa-xmark"></i> Reject</button>
      <button class="dr-btn dr-btn-approve" type="button" data-action="approve" data-url="ajax/destination_request_action.php" data-id="<?= $id ?>" data-name="<?= h($r['destination_name']) ?>" data-title="Approve destination"><i class="fa-solid fa-check"></i> Approve &amp; publish</button>
    </div>
  </section>
  <?php elseif ($r['status'] === 'Rejected' && $r['rejection_reason']): ?>
    <div class="dr-reason"><strong>Rejected:</strong> <?= h($r['rejection_reason']) ?></div>
  <?php endif; ?>

  <?php if ($heroImg): ?>
  <section class="dr-hero"><img src="<?= h(asset_url($heroImg)) ?>" alt="<?= h($r['destination_name']) ?>"></section>
  <?php endif; ?>

  <?php if (!$d): ?>
    <div class="dr-none" style="margin-top:18px">This request is not linked to a full destination record, so only the basic request fields are available.</div>
  <?php endif; ?>

  <div class="dr-grid">
    <div class="dr-col">
      <section class="dr-card">
        <div class="dr-tabs" role="tablist">
          <?php foreach (['About', 'History', 'Importance'] as $i => $t): ?>
            <button type="button" class="dr-tab <?= $i === 0 ? 'on' : '' ?>" data-tab="<?= $t ?>"><?= $t ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($byType as $type => $sec): ?>
          <div class="dr-pane <?= $type === 'About' ? 'on' : '' ?>" data-pane="<?= $type ?>">
            <?php if ($sec): ?><h4><?= h($sec['title']) ?></h4><p><?= h($sec['content']) ?></p>
            <?php else: ?><p class="dr-empty">No <?= strtolower($type) ?> text submitted.</p><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$d): ?><div class="dr-pane on"><p><?= nl2br(h($r['description'] ?: 'The agent did not add a description.')) ?></p></div><?php endif; ?>
      </section>

      <section class="dr-info">
        <div class="dr-info-item"><i class="fa-regular fa-calendar"></i><span>Best time</span><strong><?= h($bestText) ?></strong></div>
        <div class="dr-info-item"><i class="fa-regular fa-clock"></i><span>Opening hours</span><strong><?= h($hoursText) ?></strong></div>
        <div class="dr-info-item"><i class="fa-solid fa-ticket"></i><span>Entry fee</span><strong><?= h(entry_label($r['entry_fee'])) ?></strong></div>
      </section>

      <div class="dr-two">
        <section class="dr-card">
          <div class="dr-card-h"><h3>Activities</h3><i class="fa-solid fa-person-hiking"></i></div>
          <?php if (!$acts): ?><p class="dr-empty">None submitted.</p><?php endif; ?>
          <div class="dr-chips"><?php foreach ($acts as $a): ?><div class="dr-chip" title="<?= h($a['description']) ?>"><i class="fa-solid fa-location-dot"></i><span><?= h($a['activity_name']) ?></span></div><?php endforeach; ?></div>
        </section>
        <section class="dr-card">
          <div class="dr-card-h"><h3>Travel tips</h3><i class="fa-regular fa-lightbulb"></i></div>
          <?php if (!$tips): ?><p class="dr-empty">None submitted.</p><?php endif; ?>
          <ul class="dr-tips"><?php foreach ($tips as $t): ?><li><i class="fa-solid fa-lightbulb"></i><span><?= h($t['tip_text']) ?></span></li><?php endforeach; ?></ul>
        </section>
      </div>

      <section class="dr-card">
        <div class="dr-card-h"><h3>Hotels</h3><i class="fa-solid fa-hotel"></i></div>
        <?php if (!$hotels): ?><div class="dr-none">None submitted.</div><?php endif; ?>
        <?php foreach ($hotels as $ht): ?>
          <div class="dr-place">
            <img src="<?= h(asset_url($ht['image_path'], 'assets/images/hotels/hotel-placeholder.jpg')) ?>" alt="<?= h($ht['hotel_name']) ?>">
            <div><strong><?= h($ht['hotel_name']) ?></strong><span><?= h($ht['address'] ?: 'Address not provided') ?></span><?php if ($ht['rating']): ?><span class="dr-star">★ <?= h($ht['rating']) ?></span><?php endif; ?></div>
            <div class="dr-price"><?= money($ht['price_per_night']) ?><small>per night</small></div>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="dr-card">
        <div class="dr-card-h"><h3>Restaurants</h3><i class="fa-solid fa-utensils"></i></div>
        <?php if (!$rests): ?><div class="dr-none">None submitted.</div><?php endif; ?>
        <?php foreach ($rests as $rs): ?>
          <div class="dr-place">
            <img src="<?= h(asset_url($rs['image_path'], 'assets/images/hotels/hotel-placeholder.jpg')) ?>" alt="<?= h($rs['restaurant_name']) ?>">
            <div><strong><?= h($rs['restaurant_name']) ?></strong><span><?= h($rs['address'] ?: 'Address not provided') ?></span><?php if ($rs['cuisine']): ?><span><?= h($rs['cuisine']) ?></span><?php endif; ?></div>
            <?php if ($rs['rating']): ?><div class="dr-price dr-star">★ <?= h($rs['rating']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    </div>

    <aside class="dr-col">
      <section class="dr-card">
        <div class="dr-card-h"><h3>Location</h3><i class="fa-solid fa-map-location-dot"></i></div>
        <?php if ($hasCoords): ?>
          <iframe class="dr-map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            src="https://maps.google.com/maps?q=<?= h($d['latitude']) ?>,<?= h($d['longitude']) ?>&z=11&output=embed" title="Map"></iframe>
          <div class="dr-coords">Latitude <code><?= h($d['latitude']) ?></code> Longitude <code><?= h($d['longitude']) ?></code></div>
        <?php else: ?><p class="dr-empty">No coordinates submitted.</p><?php endif; ?>
      </section>

      <section class="dr-card">
        <div class="dr-card-h"><h3>Submission</h3><i class="fa-solid fa-file-lines"></i></div>
        <div class="dr-rows">
          <div class="dr-row"><span>Agent</span><b><?= h($r['agent_name'] ?: '—') ?></b></div>
          <div class="dr-row"><span>Email</span><b><?= h($r['agent_email'] ?: '—') ?></b></div>
          <div class="dr-row"><span>Phone</span><b><?= h($r['agent_phone'] ?: '—') ?></b></div>
          <div class="dr-row"><span>Requested</span><b><?= fmt_datetime($r['requested_at']) ?></b></div>
          <div class="dr-row"><span>Reviewed</span><b><?= $r['reviewed_at'] ? fmt_datetime($r['reviewed_at']) : '—' ?></b></div>
          <div class="dr-row"><span>Status</span><b><?= h($r['status']) ?></b></div>
        </div>
      </section>
    </aside>
  </div>
</div>

<script>
document.querySelectorAll('.dr-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.dr-tab').forEach(function (t) { t.classList.toggle('on', t === tab); });
    document.querySelectorAll('.dr-pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === tab.dataset.tab); });
  });
});
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>