<?php
/* Contact Enquiries — list with search and filters. Opening an enquiry marks it Seen; Reply opens a modal. */
$page_title    = 'Contact Enquiries';
$page_subtitle = 'Every message sent through the Contact page, newest first.';
$extra_css = ['assets/css/enquiries.css'];
$extra_js  = ['assets/js/enquiries.js'];
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/enquiries_lib.php';
require_once __DIR__ . '/../config/mail_config.php';

/* ---- Filters (values checked against fixed lists) ---- */
$q      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$type   = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
if (!in_array($status, ENQ_FILTER_STATUSES, true)) $status = '';
if (!array_key_exists($type, ENQ_FILTER_TYPES) && $type !== 'other') $type = '';

$where = []; $types = ''; $params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(e.full_name LIKE ? OR e.email LIKE ?)';
    $types .= 'ss';
    array_push($params, $like, $like);
}
if ($status !== '') { $where[] = 'e.status = ?'; $types .= 's'; $params[] = $status; }
if ($type === 'other') {
    $where[] = "(e.enquiry_type IS NULL OR e.enquiry_type NOT IN ('plan_trip','booking_help','general','report_issue','destination','feature'))";
} elseif ($type !== '') {
    $where[] = 'e.enquiry_type = ?'; $types .= 's'; $params[] = $type;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ---- Counts and pagination ---- */
$total    = (int) (enq_fetch("SELECT COUNT(*) AS n FROM enquiries e $whereSql", $types, $params)[0]['n'] ?? 0);
$newCount = (int) (enq_fetch("SELECT COUNT(*) AS n FROM enquiries WHERE status = 'New'")[0]['n'] ?? 0);
$p = paginate($total, 15, $_GET['page'] ?? 1);

$rows = enq_fetch(
    "SELECT e.enquiry_id, e.full_name, e.email, e.subject, e.message, e.status, e.created_at,
            (SELECT COUNT(*) FROM enquiry_replies r WHERE r.enquiry_id = e.enquiry_id AND r.send_status = 'Sent') AS reply_count
     FROM enquiries e $whereSql
     ORDER BY e.created_at DESC, e.enquiry_id DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, [$p['per'], $p['offset']])
);

$filterParams = array_filter(['q' => $q, 'type' => $type, 'status' => $status], fn($v) => $v !== '');
$filtering = $q !== '' || $type !== '' || $status !== '';

include __DIR__ . '/includes/page-start.php';
?>
<section class="card enq-card">
  <header class="enq-header">
    <div>
      <h3>Enquiries</h3>
      <p>
        <?= number_format($total) ?> <?= $total === 1 ? 'enquiry' : 'enquiries' ?><?= $filtering ? ' match your filters' : '' ?>
        <span class="enq-newpill" id="enqNewCount"><?= $newCount ?> new</span>
      </p>
    </div>
  </header>

  <form class="enq-toolbar" id="enqFilters" method="get">
    <div class="enq-field">
      <label class="enq-label" for="enqQ">Search by name or email</label>
      <div class="enq-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input id="enqQ" type="search" name="q" value="<?= h($q) ?>" placeholder="Search name or email" maxlength="100" autocomplete="off">
      </div>
    </div>
    <div class="enq-field">
      <label class="enq-label" for="enqType">Filter by enquiry type</label>
      <select id="enqType" name="type">
        <option value="">All enquiry types</option>
        <?php foreach (ENQ_FILTER_TYPES as $key => $label): ?>
          <option value="<?= h($key) ?>"<?= $type === $key ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
        <option value="other"<?= $type === 'other' ? ' selected' : '' ?>>Other</option>
      </select>
    </div>
    <div class="enq-field">
      <label class="enq-label" for="enqStatus">Filter by status</label>
      <select id="enqStatus" name="status">
        <option value="">All statuses</option>
        <?php foreach (ENQ_FILTER_STATUSES as $s): ?>
          <option value="<?= h($s) ?>"<?= $status === $s ? ' selected' : '' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <noscript><button class="btn btn-primary btn-sm" type="submit">Apply filters</button></noscript>
  </form>

  <div class="table-wrap enq-wrap" data-from="<?= h(MAIL_FROM_EMAIL) ?>">
    <table class="data enq-table" id="enqTable">
      <colgroup>
        <col class="col-id"><col class="col-user"><col class="col-email"><col class="col-type">
        <col class="col-msg"><col class="col-date"><col class="col-status"><col class="col-actions">
      </colgroup>
      <thead>
        <tr>
          <th>ID</th><th>User</th><th>Email</th><th>Enquiry type</th><th>Message</th>
          <th>Submitted</th><th>Status</th><th class="c-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8">
            <div class="empty">
              <i class="fa-regular fa-envelope-open"></i>
              <h4><?= $filtering ? 'No enquiries match these filters' : 'No enquiries yet' ?></h4>
              <p><?= $filtering ? 'Try a different search or clear the filters.' : 'Messages sent from the Contact page will appear here.' ?></p>
            </div>
          </td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r):
          $preview = mb_strimwidth(preg_replace('/\s+/', ' ', (string) $r['message']), 0, 90, '…', 'UTF-8');
          $label = enq_type_label($r['subject']);
          $replied = (int) $r['reply_count'] > 0;
        ?>
        <tr data-id="<?= (int) $r['enquiry_id'] ?>">
          <td class="enq-id">#<?= (int) $r['enquiry_id'] ?></td>
          <td>
            <div class="enq-person">
              <span class="enq-avatar" aria-hidden="true"><?= h(initials($r['full_name'])) ?></span>
              <strong class="enq-name" title="<?= h($r['full_name']) ?>"><?= h($r['full_name']) ?></strong>
            </div>
          </td>
          <td><span class="enq-email" title="<?= h($r['email']) ?>"><?= h($r['email']) ?></span></td>
          <td><span class="enq-type" title="<?= h($label) ?>"><?= h($label) ?></span></td>
          <td><span class="enq-msg"><?= h($preview) ?></span></td>
          <td class="enq-date"><?= h(fmt_datetime($r['created_at'])) ?></td>
          <td class="enq-statuscell">
            <span class="enq-badge <?= h(enq_status_class($r['status'])) ?>" data-status-cell><?= h($r['status']) ?></span>
            <span class="enq-replied" data-reply-cell<?= $replied ? '' : ' hidden' ?>><i class="fa-solid fa-reply"></i> Replied</span>
          </td>
          <td class="c-actions">
            <div class="enq-actions">
              <button type="button" class="enq-icon-btn enq-icon-btn--view" data-enq-view="<?= (int) $r['enquiry_id'] ?>"
                      title="View enquiry" aria-label="View enquiry #<?= (int) $r['enquiry_id'] ?>">
                <i class="fa-regular fa-eye"></i>
              </button>
              <button type="button" class="enq-icon-btn" data-enq-reply="<?= (int) $r['enquiry_id'] ?>"
                      title="Reply to customer" aria-label="Reply to enquiry #<?= (int) $r['enquiry_id'] ?>">
                <i class="fa-solid fa-reply"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total > 0): ?>
    <div class="enq-pager"><?= pager($p, $filterParams) ?></div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/page-end.php'; ?>