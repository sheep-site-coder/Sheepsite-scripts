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
define('APPS_SCRIPT_URL',  'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('APPS_SCRIPT_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');  // must match SECRET_TOKEN in dir-display-bridge.gs

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
  $type     = $_GET['json'];
  $folderId = $type === 'private' ? $config['privateFolderId'] : $config['publicFolderId'];
  $url      = APPS_SCRIPT_URL
            . '?action=storageReport'
            . '&folderId=' . urlencode($folderId)
            . '&token='    . urlencode(APPS_SCRIPT_TOKEN);

  $response = @file_get_contents($url);
  header('Content-Type: application/json');
  echo $response !== false ? $response : json_encode(['error' => 'Could not reach Apps Script']);
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
  grand.textContent = 'Total storage: ' + fmtSize(totals.public + totals.private);
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

</body>
</html>
