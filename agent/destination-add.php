<?php
ini_set('display_errors', 1); error_reporting(E_ALL);

$page_title = 'Add Destination';
$page_subtitle = 'Publish a destination. It is sent to admin for review and goes live once approved.';
require_once __DIR__ . '/includes/auth.php';

$cats    = rows("SELECT category_id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name");
$seasons = rows("SELECT season_id, season_name FROM seasons ORDER BY start_month");
$errors  = [];

/* ================= SAVE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please publish again.';
    } else {
        $name       = trim($_POST['destination_name'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $state      = trim($_POST['state'] ?? '');
        $district   = trim($_POST['district'] ?? '');
        $country    = trim($_POST['country'] ?? '') ?: 'India';
        $catId      = (int) ($_POST['category_id'] ?? 0);
        $feeOn      = isset($_POST['fee_enabled']);
        $fee        = $feeOn ? trim($_POST['entry_fee'] ?? '') : 'Free';
        $lat        = trim($_POST['latitude'] ?? '');
        $lng        = trim($_POST['longitude'] ?? '');
        $about      = trim($_POST['about'] ?? '');
        $importance = trim($_POST['importance'] ?? '');
        $history    = trim($_POST['history'] ?? '');
        $seasonIds  = array_values(array_filter(array_map('intval', $_POST['season_id'] ?? [])));
        $openT      = trim($_POST['open_time'] ?? '');
        $closeT     = trim($_POST['close_time'] ?? '');
        $coverIdx   = (int) ($_POST['cover_index'] ?? 0);
        $acts  = $_POST['activities'] ?? [];
        $tips  = $_POST['tips'] ?? [];
        $hotels = $_POST['hotels'] ?? [];
        $rests  = $_POST['restaurants'] ?? [];

        if ($name === '')  $errors[] = 'Destination name is required.';
        if ($city === '')  $errors[] = 'City is required.';
        if ($state === '') $errors[] = 'State is required.';
        if ($catId <= 0)   $errors[] = 'Choose a category.';
        if ($about === '') $errors[] = 'The About description is required.';
        if ($importance === '') $errors[] = 'The Importance description is required.';
        if (!$seasonIds)   $errors[] = 'Select at least one best time to visit.';
        if ($openT === '' || $closeT === '') $errors[] = 'Opening and closing times are required.';
        if ($feeOn && !is_numeric($fee)) $errors[] = 'Entry fee must be a number in ₹.';
        if ($lat !== '' && (!is_numeric($lat) || abs((float) $lat) > 90)) $errors[] = 'Latitude must be between -90 and 90.';
        if ($lng !== '' && (!is_numeric($lng) || abs((float) $lng) > 180)) $errors[] = 'Longitude must be between -180 and 180.';

        foreach ($acts as $a) if (trim($a['name'] ?? '') !== '' && mb_strlen(trim($a['name'])) > 100) $errors[] = 'Activity names must be under 100 characters.';
        foreach ($hotels as $i => $ht) {
            if (trim($ht['name'] ?? '') === '') continue;
            if (!is_numeric($ht['price'] ?? '')) $errors[] = 'Enter a price (₹) for every hotel.';
            if (($ht['rating'] ?? '') !== '' && (!is_numeric($ht['rating']) || $ht['rating'] > 5)) $errors[] = 'Hotel ratings must be between 0 and 5.';
        }
        foreach ($rests as $r) {
            if (trim($r['name'] ?? '') !== '' && ($r['rating'] ?? '') !== '' && (!is_numeric($r['rating']) || $r['rating'] > 5)) $errors[] = 'Restaurant ratings must be between 0 and 5.';
        }

        /* Images: at least one; the chosen cover becomes the primary image */
        $uploaded = [];
        foreach (($_FILES['images']['name'] ?? []) as $i => $n) {
            if ($p = store_image_at('images', $i, 'dest')) $uploaded[] = ['path' => $p, 'cover' => $i === $coverIdx];
        }
        if (!$uploaded && !$errors) $errors[] = 'Upload at least one image (JPG, PNG, WEBP or AVIF, up to 5 MB).';

        if (!$errors) {
            $primary = array_search(true, array_column($uploaded, 'cover'), true);
            if ($primary === false) $primary = 0;

            $slug = unique_slug('destinations', slugify($name));
            begin_tx();
            $ok = q("INSERT INTO destinations (destination_name, slug, city, district, state, country, latitude, longitude, entry_fee, status, created_by, created_at)
                     VALUES (" . sql_val($name) . ", " . sql_val($slug) . ", " . sql_val($city) . ", " . sql_val($district) . ", " . sql_val($state) . ", "
                   . sql_val($country) . ", " . ($lat !== '' ? sql_val($lat) : 'NULL') . ", " . ($lng !== '' ? sql_val($lng) : 'NULL') . ", "
                   . sql_val($fee) . ", 'Inactive', $agent_acc, NOW())");
            $destId = last_id();

            /* Images */
            foreach ($uploaded as $k => $im) {
                $ok = $ok && q("INSERT INTO destination_images (destination_id, image_path, image_title, alt_text, is_primary, display_order, status)
                                VALUES ($destId, " . sql_val($im['path']) . ", " . sql_val($name) . ", " . sql_val($name) . ", " . ($k === $primary ? 1 : 0) . ", " . ($k + 1) . ", 'Active')");
            }
            /* About / History / Importance */
            foreach ([['About', $about], ['History', $history], ['Importance', $importance]] as $n => [$type, $text]) {
                if ($text === '') continue;
                $ok = $ok && q("INSERT INTO destination_sections (destination_id, section_type, title, content, display_order, status)
                                VALUES ($destId, '$type', " . sql_val("$type of $name") . ", " . sql_val($text) . ", " . ($n + 1) . ", 'Active')");
            }
            /* Best time to visit */
            foreach ($seasonIds as $sid) {
                $ok = $ok && q("INSERT INTO destination_seasons (destination_id, season_id, is_best_time, description) VALUES ($destId, $sid, 1, NULL)");
            }
            /* Opening hours (one generic row, day_of_week = 0, applies to every day) */
            $ok = $ok && q("INSERT INTO destination_opening_hours (destination_id, day_of_week, open_time, close_time, is_closed)
                            VALUES ($destId, 0, " . sql_val($openT) . ", " . sql_val($closeT) . ", 0)");
            /* Activities */
            foreach ($acts as $n => $a) {
                $an = trim($a['name'] ?? '');
                if ($an === '') continue;
                $ad = trim($a['description'] ?? '');
                $ok = $ok && q("INSERT INTO activities (destination_id, activity_name, description, display_order, status)
                                VALUES ($destId, " . sql_val($an) . ", " . sql_val($ad) . ", " . ((int) $n + 1) . ", 'Active')");
                $aid = last_id();
                $ok = $ok && q("INSERT INTO destination_activities (destination_id, activity_id, description, display_order)
                                VALUES ($destId, $aid, " . sql_val($ad) . ", " . ((int) $n + 1) . ")");
            }
            /* Travel tips */
            $tipNo = 0;
            foreach ($tips as $t) {
                $tt = trim($t['text'] ?? '');
                if ($tt === '') continue;
                $tipNo++;
                $ok = $ok && q("INSERT INTO destination_tips (destination_id, tip_text, display_order, status) VALUES ($destId, " . sql_val($tt) . ", $tipNo, 'Active')");
            }
            /* Hotels (image from hotel_img[index]) */
            foreach ($hotels as $i => $ht) {
                $hn = trim($ht['name'] ?? '');
                if ($hn === '') continue;
                $img = store_image_at('hotel_img', $i, 'hotel');
                $ok = $ok && q("INSERT INTO hotels (destination_id, hotel_name, address, latitude, longitude, price_per_night, rating, image_path, status)
                                VALUES ($destId, " . sql_val($hn) . ", " . sql_val($ht['address'] ?? '') . ", "
                              . (($ht['lat'] ?? '') !== '' ? sql_val($ht['lat']) : 'NULL') . ", " . (($ht['lng'] ?? '') !== '' ? sql_val($ht['lng']) : 'NULL') . ", "
                              . sql_val($ht['price']) . ", " . (($ht['rating'] ?? '') !== '' ? sql_val($ht['rating']) : 'NULL') . ", "
                              . sql_val($img) . ", 'Active')");
                $hid = last_id();
                $ok = $ok && q("INSERT INTO destination_hotels (destination_id, hotel_id) VALUES ($destId, $hid)");
            }
            /* Restaurants (image from rest_img[index]) */
            foreach ($rests as $i => $r) {
                $rn = trim($r['name'] ?? '');
                if ($rn === '') continue;
                $img = store_image_at('rest_img', $i, 'rest');
                $ok = $ok && q("INSERT INTO restaurants (destination_id, restaurant_name, cuisine, address, latitude, longitude, rating, image_path, status)
                                VALUES ($destId, " . sql_val($rn) . ", " . sql_val($r['cuisine'] ?? '') . ", " . sql_val($r['address'] ?? '') . ", "
                              . (($r['lat'] ?? '') !== '' ? sql_val($r['lat']) : 'NULL') . ", " . (($r['lng'] ?? '') !== '' ? sql_val($r['lng']) : 'NULL') . ", "
                              . (($r['rating'] ?? '') !== '' ? sql_val($r['rating']) : 'NULL') . ", " . sql_val($img) . ", 'Active')");
                $rid = last_id();
                $ok = $ok && q("INSERT INTO destination_restaurants (destination_id, restaurant_id) VALUES ($destId, $rid)");
            }
            /* Category */
            $ok = $ok && q("INSERT INTO destination_categories (destination_id, category_id) VALUES ($destId, $catId)");
            /* Request for admin review, linked to the destination */
            $ok = $ok && q("INSERT INTO destination_requests (agent_id, destination_name, city, district, state, country, category_id, entry_fee, description, image_path, status, requested_at, linked_destination_id)
                            VALUES ($agent_id, " . sql_val($name) . ", " . sql_val($city) . ", " . sql_val($district) . ", " . sql_val($state) . ", " . sql_val($country) . ", "
                          . $catId . ", " . sql_val($fee) . ", " . sql_val($about) . ", " . sql_val($uploaded[$primary]['path']) . ", 'Pending', NOW(), $destId)");

            if ($ok) {
                commit_tx();
                notify_account($agent_acc, 'destination_submitted', 'Destination submitted for review',
                    "\"$name\" was sent to admin. You will be notified once it is reviewed.", 'destinations.php');
                header('Location: destinations.php?msg=' . urlencode('Destination submitted for admin review.') . '&type=success');
                exit;
            }
            rollback_tx();
            //$errors[] = 'Could not save the destination. Please check the fields and try again.';
            $errors[] = 'Could not save the destination: ' . mysqli_error($conn);
        }
    }
}

include __DIR__ . '/includes/page-start.php';
$v = $_POST ?? [];
?>
<a class="dv-back" href="destinations.php"><i class="fa-solid fa-arrow-left"></i> Back to my destinations</a>

<?php if ($errors): ?>
  <div class="alert alert-danger" style="margin-top:6px"><i class="fa-solid fa-circle-exclamation"></i>
    <span><?= implode('<br>', array_map('h', $errors)) ?></span></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="add-form" data-busy>
  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

  <!-- 1. Basics -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">1</span><div><h3>Destination basics</h3><p>Name, location and category</p></div></header>
    <div class="form-grid">
      <div class="field wide"><label class="field-label" for="dn">Destination name *</label>
        <input id="dn" type="text" name="destination_name" required value="<?= h($v['destination_name'] ?? '') ?>" placeholder="e.g. Kerala Backwaters"></div>
      <div class="field"><label class="field-label" for="city">City *</label>
        <input id="city" type="text" name="city" required value="<?= h($v['city'] ?? '') ?>"></div>
      <div class="field"><label class="field-label" for="district">District</label>
        <input id="district" type="text" name="district" value="<?= h($v['district'] ?? '') ?>"></div>
      <div class="field"><label class="field-label" for="state">State *</label>
        <input id="state" type="text" name="state" required value="<?= h($v['state'] ?? '') ?>"></div>
      <div class="field"><label class="field-label" for="country">Country</label>
        <input id="country" type="text" name="country" value="<?= h($v['country'] ?? 'India') ?>"></div>
      <div class="field wide"><label class="field-label" for="cat">Category *</label>
        <select id="cat" name="category_id" required style="width:100%">
          <option value="">Select a category</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['category_id'] ?>" <?= (int) ($v['category_id'] ?? 0) === (int) $c['category_id'] ? 'selected' : '' ?>><?= h($c['category_name']) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
  </section>

  <!-- 2. Images -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">2</span><div><h3>Images</h3><p>Upload photos and choose one cover image</p></div></header>
    <label class="dropzone" for="images">
      <i class="fa-solid fa-cloud-arrow-up"></i>
      <strong>Click to choose images</strong>
      <span>JPG, PNG, WEBP or AVIF · up to 5 MB each</span>
      <input id="images" type="file" name="images[]" multiple accept="image/*" data-gallery="galleryPreview">
    </label>
    <div class="gallery-pick" id="galleryPreview"></div>
  </section>

  <!-- 3. Descriptions -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">3</span><div><h3>Description</h3><p>About, importance and history</p></div></header>
    <div class="field"><label class="field-label" for="about">About *</label>
      <textarea id="about" name="about" rows="4" required><?= h($v['about'] ?? '') ?></textarea></div>
    <div class="field"><label class="field-label" for="importance">Importance *</label>
      <textarea id="importance" name="importance" rows="3" required><?= h($v['importance'] ?? '') ?></textarea></div>
    <div class="field"><label class="field-label" for="history">History</label>
      <textarea id="history" name="history" rows="3"><?= h($v['history'] ?? '') ?></textarea></div>
  </section>

  <!-- 4. Visit details -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">4</span><div><h3>Visit details</h3><p>Best time, opening hours and entry fee</p></div></header>
    <div class="field">
      <label class="field-label">Best time to visit *</label>
      <div class="chip-pick">
        <?php foreach ($seasons as $s): ?>
          <label class="chip-check"><input type="checkbox" name="season_id[]" value="<?= (int) $s['season_id'] ?>"
            <?= in_array((int) $s['season_id'], array_map('intval', $v['season_id'] ?? []), true) ? 'checked' : '' ?>>
            <span><?= h($s['season_name']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-grid">
      <div class="field"><label class="field-label" for="ot">Opening time *</label>
        <input id="ot" type="time" name="open_time" required value="<?= h($v['open_time'] ?? '') ?>"></div>
      <div class="field"><label class="field-label" for="ct">Closing time *</label>
        <input id="ct" type="time" name="close_time" required value="<?= h($v['close_time'] ?? '') ?>"></div>
    </div>
    <div class="fee-row">
      <div class="fee-head">
        <div><strong>Entry fee</strong><span>Turn off if the place is free to enter</span></div>
        <label class="switch"><input type="checkbox" name="fee_enabled" data-toggle-target="entryFee" <?= (!$_POST || isset($_POST['fee_enabled'])) ? 'checked' : '' ?>><span></span></label>
      </div>
      <div class="input-icon"><span class="rupee">₹</span>
        <input id="entryFee" type="text" name="entry_fee" inputmode="decimal" placeholder="100" value="<?= h($v['entry_fee'] ?? '') ?>"></div>
    </div>
  </section>

  <!-- 5. Location -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">5</span><div><h3>Location</h3><p>Coordinates shown on the map</p></div></header>
    <div class="form-grid">
      <div class="field"><label class="field-label" for="lat">Latitude</label>
        <input id="lat" type="text" name="latitude" placeholder="9.4981" value="<?= h($v['latitude'] ?? '') ?>"></div>
      <div class="field"><label class="field-label" for="lng">Longitude</label>
        <input id="lng" type="text" name="longitude" placeholder="76.3388" value="<?= h($v['longitude'] ?? '') ?>"></div>
    </div>
  </section>

  <!-- 6. Activities -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">6</span><div><h3>Activities</h3><p>Add as many as you like</p></div>
      <button type="button" class="btn btn-soft btn-sm fs-add" data-add="tplActivity" data-target="listActivities"><i class="fa-solid fa-plus"></i> Add activity</button></header>
    <div class="repeat-list" id="listActivities"></div>
  </section>

  <!-- 7. Travel tips -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">7</span><div><h3>Travel tips</h3><p>Short, useful advice for visitors</p></div>
      <button type="button" class="btn btn-soft btn-sm fs-add" data-add="tplTip" data-target="listTips"><i class="fa-solid fa-plus"></i> Add tip</button></header>
    <div class="repeat-list" id="listTips"></div>
  </section>

  <!-- 8. Hotels -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">8</span><div><h3>Hotels</h3><p>Nearby stays with price and photo</p></div>
      <button type="button" class="btn btn-soft btn-sm fs-add" data-add="tplHotel" data-target="listHotels" data-indexed="hotel_img"><i class="fa-solid fa-plus"></i> Add hotel</button></header>
    <div class="repeat-list" id="listHotels"></div>
  </section>

  <!-- 9. Restaurants -->
  <section class="card card-pad form-section">
    <header class="fs-head"><span class="fs-num">9</span><div><h3>Restaurants</h3><p>Local places to eat</p></div>
      <button type="button" class="btn btn-soft btn-sm fs-add" data-add="tplRest" data-target="listRests" data-indexed="rest_img"><i class="fa-solid fa-plus"></i> Add restaurant</button></header>
    <div class="repeat-list" id="listRests"></div>
  </section>

  <!-- Actions -->
  <div class="publish-bar">
    <div><strong>Ready to publish?</strong><span>It goes to admin for review. You will get a notification when it is decided.</span></div>
    <div class="publish-actions">
      <a class="btn btn-ghost" href="destinations.php">Cancel</a>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Publish</button>
    </div>
  </div>
</form>

<!-- Row templates (cloned by agent.js; __i__ is replaced with a unique index) -->
<template id="tplActivity">
  <div class="repeat-card">
    <div class="rc-head"><span>Activity</span><button type="button" class="rc-remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="form-grid">
      <div class="field"><label class="field-label">Activity name *</label><input type="text" name="activities[__i__][name]"></div>
      <div class="field"><label class="field-label">Description</label><input type="text" name="activities[__i__][description]"></div>
    </div>
  </div>
</template>
<template id="tplTip">
  <div class="repeat-card">
    <div class="rc-head"><span>Travel tip</span><button type="button" class="rc-remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="field"><label class="field-label">Description</label><textarea name="tips[__i__][text]" rows="2"></textarea></div>
  </div>
</template>
<template id="tplHotel">
  <div class="repeat-card">
    <div class="rc-head"><span>Hotel</span><button type="button" class="rc-remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="form-grid">
      <div class="field"><label class="field-label">Hotel name *</label><input type="text" name="hotels[__i__][name]"></div>
      <div class="field"><label class="field-label">Address</label><input type="text" name="hotels[__i__][address]"></div>
      <div class="field"><label class="field-label">Price per night (₹) *</label><input type="text" inputmode="decimal" name="hotels[__i__][price]"></div>
      <div class="field"><label class="field-label">Rating (0–5)</label><input type="text" inputmode="decimal" name="hotels[__i__][rating]"></div>
      <div class="field"><label class="field-label">Latitude</label><input type="text" name="hotels[__i__][lat]"></div>
      <div class="field"><label class="field-label">Longitude</label><input type="text" name="hotels[__i__][lng]"></div>
      <div class="field wide"><label class="field-label">Hotel image</label><input type="file" accept="image/*" name="hotel_img[__i__]"></div>
    </div>
  </div>
</template>
<template id="tplRest">
  <div class="repeat-card">
    <div class="rc-head"><span>Restaurant</span><button type="button" class="rc-remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="form-grid">
      <div class="field"><label class="field-label">Restaurant name *</label><input type="text" name="restaurants[__i__][name]"></div>
      <div class="field"><label class="field-label">Cuisine</label><input type="text" name="restaurants[__i__][cuisine]"></div>
      <div class="field"><label class="field-label">Rating (0–5)</label><input type="text" inputmode="decimal" name="restaurants[__i__][rating]"></div>
      <div class="field"><label class="field-label">Address</label><input type="text" name="restaurants[__i__][address]"></div>
      <div class="field"><label class="field-label">Latitude</label><input type="text" name="restaurants[__i__][lat]"></div>
      <div class="field"><label class="field-label">Longitude</label><input type="text" name="restaurants[__i__][lng]"></div>
      <div class="field wide"><label class="field-label">Restaurant image</label><input type="file" accept="image/*" name="rest_img[__i__]"></div>
    </div>
  </div>
</template>
<?php include __DIR__ . '/includes/page-end.php'; ?>
