<?php
/**
 * agent/index.php
 * ------------------------------------------------------------
 * The agent side of the site is intentionally minimal for now —
 * this page exists mainly so auth/login.php has somewhere real to
 * send a freshly-verified or returning agent. It shows their
 * application status (agents.status) and basic profile info.
 * A full agent dashboard (packages, requests, bookings) is a
 * separate, larger piece of work.
 * ------------------------------------------------------------
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['agent_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode('agent/index.php'));
    exit;
}


/* Agent entry point: go straight to the dashboard when signed in, otherwise to the agent sign-in. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Location: ' . (!empty($_SESSION['agent_id']) ? 'dashboard.php' : 'login.php'));
exit;

$agentId = (int) $_SESSION['agent_id'];
$stmt = mysqli_prepare($conn, "SELECT ag.*, a.email
                                FROM agents ag
                                JOIN accounts a ON a.account_id = ag.account_id
                                WHERE ag.agent_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $agentId);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$agent) { session_destroy(); header('Location: ../auth/login.php'); exit; }

$destReqCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM destination_requests WHERE agent_id = $agentId"))['c'] ?? 0);
$packageCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM packages WHERE agent_id = $agentId"))['c'] ?? 0);

$statusCopy = [
    'Pending'  => ['title' => 'Your application is under review', 'body' => 'Our team is checking your Aadhaar, PAN and business documents. This usually takes 1-2 business days &mdash; we&rsquo;ll email you the outcome.', 'tone' => 'pending'],
    'Approved' => ['title' => 'You&rsquo;re a verified NextTrip agent', 'body' => 'Your application has been approved. The full agent workspace (packages, destination requests, bookings) is coming soon.', 'tone' => 'approved'],
    'Rejected' => ['title' => 'Your application wasn&rsquo;t approved', 'body' => $agent['rejection_reason'] ? htmlspecialchars($agent['rejection_reason']) : 'Please contact support for details, or get in touch if you&rsquo;d like to reapply with updated documents.', 'tone' => 'rejected'],
][$agent['status']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agent Portal · NextTrip</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root{ --forest-800:#17452a; --forest-700:#1e6e3c; --bright-500:#3dd267; --mint-100:#eaf7f0; --ink-900:#16241c; --ink-600:#556155; --line:#e6ebe6; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:'Inter',sans-serif; background:#f7faf7; color:var(--ink-900); }
  h1,h2,.brand{ font-family:'Space Grotesk',sans-serif; }
  header{ display:flex; align-items:center; justify-content:space-between; padding:16px 28px; background:#fff; border-bottom:1px solid var(--line); }
  .brand{ display:inline-flex; align-items:center; gap:8px; font-weight:700; font-size:18px; text-decoration:none; color:var(--forest-800); }
  .brand span:last-child{ color:var(--bright-500); }
  .logout{ font-size:13px; font-weight:600; color:var(--ink-600); text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
  .logout:hover{ color:var(--forest-700); }
  main{ max-width:760px; margin:0 auto; padding:48px 20px; display:flex; flex-direction:column; gap:20px; }
  .status-card{ background:#fff; border-radius:18px; padding:32px; box-shadow:0 12px 30px -18px rgba(15,32,19,.25); display:flex; gap:18px; align-items:flex-start; }
  .status-card__icon{ width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex:none; }
  .status-card--pending .status-card__icon{ background:#fff3d6; color:#a2720a; }
  .status-card--approved .status-card__icon{ background:var(--mint-100); color:var(--forest-700); }
  .status-card--rejected .status-card__icon{ background:#fde7e5; color:#b8331f; }
  .status-card h2{ margin:0 0 8px; font-size:19px; }
  .status-card p{ margin:0; color:var(--ink-600); font-size:14px; line-height:1.6; }
  .badge{ display:inline-flex; align-items:center; gap:6px; font-size:11.5px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:5px 12px; border-radius:999px; margin-bottom:10px; }
  .status-card--pending .badge{ background:#fff3d6; color:#a2720a; }
  .status-card--approved .badge{ background:var(--mint-100); color:var(--forest-700); }
  .status-card--rejected .badge{ background:#fde7e5; color:#b8331f; }
  .grid{ display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
  .stat{ background:#fff; border-radius:14px; padding:18px; text-align:center; box-shadow:0 8px 20px -16px rgba(15,32,19,.2); }
  .stat strong{ display:block; font-size:22px; font-family:'Space Grotesk',sans-serif; color:var(--forest-800); }
  .stat span{ font-size:12px; color:var(--ink-600); }
  .profile-card{ background:#fff; border-radius:14px; padding:22px; box-shadow:0 8px 20px -16px rgba(15,32,19,.2); }
  .profile-card h3{ margin:0 0 12px; font-size:14px; }
  .profile-row{ display:flex; justify-content:space-between; padding:8px 0; border-top:1px solid var(--line); font-size:13.5px; }
  .profile-row:first-of-type{ border-top:none; }
  .profile-row span:first-child{ color:var(--ink-600); }
  .profile-row span:last-child{ font-weight:600; text-align:right; }
</style>
</head>
<body>
<header>
  <a class="brand" href="../index.php"><span>Next</span><span>Trip</span></a>
  <a class="logout" href="logout.php"><i class="bi bi-box-arrow-right"></i> Log out</a>
</header>
<main>
  <div class="status-card status-card--<?= strtolower($agent['status']) ?>">
    <div class="status-card__icon">
      <?php if ($agent['status'] === 'Approved'): ?><i class="bi bi-patch-check-fill"></i>
      <?php elseif ($agent['status'] === 'Rejected'): ?><i class="bi bi-x-circle-fill"></i>
      <?php else: ?><i class="bi bi-hourglass-split"></i><?php endif; ?>
    </div>
    <div>
      <span class="badge"><?= htmlspecialchars($agent['status']) ?></span>
      <h2>Welcome, <?= htmlspecialchars(explode(' ', $agent['full_name'])[0]) ?> &mdash; <?= $statusCopy['title'] ?></h2>
      <p><?= $statusCopy['body'] ?></p>
    </div>
  </div>

  <div class="grid">
    <div class="stat"><strong><?= $destReqCount ?></strong><span>Destination requests</span></div>
    <div class="stat"><strong><?= $packageCount ?></strong><span>Packages</span></div>
    <div class="stat"><strong>&mdash;</strong><span>Bookings (coming soon)</span></div>
  </div>

  <div class="profile-card">
    <h3>Your details</h3>
    <div class="profile-row"><span>Full name</span><span><?= htmlspecialchars($agent['full_name']) ?></span></div>
    <div class="profile-row"><span>Email</span><span><?= htmlspecialchars($agent['email']) ?></span></div>
    <div class="profile-row"><span>Phone</span><span><?= htmlspecialchars($agent['phone']) ?></span></div>
    <div class="profile-row"><span>City</span><span><?= htmlspecialchars($agent['city']) ?></span></div>
    <div class="profile-row"><span>Aadhaar</span><span><?= $agent['aadhaar_file'] ? '<i class="bi bi-check-circle-fill" style="color:#1e6e3c"></i> Uploaded' : '&mdash;' ?></span></div>
    <div class="profile-row"><span>PAN</span><span><?= $agent['pan_file'] ? '<i class="bi bi-check-circle-fill" style="color:#1e6e3c"></i> Uploaded' : '&mdash;' ?></span></div>
    <div class="profile-row"><span>Business document</span><span><?= $agent['business_document_file'] ? '<i class="bi bi-check-circle-fill" style="color:#1e6e3c"></i> Uploaded' : 'Not provided' ?></span></div>
  </div>
</main>
</body>
</html>
