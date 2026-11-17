<?php
$page_title = 'Dashboard';
$page_subtitle = 'Platform overview, signups and approval queue';
require_once __DIR__ . '/includes/auth.php';

$tourists   = (int) scalar("SELECT COUNT(*) FROM users");
$partners   = (int) scalar("SELECT COUNT(*) FROM agents WHERE status = 'Approved'");
$liveDest   = (int) scalar("SELECT COUNT(*) FROM destinations WHERE status = 'Active'");
$livePkgs   = (int) scalar("SELECT COUNT(*) FROM packages WHERE status = 'Active'");
$pendingAg  = (int) scalar("SELECT COUNT(*) FROM agents WHERE status = 'Pending'");
$pendingDst = (int) scalar("SELECT COUNT(*) FROM destination_requests WHERE status = 'Pending'");
$bookingCnt = (int) scalar("SELECT COUNT(*) FROM bookings");
$revenue    = (float) scalar("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE booking_status IN ('Accepted','Confirmed')");

/* 30-day signup series */
$start = date('Y-m-d', strtotime('-60 days'));
$uMap = $aMap = [];
foreach (rows("SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE created_at >= '$start' GROUP BY d") as $r) $uMap[$r['d']] = (int) $r['c'];
foreach (rows("SELECT DATE(created_at) d, COUNT(*) c FROM agents WHERE created_at >= '$start' GROUP BY d") as $r) $aMap[$r['d']] = (int) $r['c'];
$labels = $tourSeries = $agentSeries = [];
for ($i = 60; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('M j', strtotime($d));
    $tourSeries[] = $uMap[$d] ?? 0;
    $agentSeries[] = $aMap[$d] ?? 0;
}
$newThisWeek = array_sum(array_slice($tourSeries, -7));

$latestBookings = rows("SELECT b.booking_id, b.total_amount, b.booking_status, b.created_at,
                               CONCAT(u.first_name,' ',u.last_name) AS user_name, p.package_name
                        FROM bookings b
                        JOIN users u ON u.user_id = b.user_id
                        JOIN packages p ON p.package_id = b.package_id
                        ORDER BY b.created_at DESC LIMIT 6");
$pendingAgentList = rows("SELECT agent_id, full_name, city, created_at FROM agents WHERE status = 'Pending' ORDER BY created_at DESC LIMIT 4");
$pendingDestList  = rows("SELECT request_id, destination_name, city, state, requested_at FROM destination_requests WHERE status = 'Pending' ORDER BY requested_at DESC LIMIT 4");

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>

<section class="dash-hero anim" style="--d:0">
  <div>
    <p class="dash-eyebrow"><?= date('l') ?></p>
    <h2 class="dash-greet">Welcome back, <span>Administrator</span></h2>
    <p class="dash-sub">
      <?= $pendingAg + $pendingDst ?> item<?= ($pendingAg + $pendingDst) === 1 ? '' : 's' ?> need your review today.
      <?= $newThisWeek ?> new tourist<?= $newThisWeek === 1 ? '' : 's' ?> joined this week.
    </p>
  </div>
  <div class="dash-hero-actions">
    <a class="btn btn-ghost" href="agent-requests.php"><i class="fa-solid fa-file-signature"></i> Review agents</a>
    <a class="btn btn-amber" href="destination-requests.php"><i class="fa-solid fa-inbox"></i> Open queue</a>
  </div>
</section>

<section class="kpi-grid">
  <article class="kpi anim" style="--d:1">
    <div class="kpi-top"><span class="kpi-icon g"><i class="fa-solid fa-users"></i></span><span class="kpi-tag">Tourists</span></div>
    <div class="kpi-value" data-count="<?= $tourists ?>">0</div>
    <div class="kpi-label">Registered tourists</div>
    <div class="kpi-foot"><b>+<?= $newThisWeek ?></b> this week</div>
  </article>
  <article class="kpi anim" style="--d:2">
    <div class="kpi-top"><span class="kpi-icon a"><i class="fa-solid fa-user-tie"></i></span><span class="kpi-tag">Partners</span></div>
    <div class="kpi-value" data-count="<?= $partners ?>">0</div>
    <div class="kpi-label">Onboarded partners</div>
    <div class="kpi-foot"><b><?= $pendingAg ?></b> awaiting review</div>
  </article>
  <article class="kpi anim" style="--d:3">
    <div class="kpi-top"><span class="kpi-icon b"><i class="fa-solid fa-map-location-dot"></i></span><span class="kpi-tag">Catalog</span></div>
    <div class="kpi-value" data-count="<?= $liveDest ?>">0</div>
    <div class="kpi-label">Live destinations</div>
    <div class="kpi-foot"><b><?= $pendingDst ?></b> requests pending</div>
  </article>
  <article class="kpi anim" style="--d:4">
    <div class="kpi-top"><span class="kpi-icon r"><i class="fa-solid fa-box-open"></i></span><span class="kpi-tag">Revenue</span></div>
    <div class="kpi-value" data-count="<?= $livePkgs ?>">0</div>
    <div class="kpi-label">Live packages</div>
    <div class="kpi-foot"><b><?= number_format($bookingCnt) ?></b> bookings · <?= money($revenue) ?></div>
  </article>
</section>

<section class="dash-main">
  <div class="panel anim" style="--d:5">
    <header class="panel-head">
      <div><h3>New signups</h3><p>Tourist and agent registrations over time</p></div>
      <div class="segmented" id="rangeToggle">
        <button type="button" data-range="7">7 days</button>
        <button type="button" class="active" data-range="30">30 days</button>
      </div>
    </header>
    <div class="legend">
      <span><i style="background:#1f8a54"></i>Tourists</span>
      <span><i style="background:#f4a627"></i>Agents</span>
    </div>
    <div class="chart-box"><canvas id="signupChart" aria-label="New signups chart"></canvas></div>
  </div>

  <div class="panel anim" style="--d:6">
    <header class="panel-head">
      <div><h3>Needs approval</h3><p>Latest items waiting for a decision</p></div>
    </header>
    <div class="queue">
      <?php foreach ($pendingAgentList as $ag): ?>
        <a class="queue-item" href="agent-requests.php">
          <span class="queue-icon amber"><i class="fa-solid fa-user-tie"></i></span>
          <div><strong><?= h($ag['full_name']) ?></strong><span>Agent application · <?= h($ag['city'] ?: 'Unknown city') ?></span></div>
          <em><?= time_ago($ag['created_at']) ?></em>
        </a>
      <?php endforeach; ?>
      <?php foreach ($pendingDestList as $dr): ?>
        <a class="queue-item" href="destination-request-view.php?id=<?= (int) $dr['request_id'] ?>">
          <span class="queue-icon blue"><i class="fa-solid fa-map-location-dot"></i></span>
          <div><strong><?= h($dr['destination_name']) ?></strong><span>Destination request · <?= h($dr['city']) ?>, <?= h($dr['state']) ?></span></div>
          <em><?= time_ago($dr['requested_at']) ?></em>
        </a>
      <?php endforeach; ?>
      <?php if (!$pendingAgentList && !$pendingDestList): ?>
        <div class="empty-soft"><i class="fa-solid fa-circle-check"></i><p>You're all caught up. Nothing is waiting for review.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="dash-bottom">
  <div class="panel anim" style="--d:7">
    <header class="panel-head">
      <div><h3>Quick actions</h3><p>Jump straight to the work</p></div>
    </header>
    <div class="actions">
      <a href="destinations.php" class="action"><span class="action-ic g"><i class="fa-solid fa-map-location-dot"></i></span><strong>Destinations</strong><small>Browse and review live places</small></a>
      <a href="destination-requests.php" class="action"><span class="action-ic a"><i class="fa-solid fa-inbox"></i></span><strong>Destination requests</strong><small><?= $pendingDst ?> waiting</small></a>
      <a href="agent-requests.php" class="action"><span class="action-ic b"><i class="fa-solid fa-file-signature"></i></span><strong>Agent requests</strong><small><?= $pendingAg ?> to review</small></a>
      <a href="packages.php" class="action"><span class="action-ic r"><i class="fa-solid fa-box-open"></i></span><strong>Packages</strong><small>Moderate tour packages</small></a>
    </div>
  </div>

  <div class="panel anim" style="--d:8">
    <header class="panel-head">
      <div><h3>Latest bookings</h3><p>Most recent traveller bookings</p></div>
      <a class="btn btn-ghost btn-sm" href="bookings.php">View all</a>
    </header>
    <?php if (!$latestBookings): ?>
      <div class="empty-soft"><i class="fa-solid fa-ticket"></i><p>Bookings will appear here as travellers book packages.</p></div>
    <?php else: ?>
    <ul class="book-list">
      <?php foreach ($latestBookings as $b): ?>
      <li>
        <div class="mini-avatar"><?= h(initials($b['user_name'])) ?></div>
        <div class="book-body">
          <strong><?= h($b['user_name']) ?></strong>
          <span><?= h($b['package_name']) ?></span>
        </div>
        <div class="book-end">
          <span class="num"><?= money($b['total_amount']) ?></span>
          <?= badge($b['booking_status']) ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
  /* Animated count-up numbers */
  document.querySelectorAll('[data-count]').forEach((el) => {
    const end = Number(el.dataset.count), dur = 1100, t0 = performance.now();
    const step = (t) => {
      const p = Math.min(1, (t - t0) / dur), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(end * eased).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });

  /* Signup chart */
  const labels = <?= json_encode($labels) ?>;
  const tourists = <?= json_encode($tourSeries) ?>;
  const agents = <?= json_encode($agentSeries) ?>;
  const ctx = document.getElementById('signupChart');
  if (!ctx || typeof Chart === 'undefined') return;

  const grad = (c, a) => {
    const g = c.createLinearGradient(0, 0, 0, 300);
    g.addColorStop(0, `rgba(${a},.28)`); g.addColorStop(1, `rgba(${a},0)`);
    return g;
  };
  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Tourists', data: tourists, borderColor: '#1f8a54', borderWidth: 2.5, tension: .42, fill: true,
          backgroundColor: (c) => c.chart.ctx ? grad(c.chart.ctx, '31,138,84') : 'rgba(31,138,84,.15)',
          pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: '#fff', pointHoverBorderWidth: 3 },
        { label: 'Agents', data: agents, borderColor: '#f4a627', borderWidth: 2, tension: .42, fill: false,
          pointRadius: 0, pointHoverRadius: 5 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 1100, easing: 'easeOutQuart' },
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false },
        tooltip: { backgroundColor: '#0f3d2a', padding: 12, cornerRadius: 12, titleFont: { family: 'Manrope', weight: '700' }, bodyFont: { family: 'Manrope' } } },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { color: '#7b8a82', maxTicksLimit: 8, font: { family: 'Manrope', size: 11 } } },
        y: { beginAtZero: true, border: { display: false }, ticks: { precision: 0, color: '#7b8a82', font: { family: 'Manrope', size: 11 } }, grid: { color: '#eef3f0' } }
      }
    }
  });

  document.querySelectorAll('#rangeToggle button').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#rangeToggle button').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      const n = Number(btn.dataset.range);
      chart.data.labels = labels.slice(-n);
      chart.data.datasets[0].data = tourists.slice(-n);
      chart.data.datasets[1].data = agents.slice(-n);
      chart.update();
    });
  });
})();
</script>

<?php include __DIR__ . '/includes/page-end.php'; ?>