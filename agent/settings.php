<?php
$page_title = 'Settings';
$page_subtitle = 'Your profile, contact details and password';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please save again.';
    } elseif (($_POST['form'] ?? '') === 'profile') {
        $name = trim($_POST['full_name'] ?? '');
        if ($name === '') $errors[] = 'Full name is required.';
        if (!$errors) {
            q("UPDATE agents SET full_name = " . sql_val($name) . ", phone = " . sql_val(trim($_POST['phone'] ?? ''))
            . ", address = " . sql_val(trim($_POST['address'] ?? '')) . ", city = " . sql_val(trim($_POST['city'] ?? ''))
            . " WHERE agent_id = $agent_id");
            header('Location: settings.php?msg=' . urlencode('Profile updated.') . '&type=success'); exit;
        }
    } elseif (($_POST['form'] ?? '') === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $acc = row("SELECT password_hash FROM accounts WHERE account_id = $agent_acc");
        $okCurrent = $acc && ($acc['password_hash'] === $current || password_verify($current, $acc['password_hash']));
        if (!$okCurrent) $errors[] = 'Your current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'The new passwords do not match.';
        if (!$errors) {
            q("UPDATE accounts SET password_hash = " . sql_val(password_hash($new, PASSWORD_DEFAULT)) . " WHERE account_id = $agent_acc");
            header('Location: settings.php?msg=' . urlencode('Password changed.') . '&type=success'); exit;
        }
    }
}

include __DIR__ . '/includes/page-start.php';
$a = row("SELECT * FROM agents WHERE agent_id = $agent_id");
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('h', $errors)) ?></span></div>
<?php endif; ?>

<div class="settings-grid">
  <form method="POST" class="card card-pad form-section" data-busy>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="form" value="profile">
    <header class="fs-head"><span class="fs-num"><i class="fa-solid fa-user"></i></span><div><h3>Profile</h3><p>Shown to travellers and on your bookings</p></div></header>
    <div class="form-grid">
      <div class="field wide"><label class="field-label">Full name *</label><input type="text" name="full_name" required value="<?= h($a['full_name'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">Phone</label><input type="text" name="phone" value="<?= h($a['phone'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">City</label><input type="text" name="city" value="<?= h($a['city'] ?? '') ?>"></div>
      <div class="field wide"><label class="field-label">Address</label><input type="text" name="address" value="<?= h($a['address'] ?? '') ?>"></div>
      <div class="field wide"><label class="field-label">Login email</label><input type="email" value="<?= h($agent_email) ?>" disabled></div>
    </div>
    <div class="publish-actions" style="justify-content:flex-end;margin-top:6px">
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save profile</button>
    </div>
  </form>

  <form method="POST" class="card card-pad form-section" data-busy>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="form" value="password">
    <header class="fs-head"><span class="fs-num"><i class="fa-solid fa-lock"></i></span><div><h3>Password</h3><p>Use at least 8 characters</p></div></header>
    <div class="form-grid">
      <div class="field wide"><label class="field-label">Current password</label><input type="password" name="current_password" required></div>
      <div class="field"><label class="field-label">New password</label><input type="password" name="new_password" minlength="8" required></div>
      <div class="field"><label class="field-label">Confirm new password</label><input type="password" name="confirm_password" minlength="8" required></div>
    </div>
    <div class="publish-actions" style="justify-content:flex-end;margin-top:6px">
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-key"></i> Change password</button>
    </div>
  </form>
</div>

<section class="card card-pad" style="margin-top:22px">
  <header class="fs-head"><span class="fs-num"><i class="fa-solid fa-shield-halved"></i></span><div><h3>Account status</h3><p>Your partner account details</p></div></header>
  <dl class="kv" style="margin-top:14px">
    <div><dt>Status</dt><dd><?= badge($a['status'] ?? 'Approved') ?></dd></div>
    <div><dt>Approved on</dt><dd><?= fmt_date($a['reviewed_at'] ?? null) ?></dd></div>
    <div><dt>Partner since</dt><dd><?= fmt_date($a['created_at'] ?? null) ?></dd></div>
  </dl>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>
