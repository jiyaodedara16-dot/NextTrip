<?php
$page_title = 'Booking Receipt';
$page_subtitle = 'Your confirmed booking and payment summary';
require_once __DIR__ . '/includes/auth.php';

$bid = (int) ($_GET['booking_id'] ?? 0);
$b = row("SELECT b.*, p.package_name, p.price_per_person, p.duration_days,
                 ag.full_name AS agent_name, ag.phone AS agent_phone, a.email AS agent_email,
                 u.first_name, u.last_name, u.phone AS user_phone, ua.email AS user_email,
                 (SELECT pm.razorpay_payment_id FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS pay_id,
                 (SELECT pm.paid_at FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS paid_at,
                 (SELECT pm.payment_method FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS pay_method
          FROM bookings b
          JOIN packages p ON p.package_id = b.package_id
          JOIN agents ag ON ag.agent_id = b.agent_id
          JOIN accounts a ON a.account_id = ag.account_id
          JOIN users u ON u.user_id = b.user_id
          JOIN accounts ua ON ua.account_id = u.account_id
          WHERE b.booking_id = $bid AND b.user_id = $user_id");
if (!$b || $b['booking_status'] !== 'Confirmed') {
    header('Location: bookings.php?msg=' . urlencode('A receipt is available once your booking is confirmed.') . '&type=error');
    exit;
}

$site = row("SELECT * FROM website_settings ORDER BY setting_id LIMIT 1") ?: [];
$siteName    = $site['website_name'] ?? 'NextTrip';
$siteEmail   = $site['website_email'] ?? '';
$sitePhone   = $site['website_phone'] ?? '';
$siteAddress = $site['website_address'] ?? '';

$price    = (float) $b['price_per_person'];
$adults   = (int) $b['adult_count'];
$children = (int) $b['child_count'];
$adultAmt = $price * $adults;
$childAmt = $price * 0.5 * $children;
$baseAmt  = $adultAmt + $childAmt;
$discount = (float) $b['discount_amount'];
$subtotal = (float) $b['subtotal'];
$gst      = (float) $b['gst_amount'];
$total    = (float) $b['total_amount'];
$receiptNo = 'NT-' . str_pad((string) $bid, 6, '0', STR_PAD_LEFT);
$sigFile = null;
foreach (glob(__DIR__ . '/../assets/images/signature/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [] as $f) {
    $sigFile = 'assets/images/signature/' . basename($f);
    break;
}
$travellerName = trim($b['first_name'] . ' ' . $b['last_name']);
$endDate = date('d M Y', strtotime($b['travel_date'] . ' +' . (int) $b['duration_days'] . ' days'));

$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<style>
  .rc-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
  .rc-btn { display: inline-flex; align-items: center; gap: 8px; font-family: 'Manrope', system-ui, sans-serif; font-weight: 800; font-size: 14px; border: 0; border-radius: 13px; padding: 12px 20px; cursor: pointer; text-decoration: none; color: #fff; background: linear-gradient(135deg, #1f8a54, #146339); box-shadow: 0 14px 24px -14px rgba(20,99,57,.9); transition: transform .2s, box-shadow .2s; }
  .rc-btn:hover { transform: translateY(-2px); color: #fff; }
  .rc-btn-ghost { background: #fff; color: #146339; border: 1.5px solid #cfe9d9; box-shadow: none; }
  .rc-btn-ghost:hover { background: #e8f6ee; }

  .rc { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; background: #fff; border: 1px solid #e3ebe6; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 50px -34px rgba(16,24,20,.35); width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto; display: flex; flex-direction: column; animation: rc-in .6s cubic-bezier(.22,1,.36,1) both; }
  .rc-body { flex: 1; }
  .rc-sign-img { display: block; height: 64px; width: auto; max-width: 220px; margin: 0 auto 4px; object-fit: contain; }
  @page { size: A4; margin: 12mm; }
  .rc-head { background: linear-gradient(120deg, #0f4a30, #1f8a54); color: #fff; padding: 28px 32px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; flex-wrap: wrap; position: relative; overflow: hidden; }
  .rc-head::after { content: ''; position: absolute; right: -60px; top: -60px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,.07); }
  .rc-brand { display: flex; align-items: center; gap: 12px; }
  .rc-brand-mark { width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(135deg, #f4a627, #d98218); display: grid; place-items: center; font-size: 20px; color: #fff; }
  .rc-brand h1 { font-family: 'Fraunces', Georgia, serif; font-size: 26px; font-weight: 800; margin: 0; }
  .rc-brand small { display: block; font-size: 12px; opacity: .85; margin-top: 2px; }
  .rc-title { text-align: right; }
  .rc-title strong { display: block; font-size: 12px; letter-spacing: .14em; text-transform: uppercase; opacity: .8; }
  .rc-title span { font-family: 'Fraunces', Georgia, serif; font-size: 22px; font-weight: 700; }
  .rc-status { display: inline-flex; align-items: center; gap: 7px; margin-top: 8px; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.18); font-size: 12px; font-weight: 800; }

  .rc-body { padding: 28px 32px; }
  .rc-section { margin-bottom: 26px; }
  .rc-section h3 { font-size: 11.5px; letter-spacing: .14em; text-transform: uppercase; color: #1f8a54; font-weight: 800; margin: 0 0 12px; display: flex; align-items: center; gap: 10px; }
  .rc-section h3::after { content: ''; flex: 1; height: 1px; background: #e3ebe6; }
  .rc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; }
  .rc-field small { display: block; font-size: 11.5px; color: #6b7a72; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
  .rc-field strong { display: block; font-size: 14.5px; margin-top: 3px; word-break: break-word; }

  .rc-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .rc-table td { padding: 11px 4px; border-bottom: 1px dashed #e3ebe6; }
  .rc-table td:last-child { text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
  .rc-table .rc-sub td { border-top: 1.5px solid #e3ebe6; font-weight: 800; }
  .rc-table .rc-disc td { color: #146339; }
  .rc-table .rc-total td { border-top: 2px solid #1f8a54; border-bottom: 0; padding-top: 16px; font-size: 16px; }
  .rc-table .rc-total td:last-child { font-family: 'Fraunces', Georgia, serif; font-size: 22px; color: #146339; }
  .rc-muted { color: #6b7a72; font-size: 13px; }
  .rc-code { display: inline-block; margin-left: 8px; padding: 2px 9px; border-radius: 8px; background: #e8f6ee; color: #146339; font-size: 12px; font-weight: 800; letter-spacing: .06em; }

  .rc-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 24px; flex-wrap: wrap; margin-top: 10px; padding-top: 22px; border-top: 1px dashed #e3ebe6; }
  .rc-sign { min-width: 240px; text-align: center; }
  .rc-sign-line { height: 56px; border-bottom: 1.5px solid #1d2a24; margin-bottom: 8px; display: flex; align-items: flex-end; justify-content: center; font-family: 'Fraunces', Georgia, serif; font-style: italic; font-size: 20px; color: #146339; padding-bottom: 4px; }
  .rc-sign small { font-size: 12px; color: #6b7a72; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
  .rc-note { font-size: 12px; color: #6b7a72; line-height: 1.6; max-width: 380px; }
  .rc-thanks { text-align: center; padding: 16px 32px 22px; background: #f7faf8; font-size: 13px; color: #4a5a52; border-top: 1px solid #e3ebe6; }

  @keyframes rc-in { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
  @media (max-width: 640px) { .rc-grid { grid-template-columns: 1fr; } .rc-head, .rc-body { padding: 22px; } .rc-title { text-align: left; } }
  @media print {
    body, .admin-shell, .main { background: #fff !important; }
    .sidebar, .topbar, .no-print, .scrim { display: none !important; }
    .main { margin-left: 0 !important; }
    .content { padding: 0 !important; }
    .rc { box-shadow: none; border: 0; width: 100%; min-height: 0; border-radius: 0; animation: none; }
    .rc-head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>

<div class="rc-toolbar no-print">
  <a class="rc-btn rc-btn-ghost" href="bookings.php"><i class="fa-solid fa-arrow-left"></i> Back to bookings</a>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="rc-btn" type="button" id="rcPdf"><i class="fa-solid fa-file-arrow-down"></i> Download PDF (A4)</button>
    <button class="rc-btn rc-btn-ghost" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
  </div>
</div>

<article class="rc" id="receipt">
  <header class="rc-head">
    <div class="rc-brand">
      <div class="rc-brand-mark"><i class="fa-solid fa-compass"></i></div>
      <div>
        <h1><?= h($siteName) ?></h1>
        <small><?= h($siteAddress ?: 'Smart tourism platform · India') ?></small>
      </div>
    </div>
    <div class="rc-title">
      <strong>Booking receipt</strong>
      <span><?= h($receiptNo) ?></span>
      <div><span class="rc-status"><i class="fa-solid fa-circle-check"></i> Confirmed &amp; paid</span></div>
    </div>
  </header>

  <div class="rc-body">
    <section class="rc-section">
      <h3>Booking details</h3>
      <div class="rc-grid">
        <div class="rc-field"><small>Package</small><strong><?= h($b['package_name']) ?></strong></div>
        <div class="rc-field"><small>Booking ID</small><strong>#<?= (int) $b['booking_id'] ?></strong></div>
        <div class="rc-field"><small>Travel dates</small><strong><?= fmt_date($b['travel_date']) ?> – <?= h($endDate) ?></strong></div>
        <div class="rc-field"><small>Duration</small><strong><?= (int) $b['duration_days'] ?> days</strong></div>
        <div class="rc-field"><small>Travel partner</small><strong><?= h($b['agent_name']) ?></strong></div>
        <div class="rc-field"><small>Booked on</small><strong><?= fmt_date($b['created_at']) ?></strong></div>
      </div>
    </section>

    <section class="rc-section">
      <h3>Traveller</h3>
      <div class="rc-grid">
        <div class="rc-field"><small>Name</small><strong><?= h($travellerName) ?></strong></div>
        <div class="rc-field"><small>Contact number</small><strong><?= h($b['user_phone'] ?: '—') ?></strong></div>
        <div class="rc-field"><small>Email</small><strong><?= h($b['user_email']) ?></strong></div>
        <div class="rc-field"><small>Total persons</small><strong><?= $adults + $children ?> (<?= $adults ?> adult<?= $adults === 1 ? '' : 's' ?><?= $children ? ', ' . $children . ' child' . ($children === 1 ? '' : 'ren') : '' ?>)</strong></div>
      </div>
    </section>

    <section class="rc-section">
      <h3>Payment calculation</h3>
      <table class="rc-table">
        <tr><td><?= $adults ?> adult<?= $adults === 1 ? '' : 's' ?> × <?= money($price) ?></td><td><?= money($adultAmt) ?></td></tr>
        <?php if ($children > 0): ?>
        <tr><td><?= $children ?> child<?= $children === 1 ? '' : 'ren' ?> × <?= money($price * 0.5) ?> (50%)</td><td><?= money($childAmt) ?></td></tr>
        <?php endif; ?>
        <tr class="rc-sub"><td>Package amount</td><td><?= money($baseAmt) ?></td></tr>
        <?php if ($discount > 0): ?>
        <tr class="rc-disc"><td>Coupon discount<?php if ($b['coupon_code']): ?><span class="rc-code"><?= h($b['coupon_code']) ?></span><?php endif; ?></td><td>− <?= money($discount) ?></td></tr>
        <?php endif; ?>
        <tr><td>Subtotal after discount</td><td><?= money($subtotal) ?></td></tr>
        <tr><td>GST (5%)</td><td><?= money($gst) ?></td></tr>
        <tr class="rc-total"><td>Total paid</td><td><?= money($total) ?></td></tr>
      </table>
    </section>

    <section class="rc-section">
      <h3>Payment information</h3>
      <div class="rc-grid">
        <div class="rc-field"><small>Payment ID</small><strong><?= h($b['pay_id'] ?: '—') ?></strong></div>
        <div class="rc-field"><small>Method</small><strong><?= h(['upi' => 'UPI', 'gpay' => 'Google Pay', 'card' => 'Card', 'netbanking' => 'Net banking', 'all' => 'Online'][$b['pay_method']] ?? 'Online') ?></strong></div>
        <div class="rc-field"><small>Paid on</small><strong><?= $b['paid_at'] ? date('d M Y, g:i A', strtotime($b['paid_at'])) : '—' ?></strong></div>
        <div class="rc-field"><small>Currency</small><strong>Indian Rupee (INR)</strong></div>
      </div>
    </section>

    <div class="rc-foot">
      <div class="rc-note">
        This is a computer-generated receipt and is valid without a physical signature.
        For help, contact <?= h($siteEmail ?: 'support') ?><?= $sitePhone ? ' or call ' . h($sitePhone) : '' ?>.
      </div>
      <div class="rc-sign">
        <div class="rc-sign-line">
          <?php if ($sigFile): ?>
            <img class="rc-sign-img" src="<?= h(asset_url($sigFile)) ?>" alt="Signature">
          <?php else: ?>
            <?= h($siteName) ?>
          <?php endif; ?>
        </div>
        <small>Authorised signature</small>
      </div>
    </div>
  </div>

  <div class="rc-thanks">Thank you for travelling with <?= h($siteName) ?>. Have a wonderful trip!</div>
</article>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
document.getElementById('rcPdf')?.addEventListener('click', async function () {
  var btn = this, original = btn.innerHTML;
  btn.disabled = true;
  btn.textContent = 'Preparing PDF…';
  try {
    var el = document.getElementById('receipt');
    var canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
    var jsPDF = window.jspdf.jsPDF;
    var pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    var pageW = 210, pageH = 297, margin = 10;
    var imgW = pageW - margin * 2;
    var imgH = canvas.height * imgW / canvas.width;
    var data = canvas.toDataURL('image/png');
    if (imgH <= pageH - margin * 2) {
      pdf.addImage(data, 'PNG', margin, margin, imgW, imgH);
    } else {
      var k = (pageH - margin * 2) / imgH;
      pdf.addImage(data, 'PNG', (pageW - imgW * k) / 2, margin, imgW * k, imgH * k);
    }
    pdf.save('NextTrip-Receipt-<?= h($receiptNo) ?>.pdf');
  } catch (err) {
    alert('Could not create the PDF. Use Print and choose "Save as PDF".');
  } finally {
    btn.disabled = false;
    btn.innerHTML = original;
  }
});
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
