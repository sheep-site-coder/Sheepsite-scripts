<?php
// -------------------------------------------------------
// storage-report.php
// Shows storage usage breakdown for a building's Public
// and Private Drive folders, with per-subfolder totals.
//
// Admin-authenticated — reuses manage_auth_{building} session.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',  __DIR__ . '/credentials/');
define('CONFIG_DIR',       __DIR__ . '/config/');
require_once __DIR__ . '/storage/storage.php';

// -------------------------------------------------------
// Validate building + session
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$adminCredFile = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
  header('Location: admin.php?building=' . urlencode($building));
  exit;
}

$sessionKey = 'manage_auth_' . $building;
if (empty($_SESSION[$sessionKey])) {
  header('Location: admin.php?building=' . urlencode($building));
  exit;
}

$buildings = require __DIR__ . '/buildings.php';

if (!isset($buildings[$building])) {
  die('<p style="color:red;">Building not configured in buildings.php.</p>');
}

$config     = $buildings[$building];
$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));

// -------------------------------------------------------
// JSON mode — called by client-side JS for each folder
// -------------------------------------------------------
if (isset($_GET['json'])) {
  $tree = $_GET['json'] === 'private' ? 'private' : 'public';
  header('Content-Type: application/json');
  echo stStorageReport($building, $tree);
  exit;
}

// -------------------------------------------------------
// POST: cache grand total
// Called by JS once both public + private totals are known.
// Writes storageUsed + storageUpdated to config/{building}.json.
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cacheTotal') {
  $total = filter_var($_POST['total'] ?? '', FILTER_VALIDATE_INT);
  if ($total !== false && $total >= 0) {
    $cfgFile = CONFIG_DIR . $building . '.json';
    $cfg     = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) ?? [] : [];
    $cfg['storageUsed']    = $total;
    $cfg['storageUpdated'] = date('c');
    if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
    file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT));
  }
  header('Content-Type: application/json');
  echo json_encode(['ok' => true]);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Storage Report</title>
  <style>
    body          { font-family: sans-serif; max-width: 680px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar      { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1            { margin: 0; font-size: 1.5rem; }
    .back         { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover   { text-decoration: underline; }
    h2            { font-size: 1.05rem; margin: 2rem 0 0.6rem; color: #333; border-bottom: 1px solid #eee; padding-bottom: 0.4rem; }
    table         { width: 100%; border-collapse: collapse; font-size: 0.9rem; margin-bottom: 0.5rem; }
    th            { text-align: left; padding: 0.45rem 0.75rem; border-bottom: 2px solid #ddd; color: #555; font-weight: 600; }
    td            { padding: 0.45rem 0.75rem; border-bottom: 1px solid #eee; }
    .total-row td { border-bottom: none; border-top: 2px solid #ddd; font-weight: bold; }
    .size         { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .spinner      { display: inline-block; width: 16px; height: 16px; border: 3px solid #ddd;
                    border-top-color: #0070f3; border-radius: 50%; animation: spin 0.7s linear infinite;
                    vertical-align: middle; margin-right: 0.5rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading      { color: #888; font-size: 0.9rem; margin: 0.75rem 0; }
    .error        { color: #c00; font-size: 0.9rem; margin: 0.75rem 0; }
    .empty        { color: #888; font-size: 0.9rem; margin: 0.75rem 0; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Storage</h1>
  <a href="admin.php?building=<?= urlencode($building) ?>" class="back">← Admin</a>
</div>

<div id="grand-total" style="font-size:1.1rem;font-weight:bold;margin-bottom:2rem;color:#333;"></div>

<h2>Public Folder</h2>
<div id="public-area"><div class="loading"><span class="spinner"></span>Calculating...</div></div>

<h2>Private Folder</h2>
<div id="private-area"><div class="loading"><span class="spinner"></span>Calculating...</div></div>

<script>
function fmtSize(bytes) {
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
  if (bytes >= 1048576)    return (bytes / 1048576).toFixed(1)    + ' MB';
  if (bytes >= 1024)       return Math.round(bytes / 1024)        + ' KB';
  return bytes + ' B';
}

function renderReport(data, containerId) {
  var el = document.getElementById(containerId);

  if (data.error) {
    el.innerHTML = '<div class="error">Error: ' + data.error + '</div>';
    return;
  }

  if (!data.subfolders.length && data.total === 0) {
    el.innerHTML = '<div class="empty">Empty folder</div>';
    return;
  }

  var rows = '';
  data.subfolders.forEach(function(sf) {
    rows += '<tr><td>' + sf.name + '</td><td class="size">' + fmtSize(sf.size) + '</td></tr>';
  });
  rows += '<tr class="total-row"><td>Total</td><td class="size">' + fmtSize(data.total) + '</td></tr>';

  el.innerHTML =
    '<table>' +
      '<thead><tr><th>Folder</th><th style="text-align:right;">Size</th></tr></thead>' +
      '<tbody>' + rows + '</tbody>' +
    '</table>';
}

var building = <?= json_encode($building) ?>;
var base     = 'storage-report.php?building=' + encodeURIComponent(building);
var totals   = {};

function updateGrandTotal() {
  if (totals.public === undefined || totals.private === undefined) return;
  var grand = document.getElementById('grand-total');
  if (totals.public === null || totals.private === null) {
    grand.textContent = '';
    return;
  }
  var combined = totals.public + totals.private;
  grand.textContent = 'Total storage: ' + fmtSize(combined);

  // Cache grand total server-side for limit enforcement and dashboard display
  fetch(base, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=cacheTotal&total=' + combined
  });
}

fetch(base + '&json=public')
  .then(function(r) { return r.json(); })
  .then(function(d) {
    renderReport(d, 'public-area');
    totals.public = d.error ? null : d.total;
    updateGrandTotal();
  })
  .catch(function() {
    document.getElementById('public-area').innerHTML = '<div class="error">Failed to load</div>';
    totals.public = null;
    updateGrandTotal();
  });

fetch(base + '&json=private')
  .then(function(r) { return r.json(); })
  .then(function(d) {
    renderReport(d, 'private-area');
    totals.private = d.error ? null : d.total;
    updateGrandTotal();
  })
  .catch(function() {
    document.getElementById('private-area').innerHTML = '<div class="error">Failed to load</div>';
    totals.private = null;
    updateGrandTotal();
  });
</script>

<?php require __DIR__ . '/woolsy-admin-widget.php'; ?>
</body>
</html>
