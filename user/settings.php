<?php
$page_title = 'Settings';
$page_subtitle = 'Your profile details and password';
require_once __DIR__ . '/includes/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $okToken = !empty($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$okToken) {
        $errors[] = 'Your session expired. Please save again.';
    } elseif (($_POST['form'] ?? '') === 'profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if ($first === '' || $last === '') $errors[] = 'First and last name are required.';
        if (!preg_match('/^[0-9+\- ]{7,15}$/', $phone)) $errors[] = 'Enter a valid phone number.';
        if (!$errors) {
            q("UPDATE users SET first_name = " . sql_val($first) . ", last_name = " . sql_val($last) . ", phone = " . sql_val($phone)
            . ", address = " . sql_val(trim($_POST['address'] ?? '')) . ", city = " . sql_val(trim($_POST['city'] ?? '')) . " WHERE user_id = $user_id");
            $_SESSION['user_name'] = $first . ' ' . $last;
            header('Location: settings.php?msg=' . urlencode('Profile updated.') . '&type=success'); exit;
        }
    } elseif (($_POST['form'] ?? '') === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $acc = row("SELECT password_hash FROM accounts WHERE account_id = $user_acc");
        $valid = $acc && ($acc['password_hash'] === $current || password_verify($current, $acc['password_hash']));
        if (!$valid) $errors[] = 'Your current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'The new password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'The new passwords do not match.';
        if (!$errors) {
            q("UPDATE accounts SET password_hash = " . sql_val(password_hash($new, PASSWORD_DEFAULT)) . " WHERE account_id = $user_acc");
            header('Location: settings.php?msg=' . urlencode('Password changed.') . '&type=success'); exit;
        }
    }
}

$u = row("SELECT * FROM users WHERE user_id = $user_id");
$extra_js = [];
include __DIR__ . '/includes/page-start.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-danger anim" style="--d:0"><i class="fa-solid fa-circle-exclamation"></i><span><?= implode('<br>', array_map('h', $errors)) ?></span></div>
<?php endif; ?>

<section class="card card-pad anim" style="--d:0;display:flex;gap:20px;align-items:center;flex-wrap:wrap">
  <div class="ud-avatar-xl"><?= h(initials($user_name)) ?></div>
  <div class="ud-profile-head">
    <div><strong><?= h($user_name) ?></strong><span><?= h($user_email) ?></span></div>
  </div>
</section>

<div class="settings-grid" style="margin-top:22px">
  <form method="POST" class="card card-pad form-section anim" style="--d:1" data-busy>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="form" value="profile">
    <header class="fs-head"><span class="fs-num"><i class="fa-solid fa-user"></i></span><div><h3>Profile</h3><p>Used for bookings and confirmations</p></div></header>
    <div class="form-grid">
      <div class="field"><label class="field-label">First name *</label><input type="text" name="first_name" required value="<?= h($u['first_name'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">Last name *</label><input type="text" name="last_name" required value="<?= h($u['last_name'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">Phone *</label><input type="text" name="phone" required value="<?= h($u['phone'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">City</label><input type="text" name="city" value="<?= h($u['city'] ?? '') ?>"></div>
      <div class="field" style="grid-column:1/-1"><label class="field-label">Address</label><input type="text" name="address" value="<?= h($u['address'] ?? '') ?>"></div>
      <div class="field" style="grid-column:1/-1"><label class="field-label">Email (login)</label><input type="email" value="<?= h($user_email) ?>" disabled></div>
    </div>
    <div class="publish-actions" style="justify-content:flex-end"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save profile</button></div>
  </form>

  <form method="POST" class="card card-pad form-section anim" style="--d:2" data-busy>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="form" value="password">
    <header class="fs-head"><span class="fs-num"><i class="fa-solid fa-lock"></i></span><div><h3>Password</h3><p>Use at least 8 characters</p></div></header>
    <div class="form-grid">
      <div class="field" style="grid-column:1/-1"><label class="field-label">Current password</label><input type="password" name="current_password" required></div>
      <div class="field"><label class="field-label">New password</label><input type="password" name="new_password" minlength="8" required></div>
      <div class="field"><label class="field-label">Confirm new password</label><input type="password" name="confirm_password" minlength="8" required></div>
    </div>
    <div class="publish-actions" style="justify-content:flex-end"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-key"></i> Change password</button></div>
  </form>
</div>
<?php include __DIR__ . '/includes/page-end.php'; ?>
