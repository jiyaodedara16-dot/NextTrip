<?php
$page_title = 'Dashboard';
$page_subtitle = 'Your destinations, packages, booking requests and notifications';
require_once __DIR__ . '/includes/auth.php';

/* ---- Counts ---- */
$destCnt    = (int) scalar("SELECT COUNT(*) FROM destinations WHERE created_by = $agent_acc");
$pkgCnt     = (int) scalar("SELECT COUNT(*) FROM packages WHERE agent_id = $agent_id");
$bookCnt    = (int) scalar("SELECT COUNT(*) FROM bookings WHERE agent_id = $agent_id");
$reqCnt     = (int) scalar("SELECT COUNT(*) FROM bookings WHERE agent_id = $agent_id AND booking_status = 'Pending'");
$reviewCnt  = (int) scalar("SELECT COUNT(*) FROM package_reviews pr JOIN packages p ON p.package_id = pr.package_id
                            WHERE p.agent_id = $agent_id AND pr.status = 'Approved'");
$pendingDst = (int) scalar("SELECT COUNT(*) FROM destination_requests WHERE agent_id = $agent_id AND status = 'Pending'");

/* ---- Destinations per category (whole numbers) ---- */
$cats = rows("SELECT c.category_name AS name, COUNT(DISTINCT dc.destination_id) AS n
              FROM destination_categories dc
              JOIN destinations d ON d.destination_id = dc.destination_id AND d.created_by = $agent_acc
              JOIN categories c ON c.category_id = dc.category_id
              GROUP BY c.category_id, c.category_name ORDER BY n DESC");
$uncat = (int) scalar("SELECT COUNT(*) FROM destinations d WHERE d.created_by = $agent_acc
                       AND NOT EXISTS (SELECT 1 FROM destination_categories dc WHERE dc.destination_id = d.destination_id)");
if ($uncat > 0) $cats[] = ['name' => 'Uncategorised', 'n' => $uncat];
$catLabels = array_column($cats, 'name');
$catCounts = array_map('intval', array_column($cats, 'n'));

/* ---- Monthly bookings, last 6 months (real data) ---- */
$byMonth = [];
foreach (rows("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM bookings
               WHERE agent_id = $agent_id AND created_at >= DATE_FORMAT(NOW() - INTERVAL 5 MONTH, '%Y-%m-01')
               GROUP BY ym") as $r) {
    $byMonth[$r['ym']] = (int) $r['c'];
}
$mLabels = [];
$mData = [];
for ($i = 5; $i >= 0; $i--) {
    $t = strtotime("first day of -$i month");
    $mLabels[] = date('M Y', $t);
    $mData[] = $byMonth[date('Y-m', $t)] ?? 0;
}

/* ---- Notifications feed: admin decisions + new traveller booking requests ---- */
$notes = rows("SELECT title, message, link, is_read, created_at FROM notifications
               WHERE account_id = $agent_acc ORDER BY created_at DESC LIMIT 6");
$newReqs = rows("SELECT b.created_at, b.total_amount, CONCAT(u.first_name, ' ', u.last_name) AS traveller, p.package_name
                 FROM bookings b
                 JOIN users u ON u.user_id = b.user_id
                 JOIN packages p ON p.package_id = b.package_id
                 WHERE b.agent_id = $agent_id AND b.booking_status = 'Pending'
                 ORDER BY b.created_at DESC LIMIT 5");
$feed = [];
foreach ($notes as $n) {
    $feed[] = ['icon' => 'fa-bell', 'tone' => 'g', 'title' => $n['title'], 'text' => $n['message'],
               'time' => $n['created_at'], 'link' => $n['link'] ?: 'notifications.php', 'unread' => !$n['is_read']];
}
foreach ($newReqs as $r) {
    $feed[] = ['icon' => 'fa-bell-concierge', 'tone' => 'a', 'title' => 'New booking request',
               'text' => $r['traveller'] . ' · ' . $r['package_name'], 'time' => $r['created_at'],
               'link' => 'bookings.php', 'unread' => false];
}
usort($feed, fn($a, $b) => strcmp((string) $b['time'], (string) $a['time']));
$feed = array_slice($feed, 0, 8);

$pendingList = rows("SELECT b.booking_id, b.total_amount, b.travel_date, CONCAT(u.first_name, ' ', u.last_name) AS traveller, p.package_name
                     FROM bookings b JOIN users u ON u.user_id = b.user_id JOIN packages p ON p.package_id = b.package_id
                     WHERE b.agent_id = $agent_id AND b.booking_status = 'Pending' ORDER BY b.created_at DESC LIMIT 4");

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>

<section class="dash-hero anim" style="--d:0">
  <div>
    <p class="dash-eyebrow"><?= date('l, d F') ?></p>
    <h2 class="dash-greet">Welcome back, <span><?= h(explode(' ', $agent_name)[0]) ?></span></h2>
    <p class="dash-sub">
      <?= $reqCnt ?> booking request<?= $reqCnt === 1 ? '' : 's' ?> waiting for your decision ·
      <?= $pendingDst ?> destination<?= $pendingDst === 1 ? '' : 's' ?> in admin review
    </p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-ghost" href="destination-add.php"><i class="fa-solid fa-plus"></i> Add destination</a>
    <a class="btn btn-amber" href="bookings.php"><i class="fa-solid fa-bell-concierge"></i> Review requests</a>
  </div>
</section>

<section class="kpi-grid kpi-5">
  <article class="kpi anim" style="--d:1">
    <div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-map-location-dot"></i></span><span class="kpi-tag">Catalog</span></div>
    <div class="kpi-value" data-count="<?= $destCnt ?>">0</div>
    <div class="kpi-label">Destinations</div>
    <div class="kpi-foot"><b><?= $pendingDst ?></b> awaiting admin review</div>
  </article>
  <article class="kpi anim" style="--d:2">
    <div class="kpi-top"><span class="kpi-icon b"><i class="fa-solid fa-box-open"></i></span><span class="kpi-tag">Catalog</span></div>
    <div class="kpi-value" data-count="<?= $pkgCnt ?>">0</div>
    <div class="kpi-label">Tour packages</div>
    <div class="kpi-foot"><a href="packages.php">Manage packages →</a></div>
  </article>
  <article class="kpi anim" style="--d:3">
    <div class="kpi-top"><span class="kpi-icon a"><i class="fa-solid fa-ticket"></i></span><span class="kpi-tag">Sales</span></div>
    <div class="kpi-value" data-count="<?= $bookCnt ?>">0</div>
    <div class="kpi-label">Bookings received</div>
    <div class="kpi-foot"><b><?= $reqCnt ?></b> pending decision</div>
  </article>
  <article class="kpi anim" style="--d:4">
    <div class="kpi-top"><span class="kpi-icon r"><i class="fa-solid fa-bell-concierge"></i></span><span class="kpi-tag">Requests</span></div>
    <div class="kpi-value" data-count="<?= $reqCnt ?>">0</div>
    <div class="kpi-label">Booking requests</div>
    <div class="kpi-foot"><a href="bookings.php">Review now →</a></div>
  </article>
  <article class="kpi anim" style="--d:5">
    <div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-star"></i></span><span class="kpi-tag">Feedback</span></div>
    <div class="kpi-value" data-count="<?= $reviewCnt ?>">0</div>
    <div class="kpi-label">Traveller reviews</div>
    <div class="kpi-foot">On your approved packages</div>
  </article>
</section>

<section class="dash-main">
  <div class="panel anim" style="--d:6">
    <header class="panel-head">
      <div><h3>Monthly booking trend</h3><p>Tour bookings received over the last 6 months</p></div>
      <span class="chip green"><i class="fa-regular fa-calendar"></i> Last 6 months</span>
    </header>
    <div class="chart-box"><canvas id="bookingTrend" aria-label="Monthly booking trend"></canvas></div>
  </div>

  <div class="panel anim" style="--d:7">
    <header class="panel-head">
      <div><h3>Destinations by category</h3><p>How your destinations are spread</p></div>
    </header>
    <?php if (!$catCounts || array_sum($catCounts) === 0): ?>
      <div class="empty-soft"><i class="fa-solid fa-chart-pie"></i><p>Add destinations with categories to see the breakdown.</p></div>
    <?php else: ?>
    <div class="donut-wrap">
      <div class="donut-box">
        <canvas id="categoryDonut" aria-label="Destinations by category"></canvas>
        <div class="donut-center"><strong><?= array_sum($catCounts) ?></strong><span>Total</span></div>
      </div>
      <ul class="donut-legend">
        <?php $total = max(1, array_sum($catCounts)); foreach ($catLabels as $i => $lbl): ?>
          <li><i style="background:<?= ['#0f4a30','#1f8a54','#3fae7a','#7fd1a4','#b6e6cb','#f4a627','#cfe9dc'][$i % 7] ?>"></i>
            <span><?= h($lbl) ?></span><b><?= $catCounts[$i] ?></b></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="dash-bottom">
  <div class="panel anim" style="--d:8">
    <header class="panel-head">
      <div><h3>Notifications</h3><p>Admin decisions and new traveller requests</p></div>
      <a class="btn btn-ghost btn-sm" href="notifications.php">View all</a>
    </header>
    <?php if (!$feed): ?>
      <div class="empty-soft"><i class="fa-regular fa-bell"></i><p>No notifications yet.</p></div>
    <?php else: ?>
    <ul class="feed">
      <?php foreach ($feed as $f): ?>
        <li class="<?= $f['unread'] ? 'unread' : '' ?>">
          <a href="<?= h($f['link']) ?>">
            <span class="feed-ic <?= $f['tone'] ?>"><i class="fa-solid <?= $f['icon'] ?>"></i></span>
            <div class="feed-body"><strong><?= h($f['title']) ?></strong><span><?= h($f['text']) ?></span></div>
            <em><?= time_ago($f['time']) ?></em>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <div class="panel anim" style="--d:9">
    <header class="panel-head">
      <div><h3>Waiting for your decision</h3><p>Booking requests from travellers</p></div>
      <a class="btn btn-ghost btn-sm" href="bookings.php">All requests</a>
    </header>
    <?php if (!$pendingList): ?>
      <div class="empty-soft"><i class="fa-solid fa-circle-check"></i><p>No pending requests. You're all caught up.</p></div>
    <?php else: ?>
    <ul class="book-list">
      <?php foreach ($pendingList as $b): ?>
        <li>
          <div class="mini-avatar"><?= h(initials($b['traveller'])) ?></div>
          <div class="book-body"><strong><?= h($b['traveller']) ?></strong><span><?= h($b['package_name']) ?> · travel <?= fmt_date($b['travel_date']) ?></span></div>
          <div class="book-end"><span class="num"><?= money($b['total_amount']) ?></span><a class="btn btn-soft btn-sm" href="bookings.php">Decide</a></div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
  document.querySelectorAll('[data-count]').forEach((el) => {
    const end = Number(el.dataset.count), t0 = performance.now(), dur = 1000;
    const step = (t) => {
      const p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * e).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
  if (typeof Chart === 'undefined') return;

  const trend = document.getElementById('bookingTrend');
  if (trend) {
    new Chart(trend, {
      type: 'line',
      data: {
        labels: <?= json_encode($mLabels) ?>,
        datasets: [{
          label: 'Bookings', data: <?= json_encode($mData) ?>,
          borderColor: '#1f8a54', borderWidth: 2.5, tension: .4, fill: true,
          backgroundColor: (c) => {
            const g = c.chart.ctx.createLinearGradient(0, 0, 0, 260);
            g.addColorStop(0, 'rgba(31,138,84,.28)'); g.addColorStop(1, 'rgba(31,138,84,0)');
            return g;
          },
          pointRadius: 5, pointBackgroundColor: '#fff', pointBorderColor: '#1f8a54', pointBorderWidth: 2.5,
          pointHoverRadius: 7
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1100, easing: 'easeOutQuart' },
        plugins: { legend: { display: false },
          tooltip: { backgroundColor: '#0f3d2a', padding: 12, cornerRadius: 12 } },
        scales: {
          x: { grid: { display: false }, border: { display: false }, ticks: { color: '#7b8a82', font: { family: 'Manrope' } } },
          y: { beginAtZero: true, border: { display: false }, ticks: { precision: 0, color: '#7b8a82', font: { family: 'Manrope' } }, grid: { color: '#eef3f0' } }
        }
      }
    });
  }

  const donut = document.getElementById('categoryDonut');
  if (donut) {
    new Chart(donut, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{ data: <?= json_encode($catCounts) ?>,
          backgroundColor: ['#0f4a30','#1f8a54','#3fae7a','#7fd1a4','#b6e6cb','#f4a627','#cfe9dc'],
          borderColor: '#fff', borderWidth: 3, hoverOffset: 10 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '68%',
        animation: { animateRotate: true, duration: 1100 },
        plugins: { legend: { display: false },
          tooltip: { backgroundColor: '#0f3d2a', padding: 10, cornerRadius: 10,
            callbacks: { label: (c) => ` ${c.label}: ${c.parsed} destination${c.parsed === 1 ? '' : 's'}` } } }
      }
    });
  }
})();
</script>

<?php include __DIR__ . '/includes/page-end.php'; ?>
