<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config/config.php';
require_once 'includes/coupon-lib.php';

$pid = (int) ($_GET['id'] ?? 0);
if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php?redirect=' . urlencode('package-booking.php?id=' . $pid));
    exit;
}
$uid  = (int) $_SESSION['user_id'];
$pkg  = bk_row("SELECT p.*, ag.full_name AS agent_name, ag.city AS agent_city, ag.phone AS agent_phone, a.email AS agent_email
                FROM packages p JOIN agents ag ON ag.agent_id = p.agent_id JOIN accounts a ON a.account_id = ag.account_id
                WHERE p.package_id = $pid AND p.status = 'Active'");
$user = bk_row("SELECT u.first_name, u.last_name, u.phone, a.email FROM users u JOIN accounts a ON a.account_id = u.account_id WHERE u.user_id = $uid");
$cover = $pkg ? bk_row("SELECT image_path FROM package_images WHERE package_id = $pid AND status = 'Active' ORDER BY (image_type = 'Cover') DESC, display_order LIMIT 1") : null;
$cat   = $pkg ? bk_row("SELECT c.category_name FROM package_categories pc JOIN categories c ON c.category_id = pc.category_id WHERE pc.package_id = $pid LIMIT 1") : null;
$dests = $pkg ? bk_rows("SELECT d.destination_name, d.city FROM package_destinations pd JOIN destinations d ON d.destination_id = pd.destination_id WHERE pd.package_id = $pid ORDER BY pd.display_order") : [];
$tags  = $pkg ? bk_rows("SELECT item_name FROM package_inclusions WHERE package_id = $pid AND status = 'Active' ORDER BY display_order LIMIT 3") : [];

$e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$resolveImg = function (?string $p): ?string {
    if (empty($p)) return null;
    if (preg_match('#^https?://#', $p)) return $p;
    return is_file(__DIR__ . '/' . ltrim($p, '/')) ? ltrim($p, '/') : null;
};
$destImg = $pkg ? bk_row("SELECT di.image_path FROM package_destinations pd
                          JOIN destination_images di ON di.destination_id = pd.destination_id AND di.status = 'Active'
                          WHERE pd.package_id = $pid
                          ORDER BY pd.display_order, di.is_primary DESC, di.display_order LIMIT 1") : null;
$img = $resolveImg($cover['image_path'] ?? null)
    ?? $resolveImg($destImg['image_path'] ?? null)
    ?? 'assets/images/destinations/placeholder.jpg';
$name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$minDate = date('Y-m-d', strtotime('+1 day'));
$maxA = (int) ($pkg['max_adults'] ?? 0);
$maxC = (int) ($pkg['max_children'] ?? 0);
$places = implode(' · ', array_map(fn($d) => $d['destination_name'], $dests));
$agentInitials = mb_strtoupper(mb_substr($pkg['agent_name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book <?= $e($pkg['package_name'] ?? 'package') ?> · NextTrip</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/navbar.css">
<link rel="stylesheet" href="assets/css/footer.css">
<link rel="stylesheet" href="assets/css/package-booking.css">
</head>
<body class="bkp-page">
<?php include 'includes/navbar.php'; ?>

<main class="bkp">
  <?php if (!$pkg): ?>
    <section class="bkp-missing">
      <i class="bi bi-compass"></i>
      <h1>This package is not available</h1>
      <p>It may have been removed or is not live yet.</p>
      <a class="bkp-btn bkp-btn-primary" href="packages.php">Browse packages</a>
    </section>
  <?php else: ?>

  <!-- Breadcrumb + hero -->
  <header class="bkp-hero">
    <nav class="bkp-crumbs" aria-label="Breadcrumb">
      <a href="index.php">Home</a><i class="bi bi-chevron-right"></i>
      <a href="packages.php">Packages</a><i class="bi bi-chevron-right"></i>
      <a href="package-details.php?id=<?= $pid ?>">Package Details</a><i class="bi bi-chevron-right"></i>
      <span>Book Package</span>
    </nav>
    <p class="bkp-kicker">Your journey starts here</p>
    <h1>Complete Your Booking</h1>
    <p class="bkp-lede">Review your package, share your travel details, and submit your request.</p>

    <ol class="bkp-steps" id="bkpSteps">
      <li class="is-done"><span class="bkp-dot"><i class="bi bi-check-lg"></i></span><div><strong>Package Details</strong><small>Trip selected</small></div></li>
      <li class="is-current"><span class="bkp-dot">2</span><div><strong>Traveller Information</strong><small>Current step</small></div></li>
      <li><span class="bkp-dot">3</span><div><strong>Booking Confirmation</strong><small>Agent review</small></div></li>
    </ol>
  </header>

  <div class="bkp-shell" id="bkpShell">
    <!-- ============ Form ============ -->
    <form id="bkpForm" class="bkp-form" novalidate>
      <input type="hidden" name="package_id" value="<?= $pid ?>">

      <section class="bkp-card bkp-anim" style="--d:0">
        <header class="bkp-card-h">
          <span class="bkp-icon"><i class="bi bi-people"></i></span>
          <div><h2>Traveller Information</h2><p>Tell us who we should contact about this trip.</p></div>
        </header>
        <div class="bkp-field">
          <label for="bkpName">Full name <b>*</b></label>
          <div class="bkp-input"><i class="bi bi-person"></i><input id="bkpName" name="contact_name" type="text" value="<?= $e($name) ?>" autocomplete="name" required></div>
        </div>
        <div class="bkp-grid-2">
          <div class="bkp-field">
            <label for="bkpEmail">Email address <b>*</b></label>
            <div class="bkp-input"><i class="bi bi-envelope"></i><input id="bkpEmail" name="contact_email" type="email" value="<?= $e($user['email'] ?? '') ?>" autocomplete="email" required></div>
          </div>
          <div class="bkp-field">
            <label for="bkpPhone">Mobile number <b>*</b></label>
            <div class="bkp-input bkp-phone"><span class="bkp-prefix">+91</span><input id="bkpPhone" name="contact_phone" type="tel" value="<?= $e($user['phone'] ?? '') ?>" autocomplete="tel" required></div>
          </div>
        </div>
      </section>

      <section class="bkp-card bkp-anim" style="--d:1">
        <header class="bkp-card-h">
          <span class="bkp-icon"><i class="bi bi-calendar-event"></i></span>
          <div><h2>Travel Details</h2><p>Choose when you'd like to travel and who's joining.</p></div>
        </header>
        <div class="bkp-field bkp-date">
          <label for="bkpDate">Travel start date <b>*</b></label>
          <div class="bkp-input"><i class="bi bi-calendar3"></i><input id="bkpDate" name="travel_date" type="date" min="<?= $minDate ?>" required></div>
          <small>Select any available date from tomorrow onwards.</small>
        </div>

        <div class="bkp-guests">
          <div class="bkp-guest">
            <div><strong>Adults</strong><small>Age 13+</small></div>
            <div class="bkp-stepper">
              <button type="button" data-kind="adult" data-d="-1" aria-label="Remove an adult">−</button>
              <output id="bkpAdults">1</output>
              <button type="button" data-kind="adult" data-d="1" aria-label="Add an adult">+</button>
            </div>
          </div>
          <div class="bkp-guest">
            <div><strong>Children</strong><small>Age 3–12 · 50% fare</small></div>
            <div class="bkp-stepper">
              <button type="button" data-kind="child" data-d="-1" aria-label="Remove a child">−</button>
              <output id="bkpChildren">0</output>
              <button type="button" data-kind="child" data-d="1" aria-label="Add a child">+</button>
            </div>
          </div>
        </div>
        <?php if ($maxA || $maxC): ?>
          <p class="bkp-limit"><i class="bi bi-info-circle"></i> This package allows up to <?= $maxA ?: 'any number of' ?> adults and <?= $maxC ?: 'any number of' ?> children.</p>
        <?php endif; ?>

        <div class="bkp-field">
          <label for="bkpNotes">Anything else we should know about your trip? <span class="bkp-opt">Optional</span></label>
          <textarea id="bkpNotes" name="special_requests" rows="4" maxlength="500" placeholder="Dietary preferences, accessibility needs, special occasions…"></textarea>
          <small>We'll share this with your travel agent.</small>
        </div>
      </section>

      <label class="bkp-terms bkp-anim" style="--d:2">
        <input type="checkbox" id="bkpTerms" name="terms" value="1">
        <span>I agree to the <a href="#" class="bkp-link">booking terms and conditions</a> and understand that my booking is subject to agent confirmation.</span>
      </label>

      <div class="bkp-alert" id="bkpAlert" role="alert" hidden></div>

      <button type="button" class="bkp-btn bkp-btn-primary bkp-submit bkp-anim" id="bkpSubmit" style="--d:3">
        <span>Submit Booking Request</span><i class="bi bi-arrow-right"></i>
      </button>
      <p class="bkp-fine bkp-anim" style="--d:3"><i class="bi bi-lock"></i> No payment is taken at this stage. Your request goes to the assigned agent.</p>
    </form>

    <!-- ============ Summary ============ -->
    <aside class="bkp-aside">
      <article class="bkp-card bkp-pkg bkp-anim" style="--d:0">
        <div class="bkp-pkg-img">
          <img src="<?= $e(preg_match('#^https?://#', $img) ? $img : ltrim($img, '/')) ?>" alt="<?= $e($pkg['package_name']) ?>">
          <span class="bkp-badge">Selected package</span>
        </div>
        <div class="bkp-pkg-body">
          <?php if ($cat): ?><p class="bkp-cat"><?= $e(mb_strtoupper($cat['category_name'])) ?></p><?php endif; ?>
          <h3><?= $e($pkg['package_name']) ?></h3>
         
          <div class="bkp-pkg-foot">
            <div><small>Starting from</small><strong><?= '₹' . number_format((float) $pkg['price_per_person'], 2) ?></strong><small> / adult</small></div>
            <a href="package-details.php?id=<?= $pid ?>">View details <i class="bi bi-chevron-right"></i></a>
          </div>
        </div>
      </article>

      <article class="bkp-card bkp-partner bkp-anim" style="--d:1">
        <p class="bkp-cat">Your travel partner</p>
        <div class="bkp-partner-row">
          <span class="bkp-avatar"><?= $e($agentInitials) ?><i class="bi bi-patch-check-fill"></i></span>
          <div><strong><?= $e($pkg['agent_name']) ?></strong><small><?= $e($pkg['agent_city'] ?: 'NextTrip partner') ?></small>
            <span class="bkp-verified"><i class="bi bi-shield-check"></i> Verified travel agent</span></div>
        </div>
        <p class="bkp-note">Your request will be sent to <?= $e(explode(' ', $pkg['agent_name'])[0]) ?> for review. They will confirm availability and guide you through the next steps.</p>
      </article>

      <article class="bkp-card bkp-price bkp-anim" style="--d:2">
        <div class="bkp-price-h"><h3>Price summary</h3><i class="bi bi-tag"></i></div>
        <p class="bkp-muted">All prices in INR</p>

        <div class="bkp-lines">
          <div class="bkp-line"><span id="bkpLineA">1 Adult × ₹<?= number_format((float) $pkg['price_per_person'], 2) ?></span><b id="bkpAdultAmt">—</b></div>
          <div class="bkp-line" id="bkpRowC" hidden><span id="bkpLineC">Children</span><b id="bkpChildAmt">—</b></div>
          <div class="bkp-line bkp-sub"><span>Subtotal</span><b id="bkpBase">—</b></div>
          <div class="bkp-line bkp-discount" id="bkpRowD" hidden><span>Coupon <em id="bkpCodeTag"></em></span><b id="bkpDiscount">—</b></div>
          <div class="bkp-line"><span>GST (5%)</span><b id="bkpGst">—</b></div>
        </div>

        <div class="bkp-total"><div><strong>Total payable</strong><small>Estimated amount</small></div><strong id="bkpTotal" class="bkp-total-amt">—</strong></div>
        <p class="bkp-save" id="bkpSave" hidden></p>

        <div class="bkp-coupon">
          <label for="bkpCoupon">Have a coupon code?</label>
          <div class="bkp-coupon-row" id="bkpCouponRow">
            <input id="bkpCoupon" type="text" maxlength="30" placeholder="Enter promo code" autocomplete="off">
            <button type="button" id="bkpApply" class="bkp-btn bkp-btn-apply">Apply</button>
          </div>
          <div class="bkp-applied" id="bkpApplied" hidden>
            <span><i class="bi bi-check-circle-fill"></i> <strong id="bkpAppliedCode"></strong> applied</span>
            <button type="button" id="bkpRemove">Remove</button>
          </div>
          <p class="bkp-coupon-msg" id="bkpCouponMsg" role="status"></p>
        </div>
      </article>

      <article class="bkp-card bkp-help bkp-anim" style="--d:3">
        <span class="bkp-icon"><i class="bi bi-headset"></i></span>
        <div><strong>Need help with your booking?</strong><small>Call <?= $e($pkg['agent_phone'] ?: 'your travel agent') ?> or email <?= $e($pkg['agent_email']) ?></small></div>
      </article>
    </aside>
  </div>

  <!-- ============ Confirmation ============ -->
  <section class="bkp-done" id="bkpDone" hidden>
    <div class="bkp-done-ic"><i class="bi bi-check-lg"></i></div>
    <p class="bkp-kicker">Step 3 of 3</p>
    <h2>Request sent</h2>
    <p>Booking <strong id="bkpDoneId">#</strong> is with <?= $e($pkg['agent_name']) ?>. You'll receive an email once it's accepted, then you can pay securely from My Bookings.</p>
    <div class="bkp-done-actions">
      <a class="bkp-btn bkp-btn-primary" href="user/bookings.php">Go to My Bookings</a>
      <a class="bkp-btn bkp-btn-ghost" href="packages.php">Browse more packages</a>
    </div>
  </section>

  <script>window.BKP = { packageId: <?= $pid ?>, price: <?= (float) $pkg['price_per_person'] ?>, maxA: <?= $maxA ?>, maxC: <?= $maxC ?> };</script>
  <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
<script src="assets/js/navbar.js"></script>
<?php if ($pkg): ?><script src="assets/js/package-booking.js"></script><?php endif; ?>
</body>
</html>
