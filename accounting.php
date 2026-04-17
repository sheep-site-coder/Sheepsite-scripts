<?php
// -------------------------------------------------------
// accounting.php — SheepSite LLC revenue reporting
//
// Master admin only. Scans all paid invoices across all
// buildings and generates Wave-compatible CSV reports.
//
// Reports are non-overlapping: each new report starts the
// day after the previous one ended and stops at yesterday.
//
// CSV format: Date,Description,Amount (UTF-8 with BOM)
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',       __DIR__ . '/config/');
define('INVOICES_DIR',     __DIR__ . '/invoices/');
define('REPORTS_DIR',      __DIR__ . '/config/accounting_reports/');
define('SESSION_KEY',      'master_admin_auth');

// -------------------------------------------------------
// Auth guard
// -------------------------------------------------------
$masterCredFile = CREDENTIALS_DIR . '_master.json';
if (!file_exists($masterCredFile)) {
  die('<p style="color:red;">Master credentials not configured.</p>');
}
if (empty($_SESSION[SESSION_KEY])) {
  header('Location: master-admin.php');
  exit;
}

// -------------------------------------------------------
// Ensure reports directory exists
// -------------------------------------------------------
if (!is_dir(REPORTS_DIR)) {
  mkdir(REPORTS_DIR, 0755, true);
  file_put_contents(REPORTS_DIR . '.htaccess', "Deny from all\n");
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------

function loadReportIndex(): array {
  $f = REPORTS_DIR . 'index.json';
  return file_exists($f) ? json_decode(file_get_contents($f), true) ?? [] : [];
}

function saveReportIndex(array $index): void {
  file_put_contents(REPORTS_DIR . 'index.json', json_encode($index, JSON_PRETTY_PRINT));
}

// Returns all paid invoices across all buildings, sorted by paidDate ASC
function loadAllPaidInvoices(): array {
  $invoices = [];
  if (!is_dir(INVOICES_DIR)) return $invoices;

  foreach (scandir(INVOICES_DIR) as $building) {
    if ($building === '.' || $building === '..') continue;
    $dir = INVOICES_DIR . $building . '/';
    if (!is_dir($dir)) continue;
    foreach (glob($dir . '*.json') as $f) {
      $inv = json_decode(file_get_contents($f), true);
      if (($inv['status'] ?? '') === 'paid' && !empty($inv['paidDate'])) {
        $invoices[] = $inv;
      }
    }
  }

  usort($invoices, fn($a, $b) => strcmp($a['paidDate'], $b['paidDate']));
  return $invoices;
}

function invoiceDescription(array $inv): string {
  $building = ucwords(str_replace(['_', '-'], ' ', $inv['building']));
  $type     = $inv['invoiceType'] ?? 'renewal';
  $id       = $inv['id'] ?? '';

  $label = match($type) {
    'renewal' => 'Annual Renewal',
    'woolsy'  => 'Woolsy Credits',
    'storage' => 'Storage Upgrade',
    default   => !empty($inv['lineItems'][0]['description'])
                   ? $inv['lineItems'][0]['description']
                   : 'Other',
  };

  return $building . ' — ' . $label . ' (' . $id . ')';
}

// Build CSV string for invoices in the given date range (inclusive)
function buildCsv(array $allInvoices, string $from, string $to): string {
  $rows = [];
  foreach ($allInvoices as $inv) {
    $d = $inv['paidDate'];
    if ($d >= $from && $d <= $to) {
      $rows[] = $inv;
    }
  }

  // UTF-8 BOM so Excel opens it correctly
  $csv = "\xEF\xBB\xBFDate,Description,Amount\n";
  foreach ($rows as $inv) {
    $date   = $inv['paidDate'];
    $desc   = str_replace(['"', "\n", "\r"], ["'", ' ', ' '], invoiceDescription($inv));
    $amount = number_format((float)$inv['total'], 2, '.', '');
    $csv   .= '"' . $date . '","' . $desc . '",' . $amount . "\n";
  }
  return $csv;
}

// -------------------------------------------------------
// Determine next report date range
// -------------------------------------------------------
$reportIndex = loadReportIndex();
$lastTo      = null;
if (!empty($reportIndex)) {
  $lastTo = $reportIndex[count($reportIndex) - 1]['to'];
}

$yesterday   = date('Y-m-d', strtotime('-1 day'));
$allInvoices = loadAllPaidInvoices();

if ($lastTo === null) {
  // First ever report — find earliest paid invoice
  $nextFrom = !empty($allInvoices) ? $allInvoices[0]['paidDate'] : date('Y-m-d');
} else {
  $nextFrom = date('Y-m-d', strtotime($lastTo . ' +1 day'));
}

$canCreate    = $nextFrom <= $yesterday;
$pendingCount = 0;
if ($canCreate) {
  foreach ($allInvoices as $inv) {
    if ($inv['paidDate'] >= $nextFrom && $inv['paidDate'] <= $yesterday) {
      $pendingCount++;
    }
  }
  if ($pendingCount === 0) $canCreate = false;
}

// -------------------------------------------------------
// Download action
// -------------------------------------------------------
if (isset($_GET['download'])) {
  $filename = basename($_GET['download']);
  // Validate: only allow our own report files
  $found = false;
  foreach ($reportIndex as $r) {
    if ($r['file'] === $filename) { $found = true; break; }
  }
  if (!$found) {
    http_response_code(404);
    die('Not found.');
  }
  $path = REPORTS_DIR . $filename;
  if (!file_exists($path)) {
    http_response_code(404);
    die('File missing.');
  }
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  readfile($path);
  exit;
}

// -------------------------------------------------------
// Create report action
// -------------------------------------------------------
$flashMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  if (!$canCreate) {
    $flashMsg = 'error:Nothing to report for this period.';
  } else {
    $csv      = buildCsv($allInvoices, $nextFrom, $yesterday);
    $filename = 'sheepsite-revenue-' . $nextFrom . '_' . $yesterday . '.csv';
    file_put_contents(REPORTS_DIR . $filename, $csv);

    $count = substr_count($csv, "\n") - 1; // subtract header
    $total = 0.0;
    foreach ($allInvoices as $inv) {
      if ($inv['paidDate'] >= $nextFrom && $inv['paidDate'] <= $yesterday) {
        $total += (float)$inv['total'];
      }
    }

    $reportIndex[] = [
      'from'  => $nextFrom,
      'to'    => $yesterday,
      'file'  => $filename,
      'count' => $count,
      'total' => round($total, 2),
    ];
    saveReportIndex($reportIndex);

    $flashMsg = 'ok:Report created: ' . $filename;

    // Recalculate for page render
    $lastTo      = $yesterday;
    $nextFrom    = date('Y-m-d', strtotime($yesterday . ' +1 day'));
    $canCreate   = false;
    $pendingCount = 0;
  }
}

[$flashType, $flashText] = $flashMsg ? explode(':', $flashMsg, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SheepSite — Revenue Reports</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body       { font-family: sans-serif; max-width: 900px; margin: 0 auto; padding: 1.5rem 1rem; background: #f5f5f5; color: #222; }
    h1         { font-size: 1.5rem; margin: 0 0 0.25rem; }
    .back      { font-size: 0.85rem; color: #555; text-decoration: none; }
    .back:hover{ text-decoration: underline; }
    .topbar    { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
    .card      { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 1.5rem; margin-bottom: 1.25rem; }
    .card h2   { margin: 0 0 1rem; font-size: 1.1rem; }
    .btn       { display: inline-block; padding: 0.5rem 1.1rem; border-radius: 4px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
    .btn-primary   { background: #1a73e8; color: #fff; }
    .btn-primary:hover { background: #1558b0; }
    .btn-secondary { background: #eee; color: #333; border: 1px solid #ccc; }
    .btn-secondary:hover { background: #ddd; }
    .btn:disabled  { opacity: 0.45; cursor: default; }
    .flash-ok  { background: #f0faf0; border: 1px solid #c3e0c3; color: #2a6b2a; border-radius: 4px; padding: 0.75rem 1rem; margin-bottom: 1rem; }
    .flash-err { background: #fff0f0; border: 1px solid #f0c0c0; color: #c00; border-radius: 4px; padding: 0.75rem 1rem; margin-bottom: 1rem; }
    .next-range{ background: #f0f7ff; border: 1px solid #b8d4f0; border-radius: 4px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.9rem; color: #1a4a8a; }
    .no-range  { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 4px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.9rem; color: #777; }
    table      { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th         { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 2px solid #ddd; color: #555; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
    td         { padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; }
    tr:last-child td { border-bottom: none; }
    .amount    { text-align: right; font-variant-numeric: tabular-nums; }
    .empty     { color: #999; font-style: italic; padding: 1rem 0.75rem; }
  </style>
</head>
<body>

<div class="topbar">
  <div>
    <a href="master-admin.php" class="back">← Master Admin</a>
    <h1>Revenue Reports</h1>
  </div>
</div>

<?php if ($flashType === 'ok'): ?>
  <div class="flash-ok"><?= htmlspecialchars($flashText) ?></div>
<?php elseif ($flashType === 'error'): ?>
  <div class="flash-err"><?= htmlspecialchars($flashText) ?></div>
<?php endif; ?>

<!-- Create new report -->
<div class="card">
  <h2>Create New Report</h2>

  <?php if ($canCreate): ?>
    <div class="next-range">
      Will cover <strong><?= $nextFrom ?></strong> to <strong><?= $yesterday ?></strong>
      &nbsp;·&nbsp; <?= $pendingCount ?> transaction<?= $pendingCount !== 1 ? 's' : '' ?>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <button type="submit" class="btn btn-primary">Create Report</button>
    </form>
  <?php elseif ($nextFrom > $yesterday): ?>
    <div class="no-range">
      Next report period starts <strong><?= $nextFrom ?></strong>.
      Reports run through yesterday — check back tomorrow.
    </div>
    <button class="btn btn-primary" disabled>Create Report</button>
  <?php else: ?>
    <div class="no-range">No paid invoices found in the next report period (<?= $nextFrom ?> to <?= $yesterday ?>).</div>
    <button class="btn btn-primary" disabled>Create Report</button>
  <?php endif; ?>
</div>

<!-- Past reports -->
<div class="card">
  <h2>Past Reports</h2>
  <?php if (empty($reportIndex)): ?>
    <div class="empty">No reports generated yet.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Period</th>
          <th>Transactions</th>
          <th class="amount">Revenue</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($reportIndex) as $r): ?>
          <tr>
            <td><?= $r['from'] ?> &rarr; <?= $r['to'] ?></td>
            <td><?= $r['count'] ?></td>
            <td class="amount">$<?= number_format($r['total'], 2) ?></td>
            <td>
              <a href="?download=<?= urlencode($r['file']) ?>" class="btn btn-secondary">Download CSV</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

</body>
</html>
