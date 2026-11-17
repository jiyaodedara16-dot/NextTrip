<?php
$page_title = 'Website Settings';
$page_subtitle = 'Changes here update the footer, contact page and every place that shows this information';
require_once __DIR__ . '/includes/auth.php';

$s = row("SELECT * FROM website_settings WHERE setting_id = 1") ?: [];
$errors = [];
$values = $s;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'website_name'        => trim($_POST['website_name'] ?? ''),
        'website_description' => trim($_POST['website_description'] ?? ''),
        'website_email'       => trim($_POST['website_email'] ?? ''),
        'website_phone'       => trim($_POST['website_phone'] ?? ''),
        'website_address'     => trim($_POST['website_address'] ?? ''),
        'facebook_url'        => trim($_POST['facebook_url'] ?? ''),
        'instagram_url'       => trim($_POST['instagram_url'] ?? ''),
    ];
    if ($values['website_name'] === '') $errors[] = 'Website name is required.';
    if ($values['website_email'] !== '' && !filter_var($values['website_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    foreach (['facebook_url', 'instagram_url'] as $k) {
        if ($values[$k] !== '' && !preg_match('#^https?://#i', $values[$k])) $errors[] = 'Social links must start with http:// or https://.';
    }

    if (!$errors) {
        $sets = [];
        foreach ($values as $k => $v) $sets[] = "$k = " . sql_val($v);
        $adminId = (int) $_SESSION['admin_id'];
        if (q("UPDATE website_settings SET " . implode(', ', $sets) . ", updated_by = $adminId, updated_at = NOW() WHERE setting_id = 1")) {
            header('Location: settings.php?msg=' . urlencode('Website settings saved. Changes are live across the site.') . '&type=success');
            exit;
        }
        $errors[] = 'Could not save the settings. Please try again.';
    }
}

include __DIR__ . '/includes/page-start.php';
?>
<form method="POST" class="settings-form">
  <?php if ($errors): ?>
    <div class="alert alert-danger" style="margin-bottom:18px"><i class="fa-solid fa-circle-exclamation"></i>
      <span><?= implode('<br>', array_map('h', $errors)) ?></span></div>
  <?php endif; ?>

  <div class="settings-grid">
    <section class="card card-pad">
      <div class="set-head"><div class="stat-icon green"><i class="fa-solid fa-globe"></i></div>
        <div><h3>Website identity</h3><p class="muted">Name and short description used in the footer and listings.</p></div></div>
      <div class="form-grid" style="margin-top:18px">
        <div class="field wide"><label class="field-label" for="website_name">Website name</label>
          <input type="text" id="website_name" name="website_name" required value="<?= h($values['website_name'] ?? '') ?>"></div>
        <div class="field wide"><label class="field-label" for="website_description">Footer description</label>
          <textarea id="website_description" name="website_description" rows="4"><?= h($values['website_description'] ?? '') ?></textarea></div>
      </div>
    </section>

    <section class="card card-pad">
      <div class="set-head"><div class="stat-icon blue"><i class="fa-solid fa-address-book"></i></div>
        <div><h3>Contact details</h3><p class="muted">Shown on the footer and the contact page.</p></div></div>
      <div class="form-grid" style="margin-top:18px">
        <div class="field"><label class="field-label" for="website_email">Email</label>
          <input type="email" id="website_email" name="website_email" value="<?= h($values['website_email'] ?? '') ?>"></div>
        <div class="field"><label class="field-label" for="website_phone">Phone</label>
          <input type="text" id="website_phone" name="website_phone" placeholder="+91 79 2328 9000" value="<?= h($values['website_phone'] ?? '') ?>"></div>
        <div class="field wide"><label class="field-label" for="website_address">Office address</label>
          <input type="text" id="website_address" name="website_address" value="<?= h($values['website_address'] ?? '') ?>"></div>
      </div>
    </section>

    <section class="card card-pad">
      <div class="set-head"><div class="stat-icon amber"><i class="fa-solid fa-share-nodes"></i></div>
        <div><h3>Social links</h3><p class="muted">Leave blank to hide an icon.</p></div></div>
      <div class="form-grid" style="margin-top:18px">
        <div class="field wide"><label class="field-label" for="facebook_url">Facebook URL</label>
          <input type="text" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/yourpage" value="<?= h($values['facebook_url'] ?? '') ?>"></div>
        <div class="field wide"><label class="field-label" for="instagram_url">Instagram URL</label>
          <input type="text" id="instagram_url" name="instagram_url" placeholder="https://instagram.com/yourpage" value="<?= h($values['instagram_url'] ?? '') ?>"></div>
      </div>
    </section>

    <aside class="card card-pad settings-side">
      <h3>Where this appears</h3>
      <ul class="list-plain" style="margin-top:12px">
        <li><div>Website footer<small>Description, email, phone, address, social icons</small></div></li>
        <li><div>Contact page<small>Email, phone and address</small></div></li>
      </ul>
      <p class="muted" style="font-size:12.5px;margin-top:14px">Changes go live as soon as you save. Refresh the public site if it's already open.</p>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:18px"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
    </aside>
  </div>
</form>

<style>
.settings-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 22px; align-items: start; }
.settings-grid > section:nth-child(3) { grid-column: 1; }
.settings-side { position: sticky; top: 96px; grid-column: 2; grid-row: 1 / span 3; }
.set-head { display: flex; gap: 14px; align-items: center; }
.set-head h3 { font-size: 17px; }
.set-head p { font-size: 12.5px; }
@media (max-width: 1024px) { .settings-grid { grid-template-columns: 1fr; } .settings-side { position: static; grid-column: auto; grid-row: auto; } .settings-grid > section:nth-child(3) { grid-column: auto; } }
</style>
<?php include __DIR__ . '/includes/page-end.php'; ?>