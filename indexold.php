<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
$currentPage = 'index.php';

function jh($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function j_rows($sql) { global $conn; $r = mysqli_query($conn, $sql); return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : []; }
function j_val($sql) { global $conn; $r = mysqli_query($conn, $sql); $x = $r ? mysqli_fetch_row($r) : null; return $x ? $x[0] : 0; }
function j_img($path) {
    if (!$path) return null;
    $p = ltrim($path, '/');
    return is_file(__DIR__ . '/' . $p) ? $p : null;
}

$dests = j_rows("SELECT d.destination_id, d.destination_name, d.city, d.state,
    (SELECT di.image_path FROM destination_images di WHERE di.destination_id = d.destination_id AND di.status = 'Active'
     ORDER BY di.is_primary DESC, di.display_order LIMIT 1) AS img
    FROM destinations d WHERE d.status = 'Active' ORDER BY d.destination_id DESC LIMIT 7");

$pkgs = j_rows("SELECT p.package_id, p.package_name, p.duration_days, p.duration_nights, p.price_per_person,
    (SELECT pi.image_path FROM package_images pi WHERE pi.package_id = p.package_id AND pi.status = 'Active'
     ORDER BY (pi.image_type = 'Cover') DESC, pi.display_order LIMIT 1) AS img
    FROM packages p WHERE p.status = 'Active' ORDER BY p.package_id DESC LIMIT 6");

$stats = [
    ['Destinations', (int) j_val("SELECT COUNT(*) FROM destinations WHERE status = 'Active'")],
    ['Tour packages', (int) j_val("SELECT COUNT(*) FROM packages WHERE status = 'Active'")],
    ['Trips confirmed', (int) j_val("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Confirmed'")],
    ['Travel partners', (int) j_val("SELECT COUNT(*) FROM agents WHERE status = 'Approved'")],
];
$polaroids = array_slice($dests, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NextTrip · Explore India Smartly</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/navbar.css">
<link rel="stylesheet" href="assets/css/footer.css">
<link rel="stylesheet" href="assets/css/home-journal.css">
<script>document.documentElement.classList.add('js-reveal');</script>
</head>
<body class="jr">
<?php include 'includes/navbar.php'; ?>

<div class="jr-progress" aria-hidden="true"></div>

<main>
  <section class="jr-hero">
    <div class="jr-hero-copy">
      <p class="jr-tag">Smart tourism · India</p>
      <h1>Journeys across <em>incredible</em> India</h1>
      <p class="jr-lede">Find places away from the crowds, plan with verified travel partners, and book with a secure payment at the end.</p>
      <div class="jr-actions">
        <a class="jr-btn jr-btn-main" href="destinations.php" data-login-required data-login-target="destinations.php">Explore destinations <i class="fa-solid fa-arrow-right"></i></a>
        <a class="jr-btn jr-btn-line" href="packages.php" data-login-required data-login-target="packages.php">See packages</a>
      </div>
    </div>

    <div class="jr-album" aria-label="Featured destinations">
      <?php foreach ($polaroids as $i => $d): $src = j_img($d['img']); ?>
        <figure class="jr-polaroid jr-p<?= $i ?>" data-tilt>
          <div class="jr-photo<?= $src ? '' : ' jr-noimg' ?>">
            <?php if ($src): ?><img src="<?= jh($src) ?>" alt="<?= jh($d['destination_name']) ?>" loading="lazy"><?php else: ?><span><?= jh(mb_substr($d['destination_name'], 0, 1)) ?></span><?php endif; ?>
          </div>
          <figcaption><?= jh($d['destination_name']) ?><small><?= jh($d['city']) ?>, <?= jh($d['state']) ?></small></figcaption>
        </figure>
      <?php endforeach; ?>
      <?php if (!$polaroids): ?><p class="jr-empty">Published destinations will appear here.</p><?php endif; ?>
      <div class="jr-stamp" aria-hidden="true"><span>Verified<br>partners</span></div>
    </div>
  </section>

  <section class="jr-tickets" id="stats">
    <?php foreach ($stats as $s): ?>
      <div class="jr-ticket" data-reveal>
        <b data-count="<?= (int) $s[1] ?>"><?= (int) $s[1] ?></b>
        <span><?= jh($s[0]) ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="jr-sec">
    <header class="jr-head" data-reveal>
      <p class="jr-kicker">01 · Destinations</p>
      <h2>Places worth the journey</h2>
    </header>
    <div class="jr-dest">
      <?php if (!$dests): ?><p class="jr-empty">Destinations will appear here once they are published.</p><?php endif; ?>
      <?php foreach ($dests as $i => $d): $src = j_img($d['img']); ?>
        <a class="jr-dcard<?= $i === 0 ? ' jr-feature' : '' ?>" href="place-details.php?id=<?= (int) $d['destination_id'] ?>" data-login-required data-reveal style="--d:<?= $i ?>">
          <div class="jr-dimg<?= $src ? '' : ' jr-noimg' ?>">
            <?php if ($src): ?><img src="<?= jh($src) ?>" alt="<?= jh($d['destination_name']) ?>" loading="lazy"><?php else: ?><span><?= jh(mb_substr($d['destination_name'], 0, 1)) ?></span><?php endif; ?>
          </div>
          <div class="jr-dtext">
            <h3><?= jh($d['destination_name']) ?></h3>
            <p><i class="fa-solid fa-location-dot"></i> <?= jh($d['city']) ?>, <?= jh($d['state']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="jr-sec jr-sec-alt">
    <header class="jr-head" data-reveal>
      <p class="jr-kicker">02 · Packages</p>
      <h2>Ready-made journeys</h2>
    </header>
    <div class="jr-plist">
      <?php if (!$pkgs): ?><p class="jr-empty">Packages will appear here once they are published.</p><?php endif; ?>
      <?php foreach ($pkgs as $p): $src = j_img($p['img']); ?>
        <a class="jr-prow" href="package-details.php?id=<?= (int) $p['package_id'] ?>" data-login-required data-reveal>
          <div class="jr-pthumb<?= $src ? '' : ' jr-noimg' ?>">
            <?php if ($src): ?><img src="<?= jh($src) ?>" alt="<?= jh($p['package_name']) ?>" loading="lazy"><?php else: ?><span><?= jh(mb_substr($p['package_name'], 0, 1)) ?></span><?php endif; ?>
          </div>
          <div class="jr-pname">
            <h3><?= jh($p['package_name']) ?></h3>
            <p><?= (int) $p['duration_days'] ?> days · <?= (int) $p['duration_nights'] ?> nights</p>
          </div>
          <div class="jr-pprice">
            <small>from</small>
            <b>₹<?= number_format((float) $p['price_per_person']) ?></b>
            <span>per adult</span>
          </div>
          <i class="fa-solid fa-arrow-right jr-parrow" aria-hidden="true"></i>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="jr-sec">
    <header class="jr-head" data-reveal>
      <p class="jr-kicker">03 · How it works</p>
      <h2>From idea to departure</h2>
    </header>
    <div class="jr-route">
      <svg class="jr-path" viewBox="0 0 1000 110" preserveAspectRatio="none" aria-hidden="true">
        <path d="M80 55 C 260 0, 360 110, 500 55 S 740 0, 920 55" fill="none" stroke="#1f8a54" stroke-width="2.5" stroke-dasharray="2 10" stroke-linecap="round"/>
      </svg>
      <div class="jr-stop" data-reveal><span>1</span><h3>Choose</h3><p>Browse destinations and packages, and save your favourites.</p></div>
      <div class="jr-stop" data-reveal><span>2</span><h3>Request</h3><p>Send a booking request to a verified travel partner.</p></div>
      <div class="jr-stop" data-reveal><span>3</span><h3>Travel</h3><p>Pay securely once accepted, then get your receipt and go.</p></div>
    </div>
  </section>

  <section class="jr-sec jr-post-wrap">
    <article class="jr-post" data-reveal>
      <div class="jr-post-text">
        <p class="jr-kicker">Travel partners</p>
        <h2>Show travellers the places you know best</h2>
        <p>Register as an agent, list your destinations and packages, and receive booking requests directly.</p>
      </div>
      <a class="jr-btn jr-btn-main" href="auth/register.php?role=agent">Register as partner <i class="fa-solid fa-arrow-right"></i></a>
    </article>
  </section>
</main>

<?php include 'includes/footer.php'; ?>
<script src="assets/js/navbar.js"></script>
<script src="assets/js/login-gate.js"></script>
<script src="assets/js/home-journal.js"></script>
</body>
</html>