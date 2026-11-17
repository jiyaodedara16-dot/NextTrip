<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();

/* Document row: View opens a preview inside this panel (no new tab) */
function ad_doc($label, $number, $file, $icon) {
    $exists = $file && is_file(__DIR__ . '/../../' . ltrim($file, '/'));
    $num = $number ? h($number) : '<span class="ad-muted">Not provided</span>';

    if (!$file) {
        $state = '<span class="ad-chip idle">No file</span>';
        $preview = '';
    } elseif (!$exists) {
        $state = '<span class="ad-chip bad" title="' . h($file) . '"><i class="fa-solid fa-triangle-exclamation"></i> File missing on server</span>';
        $preview = '';
    } else {
        $url = asset_url($file);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $body = '<iframe class="ad-frame" src="' . h($url) . '" title="' . h($label) . '"></iframe>';
        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $body = '<img class="ad-img" src="' . h($url) . '" alt="' . h($label) . '">';
        } else {
            $body = '<p class="ad-muted">Preview is not available for this file type.</p>';
        }
        $body .= '<a class="ad-doc-btn" href="' . h($url) . '"><i class="fa-solid fa-download"></i> Download</a>';
        $state = '<span class="ad-chip ok"><i class="fa-solid fa-circle-check"></i> Uploaded</span>';
        $preview = '<details class="ad-view"><summary class="ad-doc-btn"><i class="fa-regular fa-eye"></i> View</summary>'
                 . '<div class="ad-preview">' . $body . '</div></details>';
    }

    echo '<div class="ad-doc">'
       . '<span class="ad-ic"><i class="fa-solid ' . $icon . '"></i></span>'
       . '<div class="ad-doc-txt"><small>' . h($label) . '</small><strong>' . $num . '</strong>' . $state . '</div>'
       . ($preview ? '<div class="ad-doc-act">' . $preview . '</div>' : '')
       . '</div>';
}

$id = (int) ($_GET['id'] ?? 0);
$ag = row("SELECT ag.*, a.email FROM agents ag JOIN accounts a ON a.account_id = ag.account_id WHERE ag.agent_id = $id");
if (!$ag) {
    echo '<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span>This record could not be found.</span></div>';
    exit;
}

$accId   = (int) $ag['account_id'];
$destCnt = (int) scalar("SELECT COUNT(*) FROM destinations WHERE created_by = $accId");
$pkgCnt  = (int) scalar("SELECT COUNT(*) FROM packages WHERE agent_id = $id");
$bookCnt = (int) scalar("SELECT COUNT(*) FROM bookings WHERE agent_id = $id");
$revCnt  = (int) scalar("SELECT COUNT(*) FROM package_reviews pr JOIN packages p ON p.package_id = pr.package_id WHERE p.agent_id = $id");

$status    = $ag['status'];
$isPending = $status === 'Pending';
$statusCls = ['Approved' => 'ok', 'Pending' => 'warn', 'Rejected' => 'bad'][$status] ?? 'idle';
$name      = $ag['full_name'];
?>
<style>
  .ad { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; margin: 0; }
  .ad *, .ad *::before, .ad *::after { box-sizing: border-box; }
  .ad-muted { color: #9aa8a0; font-weight: 500; }

  .ad-hero { position: relative; overflow: hidden; border-radius: 18px; padding: 22px; color: #fff; background: linear-gradient(135deg, #0f4a30 0%, #1f8a54 100%); display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
  .ad-hero::after { content: ''; position: absolute; right: -55px; top: -65px; width: 170px; height: 170px; border-radius: 50%; background: rgba(255,255,255,.08); }
  .ad-avatar { width: 68px; height: 68px; border-radius: 20px; flex-shrink: 0; display: grid; place-items: center; font-family: 'Fraunces', Georgia, serif; font-size: 24px; font-weight: 700; color: #0f4a30; background: #f4a627; }
  .ad-who { flex: 1; min-width: 180px; position: relative; z-index: 1; }
  .ad-who h3 { font-family: 'Fraunces', Georgia, serif; font-size: 22px; font-weight: 700; margin: 0; line-height: 1.2; }
  .ad-who p { margin: 5px 0 0; font-size: 13.5px; opacity: .9; word-break: break-all; }
  .ad-state { display: inline-flex; align-items: center; gap: 7px; margin-top: 10px; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3); }
  .ad-state i { width: 7px; height: 7px; border-radius: 50%; background: #7ee2a8; }
  .ad-state.warn { color: #ffe3a3; } .ad-state.warn i { background: #f4a627; }
  .ad-state.bad { color: #ffd1d1; } .ad-state.bad i { background: #ff8d8d; }

  .ad-reason { margin: 14px 0 0; padding: 12px 14px; border-radius: 12px; background: #fbe9e9; border: 1px solid #f2c9c9; color: #a52d2d; font-size: 13.5px; line-height: 1.6; }
  .ad-reason strong { display: block; font-size: 11.5px; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }

  .ad-stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; margin-top: 14px; }
  .ad-stat { background: #fff; border: 1px solid #e3ebe6; border-radius: 14px; padding: 14px 10px; text-align: center; }
  .ad-stat b { display: block; font-family: 'Fraunces', Georgia, serif; font-size: 20px; color: #146339; font-variant-numeric: tabular-nums; }
  .ad-stat span { font-size: 10.5px; color: #6b7a72; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }

  .ad-title { display: flex; align-items: center; gap: 12px; margin: 20px 0 10px; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #1f8a54; }
  .ad-title::after { content: ''; flex: 1; height: 1px; background: #e3ebe6; }

  .ad-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .ad-field { background: #f8fbf9; border: 1px solid #e3ebe6; border-radius: 13px; padding: 11px 13px; display: flex; gap: 11px; align-items: flex-start; min-width: 0; }
  .ad-field.wide { grid-column: 1 / -1; }
  .ad-ic { width: 32px; height: 32px; border-radius: 10px; background: #e8f6ee; color: #1f8a54; display: grid; place-items: center; font-size: 12.5px; flex-shrink: 0; }
  .ad-field small, .ad-doc-txt small { display: block; font-size: 10.5px; font-weight: 800; color: #6b7a72; text-transform: uppercase; letter-spacing: .06em; }
  .ad-field strong, .ad-doc-txt strong { display: block; font-size: 14px; font-weight: 600; margin-top: 2px; word-break: break-word; }

  .ad-docs { display: grid; gap: 10px; }
  .ad-doc { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; background: #fff; border: 1px solid #e3ebe6; border-radius: 14px; padding: 13px 14px; }
  .ad-doc-txt { flex: 1; min-width: 160px; }
  .ad-doc-act { width: 100%; }
  .ad-doc-btn { display: inline-flex; align-items: center; gap: 7px; padding: 8px 13px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 800; color: #146339; background: #e8f6ee; border: 1px solid #cfe9d9; cursor: pointer; list-style: none; }
  .ad-doc-btn:hover { background: #d3eedd; color: #146339; }
  .ad-view summary::-webkit-details-marker { display: none; }
  .ad-doc-act .ad-view + .ad-doc-btn, .ad-view { display: inline-block; }
  .ad-preview { margin-top: 12px; display: grid; gap: 10px; }
  .ad-frame { width: 100%; height: 420px; border: 1px solid #e3ebe6; border-radius: 12px; background: #f3f6f4; }
  .ad-img { width: 100%; max-height: 420px; object-fit: contain; border-radius: 12px; background: #f3f6f4; border: 1px solid #e3ebe6; }
  .ad-preview .ad-doc-btn { justify-self: start; }
  .ad-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; padding: 4px 9px; border-radius: 999px; margin-top: 5px; }
  .ad-chip.ok { color: #146339; background: #e8f6ee; }
  .ad-chip.bad { color: #a52d2d; background: #fbe9e9; }
  .ad-chip.idle { color: #6b7a72; background: #f1f4f2; }

  .ad-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; padding-top: 16px; border-top: 1px solid #e3ebe6; flex-wrap: wrap; }
  .ad-btn { display: inline-flex; align-items: center; gap: 8px; font: inherit; font-size: 14px; font-weight: 800; padding: 11px 18px; border-radius: 12px; border: 1.5px solid transparent; cursor: pointer; }
  .ad-btn-approve { color: #fff; background: linear-gradient(135deg, #1f8a54, #146339); }
  .ad-btn-reject { color: #d64545; background: #fff; border-color: #f2c9c9; }
  .ad-btn-reject:hover { background: #d64545; color: #fff; border-color: #d64545; }

  @media (max-width: 640px) { .ad-stats { grid-template-columns: repeat(2, minmax(0,1fr)); } .ad-grid { grid-template-columns: 1fr; } }
    <style>
  .ad { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; }
  .ad *, .ad *::before, .ad *::after { box-sizing: border-box; }
  .ad > * { margin: 0 !important; }
  .ad-muted { color: #9aa8a0; font-weight: 500; }

  /* Header */
  .ad-hero { position: relative; overflow: hidden; border-radius: 18px; padding: 22px; color: #fff; background: linear-gradient(135deg, #0f4a30, #1f8a54); display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
  .ad-avatar { width: 64px; height: 64px; border-radius: 18px; flex-shrink: 0; display: grid; place-items: center; font-family: 'Fraunces', Georgia, serif; font-size: 22px; font-weight: 700; color: #0f4a30; background: #f4a627; }
  .ad-who { flex: 1; min-width: 180px; }
  .ad-who h3 { font-family: 'Fraunces', Georgia, serif; font-size: 21px; font-weight: 700; line-height: 1.2; color: #fff; }
  .ad-who p { margin-top: 4px; font-size: 13px; opacity: .9; word-break: break-all; }
  .ad-state { display: inline-flex; align-items: center; gap: 7px; margin-top: 10px; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3); color: #fff; }
  .ad-state i { width: 7px; height: 7px; border-radius: 50%; background: #7ee2a8; }
  .ad-state.warn { color: #ffe3a3; } .ad-state.warn i { background: #f4a627; }
  .ad-state.bad { color: #ffd1d1; } .ad-state.bad i { background: #ff8d8d; }

  .ad-reason { padding: 12px 14px; border-radius: 12px; background: #fbe9e9; border: 1px solid #f2c9c9; color: #a52d2d; font-size: 13.5px; line-height: 1.6; }
  .ad-reason strong { display: block; font-size: 11.5px; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }

  /* Stat row */
  .ad-stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; }
  .ad-stat { background: #fff; border: 1px solid #e3ebe6; border-radius: 14px; padding: 14px 10px; text-align: center; }
  .ad-stat b { display: block; font-family: 'Fraunces', Georgia, serif; font-size: 20px; color: #146339; }
  .ad-stat span { font-size: 10.5px; color: #6b7a72; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }

  /* Section titles */
  .ad-title { display: flex; align-items: center; gap: 12px; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #1f8a54; }
  .ad-title::after { content: ''; flex: 1; height: 1px; background: #e3ebe6; }

  /* Info fields: two columns with a real gap */
  .ad-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .ad-field { background: #f8fbf9; border: 1px solid #e3ebe6; border-radius: 13px; padding: 12px 14px; display: flex; gap: 12px; align-items: center; min-width: 0; }
  .ad-field.wide { grid-column: 1 / -1; }
  .ad-ic { width: 34px; height: 34px; border-radius: 10px; background: #e8f6ee; color: #1f8a54; display: grid; place-items: center; font-size: 13px; flex-shrink: 0; }
  .ad-field small, .ad-doc-txt small { display: block; font-size: 10.5px; font-weight: 800; color: #6b7a72; text-transform: uppercase; letter-spacing: .06em; }
  .ad-field strong, .ad-doc-txt strong { display: block; font-size: 14px; font-weight: 600; margin-top: 3px; word-break: break-word; }

  /* Documents: one card per row, icon | text | action */
  .ad-docs { display: flex; flex-direction: column; gap: 12px; }
  .ad-doc { display: grid; grid-template-columns: 34px minmax(0,1fr) auto; align-items: center; gap: 14px; background: #fff; border: 1px solid #e3ebe6; border-radius: 14px; padding: 14px 16px; }
  .ad-doc .ad-ic { width: 34px; height: 34px; }
  .ad-doc-txt { min-width: 0; }
  .ad-doc-act { align-self: start; }

  .ad-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; padding: 4px 9px; border-radius: 999px; margin-top: 6px; }
  .ad-chip.ok { color: #146339; background: #e8f6ee; }
  .ad-chip.bad { color: #a52d2d; background: #fbe9e9; }
  .ad-chip.idle { color: #6b7a72; background: #f1f4f2; }

  .ad-doc-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 14px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 800; color: #146339; background: #e8f6ee; border: 1px solid #cfe9d9; cursor: pointer; list-style: none; white-space: nowrap; }
  .ad-doc-btn:hover { background: #d3eedd; color: #146339; }
  .ad-view summary::-webkit-details-marker { display: none; }
  .ad-view[open] > summary { margin-bottom: 0; }
  .ad-preview { margin-top: 12px; display: flex; flex-direction: column; gap: 10px; }
  .ad-frame { width: 100%; height: 420px; border: 1px solid #e3ebe6; border-radius: 12px; background: #f3f6f4; }
  .ad-img { width: 100%; max-height: 420px; object-fit: contain; border-radius: 12px; background: #f3f6f4; border: 1px solid #e3ebe6; }
  .ad-preview .ad-doc-btn { align-self: flex-start; }

  /* Buttons */
  .ad-actions { display: flex; gap: 10px; justify-content: flex-end; padding-top: 16px; border-top: 1px solid #e3ebe6; }
  .ad-btn { display: inline-flex; align-items: center; gap: 8px; font: inherit; font-size: 14px; font-weight: 800; padding: 11px 18px; border-radius: 12px; border: 1.5px solid transparent; cursor: pointer; }
  .ad-btn-approve { color: #fff; background: linear-gradient(135deg, #1f8a54, #146339); }
  .ad-btn-reject { color: #d64545; background: #fff; border-color: #f2c9c9; }
  .ad-btn-reject:hover { background: #d64545; color: #fff; border-color: #d64545; }

  @media (max-width: 640px) {
    .ad-stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .ad-grid { grid-template-columns: 1fr; }
    .ad-doc { grid-template-columns: 34px minmax(0,1fr); }
    .ad-doc-act { grid-column: 1 / -1; }
  }
</style>
</style>

<div class="ad" style="display:flex;flex-direction:column;gap:16px;">
  <section class="ad-hero">
    <div class="ad-avatar"><?= h(initials($name)) ?></div>
    <div class="ad-who">
      <h3><?= h($name) ?></h3>
      <p><?= h($ag['email']) ?></p>
      <span class="ad-state <?= $statusCls ?>"><i></i><?= h($status) ?></span>
    </div>
  </section>

  <?php if ($status === 'Rejected' && $ag['rejection_reason']): ?>
    <div class="ad-reason"><strong>Rejection reason</strong><?= h($ag['rejection_reason']) ?></div>
  <?php endif; ?>

  <div class="ad-stats">
    <div class="ad-stat"><b><?= $destCnt ?></b><span>Destinations</span></div>
    <div class="ad-stat"><b><?= $pkgCnt ?></b><span>Packages</span></div>
    <div class="ad-stat"><b><?= $bookCnt ?></b><span>Bookings</span></div>
    <div class="ad-stat"><b><?= $revCnt ?></b><span>Reviews</span></div>
  </div>

  <div class="ad-title">Basic information</div>
  <div class="ad-grid">
    <div class="ad-field"><span class="ad-ic"><i class="fa-regular fa-user"></i></span><div><small>Full name</small><strong><?= h($name) ?></strong></div></div>
    <div class="ad-field"><span class="ad-ic"><i class="fa-regular fa-envelope"></i></span><div><small>Email</small><strong><?= h($ag['email']) ?></strong></div></div>
    <div class="ad-field"><span class="ad-ic"><i class="fa-solid fa-phone"></i></span><div><small>Phone</small><strong><?= $ag['phone'] ? h($ag['phone']) : '<span class="ad-muted">Not provided</span>' ?></strong></div></div>
    <div class="ad-field"><span class="ad-ic"><i class="fa-solid fa-city"></i></span><div><small>City</small><strong><?= $ag['city'] ? h($ag['city']) : '<span class="ad-muted">Not provided</span>' ?></strong></div></div>
    <div class="ad-field wide"><span class="ad-ic"><i class="fa-solid fa-location-dot"></i></span><div><small>Address</small><strong><?= $ag['address'] ? h($ag['address']) : '<span class="ad-muted">Not provided</span>' ?></strong></div></div>
    <div class="ad-field"><span class="ad-ic"><i class="fa-regular fa-calendar-plus"></i></span><div><small>Registered</small><strong><?= fmt_datetime($ag['created_at']) ?></strong></div></div>
    <div class="ad-field"><span class="ad-ic"><i class="fa-regular fa-calendar-check"></i></span><div><small>Reviewed</small><strong><?= $ag['reviewed_at'] ? fmt_datetime($ag['reviewed_at']) : '<span class="ad-muted">Not yet</span>' ?></strong></div></div>
  </div>

  <div class="ad-title">Verification documents</div>
  <div class="ad-docs">
    <?php
      ad_doc('Aadhaar number', $ag['aadhaar_number'] ?? null, $ag['aadhaar_file'], 'fa-id-card');
      ad_doc('PAN number', $ag['pan_number'] ?? null, $ag['pan_file'], 'fa-id-card');
      ad_doc(($ag['business_document_type'] ?? null) ?: 'GST / Business certificate', $ag['business_document_number'] ?? null, $ag['business_document_file'], 'fa-file-contract');
    ?>
  </div>

  <?php if ($isPending): ?>
  <div class="ad-actions">
    <button class="ad-btn ad-btn-reject" type="button" data-action="reject" data-url="ajax/agent_request_action.php" data-id="<?= $id ?>" data-name="<?= h($name) ?>"><i class="fa-solid fa-xmark"></i> Reject</button>
    <button class="ad-btn ad-btn-approve" type="button" data-action="approve" data-url="ajax/agent_request_action.php" data-id="<?= $id ?>" data-name="<?= h($name) ?>" data-title="Approve agent"><i class="fa-solid fa-check"></i> Approve</button>
  </div>
  <?php endif; ?>
</div>