<?php
$page_title = 'Write a Review';
$page_subtitle = 'Share how your trip went, destination by destination';
require_once __DIR__ . '/includes/auth.php';

$bid = (int) ($_GET['booking_id'] ?? 0);
$b = row("SELECT b.booking_id, b.booking_status, b.travel_date, b.package_id, p.package_name, p.duration_days, ag.full_name AS agent_name
          FROM bookings b JOIN packages p ON p.package_id = b.package_id JOIN agents ag ON ag.agent_id = b.agent_id
          WHERE b.booking_id = $bid AND b.user_id = $user_id");
if (!$b) { header('Location: bookings.php?msg=' . urlencode('Booking not found.') . '&type=error'); exit; }

$endDate = date('Y-m-d', strtotime($b['travel_date'] . ' +' . (int) $b['duration_days'] . ' days'));
$ended   = $b['booking_status'] === 'Confirmed' && $endDate <= date('Y-m-d');

$dests = rows("SELECT d.destination_id, d.destination_name, d.city, d.state,
                      (SELECT di.image_path FROM destination_images di WHERE di.destination_id = d.destination_id AND di.status = 'Active'
                         ORDER BY di.is_primary DESC, di.display_order LIMIT 1) AS img
               FROM package_destinations pd JOIN destinations d ON d.destination_id = pd.destination_id
               WHERE pd.package_id = " . (int) $b['package_id'] . " ORDER BY pd.display_order");

$myDest = [];
foreach (rows("SELECT * FROM destination_reviews WHERE booking_id = $bid") as $r) $myDest[(int) $r['destination_id']] = $r;
$myPkg  = row("SELECT * FROM package_reviews WHERE booking_id = $bid");
$reviewed = $myPkg || $myDest;

$timeOptions = [];
for ($h = 0; $h <= 23; $h++) $timeOptions[sprintf('%02d:00', $h)] = date('g A', strtotime(sprintf('%02d:00', $h)));
$crowdOptions = ['Very Low', 'Low', 'Medium', 'High', 'Very High'];

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<style>
  .rv { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; --g1:#0f4a30; --g2:#146339; --g3:#1f8a54; --g4:#2fb573; --gs:#e8f6ee; --gb:#cfe9d9; --mut:#6b7a72; --line:#e3ebe6; --amb:#f4a627; --red:#d64545; }
  .rv *, .rv *::before, .rv *::after { box-sizing: border-box; }
  .rv h2, .rv h3 { font-family: 'Fraunces', Georgia, serif; margin: 0; }
  .rv p { margin: 0; }
  .rv-back { display: inline-flex; align-items: center; gap: 8px; color: var(--g2); font-weight: 700; font-size: 13.5px; text-decoration: none; }
  .rv-hero { margin-top: 14px; padding: 26px 28px; border-radius: 22px; background: linear-gradient(135deg, var(--g1), var(--g3)); color: #fff; display: flex; justify-content: space-between; align-items: center; gap: 18px; flex-wrap: wrap; box-shadow: 0 24px 40px -26px rgba(15,74,48,.7); animation: rv-rise .6s cubic-bezier(.22,1,.36,1) both; }
  .rv-hero h2 { font-size: 26px; }
  .rv-hero p { font-size: 13.5px; opacity: .9; margin-top: 6px; }
  .rv-hero-tag { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3); padding: 8px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 800; }
  .rv-notice { margin-top: 18px; padding: 16px 18px; border-radius: 16px; background: #fdf3dd; color: #8a5d05; border: 1px solid #f6e4bd; font-size: 14px; line-height: 1.6; }
  .rv-section-h { display: flex; align-items: center; gap: 12px; margin: 30px 0 14px; }
  .rv-section-h span { width: 36px; height: 36px; border-radius: 12px; background: var(--gs); color: var(--g3); display: grid; place-items: center; }
  .rv-section-h h3 { font-size: 21px; }
  .rv-section-h p { font-size: 13px; color: var(--mut); }
  .rv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; }
  .rv-card { background: #fff; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; box-shadow: 0 12px 28px -22px rgba(16,24,20,.3); animation: rv-rise .6s cubic-bezier(.22,1,.36,1) both; animation-delay: calc(var(--i, 0) * 80ms); transition: transform .3s, box-shadow .3s; }
  .rv-card:hover { transform: translateY(-3px); box-shadow: 0 22px 40px -24px rgba(16,24,20,.38); }
  .rv-card-img { height: 120px; background: linear-gradient(135deg, #e6f5ec, #cfe9d9); position: relative; }
  .rv-card-img img { width: 100%; height: 100%; object-fit: cover; }
  .rv-card-body { padding: 18px 20px 20px; display: flex; flex-direction: column; gap: 12px; }
  .rv-card-title { font-family: 'Fraunces', Georgia, serif; font-size: 19px; font-weight: 700; }
  .rv-card-meta { font-size: 12.5px; color: var(--mut); }
  .rv-label { display: block; font-size: 12px; font-weight: 800; letter-spacing: .04em; color: #33443b; margin-bottom: 6px; }
  .rv-field textarea, .rv-field select, .rv-field input[type=text] { width: 100%; font: inherit; font-size: 14px; color: #1d2a24; border: 1.5px solid var(--line); border-radius: 13px; padding: 11px 13px; background: #fbfdfc; transition: border-color .2s, box-shadow .2s; }
  .rv-field textarea { min-height: 96px; resize: vertical; }
  .rv-field textarea:focus, .rv-field select:focus, .rv-field input:focus { outline: none; border-color: var(--g3); box-shadow: 0 0 0 4px rgba(31,138,84,.14); }
  .rv-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .rv-stars { display: inline-flex; gap: 6px; }
  .rv-stars button { border: 0; background: none; padding: 2px; cursor: pointer; color: #d7e0db; font-size: 26px; line-height: 1; transition: transform .2s, color .2s; }
  .rv-stars button:hover { transform: scale(1.15); }
  .rv-stars button.on { color: var(--amb); }
  .rv-stars-text { font-size: 12.5px; color: var(--mut); margin-left: 8px; align-self: center; }
  .rv-pkg { background: linear-gradient(160deg, #fff, var(--gs)); border: 1px solid var(--gb); border-radius: 22px; padding: 24px; display: grid; gap: 16px; animation: rv-rise .6s .2s cubic-bezier(.22,1,.36,1) both; }
  .rv-submit { display: flex; justify-content: flex-end; gap: 12px; margin-top: 26px; align-items: center; flex-wrap: wrap; }
  .rv-btn { font: inherit; font-weight: 800; font-size: 15px; border: 0; border-radius: 14px; padding: 14px 26px; cursor: pointer; color: #fff; background: linear-gradient(135deg, var(--g3), var(--g1)); box-shadow: 0 16px 26px -14px rgba(31,138,84,.95); transition: transform .2s, box-shadow .2s; }
  .rv-btn:hover { transform: translateY(-2px); }
  .rv-btn:disabled { opacity: .6; cursor: wait; transform: none; }
  .rv-alert { margin-top: 14px; padding: 12px 16px; border-radius: 12px; background: #fbe9e9; color: #a52d2d; font-size: 13.5px; display: none; }
  .rv-alert.show { display: block; animation: rv-rise .3s ease; }
  .rv-done-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px dashed var(--line); }
  .rv-done-item:last-child { border-bottom: 0; }
  .rv-done-item p { font-size: 13.5px; color: #3f5148; line-height: 1.6; margin-top: 4px; }
  .rv-stars-static { color: var(--amb); font-size: 15px; letter-spacing: 2px; }
  @keyframes rv-rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  @media (max-width: 720px) { .rv-grid { grid-template-columns: 1fr; } .rv-row2 { grid-template-columns: 1fr; } }
  @media (prefers-reduced-motion: reduce) { .rv *, .rv *::before, .rv *::after { animation: none !important; transition: none !important; } }

  /* Stars drawn with a text character, so they show without any icon font */
  .rv-stars button i { display: none; }
  .rv-stars button {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    padding: 0;
    cursor: pointer;
    font-family: system-ui, 'Segoe UI Symbol', sans-serif;
    font-size: 24px;
    line-height: 1;
    color: #b9c8bf;
    transition: transform .2s cubic-bezier(.22,1,.36,1), background .2s, color .2s, border-color .2s;
  }
  .rv-stars button::before { content: '\2605'; }
  .rv-stars button:hover { transform: translateY(-2px) scale(1.06); }
  .rv-stars button.on { color: #f4a627;  border-color: #f6e4bd; }
  .rv-stars { gap: 6px; align-items: center; }
  .rv-stars-text { font-size: 13px; font-weight: 700; color: #6b7a72; margin-left: 10px; }
</style>

<div class="rv">
  <a class="rv-back" href="bookings.php"><i class="fa-solid fa-arrow-left"></i> Back to my bookings</a>

  <header class="rv-hero">
    <div>
      <h2><?= h($b['package_name']) ?></h2>
      <p>Booking #<?= (int) $b['booking_id'] ?> · Travelled <?= fmt_date($b['travel_date']) ?> – <?= fmt_date($endDate) ?> · <?= h($b['agent_name']) ?></p>
    </div>
    <span class="rv-hero-tag"><?= $reviewed ? 'Review submitted' : ($ended ? 'Ready to review' : 'Trip upcoming') ?></span>
  </header>

  <?php if (!$ended): ?>
    <div class="rv-notice"><i class="fa-regular fa-calendar"></i> Reviews open after your trip ends on <strong><?= fmt_date($endDate) ?></strong>. Come back then to share your experience.</div>

  <?php elseif ($reviewed): ?>
    <div class="rv-section-h"><span><i class="fa-solid fa-star"></i></span><div><h3>Your reviews</h3><p>Thank you for sharing your experience. These are now published.</p></div></div>
    <div class="rv-grid">
      <?php foreach ($dests as $i => $d): $r = $myDest[(int) $d['destination_id']] ?? null; if (!$r) continue; ?>
        <article class="rv-card" style="--i: <?= $i ?>">
          <div class="rv-card-img"><?php if ($d['img']): ?><img src="<?= h(asset_url($d['img'])) ?>" alt=""><?php endif; ?></div>
          <div class="rv-card-body">
            <div class="rv-card-title"><?= h($d['destination_name']) ?></div>
            <div class="rv-stars-static"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></div>
            <p class="rv-done-item" style="border:0;padding:0"><?= h($r['review_text']) ?></p>
            <div class="rv-card-meta">Visited around <?= date('H:i', strtotime($r['visit_time'])) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($myPkg): ?>
      <div class="rv-section-h" style="margin-top:28px"><span><i class="fa-solid fa-box-open"></i></span><div><h3>Your package review</h3></div></div>
      <div class="rv-pkg">
        <div><div class="rv-stars-static"><?= str_repeat('★', (int) $myPkg['rating']) . str_repeat('☆', 5 - (int) $myPkg['rating']) ?></div>
          <?php if ($myPkg['review_title']): ?><h3 style="font-size:19px;margin-top:6px"><?= h($myPkg['review_title']) ?></h3><?php endif; ?>
          <p style="margin-top:8px;color:#3f5148;line-height:1.7"><?= h($myPkg['review_text']) ?></p></div>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <form id="rvForm" novalidate>
      <input type="hidden" name="booking_id" value="<?= (int) $bid ?>">

      <div class="rv-section-h"><span><i class="fa-solid fa-map-location-dot"></i></span><div><h3>Rate each destination</h3><p>Tell other travellers what each place was really like.</p></div></div>
      <div class="rv-grid">
        <?php foreach ($dests as $i => $d): $did = (int) $d['destination_id']; ?>
        <article class="rv-card" style="--i: <?= $i ?>" data-dest="<?= $did ?>">
          <div class="rv-card-img"><?php if ($d['img']): ?><img src="<?= h(asset_url($d['img'])) ?>" alt=""><?php endif; ?></div>
          <div class="rv-card-body">
            <div>
              <div class="rv-card-title"><?= h($d['destination_name']) ?></div>
              <div class="rv-card-meta"><?= h($d['city']) ?>, <?= h($d['state']) ?></div>
            </div>
            <div>
              <span class="rv-label">Your rating</span>
              <div class="rv-stars" data-target="dest_<?= $did ?>">
                <?php for ($s = 1; $s <= 5; $s++): ?><button type="button" data-v="<?= $s ?>" aria-label="<?= $s ?> star"><i class="bi bi-star-fill"></i></button><?php endfor; ?>
                <span class="rv-stars-text">Tap a star</span>
              </div>
              <input type="hidden" name="dest[<?= $did ?>][rating]" value="">
            </div>
            <div class="rv-field">
              <label class="rv-label" for="t<?= $did ?>">Your experience</label>
              <textarea id="t<?= $did ?>" name="dest[<?= $did ?>][text]" maxlength="1000" placeholder="What did you see, eat, or enjoy here?"></textarea>
            </div>
            <div class="rv-row2">
              <div class="rv-field">
                <label class="rv-label" for="v<?= $did ?>">Time you visited</label>
                <select id="v<?= $did ?>" name="dest[<?= $did ?>][visit_time]">
                  <option value="">Optional</option>
                  <?php foreach ($timeOptions as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="rv-field">
                <label class="rv-label" for="c<?= $did ?>">Crowd level</label>
                <select id="c<?= $did ?>" name="dest[<?= $did ?>][crowd_level]">
                  <option value="">Optional</option>
                  <?php foreach ($crowdOptions as $cl): ?><option value="<?= $cl ?>"><?= $cl ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <div class="rv-section-h" style="margin-top:34px"><span><i class="fa-solid fa-box-open"></i></span><div><h3>Overall package review</h3><p>How was the package as a whole: the agent, the plan, and the value?</p></div></div>
      <div class="rv-pkg">
        <div>
          <span class="rv-label">Overall rating</span>
          <div class="rv-stars" data-target="package">
            <?php for ($s = 1; $s <= 5; $s++): ?><button type="button" data-v="<?= $s ?>" aria-label="<?= $s ?> star"><i class="bi bi-star-fill"></i></button><?php endfor; ?>
            <span class="rv-stars-text">Tap a star</span>
          </div>
          <input type="hidden" name="package_rating" value="">
        </div>
        <div class="rv-field">
          <label class="rv-label" for="pTitle">Headline <span style="font-weight:600;color:var(--mut)">(optional)</span></label>
          <input id="pTitle" type="text" name="package_title" maxlength="150" placeholder="e.g. A relaxing houseboat escape">
        </div>
        <div class="rv-field">
          <label class="rv-label" for="pText">Your review</label>
          <textarea id="pText" name="package_text" maxlength="2000" placeholder="Tell other travellers about the package, the agent, and the experience overall."></textarea>
        </div>
      </div>

      <div class="rv-alert" id="rvAlert" role="alert"></div>
      <div class="rv-submit">
        <a href="bookings.php" class="rv-back" style="margin-right:auto">Cancel</a>
        <button type="submit" class="rv-btn" id="rvSubmit"><i class="fa-solid fa-paper-plane"></i> Publish my reviews</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<script>
(function () {
  var labels = ['Tap a star', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

  document.querySelectorAll('.rv-stars').forEach(function (group) {
    var input = group.parentElement.querySelector('input[type=hidden]');
    var buttons = group.querySelectorAll('button');
    var text = group.querySelector('.rv-stars-text');
    function paint(v) {
      buttons.forEach(function (b, i) { b.classList.toggle('on', i < v); });
      text.textContent = labels[v] || labels[0];
    }
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        var v = parseInt(b.getAttribute('data-v'), 10);
        input.value = v;
        paint(v);
      });
      b.addEventListener('mouseenter', function () { paint(parseInt(b.getAttribute('data-v'), 10)); });
    });
    group.addEventListener('mouseleave', function () { paint(parseInt(input.value, 10) || 0); });
  });

  var form = document.getElementById('rvForm');
  if (!form) return;
  var alertBox = document.getElementById('rvAlert');
  var submit = document.getElementById('rvSubmit');

  function fail(msg) {
    alertBox.textContent = msg;
    alertBox.classList.add('show');
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    alertBox.classList.remove('show');

    var problem = null;
    document.querySelectorAll('[data-dest]').forEach(function (card) {
      if (problem) return;
      var id = card.getAttribute('data-dest');
      var rating = parseInt(form.querySelector('input[name="dest[' + id + '][rating]"]').value, 10);
      var text = form.querySelector('textarea[name="dest[' + id + '][text]"]').value.trim();
      var name = card.querySelector('.rv-card-title').textContent;
      if (!rating) problem = 'Please rate ' + name + '.';
      else if (text.length < 10) problem = 'Please write at least 10 characters about ' + name + '.';
    });
    if (!problem && !parseInt(form.package_rating.value, 10)) problem = 'Please give the overall package a star rating.';
    if (!problem && form.package_text.value.trim().length < 10) problem = 'Please write at least 10 characters about the package.';
    if (problem) { fail(problem); return; }

    submit.disabled = true;
    var original = submit.innerHTML;
    submit.textContent = 'Publishing…';

    fetch('../ajax/submit_booking_review.php', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
      .then(function (r) {
  return r.text().then(function (text) {
    try { return JSON.parse(text); }
    catch (e) { return { success: false, message: 'Server returned an unexpected response.', detail: text.replace(/<[^>]+>/g, ' ').trim().slice(0, 300) }; }
  });
})
      .then(function (data) {
        if (data.success) {
          if (window.NT) NT.toast(data.message);
          setTimeout(function () { location.href = 'bookings.php?reviewed=1'; }, 900);
        } else {
          fail(data.message || 'Could not save your reviews.');
          submit.disabled = false;
          submit.innerHTML = original;
        }
      })
      .catch(function () {
        fail('Network problem. Please try again.');
        submit.disabled = false;
        submit.innerHTML = original;
      });
  });
})();
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
