<?php
$page_title = 'Dashboard';
$page_subtitle = 'Your favourites, bookings and recent activity';
require_once __DIR__ . '/includes/auth.php';

$favD = (int) scalar("SELECT COUNT(*) FROM favorites f JOIN destinations d ON d.destination_id = f.destination_id WHERE f.user_id = $user_id AND d.status = 'Active'");
$favP = (int) scalar("SELECT COUNT(*) FROM gallery_favorites gf JOIN gallery_photos gp ON gp.gallery_photo_id = gf.gallery_photo_id WHERE gf.user_id = $user_id AND gp.status = 'Active'");
$favK = (int) scalar("SELECT COUNT(*) FROM package_favorites pf JOIN packages p ON p.package_id = pf.package_id WHERE pf.user_id = $user_id AND p.status = 'Active'");

$statusCounts = [];
foreach (rows("SELECT booking_status s, COUNT(*) c FROM bookings WHERE user_id = $user_id GROUP BY booking_status") as $r) $statusCounts[$r['s']] = (int) $r['c'];
$bookTotal = array_sum($statusCounts);
$confirmed = $statusCounts['Confirmed'] ?? 0;
$accepted  = $statusCounts['Accepted'] ?? 0;

$upcoming = rows("SELECT b.booking_id, b.travel_date, b.total_amount, p.package_name
                  FROM bookings b JOIN packages p ON p.package_id = b.package_id
                  WHERE b.user_id = $user_id AND b.booking_status = 'Confirmed' AND b.travel_date >= CURDATE()
                  ORDER BY b.travel_date LIMIT 3");
$activity = rows("SELECT h.status, h.changed_at, h.booking_id, p.package_name
                  FROM booking_status_history h JOIN bookings b ON b.booking_id = h.booking_id JOIN packages p ON p.package_id = b.package_id
                  WHERE b.user_id = $user_id ORDER BY h.changed_at DESC LIMIT 8");
$feedText = [
    'Pending'   => ['Request sent', 'Waiting for the agent to review it.'],
    'Accepted'  => ['Request accepted', 'Pay now to confirm your trip.'],
    'Confirmed' => ['Trip confirmed', 'Payment received. See you on your trip!'],
    'Rejected'  => ['Request not accepted', 'The agent could not take this booking.'],
    'Cancelled' => ['Booking cancelled', ''],
];
$donutLabels = ['Pending', 'Accepted', 'Confirmed', 'Rejected', 'Cancelled'];
$donutData   = array_map(fn($s) => $statusCounts[$s] ?? 0, $donutLabels);

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<section class="dash-hero anim" style="--d:0">
  <div>
    <p class="dash-eyebrow"><?= date('l, d F') ?></p>
    <h2 class="dash-greet">Hello, <span><?= h(explode(' ', $user_name)[0]) ?></span></h2>
    <p class="dash-sub">
      <?= $accepted ? "You have $accepted trip" . ($accepted === 1 ? '' : 's') . ' ready to pay for.' : 'Ready for your next adventure?' ?>
      <?= $confirmed ? " · $confirmed confirmed trip" . ($confirmed === 1 ? '' : 's') . '.' : '' ?>
    </p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-ghost" href="packages.php"><i class="fa-solid fa-heart"></i> My favourites</a>
    <a class="btn btn-amber" href="bookings.php"><i class="fa-solid fa-ticket"></i> My bookings</a>
  </div>
</section>

<section class="kpi-grid ud-kpis">
  <article class="kpi anim" style="--d:1">
    <div class="kpi-top"><span class="kpi-icon r"><i class="fa-solid fa-heart"></i></span><span class="kpi-tag">Saved</span></div>
    <div class="kpi-value" data-count="<?= $favD ?>">0</div>
    <div class="kpi-label">Favourite destinations</div>
    <div class="kpi-foot"><a href="destinations.php">View all →</a></div>
  </article>
  <article class="kpi anim" style="--d:2">
    <div class="kpi-top"><span class="kpi-icon b"><i class="fa-solid fa-images"></i></span><span class="kpi-tag">Saved</span></div>
    <div class="kpi-value" data-count="<?= $favP ?>">0</div>
    <div class="kpi-label">Liked photos</div>
    <div class="kpi-foot"><a href="gallery.php">Open gallery →</a></div>
  </article>
  <article class="kpi anim" style="--d:3">
    <div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-box-open"></i></span><span class="kpi-tag">Saved</span></div>
    <div class="kpi-value" data-count="<?= $favK ?>">0</div>
    <div class="kpi-label">Favourite packages</div>
    <div class="kpi-foot"><a href="packages.php">Browse saved →</a></div>
  </article>
  <article class="kpi anim" style="--d:4">
    <div class="kpi-top"><span class="kpi-icon a"><i class="fa-solid fa-ticket"></i></span><span class="kpi-tag">Trips</span></div>
    <div class="kpi-value" data-count="<?= $bookTotal ?>">0</div>
    <div class="kpi-label">Total bookings</div>
    <div class="kpi-foot"><b><?= $confirmed ?></b> confirmed · <b><?= $accepted ?></b> to pay</div>
  </article>
  <article class="kpi anim" style="--d:5">
    <div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-plane-departure"></i></span><span class="kpi-tag">Upcoming</span></div>
    <div class="kpi-value" data-count="<?= count($upcoming) ?>">0</div>
    <div class="kpi-label">Upcoming trips</div>
    <div class="kpi-foot"><?= $upcoming ? 'Next: ' . fmt_date($upcoming[0]['travel_date']) : 'Nothing booked yet' ?></div>
  </article>
</section>

<section class="dash-main">
  <div class="panel anim" style="--d:6">
    <header class="panel-head">
      <div><h3>Recent activity</h3><p>Updates on your booking requests and payments</p></div>
    </header>
    <div id="activity"></div>
    <?php if (!$activity): ?>
      <div class="empty-soft"><i class="fa-regular fa-bell"></i><p>No activity yet. Book a package to get started.</p></div>
    <?php else: ?>
      <?php foreach ($activity as $a):
        [$title, $text] = $feedText[$a['status']] ?? [$a['status'], ''];
        $dot = $a['status'] === 'Pending' ? 'pending' : ($a['status'] === 'Rejected' ? 'rejected' : '');
      ?>
        <div class="ud-feed-item">
          <span class="ud-feed-dot <?= $dot ?>"></span>
          <div>
            <em><?= time_ago($a['changed_at']) ?></em>
            <strong><?= h($title) ?> · #<?= (int) $a['booking_id'] ?></strong>
            <span><?= h($a['package_name']) ?><?= $text ? ' — ' . h($text) : '' ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="panel anim" style="--d:7">
    <header class="panel-head">
      <div><h3>Booking status</h3><p>All your trips at a glance</p></div>
    </header>
    <?php if (!$bookTotal): ?>
      <div class="empty-soft"><i class="fa-solid fa-chart-pie"></i><p>Your booking breakdown will appear here.</p></div>
    <?php else: ?>
      <div class="donut-wrap">
        <div class="donut-box">
          <canvas id="statusDonut" aria-label="Booking status breakdown"></canvas>
          <div class="donut-center"><strong><?= $bookTotal ?></strong><span>Bookings</span></div>
        </div>
        <ul class="donut-legend">
          <?php $colors = ['#f4a627', '#2a7fb8', '#1f8a54', '#d64545', '#9aa6a0'];
          foreach ($donutLabels as $i => $lbl): if (!$donutData[$i]) continue; ?>
            <li><i style="background:<?= $colors[$i] ?>"></i><span><?= $lbl ?></span><b><?= $donutData[$i] ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="dash-bottom">
  <div class="panel anim" style="--d:8">
    <header class="panel-head">
      <div><h3>Upcoming trips</h3><p>Confirmed bookings ahead of you</p></div>
      <a class="btn btn-ghost btn-sm" href="bookings.php">All bookings</a>
    </header>
    <?php if (!$upcoming): ?>
      <div class="empty-soft"><i class="fa-solid fa-umbrella-beach"></i><p>No upcoming trips. Find your next adventure in packages.</p></div>
    <?php else: ?>
      <ul class="book-list">
        <?php foreach ($upcoming as $u): ?>
          <li>
            <div class="mini-avatar"><i class="fa-solid fa-plane"></i></div>
            <div class="book-body"><strong><?= h($u['package_name']) ?></strong><span>Travel <?= fmt_date($u['travel_date']) ?></span></div>
            <div class="book-end"><span class="num"><?= money($u['total_amount']) ?></span><span class="ud-pill confirmed">Confirmed</span></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="panel anim" style="--d:9">
    <header class="panel-head">
      <div><h3>Quick actions</h3><p>Jump straight to what you need</p></div>
    </header>
    <div class="actions">
      <a href="destinations.php" class="action"><span class="action-ic r"><i class="fa-solid fa-heart"></i></span><strong>Favourite places</strong><small><?= $favD ?> saved</small></a>
      <a href="gallery.php" class="action"><span class="action-ic b"><i class="fa-solid fa-images"></i></span><strong>Liked photos</strong><small><?= $favP ?> saved</small></a>
      <a href="packages.php" class="action"><span class="action-ic g"><i class="fa-solid fa-box-open"></i></span><strong>Saved packages</strong><small><?= $favK ?> saved</small></a>
      <a href="settings.php" class="action"><span class="action-ic a"><i class="fa-solid fa-user-gear"></i></span><strong>Edit profile</strong><small>Details and password</small></a>
    </div>
  </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
  document.querySelectorAll('[data-count]').forEach((el) => {
    const end = Number(el.dataset.count), t0 = performance.now();
    const step = (t) => { const p = Math.min(1, (t - t0) / 1000), e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * e).toLocaleString(); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  });
  const c = document.getElementById('statusDonut');
  if (c && typeof Chart !== 'undefined') {
    new Chart(c, { type: 'doughnut', data: { labels: <?= json_encode($donutLabels) ?>,
      datasets: [{ data: <?= json_encode($donutData) ?>, backgroundColor: ['#f4a627', '#2a7fb8', '#1f8a54', '#d64545', '#9aa6a0'], borderColor: '#fff', borderWidth: 3, hoverOffset: 10 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '68%', animation: { animateRotate: true, duration: 1000 },
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f3d2a', padding: 10, cornerRadius: 10 } } } });
  }
})();
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
