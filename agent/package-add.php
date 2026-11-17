<?php
$page_title = 'Add Package';
$page_subtitle = 'Build a tour package from your live destinations. It goes live as soon as you publish.';
require_once __DIR__ . '/includes/auth.php';

$myDests = rows("SELECT destination_id, destination_name, city FROM destinations
                 WHERE created_by = $agent_acc AND status = 'Active' ORDER BY destination_name");
$cats    = rows("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name");
$errors  = [];
$v = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please submit again.';
    } else {
        $name   = trim($_POST['package_name'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $days   = (int) ($_POST['duration_days'] ?? 0);
        $nights = (int) ($_POST['duration_nights'] ?? -1);
        $price  = trim($_POST['price_per_person'] ?? '');
        $maxA   = (int) ($_POST['max_adults'] ?? 0);
        $maxC   = (int) ($_POST['max_children'] ?? 0);
        $catId  = (int) ($_POST['category_id'] ?? 0);
        $destIds = array_values(array_unique(array_filter(array_map('intval', $_POST['destination_id'] ?? []))));
        $incl   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['inclusions'] ?? ''))));
        $excl   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['exclusions'] ?? ''))));
        $itin   = array_values(array_filter($_POST['itinerary'] ?? [], fn($d) => trim($d['title'] ?? '') !== ''));

        if ($name === '') $errors[] = 'Package name is required.';
        if ($desc === '') $errors[] = 'Description is required.';
        if ($days < 1) $errors[] = 'Duration must be at least 1 day.';
        if ($nights < 0) $errors[] = 'Nights cannot be negative.';
        if (!is_numeric($price) || (float) $price <= 0) $errors[] = 'Enter a valid price per person.';
        if (!$destIds) $errors[] = 'Select at least one of your live destinations.';
        if ($destIds) {
            $valid = array_column(rows("SELECT destination_id FROM destinations WHERE created_by = $agent_acc AND status = 'Active'
                                        AND destination_id IN (" . implode(',', $destIds) . ")"), 'destination_id');
            if (count($valid) !== count($destIds)) $errors[] = 'Some selected destinations are not live. Choose only live destinations.';
        }
        $cover = store_image_at('cover', 0, 'pkg');
        if (empty($_FILES['cover']['name'][0] ?? '')) $errors[] = 'Upload a cover image.';
        elseif (!$cover) $errors[] = 'Cover must be a JPG, PNG, WEBP or AVIF image under 5 MB.';

        if (!$errors) {
            $slug = unique_slug('packages', slugify($name));
            begin_tx();
            $ok = q("INSERT INTO packages (agent_id, package_name, slug, description, duration_days, duration_nights, price_per_person, max_adults, max_children, status, created_at)
                     VALUES ($agent_id, " . sql_val($name) . ", " . sql_val($slug) . ", " . sql_val($desc) . ", $days, $nights, " . sql_val($price) . ", "
                   . ($maxA ?: 'NULL') . ", " . ($maxC ?: 'NULL') . ", 'Active', NOW())");
            $pid = last_id();
            foreach ($destIds as $n => $did) {
                $ok = $ok && q("INSERT INTO package_destinations (package_id, destination_id, display_order) VALUES ($pid, $did, " . ($n + 1) . ")");
            }
            if ($ok && $catId) $ok = q("INSERT INTO package_categories (package_id, category_id) VALUES ($pid, $catId)");
            foreach ($incl as $n => $item) $ok = $ok && q("INSERT INTO package_inclusions (package_id, item_name, display_order, status) VALUES ($pid, " . sql_val($item) . ", " . ($n + 1) . ", 'Active')");
            foreach ($excl as $n => $item) $ok = $ok && q("INSERT INTO package_exclusions (package_id, item_name, display_order, status) VALUES ($pid, " . sql_val($item) . ", " . ($n + 1) . ", 'Active')");
            foreach ($itin as $n => $d) {
                $ok = $ok && q("INSERT INTO package_itinerary (package_id, day_number, title, description, display_order)
                                VALUES ($pid, " . ($n + 1) . ", " . sql_val(trim($d['title'])) . ", " . sql_val(trim($d['description'] ?? '')) . ", " . ($n + 1) . ")");
            }
            $ok = $ok && q("INSERT INTO package_images (package_id, image_path, image_title, alt_text, image_type, display_order, status)
                            VALUES ($pid, " . sql_val($cover) . ", " . sql_val($name) . ", " . sql_val($name) . ", 'Cover', 1, 'Active')");

            if ($ok) {
                commit_tx();
                notify_account($agent_acc, 'package_created', 'Package is live',
                    "\"$name\" is now live and visible to travellers.", 'packages.php');
                header('Location: packages.php?msg=' . urlencode('Package published and live.') . '&type=success');
                exit;
            }
            rollback_tx();
            $errors[] = 'Could not save the package. Please try again.';
        }
    }
}

include __DIR__ . '/includes/page-start.php';
?>
<a class="dv-back" href="packages.php"><i class="fa-solid fa-arrow-left"></i> Back to my packages</a>

<?php if ($errors): ?>
  <div class="alert alert-danger" style="margin-top:6px"><i class="fa-solid fa-circle-exclamation"></i>
    <span><?= implode('<br>', array_map('h', $errors)) ?></span></div>
<?php endif; ?>

<?php if (!$myDests): ?>
  <div class="alert alert-info" style="margin-top:6px"><i class="fa-solid fa-circle-info"></i>
    <span>You need at least one live destination before creating a package. <a href="destination-add.php">Add a destination</a> and wait for admin approval.</span></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="add-form" data-busy>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">1</span><div><h3>Package details</h3><p>Name, description and duration</p></div></header>
    <div class="form-grid">
      <div class="field wide"><label class="field-label">Package name *</label>
        <input type="text" name="package_name" required value="<?= h($v['package_name'] ?? '') ?>" placeholder="e.g. Kerala Houseboat Escape"></div>
      <div class="field wide"><label class="field-label">Description *</label>
        <textarea name="description" rows="4" required><?= h($v['description'] ?? '') ?></textarea></div>
      <div class="field"><label class="field-label">Duration (days) *</label>
        <input type="number" min="1" name="duration_days" required value="<?= h($v['duration_days'] ?? '3') ?>"></div>
      <div class="field"><label class="field-label">Nights *</label>
        <input type="number" min="0" name="duration_nights" required value="<?= h($v['duration_nights'] ?? '2') ?>"></div>
      <div class="field"><label class="field-label">Price per person (₹) *</label>
        <input type="text" inputmode="decimal" name="price_per_person" required value="<?= h($v['price_per_person'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">Category</label>
        <select name="category_id" style="width:100%">
          <option value="">Select</option>
          <?php foreach ($cats as $c): ?><option value="<?= (int) $c['category_id'] ?>" <?= (int) ($v['category_id'] ?? 0) === (int) $c['category_id'] ? 'selected' : '' ?>><?= h($c['category_name']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label class="field-label">Max adults</label>
        <input type="number" min="1" name="max_adults" value="<?= h($v['max_adults'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">Max children</label>
        <input type="number" min="0" name="max_children" value="<?= h($v['max_children'] ?? '') ?>"></div>
    </div>
  </section>

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">2</span><div><h3>Cover image</h3><p>The main photo travellers see first</p></div></header>
    <label class="dropzone" for="cover"><i class="fa-solid fa-image"></i><strong>Choose a cover image</strong>
      <span>JPG, PNG, WEBP or AVIF · up to 5 MB</span>
      <input id="cover" type="file" name="cover[0]" accept="image/*" data-single-preview="coverPreview"></label>
    <div class="gallery-pick" id="coverPreview"></div>
  </section>

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">3</span><div><h3>Places covered</h3><p>Only your live destinations can be added</p></div></header>
    <?php if ($myDests): ?>
    <div class="chip-pick">
      <?php $selected = array_map('intval', $v['destination_id'] ?? []); foreach ($myDests as $d): ?>
        <label class="chip-check"><input type="checkbox" name="destination_id[]" value="<?= (int) $d['destination_id'] ?>"
          <?= in_array((int) $d['destination_id'], $selected, true) ? 'checked' : '' ?>>
          <span><?= h($d['destination_name']) ?> <small><?= h($d['city']) ?></small></span></label>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p class="dv-empty">No live destinations yet.</p><?php endif; ?>
  </section>

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">4</span><div><h3>Itinerary</h3><p>Day by day plan</p></div>
      <button type="button" class="btn btn-soft btn-sm fs-add" data-add="tplDay" data-target="listDays"><i class="fa-solid fa-plus"></i> Add day</button></header>
    <div class="repeat-list" id="listDays"></div>
  </section>

  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">5</span><div><h3>Inclusions & exclusions</h3><p>One item per line</p></div></header>
    <div class="form-grid">
      <div class="field"><label class="field-label">Included</label>
        <textarea name="inclusions" rows="6" placeholder="Houseboat stay&#10;All meals onboard"><?= h($v['inclusions'] ?? '') ?></textarea></div>
      <div class="field"><label class="field-label">Excluded</label>
        <textarea name="exclusions" rows="6" placeholder="Flight tickets&#10;Personal expenses"><?= h($v['exclusions'] ?? '') ?></textarea></div>
    </div>
  </section>

  <div class="publish-bar">
    <div><strong>Ready to submit?</strong><span>It goes live immediately, so check the details first.</span></div>
    <div class="publish-actions">
      <a class="btn btn-ghost" href="packages.php">Cancel</a>
      <button class="btn btn-primary" type="submit" <?= !$myDests ? 'disabled' : '' ?>><i class="fa-solid fa-rocket"></i> Publish package</button>
    </div>
  </div>
</form>

<template id="tplDay">
  <div class="repeat-card">
    <div class="rc-head"><span>Day</span><button type="button" class="rc-remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="form-grid">
      <div class="field"><label class="field-label">Title</label><input type="text" name="itinerary[__i__][title]"></div>
      <div class="field"><label class="field-label">Details</label><input type="text" name="itinerary[__i__][description]"></div>
    </div>
  </div>
</template>
<?php include __DIR__ . '/includes/page-end.php'; ?>
