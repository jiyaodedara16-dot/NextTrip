<?php
$page_title = 'Create Coupon';
$page_subtitle = 'Set the rules. Admin reviews the coupon before it goes live.';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/coupon-lib.php';

$packages = rows("SELECT package_id, package_name, price_per_person FROM packages WHERE agent_id = $agent_id AND status = 'Active' ORDER BY package_name");
$users    = rows("SELECT u.user_id, u.first_name, u.last_name, a.email FROM users u JOIN accounts a ON a.account_id = u.account_id ORDER BY u.first_name, u.last_name");
$errors = [];
$v = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please submit again.';
    } else {
        $pkgId  = (int) ($_POST['package_id'] ?? 0);
        $code   = strtoupper(trim($_POST['code'] ?? ''));
        $title  = trim($_POST['title'] ?? '');
        $pct    = (float) ($_POST['discount_percent'] ?? 0);
        $minAmt = (float) ($_POST['min_amount'] ?? 0);
        $limit  = (int) ($_POST['usage_limit'] ?? 0);
        $start  = $_POST['start_date'] ?? '';
        $expiry = $_POST['expiry_date'] ?? '';
        $users_ = array_values(array_unique(array_filter(array_map('intval', $_POST['user_ids'] ?? []))));
        $today  = date('Y-m-d');

        $pkg = bk_row("SELECT package_id, package_name FROM packages WHERE package_id = $pkgId AND agent_id = $agent_id AND status = 'Active'");
        if (!$pkg) $errors[] = 'Choose one of your live packages.';
        if (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) $errors[] = 'Code must be 3 to 20 letters or numbers, with no spaces.';
        elseif (bk_row("SELECT coupon_id FROM coupons WHERE code = '" . bk_esc($code) . "'")) $errors[] = 'This coupon code is already in use. Choose another.';
        if ($pct <= 0 || $pct >= COUPON_MAX_PERCENT) $errors[] = 'Discount must be more than 0% and less than ' . COUPON_MAX_PERCENT . '%.';
        if ($minAmt < 0) $errors[] = 'Minimum amount cannot be negative.';
        if ($limit < 1 || $limit > 10000) $errors[] = 'Usage limit must be between 1 and 10,000.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || $start < $today) $errors[] = 'Start date cannot be in the past.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) || $expiry < $start) $errors[] = 'Expiry date must be on or after the start date.';
        if (!$users_) $errors[] = 'Select at least one user who can use this coupon.';
        if (mb_strlen($title) > 120) $errors[] = 'Title must be under 120 characters.';

        if (!$errors) {
            begin_tx();
            $ok = q("INSERT INTO coupons (agent_id, package_id, code, title, discount_percent, min_amount, usage_limit, usage_count,
                                          start_date, expiry_date, approval_status, created_at)
                     VALUES ($agent_id, $pkgId, " . sql_val($code) . ", " . sql_val($title) . ", $pct, $minAmt, $limit, 0, "
                   . sql_val($start) . ", " . sql_val($expiry) . ", 'Pending', NOW())");
            $cid = last_id();
            foreach ($users_ as $uid) {
                $ok = $ok && q("INSERT INTO coupon_users (coupon_id, user_id) VALUES ($cid, $uid)");
            }
            if ($ok) {
                commit_tx();
                notify_account($agent_acc, 'coupon_submitted', 'Coupon sent for approval',
                    "Coupon $code for \"{$pkg['package_name']}\" is waiting for admin approval.", 'coupons.php');
                header('Location: coupons.php?msg=' . urlencode('Coupon submitted for admin approval.') . '&type=success');
                exit;
            }
            rollback_tx();
            $errors[] = 'Could not save the coupon. Please try again.';
        }
    }
}

$selectedUsers = array_map('intval', $_POST['user_ids'] ?? []);
include __DIR__ . '/includes/page-start.php';
?>
<a class="dv-back" href="coupons.php" style="display:inline-flex;gap:8px;color:#146339;font-weight:700;font-size:13.5px;text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Back to coupons</a>

<?php if ($errors): ?>
  <div class="alert alert-danger" style="margin-top:14px"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('h', $errors)) ?></span></div>
<?php endif; ?>

<?php if (!$packages): ?>
  <div class="alert alert-info" style="margin-top:14px"><i class="fa-solid fa-circle-info"></i><span>You need a live package before creating a coupon. <a href="package-add.php">Add a package</a> first.</span></div>
<?php endif; ?>

<form method="POST" class="add-form" data-busy style="margin-top:14px">
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">1</span><div><h3>Package</h3><p>A coupon works only on the package you select</p></div></header>
    <div class="field"><label class="field-label">Package *</label>
      <select name="package_id" required style="width:100%">
        <option value="">Select a package</option>
        <?php foreach ($packages as $p): ?>
          <option value="<?= (int) $p['package_id'] ?>" <?= (int) ($v['package_id'] ?? 0) === (int) $p['package_id'] ? 'selected' : '' ?>><?= h($p['package_name']) ?> · <?= money($p['price_per_person']) ?> per person</option>
        <?php endforeach; ?>
      </select>
    </div>
  </section>

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">2</span><div><h3>Offer</h3><p>Code, discount and conditions</p></div></header>
    <div class="form-grid">
      <div class="field"><label class="field-label">Coupon code *</label>
        <input type="text" name="code" required maxlength="20" pattern="[A-Za-z0-9]{3,20}" value="<?= h($v['code'] ?? '') ?>" placeholder="SUMMER20" style="text-transform:uppercase;letter-spacing:.06em;font-weight:700"></div>
      <div class="field"><label class="field-label">Title</label>
        <input type="text" name="title" maxlength="120" value="<?= h($v['title'] ?? '') ?>" placeholder="Summer houseboat offer"></div>
      <div class="field"><label class="field-label">Discount (%) *</label>
        <input type="number" name="discount_percent" min="0.5" max="49.99" step="0.5" required value="<?= h($v['discount_percent'] ?? '') ?>" placeholder="e.g. 15">
        <small class="muted" style="font-size:11.5px">Must be less than 50%.</small></div>
      <div class="field"><label class="field-label">Minimum booking amount (₹)</label>
        <input type="number" name="min_amount" min="0" step="1" value="<?= h($v['min_amount'] ?? '0') ?>">
        <small class="muted" style="font-size:11.5px">Set 0 for no minimum.</small></div>
      <div class="field"><label class="field-label">Usage limit *</label>
        <input type="number" name="usage_limit" min="1" max="10000" required value="<?= h($v['usage_limit'] ?? '1') ?>">
        <small class="muted" style="font-size:11.5px">How many times the coupon can be used in total.</small></div>
      <div class="field"><label class="field-label">Maximum discount</label>
        <input type="text" value="No maximum limit" disabled></div>
      <div class="field"><label class="field-label">Start date *</label>
        <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" required value="<?= h($v['start_date'] ?? date('Y-m-d')) ?>"></div>
      <div class="field"><label class="field-label">Expiry date *</label>
        <input type="date" name="expiry_date" min="<?= date('Y-m-d') ?>" required value="<?= h($v['expiry_date'] ?? '') ?>"></div>
    </div>
  </section>

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">3</span><div><h3>Eligible users</h3><p>Only the users you select can apply this coupon</p></div>
      <span class="muted" id="userCount" style="font-size:12.5px;font-weight:700"></span></header>
    <div class="search-box" style="max-width:360px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="userSearch" placeholder="Search users by name or email"></div>
    <div class="user-pick" id="userPick">
      <?php foreach ($users as $u): $name = trim($u['first_name'] . ' ' . $u['last_name']); ?>
        <label class="user-opt" data-search="<?= h(strtolower($name . ' ' . $u['email'])) ?>">
          <input type="checkbox" name="user_ids[]" value="<?= (int) $u['user_id'] ?>" <?= in_array((int) $u['user_id'], $selectedUsers, true) ? 'checked' : '' ?>>
          <span class="mini-avatar"><?= h(initials($name)) ?></span>
          <span><strong><?= h($name) ?></strong><small><?= h($u['email']) ?></small></span>
          <i class="fa-solid fa-check user-tick"></i>
        </label>
      <?php endforeach; ?>
      <?php if (!$users): ?><p class="muted">No users have registered yet.</p><?php endif; ?>
    </div>
    <div class="toolbar" style="margin-top:6px"><button type="button" class="btn btn-ghost btn-sm" id="selAll">Select all shown</button>
      <button type="button" class="btn btn-ghost btn-sm" id="selNone">Clear</button></div>
  </section>

  <div class="publish-bar">
    <div><strong>Send for approval</strong><span>The coupon becomes active only after admin approves it.</span></div>
    <div class="publish-actions">
      <a class="btn btn-ghost" href="coupons.php">Cancel</a>
      <button class="btn btn-primary" type="submit" <?= !$packages ? 'disabled' : '' ?>><i class="fa-solid fa-paper-plane"></i> Submit coupon</button>
    </div>
  </div>
</form>

<style>
  .user-pick { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; max-height: 320px; overflow-y: auto; padding: 4px; margin-top: 12px; }
  .user-opt { position: relative; display: flex; align-items: center; gap: 12px; padding: 11px 12px; border: 1.5px solid #e3ebe6; border-radius: 14px; cursor: pointer; background: #fff; transition: border-color .2s, background .2s, transform .2s; }
  .user-opt:hover { transform: translateY(-2px); border-color: #cfe3d7; }
  .user-opt input { position: absolute; opacity: 0; pointer-events: none; }
  .user-opt > span:nth-child(3) { flex: 1; min-width: 0; }
  .user-opt strong { display: block; font-size: 13.5px; }
  .user-opt small { display: block; font-size: 11.5px; color: #6b7a72; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .user-tick { color: transparent; font-size: 12px; transition: color .2s, transform .3s; }
  .user-opt:has(input:checked) { border-color: #1f8a54; background: #e6f5ec; }
  .user-opt:has(input:checked) .user-tick { color: #1f8a54; transform: scale(1.15); }
  .user-opt.hidden { display: none; }
</style>
<script>
(function () {
  const list = document.getElementById('userPick');
  const search = document.getElementById('userSearch');
  const count = document.getElementById('userCount');
  const opts = () => [...list.querySelectorAll('.user-opt')];
  const refresh = () => { const n = list.querySelectorAll('input:checked').length; count.textContent = n + ' selected'; };
  search.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    opts().forEach((o) => o.classList.toggle('hidden', q !== '' && !o.dataset.search.includes(q)));
  });
  list.addEventListener('change', refresh);
  document.getElementById('selAll').addEventListener('click', () => {
    opts().filter((o) => !o.classList.contains('hidden')).forEach((o) => { o.querySelector('input').checked = true; });
    refresh();
  });
  document.getElementById('selNone').addEventListener('click', () => { opts().forEach((o) => { o.querySelector('input').checked = false; }); refresh(); });
  refresh();
})();
</script>
<?php include __DIR__ . '/includes/page-end.php'; ?>
