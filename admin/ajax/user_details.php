<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
ajax_guard();

$id = (int) ($_GET['id'] ?? 0);
$u = row("SELECT u.*, a.email, a.status, a.created_at AS joined, a.last_login
          FROM users u JOIN accounts a ON a.account_id = u.account_id WHERE u.user_id = $id");
if (!$u) {
    echo '<div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i><span>This user could not be found.</span></div>';
    exit;
}

$name      = trim($u['first_name'] . ' ' . $u['last_name']);
$bookings  = (int) scalar("SELECT COUNT(*) FROM bookings WHERE user_id = $id");
$confirmed = (int) scalar("SELECT COUNT(*) FROM bookings WHERE user_id = $id AND booking_status = 'Confirmed'");
$spent     = (float) scalar("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE user_id = $id AND booking_status = 'Confirmed'");
$reviews   = (int) scalar("SELECT COUNT(*) FROM destination_reviews WHERE user_id = $id") + (int) scalar("SELECT COUNT(*) FROM package_reviews WHERE user_id = $id");
$favs      = (int) scalar("SELECT COUNT(*) FROM favorites WHERE user_id = $id");
$active    = $u['status'] === 'Active';
$initials  = initials($name);
?>
<style>
  .udm { font-family: 'Manrope', system-ui, sans-serif; color: #1d2a24; }
  .udm *, .udm *::before, .udm *::after { box-sizing: border-box; }
  .udm-hero {
    position: relative; overflow: hidden; border-radius: 20px; padding: 24px;
    background: linear-gradient(135deg, #0f4a30 0%, #1f8a54 100%); color: #fff;
    display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
    animation: udm-in .5s cubic-bezier(.22,1,.36,1) both;
  }
  .udm-hero::after {
    content: ''; position: absolute; right: -50px; top: -60px; width: 180px; height: 180px;
    border-radius: 50%; background: rgba(255,255,255,.08);
  }
  .udm-avatar {
    width: 72px; height: 72px; border-radius: 22px; flex-shrink: 0;
    display: grid; place-items: center; font-family: 'Fraunces', Georgia, serif;
    font-size: 26px; font-weight: 700; color: #0f4a30; background: #f4a627;
    box-shadow: 0 12px 24px -12px rgba(0,0,0,.5);
  }
  .udm-who { flex: 1; min-width: 180px; position: relative; z-index: 1; }
  .udm-who h3 { font-family: 'Fraunces', Georgia, serif; font-size: 24px; font-weight: 700; margin: 0; line-height: 1.2; }
  .udm-who p { margin: 6px 0 0; font-size: 13.5px; opacity: .9; word-break: break-all; }
  .udm-state {
    display: inline-flex; align-items: center; gap: 7px; margin-top: 10px;
    padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 800;
    background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.3);
  }
  .udm-state i { width: 7px; height: 7px; border-radius: 50%; background: #7ee2a8; }
  .udm-state.off i { background: #f4a627; }
  .udm-state.off { color: #ffe3a3; }

  .udm-stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-top: 16px; }
  .udm-stat {
    background: #fff; border: 1px solid #e3ebe6; border-radius: 16px; padding: 16px 14px; text-align: center;
    box-shadow: 0 8px 20px -16px rgba(16,24,20,.3); transition: transform .3s, box-shadow .3s;
    animation: udm-in .5s cubic-bezier(.22,1,.36,1) both;
  }
  .udm-stat:nth-child(2) { animation-delay: .06s; }
  .udm-stat:nth-child(3) { animation-delay: .12s; }
  .udm-stat:nth-child(4) { animation-delay: .18s; }
  .udm-stat:hover { transform: translateY(-3px); box-shadow: 0 16px 28px -18px rgba(16,24,20,.4); }
  .udm-stat b { display: block; font-family: 'Fraunces', Georgia, serif; font-size: 22px; color: #146339; font-variant-numeric: tabular-nums; }
  .udm-stat span { font-size: 11.5px; color: #6b7a72; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }

  .udm-title { display: flex; align-items: center; gap: 12px; margin: 22px 0 12px; font-size: 11.5px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #1f8a54; }
  .udm-title::after { content: ''; flex: 1; height: 1px; background: #e3ebe6; }

  .udm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .udm-field {
    background: #f8fbf9; border: 1px solid #e3ebe6; border-radius: 14px; padding: 12px 14px;
    display: flex; gap: 12px; align-items: flex-start; min-width: 0;
  }
  .udm-field.wide { grid-column: 1 / -1; }
  .udm-ic {
    width: 34px; height: 34px; border-radius: 11px; background: #e8f6ee; color: #1f8a54;
    display: grid; place-items: center; font-size: 13px; flex-shrink: 0;
  }
  .udm-field small { display: block; font-size: 11px; font-weight: 800; color: #6b7a72; text-transform: uppercase; letter-spacing: .06em; }
  .udm-field strong { display: block; font-size: 14.5px; font-weight: 600; margin-top: 3px; word-break: break-word; }
  .udm-muted { color: #9aa8a0; font-weight: 500; }

  @keyframes udm-in { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
  @media (max-width: 640px) { .udm-stats { grid-template-columns: repeat(2, minmax(0,1fr)); } .udm-grid { grid-template-columns: 1fr; } }
  @media (prefers-reduced-motion: reduce) { .udm *, .udm *::before, .udm *::after { animation: none !important; transition: none !important; } }
</style>

<div class="udm">
  <section class="udm-hero">
    <div class="udm-avatar"><?= h($initials) ?></div>
    <div class="udm-who">
      <h3><?= h($name) ?></h3>
      <p><?= h($u['email']) ?></p>
      <span class="udm-state<?= $active ? '' : ' off' ?>"><i></i><?= $active ? 'Active account' : h($u['status']) ?></span>
    </div>
  </section>

  <div class="udm-stats">
    <div class="udm-stat"><b><?= $bookings ?></b><span>Bookings</span></div>
    <div class="udm-stat"><b><?= $confirmed ?></b><span>Confirmed</span></div>
    <div class="udm-stat"><b><?= $reviews ?></b><span>Reviews</span></div>
    <div class="udm-stat"><b><?= $favs ?></b><span>Saved places</span></div>
  </div>

  <div class="udm-title">Profile</div>
  <div class="udm-grid">
    <div class="udm-field">
      <span class="udm-ic"><i class="fa-solid fa-phone"></i></span>
      <div><small>Phone</small><strong><?= $u['phone'] ? h($u['phone']) : '<span class="udm-muted">Not provided</span>' ?></strong></div>
    </div>
    <div class="udm-field">
      <span class="udm-ic"><i class="fa-solid fa-city"></i></span>
      <div><small>City</small><strong><?= $u['city'] ? h($u['city']) : '<span class="udm-muted">Not provided</span>' ?></strong></div>
    </div>
    <div class="udm-field wide">
      <span class="udm-ic"><i class="fa-solid fa-location-dot"></i></span>
      <div><small>Address</small><strong><?= $u['address'] ? h($u['address']) : '<span class="udm-muted">Not provided</span>' ?></strong></div>
    </div>
    <div class="udm-field">
      <span class="udm-ic"><i class="fa-regular fa-calendar-plus"></i></span>
      <div><small>Joined</small><strong><?= fmt_datetime($u['joined']) ?></strong></div>
    </div>
    <div class="udm-field">
      <span class="udm-ic"><i class="fa-solid fa-right-to-bracket"></i></span>
      <div><small>Last login</small><strong><?= $u['last_login'] ? fmt_datetime($u['last_login']) : '<span class="udm-muted">Never</span>' ?></strong></div>
    </div>
    <div class="udm-field wide">
      <span class="udm-ic"><i class="fa-solid fa-indian-rupee-sign"></i></span>
      <div><small>Total paid (confirmed bookings)</small><strong><?= money($spent) ?></strong></div>
    </div>
  </div>
</div>