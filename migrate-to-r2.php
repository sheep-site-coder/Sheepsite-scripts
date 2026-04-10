<?php
// -------------------------------------------------------
// migrate-to-r2.php — Copy one building's files from
// Google Drive to Cloudflare R2. Master admin only.
//
// Drive files are left intact — rollback = remove the
// 'storage' => 'r2' line from buildings.php.
// -------------------------------------------------------
session_start();
define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');

$sessionKey = 'master_admin_auth';
if (empty($_SESSION[$sessionKey])) {
  header('Location: master-admin.php');
  exit;
}

require_once __DIR__ . '/storage/drive-storage.php';
require_once __DIR__ . '/storage/r2-storage.php';

$buildings = require __DIR__ . '/buildings.php';

// -------------------------------------------------------
// Helper: recursively list all files in a Drive tree
// Returns array of {driveId, name, size, path, tree}
// -------------------------------------------------------
function scanDriveTree(array $bldCfg, string $path, string $tree): array {
  $json = driveListFolder($bldCfg, $path, $tree, 'adm');
  $data = json_decode($json, true);
  if (isset($data['error']) || !is_array($data)) return [];

  $files = [];
  foreach ($data['files'] ?? [] as $f) {
    $files[] = [
      'driveId' => $f['id'],
      'name'    => $f['name'],
      'size'    => $f['size'] ?? '',
      'path'    => $path,
      'tree'    => $tree,
    ];
  }
  foreach ($data['folders'] ?? [] as $folder) {
    $subPath = $path ? $path . '/' . $folder['name'] : $folder['name'];
    $sub = scanDriveTree($bldCfg, $subPath, $tree);
    $files = array_merge($files, $sub);
  }
  return $files;
}

// -------------------------------------------------------
// Helper: load migration status from config
// -------------------------------------------------------
function migrationStatus(string $building): string {
  $file = CONFIG_DIR . $building . '.json';
  if (!file_exists($file)) return 'not_started';
  $cfg = json_decode(file_get_contents($file), true) ?? [];
  return $cfg['r2Migrated'] ?? false ? 'complete' : 'not_started';
}

// -------------------------------------------------------
// AJAX: scan Drive tree for a building
// GET ?action=scan&building=X
// Returns JSON: [{driveId, name, size, path, tree}, ...]
// -------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'scan') {
  set_time_limit(120);
  header('Content-Type: application/json');
  $b = $_GET['building'] ?? '';
  if (!isset($buildings[$b])) { echo json_encode(['error' => 'Invalid building']); exit; }

  $cfg   = $buildings[$b];
  $files = array_merge(
    scanDriveTree($cfg, '', 'public'),
    scanDriveTree($cfg, '', 'private')
  );
  echo json_encode(['ok' => true, 'files' => $files, 'count' => count($files)]);
  exit;
}

// -------------------------------------------------------
// AJAX: migrate one file from Drive to R2
// POST action=migrate_file
// Body: building, driveId, name, path, tree
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'migrate_file') {
  set_time_limit(120);
  header('Content-Type: application/json');

  $building = $_POST['building'] ?? '';
  $driveId  = $_POST['driveId']  ?? '';
  $name     = $_POST['name']     ?? '';
  $path     = $_POST['path']     ?? '';
  $tree     = ($_POST['tree'] ?? '') === 'private' ? 'private' : 'public';

  if (!isset($buildings[$building]) || !$driveId || !$name) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
  }

  // Fetch from Drive
  $info = driveGetDownloadInfo($driveId);
  if ($info['type'] === 'error') {
    echo json_encode(['ok' => false, 'error' => 'Drive: ' . $info['message']]);
    exit;
  }

  // Write to temp file
  $tmpFile = tempnam(sys_get_temp_dir(), 'r2mig_');
  file_put_contents($tmpFile, base64_decode($info['data']));

  // Target R2 key
  $cfg     = _r2Cfg();
  $prefix  = $building . '/' . $tree . '/' . ($path ? trim($path, '/') . '/' : '');
  $key     = $prefix . $name;
  $objPath = '/' . $cfg['bucket'] . '/' . $key;

  [$status, ] = _r2Request('PUT', $objPath, [], ['content-type' => $info['mimeType']], file_get_contents($tmpFile));
  unlink($tmpFile);

  if ($status === 200 || $status === 204) {
    echo json_encode(['ok' => true, 'key' => $key]);
  } else {
    echo json_encode(['ok' => false, 'error' => 'R2 upload failed (HTTP ' . $status . ')']);
  }
  exit;
}

// -------------------------------------------------------
// AJAX: mark building migration complete
// POST action=mark_complete&building=X
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_complete') {
  header('Content-Type: application/json');
  $b = $_POST['building'] ?? '';
  if (!isset($buildings[$b])) { echo json_encode(['ok' => false]); exit; }

  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  $cfgFile = CONFIG_DIR . $b . '.json';
  $cfg     = file_exists($cfgFile) ? (json_decode(file_get_contents($cfgFile), true) ?? []) : [];
  $cfg['r2Migrated'] = true;
  file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT));
  echo json_encode(['ok' => true]);
  exit;
}

// -------------------------------------------------------
// Page render
// -------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Migrate to R2</title>
  <style>
    body          { font-family: sans-serif; max-width: 800px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar      { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1            { margin: 0; font-size: 1.4rem; }
    .back         { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover   { text-decoration: underline; }
    .card         { border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .building-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
    .bldg-name    { font-weight: bold; font-size: 1rem; min-width: 140px; }
    .badge        { font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 12px; font-weight: 600; }
    .badge-done   { background: #d1fae5; color: #065f46; }
    .badge-todo   { background: #fef3c7; color: #92400e; }
    .badge-r2     { background: #dbeafe; color: #1e40af; }
    button        { padding: 0.4rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; }
    .btn-scan     { background: #6366f1; color: #fff; }
    .btn-scan:hover   { background: #4f46e5; }
    .btn-migrate  { background: #0070f3; color: #fff; }
    .btn-migrate:hover { background: #005bb5; }
    .btn-migrate:disabled { background: #93c5fd; cursor: default; }
    .progress-area { margin-top: 1rem; display: none; }
    .progress-bar  { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 0.5rem; }
    .progress-fill { height: 100%; background: #0070f3; transition: width 0.2s; }
    .progress-log  { font-size: 0.82rem; color: #555; max-height: 200px; overflow-y: auto;
                     border: 1px solid #eee; border-radius: 4px; padding: 0.5rem; font-family: monospace; }
    .log-ok    { color: #059669; }
    .log-err   { color: #dc2626; }
    .log-info  { color: #6b7280; }
    .summary   { margin-top: 0.75rem; font-weight: bold; }
    .note      { font-size: 0.82rem; color: #6b7280; margin-top: 0.5rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1>Migrate Files to R2</h1>
  <a href="master-admin.php" class="back">← Master Admin</a>
</div>

<p style="color:#555;margin-bottom:2rem;">
  Copies each building's files from Google Drive to Cloudflare R2.
  Drive files are <strong>not deleted</strong> — you can roll back by removing
  <code>'storage' => 'r2'</code> from <code>buildings.php</code>.
</p>

<?php foreach ($buildings as $name => $cfg): ?>
<?php
  $status  = migrationStatus($name);
  $isR2    = ($cfg['storage'] ?? 'drive') === 'r2';
?>
<div class="card" id="card-<?= htmlspecialchars($name) ?>">
  <div class="building-row">
    <span class="bldg-name"><?= htmlspecialchars($name) ?></span>
    <?php if ($status === 'complete'): ?>
      <span class="badge badge-done">✓ Migrated</span>
    <?php else: ?>
      <span class="badge badge-todo">Not migrated</span>
    <?php endif; ?>
    <?php if ($isR2): ?>
      <span class="badge badge-r2">Active: R2</span>
    <?php endif; ?>
    <?php if (!$isR2 && $status === 'complete'): ?>
      <span style="font-size:0.82rem;color:#6b7280;">Ready to switch — add <code>'storage'=>'r2'</code> to buildings.php</span>
    <?php endif; ?>
    <button class="btn-scan" onclick="startScan(<?= htmlspecialchars(json_encode($name)) ?>)">Scan Drive</button>
  </div>

  <div class="progress-area" id="progress-<?= htmlspecialchars($name) ?>">
    <div class="progress-bar"><div class="progress-fill" id="bar-<?= htmlspecialchars($name) ?>" style="width:0%"></div></div>
    <div id="progress-label-<?= htmlspecialchars($name) ?>" style="font-size:0.85rem;color:#555;margin-bottom:0.5rem;"></div>
    <button class="btn-migrate" id="btn-migrate-<?= htmlspecialchars($name) ?>" disabled
            onclick="startMigrate(<?= htmlspecialchars(json_encode($name)) ?>)">Migrate All Files</button>
    <div class="note" id="note-<?= htmlspecialchars($name) ?>"></div>
    <div class="progress-log" id="log-<?= htmlspecialchars($name) ?>"></div>
    <div class="summary" id="summary-<?= htmlspecialchars($name) ?>"></div>
  </div>
</div>
<?php endforeach; ?>

<script>
var scanResults = {};

function startScan(building) {
  var area  = document.getElementById('progress-' + building);
  var label = document.getElementById('progress-label-' + building);
  var btn   = document.getElementById('btn-migrate-' + building);
  var note  = document.getElementById('note-' + building);
  area.style.display = 'block';
  label.textContent  = 'Scanning Drive…';
  btn.disabled       = true;
  note.textContent   = '';
  document.getElementById('log-' + building).innerHTML     = '';
  document.getElementById('summary-' + building).innerHTML = '';
  document.getElementById('bar-' + building).style.width   = '0%';

  fetch('migrate-to-r2.php?action=scan&building=' + encodeURIComponent(building))
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) { label.textContent = 'Scan error: ' + (data.error || 'unknown'); return; }
      scanResults[building] = data.files;
      var pub  = data.files.filter(function(f) { return f.tree === 'public'; }).length;
      var priv = data.files.filter(function(f) { return f.tree === 'private'; }).length;
      label.textContent = data.count + ' files found (' + pub + ' public, ' + priv + ' private)';
      note.textContent  = 'Large files (>50 MB) may fail — upload those to R2 manually after migration.';
      btn.disabled = data.count === 0;
    })
    .catch(function(e) { label.textContent = 'Scan failed: ' + e.message; });
}

function startMigrate(building) {
  var files = scanResults[building];
  if (!files || !files.length) return;

  var btn     = document.getElementById('btn-migrate-' + building);
  var log     = document.getElementById('log-' + building);
  var label   = document.getElementById('progress-label-' + building);
  var bar     = document.getElementById('bar-' + building);
  var summary = document.getElementById('summary-' + building);
  btn.disabled = true;
  log.innerHTML = '';
  summary.innerHTML = '';

  var total = files.length, done = 0, errors = 0;

  function migrateNext(i) {
    if (i >= files.length) {
      bar.style.width = '100%';
      label.textContent = 'Migration complete';
      summary.innerHTML = '✓ ' + (total - errors) + ' migrated' + (errors ? ', <span style="color:#dc2626">' + errors + ' failed</span>' : '');
      if (errors === 0) markComplete(building);
      return;
    }

    var f        = files[i];
    var display  = (f.path ? f.path + '/' : '') + f.name;
    label.textContent = (i + 1) + ' / ' + total + ' — ' + f.tree + '/' + display;
    bar.style.width = Math.round((i / total) * 100) + '%';

    var body = new URLSearchParams({
      action:   'migrate_file',
      building: building,
      driveId:  f.driveId,
      name:     f.name,
      path:     f.path,
      tree:     f.tree,
    });

    fetch('migrate-to-r2.php', { method: 'POST', body: body })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        done++;
        if (res.ok) {
          appendLog(log, '✓ ' + f.tree + '/' + display, 'log-ok');
        } else {
          errors++;
          appendLog(log, '✗ ' + f.tree + '/' + display + ' — ' + (res.error || 'failed'), 'log-err');
        }
        migrateNext(i + 1);
      })
      .catch(function(e) {
        errors++;
        appendLog(log, '✗ ' + display + ' — ' + e.message, 'log-err');
        migrateNext(i + 1);
      });
  }

  migrateNext(0);
}

function markComplete(building) {
  var body = new URLSearchParams({ action: 'mark_complete', building: building });
  fetch('migrate-to-r2.php', { method: 'POST', body: body })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.ok) {
        var card = document.getElementById('card-' + building);
        var badge = card.querySelector('.badge-todo');
        if (badge) { badge.className = 'badge badge-done'; badge.textContent = '✓ Migrated'; }
      }
    });
}

function appendLog(el, msg, cls) {
  var line = document.createElement('div');
  line.className = cls || '';
  line.textContent = msg;
  el.appendChild(line);
  el.scrollTop = el.scrollHeight;
}
</script>
</body>
</html>
