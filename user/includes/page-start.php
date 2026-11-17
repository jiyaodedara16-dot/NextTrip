<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title><?= h($page_title ?? 'My Account') ?> · NextTrip</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/user.css">
<link rel="stylesheet" href="assets/css/notification-dismiss.css">
</head>
<body>
<div class="admin-shell">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <div class="scrim" id="scrim"></div>
  <div class="main">
    <?php include __DIR__ . '/topbar.php'; ?>
    <main class="content">