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
      'type'    => 'file',
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
    if (empty($sub)) {
      // Empty leaf folder — record it so migration can create a .keep placeholder
      $files[] = [
        'type' => 'folder',
        'name' => $folder['name'],
        'path' => $path,
        'tree' => $tree,
      ];
    } else {
      $files = array_merge($files, $sub);
    }
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
  $type     = ($_POST['type'] ?? 'file') === 'folder' ? 'folder' : 'file';
  $driveId  = $_POST['driveId']  ?? '';
  $name     = $_POST['name']     ?? '';
  $path     = $_POST['path']     ?? '';
  $tree     = ($_POST['tree'] ?? '') === 'private' ? 'private' : 'public';

  if (!isset($buildings[$building]) || !$name) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
  }

  $cfg    = _r2Cfg();
  $prefix = $building . '/' . $tree . '/' . ($path ? trim($path, '/') . '/' : '');

  // Empty folder — create a .keep placeholder
  if ($type === 'folder') {
    $key     = $prefix . $name . '/.keep';
    $objPath = '/' . $cfg['bucket'] . '/' . $key;
    [$status, ] = _r2Request('PUT', $objPath, [], ['content-type' => 'text/plain'], '');
    if ($status === 200 || $status === 204) {
      echo json_encode(['ok' => true, 'key' => $key]);
    } else {
      echo json_encode(['ok' => false, 'error' => 'R2 folder create failed (HTTP ' . $status . ')']);
    }
    exit;
  }

  // File — fetch from Drive
  if (!$driveId) {
    echo json_encode(['ok' => false, 'error' => 'Missing driveId']);
    exit;
  }
  $info = driveGetDownloadInfo($driveId);
  if ($info['type'] === 'error') {
    echo json_encode(['ok' => false, 'error' => 'Drive: ' . $info['message']]);
    exit;
  }

  // Write to temp file
  $tmpFile = tempnam(sys_get_temp_dir(), 'r2mig_');
  file_put_contents($tmpFile, base64_decode($info['data']));

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
// AJAX: mark building migration complete + clear listing cache
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

  // Clear all listing cache entries for this building
  $cacheDir = __DIR__ . '/cache/';
  $safe     = preg_replace('/[^a-zA-Z0-9]/', '', $b);
  if (is_dir($cacheDir)) {
    foreach (glob($cacheDir . '*_' . $safe . '_*.json') as $f) @unlink($f);
  }

  echo json_encode(['ok' => true]);
  exit;
}

// -------------------------------------------------------
// AJAX: list stray top-level prefixes under a building
// (anything that isn't public/ or private/)
// GET ?action=list_stray&building=X
// -------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'list_stray') {
  header('Content-Type: application/json');
  $b = $_GET['building'] ?? '';
  if (!isset($buildings[$b])) { echo json_encode(['ok' => false, 'error' => 'Invalid building']); exit; }

  $cfg = _r2Cfg();
  [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], [
    'list-type' => '2',
    'prefix'    => $b . '/',
    'delimiter' => '/',
    'max-keys'  => '100',
  ]);
  if ($status !== 200) {
    echo json_encode(['ok' => false, 'error' => 'R2 list failed (HTTP ' . $status . ')']); exit;
  }

  $sx       = @simplexml_load_string($body);
  $expected = [$b . '/public/', $b . '/private/'];
  $stray    = [];
  if ($sx) {
    foreach ($sx->CommonPrefixes as $cp) {
      $p = (string)$cp->Prefix;
      if (!in_array($p, $expected)) $stray[] = $p;
    }
  }
  echo json_encode(['ok' => true, 'stray' => $stray]);
  exit;
}

// -------------------------------------------------------
// AJAX: delete all objects under a stray prefix
// POST action=delete_stray, building, prefix
// Only allowed for prefixes outside public/ and private/
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_stray') {
  header('Content-Type: application/json');
  $b      = $_POST['building'] ?? '';
  $prefix = $_POST['prefix']   ?? '';

  if (!isset($buildings[$b])) { echo json_encode(['ok' => false, 'error' => 'Invalid building']); exit; }
  if (!$prefix || strpos($prefix, $b . '/') !== 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid prefix']); exit;
  }
  if (strpos($prefix, $b . '/public/') === 0 || strpos($prefix, $b . '/private/') === 0) {
    echo json_encode(['ok' => false, 'error' => 'Use file manager for public/private trees']); exit;
  }

  $cfg = _r2Cfg();
  [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], [
    'list-type' => '2',
    'prefix'    => $prefix,
    'max-keys'  => '1000',
  ]);
  if ($status !== 200) {
    echo json_encode(['ok' => false, 'error' => 'List failed (HTTP ' . $status . ')']); exit;
  }

  $sx      = @simplexml_load_string($body);
  $deleted = 0;
  $errors  = [];
  if ($sx) {
    foreach ($sx->Contents as $obj) {
      $key     = (string)$obj->Key;
      $objPath = '/' . $cfg['bucket'] . '/' . ltrim($key, '/');
      [$dStatus, ] = _r2Request('DELETE', $objPath);
      if ($dStatus === 204 || $dStatus === 200) { $deleted++; }
      else { $errors[] = $key; }
    }
  }

  if (empty($errors)) {
    echo json_encode(['ok' => true, 'deleted' => $deleted]);
  } else {
    echo json_encode(['ok' => false, 'error' => 'Failed to delete: ' . implode(', ', $errors)]);
  }
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
    .btn-stray { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
    .btn-stray:hover { background: #e5e7eb; }
    .stray-row { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0; }
    .btn-del-stray { background: #fee2e2; color: #991b1b; font-size: 0.8rem; padding: 0.15rem 0.6rem; }
    .btn-del-stray:hover { background: #fca5a5; }
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
    <button class="btn-stray" onclick="checkStray(<?= htmlspecialchars(json_encode($name)) ?>)">Check Stray Folders</button>
  </div>
  <div id="stray-<?= htmlspecialchars($name) ?>" style="display:none;margin-top:0.5rem;font-size:0.85rem;"></div>

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
      var files   = data.files.filter(function(f) { return f.type !== 'folder'; });
      var folders = data.files.filter(function(f) { return f.type === 'folder'; });
      var pub     = data.files.filter(function(f) { return f.tree === 'public'; }).length;
      var priv    = data.files.filter(function(f) { return f.tree === 'private'; }).length;
      label.textContent = files.length + ' files + ' + folders.length + ' empty folder(s) found (' + pub + ' public, ' + priv + ' private)';
      note.textContent  = 'Large files (>50 MB) may fail — upload those to R2 manually after migration.';
      btn.disabled = data.files.length === 0;
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
    var isFolder = f.type === 'folder';
    label.textContent = (i + 1) + ' / ' + total + ' — ' + (isFolder ? '📁 ' : '') + f.tree + '/' + display + (isFolder ? '/' : '');
    bar.style.width = Math.round((i / total) * 100) + '%';

    var body = new URLSearchParams({
      action:   'migrate_file',
      building: building,
      type:     f.type || 'file',
      driveId:  f.driveId || '',
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

function checkStray(building) {
  var el = document.getElementById('stray-' + building);
  el.style.display = 'block';
  el.innerHTML = 'Checking R2 for stray folders…';
  fetch('migrate-to-r2.php?action=list_stray&building=' + encodeURIComponent(building))
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) { el.innerHTML = 'Error: ' + (data.error || 'unknown'); return; }
      if (!data.stray.length) { el.innerHTML = '<span style="color:#059669">✓ No stray folders found.</span>'; return; }
      var html = '<strong style="color:#92400e">Stray prefixes found:</strong><br>';
      data.stray.forEach(function(prefix) {
        var safeB = building.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        var safeP = prefix.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        html += '<div class="stray-row"><code>' + prefix + '</code>'
              + '<button class="btn-del-stray" data-building="' + safeB + '" data-prefix="' + safeP + '" onclick="deleteStray(this.dataset.building,this.dataset.prefix,this)">Delete</button>'
              + '</div>';
      });
      el.innerHTML = html;
    })
    .catch(function(e) { el.innerHTML = 'Failed: ' + e.message; });
}

function deleteStray(building, prefix, btn) {
  if (!confirm('Delete all objects under "' + prefix + '"?')) return;
  btn.disabled = true;
  btn.textContent = 'Deleting…';
  var body = new URLSearchParams({ action: 'delete_stray', building: building, prefix: prefix });
  fetch('migrate-to-r2.php', { method: 'POST', body: body })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var row = btn.closest('.stray-row');
      if (data.ok) {
        row.innerHTML = '<span style="color:#059669">✓ Deleted ' + prefix + ' (' + data.deleted + ' object(s))</span>';
      } else {
        btn.disabled = false;
        btn.textContent = 'Delete';
        alert('Error: ' + (data.error || 'unknown'));
      }
    })
    .catch(function(e) { btn.disabled = false; btn.textContent = 'Delete'; alert(e.message); });
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
