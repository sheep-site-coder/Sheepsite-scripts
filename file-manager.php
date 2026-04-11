<?php
// -------------------------------------------------------
// file-manager.php
// Admin UI for managing files in Public and Private folders.
// Upload, delete, rename files, and create subfolders.
//
//   https://sheepsite.com/Scripts/file-manager.php?building=LyndhurstH
//
// Admin-authenticated — reuses manage_auth_{building} session.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',   __DIR__ . '/credentials/');
define('CONFIG_DIR',        __DIR__ . '/config/');
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
// Config helpers: track app-created folder IDs
// Stored in config/{building}_folders.json as a flat array of IDs.
// Only these folders ever get a Delete button in the UI.
// -------------------------------------------------------
function loadCreatedFolders(string $building): array {
  $file = CONFIG_DIR . $building . '_folders.json';
  if (!file_exists($file)) return [];
  return json_decode(file_get_contents($file), true) ?: [];
}

function saveCreatedFolders(string $building, array $ids): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . $building . '_folders.json', json_encode(array_values($ids)));
}

function loadBuildingCfg(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

// -------------------------------------------------------
// JSON: background storage refresh
// Fires on page load — fetches public + private totals from
// GAS, sums them, writes to config/{building}.json.
// Returns {ok:true, total:bytes} — caller ignores the response.
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'storage_refresh') {
  header('Content-Type: application/json');

  $total   = stStorageUsed($building);
  $cfgFile = CONFIG_DIR . $building . '.json';
  $cfg     = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) ?? [] : [];
  $cfg['storageUsed']    = $total;
  $cfg['storageUpdated'] = date('c');
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT));

  echo json_encode(['ok' => true, 'total' => $total]);
  exit;
}

// -------------------------------------------------------
// JSON: generate a storage billing URL for Buy More Storage
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'billing_url') {
  header('Content-Type: application/json');
  require_once __DIR__ . '/billing-helpers.php';
  require_once __DIR__ . '/invoice-helpers.php';
  $cfg = loadBillingBuildingConfig($building);
  if (empty($cfg['contactEmail'])) {
    echo json_encode(['ok' => false, 'error' => 'No contact email set for this building.']);
    exit;
  }
  $token = generateBillingToken($building, 'storage', $cfg);
  $cfg['billingToken']['invoiceId'] = null; // no invoice for direct link
  saveBillingBuildingConfig($building, $cfg);
  $url = 'billing.php?' . http_build_query(['building' => $building, 'type' => 'storage', 'token' => $token]);
  echo json_encode(['ok' => true, 'url' => $url]);
  exit;
}

// -------------------------------------------------------
// JSON: list folder contents (proxied from Apps Script)
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'list') {
  $tree = ($_GET['tree'] ?? '') === 'private' ? 'private' : 'public';
  $path = trim($_GET['path'] ?? '', '/');

  $raw  = stListFolder($building, $path, $tree, 'adm');
  $data = json_decode($raw, true);

  // Annotate deletable folders (only those created via this app)
  if (isset($data['folders']) && is_array($data['folders'])) {
    $createdIds = array_flip(loadCreatedFolders($building));
    foreach ($data['folders'] as &$f) {
      if (isset($f['id']) && isset($createdIds[$f['id']])) {
        $f['deletable'] = true;
      }
    }
    unset($f);
  }

  // Annotate system-protected files (embedded on building website by name — must not be renamed or deleted)
  $protectedNames = ['Announcement Page1', 'Mid-End Year Report'];
  if (isset($data['files']) && is_array($data['files'])) {
    foreach ($data['files'] as &$f) {
      if (in_array($f['name'] ?? '', $protectedNames, true)) {
        $f['protected'] = true;
      }
    }
    unset($f);
  }

  $annotated = json_encode($data);
  header('Content-Type: application/json');
  echo $annotated;
  exit;
}

// -------------------------------------------------------
// POST endpoints — all return JSON
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');

  // PHP silently drops $_POST when the request body exceeds post_max_size.
  // Detect it and return a clear error instead of falling through to "Unknown action".
  if (empty($_POST) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $limit = ini_get('post_max_size');
    echo json_encode(['ok' => false, 'error' => 'File exceeds server upload limit (post_max_size: ' . $limit . '). Increase post_max_size and upload_max_filesize in php.ini or .htaccess.']);
    exit;
  }

  $action = $_POST['action'] ?? '';

  // ---- Upload ----
  if ($action === 'upload') {
    set_time_limit(300);
    $folderId = trim($_POST['folderId'] ?? '');

    if (!$folderId || str_contains($folderId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $folderId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid folder ID']);
      exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      $code = $_FILES['file']['error'] ?? -1;
      echo json_encode(['ok' => false, 'error' => 'Upload error (code ' . $code . ')']);
      exit;
    }

    $file = $_FILES['file'];

    // Storage limit check (uses cached value from storage_refresh or cron)
    $bCfg        = loadBuildingCfg($building);
    $pricingFile  = CONFIG_DIR . 'pricing.json';
    $pricing      = file_exists($pricingFile) ? json_decode(file_get_contents($pricingFile), true) ?? [] : [];
    $defaultLimit = (int)($pricing['storageDefaultLimit'] ?? 524288000);
    $storageLimit = (int)($bCfg['storageLimit'] ?? $defaultLimit);
    $storageUsed  = (int)($bCfg['storageUsed']  ?? 0);
    // Always enforce the limit if storageUsed is known; if unknown, still block when the
    // file alone would exceed the limit (catches the "never measured" case).
    $wouldExceed = $storageUsed > 0
      ? ($storageUsed + $file['size']) > $storageLimit
      : $file['size'] > $storageLimit;
    if ($wouldExceed) {
      require_once __DIR__ . '/billing-helpers.php';
      checkStorageThreshold($building);
      echo json_encode(['ok' => false, 'error' => 'Storage limit reached. Your administrator has been notified to add more storage.']);
      exit;
    }

    $fileName = basename($file['name']);
    $mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
    $cTree    = ($_POST['cacheTree'] ?? '') === 'private' ? 'private' : 'public';
    $cPath    = trim($_POST['cachePath'] ?? '', '/');

    $result = stUploadFile($building, $cTree, $folderId, $cPath, $file['tmp_name'], $fileName, $mimeType);
    echo $result;
    exit;
  }

  // ---- Delete ----
  if ($action === 'delete') {
    $fileId = trim($_POST['fileId'] ?? '');
    if (!$fileId || str_contains($fileId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $fileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid file ID']);
      exit;
    }
    $cTree  = ($_POST['cacheTree'] ?? '') === 'private' ? 'private' : 'public';
    $result = stDeleteFile($building, $fileId, $cTree);
    echo $result;
    exit;
  }

  // ---- Rename ----
  if ($action === 'rename') {
    $fileId  = trim($_POST['fileId']  ?? '');
    $newName = trim($_POST['newName'] ?? '');
    if (!$fileId || str_contains($fileId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $fileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid file ID']);
      exit;
    }
    if (!$newName || strlen($newName) > 255) {
      echo json_encode(['ok' => false, 'error' => 'Invalid file name']);
      exit;
    }
    $cTree  = ($_POST['cacheTree'] ?? '') === 'private' ? 'private' : 'public';
    $result = stRenameFile($building, $fileId, $newName, $cTree);
    echo $result;
    exit;
  }

  // ---- Migrate tags (old file ID → new file ID after replace) ----
  if ($action === 'migrateTags') {
    $oldFileId = trim($_POST['oldFileId'] ?? '');
    $newFileId = trim($_POST['newFileId'] ?? '');
    if (!$oldFileId || str_contains($oldFileId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $oldFileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid old file ID']);
      exit;
    }
    if (!$newFileId || str_contains($newFileId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $newFileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid new file ID']);
      exit;
    }
    $tagsFile = __DIR__ . '/tags/' . $building . '.json';
    if (!file_exists($tagsFile)) {
      echo json_encode(['ok' => true, 'migrated' => false]);
      exit;
    }
    $tags = json_decode(file_get_contents($tagsFile), true) ?: [];
    if (isset($tags[$oldFileId])) {
      $tags[$newFileId] = $tags[$oldFileId];
      unset($tags[$oldFileId]);
      file_put_contents($tagsFile, json_encode($tags, JSON_PRETTY_PRINT));
      echo json_encode(['ok' => true, 'migrated' => true]);
    } else {
      echo json_encode(['ok' => true, 'migrated' => false]);
    }
    exit;
  }

  // ---- Create Folder ----
  if ($action === 'createFolder') {
    $parentFolderId = trim($_POST['parentFolderId'] ?? '');
    $name           = trim($_POST['name']           ?? '');
    if (!$parentFolderId || str_contains($parentFolderId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $parentFolderId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid parent folder ID']);
      exit;
    }
    if (!$name || strlen($name) > 255) {
      echo json_encode(['ok' => false, 'error' => 'Invalid folder name']);
      exit;
    }
    $cTree = ($_POST['cacheTree'] ?? '') === 'private' ? 'private' : 'public';
    $cPath = trim($_POST['cachePath'] ?? '', '/');
    $raw   = stCreateFolder($building, $cTree, $parentFolderId, $cPath, $name);
    $resp = json_decode($raw, true);
    // Track the new folder ID so it can be deleted later
    if (!empty($resp['ok']) && !empty($resp['id'])) {
      $ids   = loadCreatedFolders($building);
      $ids[] = $resp['id'];
      saveCreatedFolders($building, $ids);
    }
    echo $raw;
    exit;
  }

  // ---- Delete Folder ----
  if ($action === 'deleteFolder') {
    $folderId = trim($_POST['folderId'] ?? '');
    if (!$folderId || str_contains($folderId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $folderId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid folder ID']);
      exit;
    }
    // Only allow deletion if this folder was created via the app
    $ids = loadCreatedFolders($building);
    if (!in_array($folderId, $ids, true)) {
      echo json_encode(['ok' => false, 'error' => 'Folder cannot be deleted']);
      exit;
    }
    $cTree = ($_POST['cacheTree'] ?? '') === 'private' ? 'private' : 'public';
    $raw   = stDeleteFolder($building, $folderId, $cTree);
    $resp  = json_decode($raw, true);
    // Remove from tracked list on success
    if (!empty($resp['ok'])) {
      $ids = array_values(array_filter($ids, fn($id) => $id !== $folderId));
      saveCreatedFolders($building, $ids);
    }
    echo $raw;
    exit;
  }

  echo json_encode(['ok' => false, 'error' => 'Unknown action']);
  exit;
}
// Storage limit for client-side pre-check
$_pageBldCfg      = loadBuildingCfg($building);
$_pagePricing     = file_exists(CONFIG_DIR . 'pricing.json') ? json_decode(file_get_contents(CONFIG_DIR . 'pricing.json'), true) ?? [] : [];
$_pageStorageUsed  = (int)($_pageBldCfg['storageUsed']  ?? 0);
$_pageStorageLimit = (int)($_pageBldCfg['storageLimit']  ?? (int)($_pagePricing['storageDefaultLimit'] ?? 524288000));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – File Manager</title>
  <style>
    body          { font-family: sans-serif; max-width: 800px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar      { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.5rem; }
    h1            { margin: 0; font-size: 1.5rem; }
    .back         { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover   { text-decoration: underline; }

    /* Tabs */
    .tabs         { display: flex; gap: 0; border-bottom: 2px solid #ddd; margin-bottom: 1.25rem; }
    .tab          { padding: 0.5rem 1.25rem; background: none; border: none; font-size: 0.95rem;
                    cursor: pointer; color: #555; border-bottom: 2px solid transparent; margin-bottom: -2px; }
    .tab.active   { color: #0070f3; border-bottom-color: #0070f3; font-weight: 600; }
    .tab:hover:not(.active) { color: #333; }

    /* Breadcrumb */
    .breadcrumb   { font-size: 0.9rem; color: #666; margin-bottom: 0.75rem; }
    .breadcrumb a { color: #0070f3; text-decoration: none; cursor: pointer; }
    .breadcrumb a:hover { text-decoration: underline; }

    /* Toolbar */
    .toolbar      { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
    .toolbar-btn  { padding: 0.4rem 0.9rem; background: #0070f3; color: #fff; border: none;
                    border-radius: 4px; font-size: 0.85rem; cursor: pointer; }
    .toolbar-btn:hover { background: #005bb5; }

    /* Drop zone */
    .drop-zone    { border: 2px dashed #ccc; border-radius: 8px; padding: 1.25rem;
                    text-align: center; margin-bottom: 1.25rem;
                    transition: border-color 0.15s, background 0.15s; cursor: default; }
    .drop-zone.drag-over { border-color: #0070f3; background: #f0f7ff; }
    .drop-label   { font-size: 0.9rem; color: #666; }
    .browse-link  { color: #0070f3; cursor: pointer; text-decoration: underline; }
    .progress-bar { height: 8px; background: #e0e0e0; border-radius: 4px;
                    margin: 0.5rem auto; max-width: 300px; overflow: hidden; }
    .progress-fill{ height: 100%; background: #0070f3; border-radius: 4px; width: 0%;
                    transition: width 0.1s linear; }
    #progress-text{ font-size: 0.85rem; color: #555; display: block; margin-top: 0.3rem; }

    /* Section titles */
    .section-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;
                     color: #999; margin: 1.25rem 0 0.4rem; }
    .section-title:first-child { margin-top: 0; }

    /* Folder rows */
    .folder-row   { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem;
                    border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem;
                    cursor: pointer; user-select: none; }
    .folder-row:hover { background: #f5f5f5; }
    .folder-icon  { font-size: 1.1rem; flex-shrink: 0; }
    .folder-name  { flex: 1; font-weight: bold; }
    .folder-arrow { color: #bbb; font-size: 1.1rem; flex-shrink: 0; }
    .folder-del-btn { flex-shrink: 0; }

    /* File rows */
    .file-row     { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem;
                    border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; }
    .file-icon    { font-size: 1.1rem; flex-shrink: 0; }
    .file-info    { display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0; }
    .file-name    { flex: 1; font-weight: bold; color: #333; overflow: hidden;
                    text-overflow: ellipsis; white-space: nowrap; }
    .file-size    { font-size: 0.8rem; color: #888; white-space: nowrap; flex-shrink: 0; }
    .file-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }
    .rename-form  { display: none; align-items: center; gap: 0.4rem; flex: 1; min-width: 0; }
    .rename-input { flex: 1; min-width: 0; padding: 0.3rem 0.5rem; border: 1px solid #ccc;
                    border-radius: 4px; font-size: 0.9rem; }

    /* Action buttons */
    .action-btn   { padding: 0.3rem 0.65rem; border: 1px solid #ccc; border-radius: 4px;
                    font-size: 0.8rem; cursor: pointer; background: #fff; white-space: nowrap; }
    .action-btn:hover { background: #f0f0f0; }
    .rename-btn   { color: #0070f3; border-color: #a0c4f0; }
    .rename-btn:hover { background: #f0f7ff; }
    .delete-btn   { color: #c00; border-color: #f0b0b0; }
    .delete-btn:hover { background: #fff0f0; }
    .save-btn     { background: #0070f3 !important; color: #fff !important; border-color: #0070f3 !important; }
    .save-btn:hover:not(:disabled) { background: #005bb5 !important; }
    .protected-label { font-size: 0.78rem; color: #999; font-style: italic; cursor: default; }
    .replace-btn  { color: #7a4a00; border-color: #d0a060; }
    .replace-btn:hover { background: #fff8f0; }
    .save-btn:disabled { opacity: 0.6; cursor: default; }
    .cancel-btn   { color: #555; }

    .loading      { color: #999; font-style: italic; padding: 2rem 0; }
    .error        { color: #c00; padding: 1rem 0; }
    .empty        { color: #999; font-style: italic; padding: 1rem 0; }

    .fm-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4);
                        display: flex; align-items: center; justify-content: center; z-index: 100; }
    .fm-modal-box     { background: #fff; border-radius: 8px; padding: 1.5rem; max-width: 440px; width: 90%;
                        box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
    .fm-modal-box h3  { margin: 0 0 0.6rem; font-size: 1.05rem; }
    .fm-modal-box p,
    .fm-modal-box ul  { font-size: 0.875rem; color: #555; margin: 0 0 1.25rem; }
    .fm-modal-box ul  { padding-left: 1.25rem; }
    .fm-modal-box ul li { margin-bottom: 0.25rem; }
    .fm-modal-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .fm-btn-proceed   { padding: 0.5rem 1.2rem; background: #c00; color: #fff; border: none;
                        border-radius: 4px; font-size: 0.9rem; cursor: pointer; }
    .fm-btn-proceed:hover { background: #900; }
    .fm-btn-secondary { padding: 0.5rem 1.2rem; background: #0070f3; color: #fff; border: none;
                        border-radius: 4px; font-size: 0.9rem; cursor: pointer; }
    .fm-btn-secondary:hover { background: #005bb5; }
    .fm-btn-cancel    { padding: 0.5rem 1.2rem; background: #fff; border: 1px solid #ccc;
                        border-radius: 4px; font-size: 0.9rem; cursor: pointer; color: #333; }
    .fm-btn-cancel:hover { background: #f5f5f5; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – File Manager</h1>
  <a href="admin.php?building=<?= urlencode($building) ?>" class="back">← Admin</a>
</div>

<p style="font-size:0.9rem;color:#555;margin-bottom:1.25rem;">
  Upload, delete, rename, and organize files in the Public and Private folders.
  Drag one or more files onto the drop zone to upload, or click Browse to select.
</p>

<div class="tabs">
  <button class="tab active" id="tab-public"  onclick="switchTree('public')">Public</button>
  <button class="tab"        id="tab-private" onclick="switchTree('private')">Private</button>
</div>

<div class="breadcrumb" id="breadcrumb"></div>

<div class="toolbar" id="toolbar">
  <button class="toolbar-btn" onclick="promptNewFolder()">+ New Folder</button>
</div>

<div id="drop-zone" class="drop-zone">
  <div id="drop-label" class="drop-label">
    Drop a file here or <span class="browse-link" onclick="document.getElementById('file-input').click()">browse</span>
  </div>
  <div id="upload-progress" style="display:none">
    <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
    <span id="progress-text">Uploading&hellip;</span>
  </div>
</div>
<input type="file" id="file-input" style="display:none" multiple onchange="handleFileSelect(this)">
<input type="file" id="replace-input" style="display:none" onchange="handleReplaceSelect(this)">

<div id="listing"><p class="loading">Loading&hellip;</p></div>

<script>
(function () {
  var building        = <?= json_encode($building) ?>;
  var base            = 'file-manager.php?building=' + encodeURIComponent(building);
  var storageUsed     = <?= json_encode($_pageStorageUsed) ?>;
  var storageLimit    = <?= json_encode($_pageStorageLimit) ?>;
  var currentTree     = 'public';
  var currentPath     = '';
  var currentFolderId = null;
  var currentFiles    = [];   // flat list of { id, name } for duplicate detection
  var renamingId        = null;
  var renamingFolderId  = null;
  var storageRefreshFired = false;

  // -------------------------------------------------------
  // sessionStorage listing cache
  // -------------------------------------------------------
  function cacheKey() {
    return 'fm_' + building + '_' + currentTree + '_' + currentPath;
  }
  function getCached() {
    try { var v = sessionStorage.getItem(cacheKey()); return v ? JSON.parse(v) : null; } catch (e) { return null; }
  }
  function setCached(data) {
    try { sessionStorage.setItem(cacheKey(), JSON.stringify(data)); } catch (e) {}
  }
  function bustCache() {
    try { sessionStorage.removeItem(cacheKey()); } catch (e) {}
  }

  // -------------------------------------------------------
  // Bootstrap
  // -------------------------------------------------------
  loadListing();

  // -------------------------------------------------------
  // Tree switching
  // -------------------------------------------------------
  window.switchTree = function (tree) {
    currentTree = tree;
    currentPath = '';
    document.getElementById('tab-public').classList.toggle('active',  tree === 'public');
    document.getElementById('tab-private').classList.toggle('active', tree === 'private');
    loadListing();
  };

  // -------------------------------------------------------
  // Navigation
  // -------------------------------------------------------
  window.navigate = function (path) {
    currentPath = path;
    loadListing();
  };

  // -------------------------------------------------------
  // Fetch + render listing
  // -------------------------------------------------------
  function loadListing() {
    renderBreadcrumb();

    // Serve from cache instantly if available
    var cached = getCached();
    if (cached) {
      currentFolderId = cached.currentFolderId || null;
      currentFiles    = cached.files || [];
      renderListing(cached);
      return;
    }

    document.getElementById('listing').innerHTML = '<p class="loading">Loading\u2026</p>';

    var url = base + '&json=list&tree=' + currentTree
            + (currentPath ? '&path=' + encodeURIComponent(currentPath) : '');

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        currentFolderId = d.currentFolderId || null;
        currentFiles    = d.files || [];
        setCached(d);
        renderListing(d);
        // Fire storage refresh once, after the initial listing loads (not competing with it)
        if (!storageRefreshFired) {
          storageRefreshFired = true;
          fetch(base + '&json=storage_refresh').catch(function () {});
        }
      })
      .catch(function () {
        document.getElementById('listing').innerHTML = '<p class="error">Could not load folder.</p>';
      });
  }

  function renderBreadcrumb() {
    var label = currentTree === 'private' ? 'Private' : 'Public';
    var parts = currentPath ? currentPath.split('/') : [];
    var html  = '<a onclick="navigate(\'\')">&#8962; ' + esc(label) + '</a>';
    var sofar = '';
    parts.forEach(function (part, i) {
      sofar = sofar ? sofar + '/' + part : part;
      var p = sofar;
      html += ' / ';
      if (i < parts.length - 1) {
        html += '<a onclick="navigate(\'' + esc(p) + '\')">' + esc(part) + '</a>';
      } else {
        html += '<strong>' + esc(part) + '</strong>';
      }
    });
    document.getElementById('breadcrumb').innerHTML = html;
  }

  function renderListing(data) {
    if (data.error) {
      document.getElementById('listing').innerHTML = '<p class="error">' + esc(data.error) + '</p>';
      return;
    }

    var html = '';

    if (data.folders && data.folders.length) {
      html += '<div class="section-title">Folders</div>';
      data.folders.forEach(function (f) {
        var folderPath = currentPath ? currentPath + '/' + f.name : f.name;
        var fid = esc(f.id);
        var fnm = esc(f.name);
        var actionsHtml = '';
        var renameFormHtml = '';
        if (f.deletable) {
          actionsHtml = '<span id="factions-' + fid + '" class="file-actions">'
            + '<button class="action-btn rename-btn" onclick="event.stopPropagation();startFolderRename(\'' + fid + '\',\'' + fnm + '\')">Rename</button>'
            + '<button class="action-btn delete-btn folder-del-btn" onclick="event.stopPropagation();deleteFolder(\'' + fid + '\',\'' + fnm + '\')">Delete</button>'
            + '</span>';
          renameFormHtml = '<span class="rename-form" id="frform-' + fid + '" onclick="event.stopPropagation()">'
            + '<input type="text" class="rename-input" id="frinput-' + fid + '" value="' + fnm + '">'
            + '<button class="action-btn save-btn" id="frsave-' + fid + '" onclick="saveFolderRename(\'' + fid + '\')">Save</button>'
            + '<button class="action-btn cancel-btn" onclick="cancelFolderRename(\'' + fid + '\')">Cancel</button>'
            + '</span>';
        }
        html += '<div class="folder-row" id="frow-' + fid + '" onclick="navigate(\'' + esc(folderPath) + '\')">'
              + '<span class="folder-icon">&#128193;</span>'
              + '<span class="folder-name" id="ffname-' + fid + '">' + fnm + '</span>'
              + actionsHtml
              + renameFormHtml
              + '<span class="folder-arrow" id="farrow-' + fid + '">&#8250;</span>'
              + '</div>';
      });
    }

    if (data.files && data.files.length) {
      html += '<div class="section-title">Files</div>';
      data.files.forEach(function (f) {
        html += buildFileRow(f.id, f.name, f.size, f.protected, f.mimeType);
      });
    }

    if (!html) html = '<p class="empty">This folder is empty.</p>';
    document.getElementById('listing').innerHTML = html;

    // Attach keydown handlers to rename inputs
    if (data.files) {
      data.files.forEach(function (f) {
        var input = document.getElementById('rinput-' + f.id);
        if (input) {
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  saveRename(f.id);
            if (e.key === 'Escape') cancelRename(f.id);
          });
        }
      });
    }
    if (data.folders) {
      data.folders.forEach(function (f) {
        var input = document.getElementById('frinput-' + f.id);
        if (input) {
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  saveFolderRename(f.id);
            if (e.key === 'Escape') cancelFolderRename(f.id);
          });
        }
      });
    }
  }

  function buildFileRow(fileId, fileName, fileSize, isProtected, mimeType) {
    var id = esc(fileId);
    var nm = esc(fileName);
    var sz = esc(fileSize || '');
    var actions;
    if (isProtected) {
      actions = '<span class="file-actions">'
              + '<button class="action-btn replace-btn" onclick="replaceProtected(\'' + id + '\',\'' + nm + '\')">Replace</button>'
              + '<span class="protected-label" title="This file is embedded on the building website by name and cannot be renamed or deleted.">system file</span>'
              + '</span>';
    } else {
      actions = '<span class="file-actions">'
              + '<button class="action-btn rename-btn" onclick="startRename(\'' + id + '\',\'' + nm + '\')">Rename</button>'
              + '<button class="action-btn delete-btn" onclick="deleteFile(\'' + id + '\',\'' + nm + '\')">Delete</button>'
              + '</span>';
    }
    var renameForm = isProtected ? '' :
        '<span class="rename-form" id="rform-' + id + '">'
      +   '<input type="text" class="rename-input" id="rinput-' + id + '" value="' + nm + '">'
      +   '<button class="action-btn save-btn"   id="rsave-' + id + '" onclick="saveRename(\'' + id + '\')">Save</button>'
      +   '<button class="action-btn cancel-btn" onclick="cancelRename(\'' + id + '\')">Cancel</button>'
      + '</span>';
    return '<div class="file-row" id="row-' + id + '">'
         +   '<span class="file-icon">&#128196;</span>'
         +   '<span class="file-info" id="info-' + id + '">'
         +     '<span class="file-name" id="fname-' + id + '">' + nm + '</span>'
         +     '<span class="file-size">' + sz + '</span>'
         +     actions
         +   '</span>'
         +   renameForm
         + '</div>';
  }

  // -------------------------------------------------------
  // Replace protected file
  // -------------------------------------------------------
  var replaceTargetId   = null;
  var replaceTargetName = null;

  window.replaceProtected = function (fileId, fileName) {
    replaceTargetId   = fileId;
    replaceTargetName = fileName;
    var input = document.getElementById('replace-input');
    input.value = '';
    input.click();
  };

  window.handleReplaceSelect = function (input) {
    if (!input.files.length) return;
    var file = input.files[0];
    input.value = '';

    var oldId   = replaceTargetId;
    var oldName = replaceTargetName;
    replaceTargetId   = null;
    replaceTargetName = null;

    if (!oldId || !oldName || !currentFolderId) return;

    // Show progress on the drop zone
    var dropLabel = document.getElementById('drop-label');
    var progress  = document.getElementById('upload-progress');
    var fill      = document.getElementById('progress-fill');
    var text      = document.getElementById('progress-text');
    dropLabel.style.display = 'none';
    progress.style.display  = 'block';
    fill.style.width = '0%';
    text.textContent = 'Uploading replacement\u2026 0%';

    var fd = new FormData();
    fd.append('action',     'upload');
    fd.append('folderId',   currentFolderId);
    fd.append('tree',       currentTree);
    fd.append('cacheTree',  currentTree);
    fd.append('cachePath',  currentPath);
    fd.append('file',       file);

    var xhr = new XMLHttpRequest();
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        var pct = Math.round(e.loaded / e.total * 100);
        fill.style.width = pct + '%';
        text.textContent = pct < 100 ? ('Uploading replacement\u2026 ' + pct + '%') : 'Processing\u2026';
      }
    };
    xhr.onload = function () {
      var result;
      try { result = JSON.parse(xhr.responseText); } catch (e) { result = {}; }
      if (!result.ok) {
        dropLabel.style.display = '';
        progress.style.display  = 'none';
        alert('Upload failed: ' + (result.error || 'Unknown error'));
        return;
      }
      var newId = result.id;
      text.textContent = 'Renaming\u2026';
      // Rename new file to the exact protected name
      post({ action: 'rename', fileId: newId, newName: oldName })
        .then(function () {
          text.textContent = 'Migrating tags\u2026';
          return post({ action: 'migrateTags', oldFileId: oldId, newFileId: newId }).catch(function () {});
        })
        .then(function () {
          text.textContent = 'Removing old file\u2026';
          return post({ action: 'delete', fileId: oldId }).catch(function () {});
        })
        .then(function () {
          dropLabel.style.display = '';
          progress.style.display  = 'none';
          bustCache();
          loadListing();
        });
    };
    xhr.onerror = function () {
      dropLabel.style.display = '';
      progress.style.display  = 'none';
      alert('Network error during upload');
    };
    xhr.open('POST', base);
    xhr.send(fd);
  };

  // -------------------------------------------------------
  // Rename
  // -------------------------------------------------------
  window.startRename = function (fileId, fileName) {
    if (renamingId && renamingId !== fileId) cancelRename(renamingId);
    renamingId = fileId;
    document.getElementById('info-'  + fileId).style.display = 'none';
    var form = document.getElementById('rform-' + fileId);
    form.style.display = 'flex';
    var input = document.getElementById('rinput-' + fileId);
    input.value = fileName;
    input.focus();
    input.select();
  };

  window.cancelRename = function (fileId) {
    document.getElementById('info-'  + fileId).style.display = '';
    document.getElementById('rform-' + fileId).style.display = 'none';
    if (renamingId === fileId) renamingId = null;
  };

  window.saveRename = function (fileId) {
    var input   = document.getElementById('rinput-' + fileId);
    var saveBtn = document.getElementById('rsave-'  + fileId);
    var newName = input.value.trim();

    if (!newName) { input.focus(); return; }

    var oldName = document.getElementById('fname-' + fileId).textContent;
    if (newName === oldName) { cancelRename(fileId); return; }

    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving\u2026';

    post({ action: 'rename', fileId: fileId, newName: newName, cacheTree: currentTree, cachePath: currentPath })
      .then(function (d) {
        if (d.ok) {
          bustCache();
          document.getElementById('fname-' + fileId).textContent = newName;
          cancelRename(fileId);
        } else {
          alert('Rename failed: ' + (d.error || 'Unknown error'));
          saveBtn.disabled    = false;
          saveBtn.textContent = 'Save';
        }
      })
      .catch(function () {
        alert('Rename failed \u2014 please try again');
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save';
      });
  };

  // -------------------------------------------------------
  // Folder rename
  // -------------------------------------------------------
  window.startFolderRename = function (folderId, folderName) {
    if (renamingFolderId && renamingFolderId !== folderId) cancelFolderRename(renamingFolderId);
    renamingFolderId = folderId;
    document.getElementById('ffname-'   + folderId).style.display = 'none';
    var actions = document.getElementById('factions-' + folderId);
    if (actions) actions.style.display = 'none';
    document.getElementById('farrow-'   + folderId).style.display = 'none';
    var form = document.getElementById('frform-' + folderId);
    form.style.display = 'flex';
    form.style.flex    = '1';
    var input = document.getElementById('frinput-' + folderId);
    input.value = folderName;
    input.focus();
    input.select();
  };

  window.cancelFolderRename = function (folderId) {
    document.getElementById('ffname-'   + folderId).style.display = '';
    var actions = document.getElementById('factions-' + folderId);
    if (actions) actions.style.display = '';
    document.getElementById('farrow-'   + folderId).style.display = '';
    document.getElementById('frform-'   + folderId).style.display = 'none';
    if (renamingFolderId === folderId) renamingFolderId = null;
  };

  window.saveFolderRename = function (folderId) {
    var input   = document.getElementById('frinput-' + folderId);
    var saveBtn = document.getElementById('frsave-'  + folderId);
    var newName = input.value.trim();
    var oldName = document.getElementById('ffname-'  + folderId).textContent;

    if (!newName) { input.focus(); return; }
    if (newName === oldName) { cancelFolderRename(folderId); return; }

    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving\u2026';

    post({ action: 'rename', fileId: folderId, newName: newName, cacheTree: currentTree, cachePath: currentPath })
      .then(function (d) {
        if (d.ok) {
          renamingFolderId = null;
          bustCache();
          loadListing();
        } else {
          alert('Rename failed: ' + (d.error || 'Unknown error'));
          saveBtn.disabled    = false;
          saveBtn.textContent = 'Save';
        }
      })
      .catch(function () {
        alert('Rename failed \u2014 please try again');
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save';
      });
  };

  // -------------------------------------------------------
  // Delete
  // -------------------------------------------------------
  window.deleteFile = function (fileId, fileName) {
    showFmConfirm(
      'Delete \u201c' + fileName + '\u201d?',
      '<p>This cannot be undone.</p>',
      'Delete',
      function() { _doDeleteFile(fileId, fileName); }
    );
  };

  function _doDeleteFile(fileId, fileName) {

    var row = document.getElementById('row-' + fileId);
    row.style.opacity       = '0.4';
    row.style.pointerEvents = 'none';

    post({ action: 'delete', fileId: fileId, cacheTree: currentTree, cachePath: currentPath })
      .then(function (d) {
        if (d.ok) {
          bustCache();
          row.remove();
        } else {
          alert('Delete failed: ' + (d.error || 'Unknown error'));
          row.style.opacity       = '';
          row.style.pointerEvents = '';
        }
      })
      .catch(function () {
        alert('Delete failed \u2014 please try again');
        row.style.opacity       = '';
        row.style.pointerEvents = '';
      });
  };

  // -------------------------------------------------------
  // Delete folder
  // -------------------------------------------------------
  window.deleteFolder = function (folderId, folderName) {
    showFmConfirm(
      'Delete folder \u201c' + folderName + '\u201d?',
      '<p>The folder must be empty. This cannot be undone.</p>',
      'Delete',
      function() { _doDeleteFolder(folderId, folderName); }
    );
  };

  function _doDeleteFolder(folderId, folderName) {
    post({ action: 'deleteFolder', folderId: folderId, cacheTree: currentTree, cachePath: currentPath })
      .then(function (d) {
        if (d.ok) {
          bustCache();
          loadListing();
        } else {
          alert('Delete failed: ' + (d.error || 'Unknown error'));
        }
      })
      .catch(function () {
        alert('Delete failed \u2014 please try again');
      });
  }

  // -------------------------------------------------------
  // Create folder
  // -------------------------------------------------------
  window.promptNewFolder = function () {
    if (!currentFolderId) { alert('Loading \u2014 please wait'); return; }
    var name = prompt('New folder name:');
    if (!name || !name.trim()) return;

    post({ action: 'createFolder', parentFolderId: currentFolderId, name: name.trim(), cacheTree: currentTree, cachePath: currentPath })
      .then(function (d) {
        if (d.ok) {
          bustCache();
          loadListing();
        } else {
          alert('Could not create folder: ' + (d.error || 'Unknown error'));
        }
      })
      .catch(function () {
        alert('Could not create folder \u2014 please try again');
      });
  };

  // -------------------------------------------------------
  // Drop zone + upload
  // -------------------------------------------------------
  var dropZone = document.getElementById('drop-zone');

  dropZone.addEventListener('dragover', function (e) {
    e.preventDefault();
    dropZone.classList.add('drag-over');
  });
  dropZone.addEventListener('dragleave', function (e) {
    if (!dropZone.contains(e.relatedTarget)) {
      dropZone.classList.remove('drag-over');
    }
  });
  dropZone.addEventListener('drop', function (e) {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) uploadQueue(Array.from(e.dataTransfer.files));
  });

  window.handleFileSelect = function (input) {
    if (input.files.length) uploadQueue(Array.from(input.files));
    input.value = '';
  };

  // -------------------------------------------------------
  // Upload queue — processes files one at a time
  // -------------------------------------------------------
  function uploadQueue(files) {
    var total   = files.length;
    var index   = 0;
    var errors  = [];

    continueWithFiles(files);

  function continueWithFiles(files) {
    if (!currentFolderId) { alert('Loading \u2014 please wait'); return; }

    // Client-side storage pre-check (server enforces authoritatively; this is fast UX feedback)
    if (storageLimit > 0) {
      var batchSize = files.reduce(function (sum, f) { return sum + f.size; }, 0);
      if ((storageUsed + batchSize) > storageLimit) {
        showStorageLimitModal(storageUsed, storageLimit);
        return;
      }
    }

    // Check duplicates for all files at once, build replace map
    var replaceMap = {};  // filename → existing file object
    var toReplace  = files.filter(function (f) {
      for (var i = 0; i < currentFiles.length; i++) {
        if (currentFiles[i].name === f.name) { replaceMap[f.name] = currentFiles[i]; return true; }
      }
      return false;
    });

    if (toReplace.length) {
      var names    = toReplace.map(function (f) { return '<li>' + esc(f.name) + '</li>'; }).join('');
      var bodyHtml = toReplace.length === 1
        ? '<p><strong>' + esc(toReplace[0].name) + '</strong> already exists. Replace it, or skip it and upload the others?</p>'
        : '<p>These files already exist:</p><ul>' + names + '</ul><p>Replace all, or skip them and upload the others?</p>';
      showFmConfirm(
        'File' + (toReplace.length > 1 ? 's' : '') + ' already exist',
        bodyHtml,
        'Replace',
        function() { startUpload(files, files.length, replaceMap); },
        'Skip & upload others',
        function() {
          var kept = files.filter(function (f) { return !replaceMap[f.name]; });
          if (kept.length) startUpload(kept, kept.length, {});
        }
      );
      return;
    }

    startUpload(files, files.length, replaceMap);
  } // end continueWithFiles

  function startUpload(files, total, replaceMap) {
    var dropLabel = document.getElementById('drop-label');
    var progress  = document.getElementById('upload-progress');
    var fill      = document.getElementById('progress-fill');
    var text      = document.getElementById('progress-text');

    dropLabel.style.display = 'none';
    progress.style.display  = 'block';

    function uploadNext() {
      if (index >= files.length) {
        // All done
        dropLabel.style.display = '';
        progress.style.display  = 'none';
        if (errors.length) {
          alert('Some files failed to upload:\n' + errors.join('\n'));
        }
        bustCache();
        loadListing();
        return;
      }

      var file     = files[index];
      var existing = replaceMap[file.name] || null;
      var label    = total > 1 ? ('(' + (index + 1) + ' of ' + total + ') ') : '';

      fill.style.width = '0%';
      text.textContent = label + 'Uploading \u201c' + file.name + '\u201d\u2026 0%';

      var fd = new FormData();
      fd.append('action',    'upload');
      fd.append('folderId',  currentFolderId);
      fd.append('tree',      currentTree);
      fd.append('cacheTree', currentTree);
      fd.append('cachePath', currentPath);
      fd.append('file',      file);

      var xhr = new XMLHttpRequest();

      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
          var pct = Math.round(e.loaded / e.total * 100);
          fill.style.width = pct + '%';
          text.textContent = label + (pct < 100
            ? ('Uploading \u201c' + file.name + '\u201d\u2026 ' + pct + '%')
            : ('Processing \u201c' + file.name + '\u201d\u2026'));
        }
      };

      xhr.onload = function () {
        var result;
        try { result = JSON.parse(xhr.responseText); } catch (err) { result = {}; }

        if (result.ok) {
          var newFileId = result.id;
          var next = function () { index++; uploadNext(); };
          if (existing) {
            // Migrate tags from old file ID to new file ID, then delete old file
            post({ action: 'migrateTags', oldFileId: existing.id, newFileId: newFileId })
              .catch(function () {})
              .then(function () {
                return post({ action: 'delete', fileId: existing.id }).catch(function () {});
              })
              .then(next);
          } else {
            next();
          }
        } else {
          if (result.error && result.error.indexOf('Storage limit') === 0) {
            // Stop the queue and show the billing modal
            dropLabel.style.display = '';
            progress.style.display  = 'none';
            showStorageLimitModal(storageUsed, storageLimit);
            return;
          }
          errors.push('\u2022 ' + file.name + ': ' + (result.error || 'Unknown error'));
          index++;
          uploadNext();
        }
      };

      xhr.onerror = function () {
        errors.push('\u2022 ' + file.name + ': network error');
        index++;
        uploadNext();
      };

      xhr.open('POST', base);
      xhr.send(fd);
    }

    uploadNext();
  } // end startUpload
  } // end uploadQueue

  // -------------------------------------------------------
  // Helpers
  // -------------------------------------------------------
  function post(params) {
    var body = Object.keys(params)
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
      .join('&');
    return fetch(base, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    body
    }).then(function (r) { return r.json(); });
  }

  function fmtSize(bytes) {
    if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
    return Math.round(bytes / 1024) + ' KB';
  }

  function fmtBytes(b) {
    if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
    if (b >= 1048576)    return (b / 1048576).toFixed(1)    + ' MB';
    if (b >= 1024)       return Math.round(b / 1024)        + ' KB';
    return b + ' B';
  }

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // -------------------------------------------------------
  // Confirm modal
  // -------------------------------------------------------
  function showStorageLimitModal(used, limit) {
    var usedStr  = fmtBytes(used);
    var limitStr = fmtBytes(limit);
    var body = used > 0
      ? '<p>You have used ' + usedStr + ' of your ' + limitStr + ' storage limit.</p>'
      : '<p>This file exceeds your ' + limitStr + ' storage limit.</p>';
    showFmConfirm(
      'Storage limit reached',
      body + '<p>Add more storage to continue uploading.</p>',
      'Buy More Storage',
      function () {
        fetch(base + '&json=billing_url')
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.ok) { window.location.href = d.url; }
            else { alert('Could not load billing page: ' + (d.error || 'Unknown error')); }
          })
          .catch(function () { alert('Could not load billing page. Please try again.'); });
      }
    );
  }

  var fmConfirmCb = null;

  function showFmConfirm(title, bodyHtml, proceedLabel, onProceed, altLabel, onAlt) {
    document.getElementById('fm-confirm-title').textContent = title;
    document.getElementById('fm-confirm-body').innerHTML    = bodyHtml;
    document.getElementById('fm-confirm-proceed').textContent = proceedLabel;
    var altBtn = document.getElementById('fm-confirm-alt');
    if (altLabel) {
      altBtn.textContent    = altLabel;
      altBtn.style.display  = '';
      altBtn.onclick        = function() { closeFmConfirm(); if (onAlt) onAlt(); };
    } else {
      altBtn.style.display  = 'none';
    }
    fmConfirmCb = onProceed;
    document.getElementById('fm-confirm-overlay').style.display = 'flex';
  }

  window.closeFmConfirm = function() {
    document.getElementById('fm-confirm-overlay').style.display = 'none';
    fmConfirmCb = null;
  };

  window.proceedFmConfirm = function() {
    var cb = fmConfirmCb;
    closeFmConfirm();
    if (cb) cb();
  };

})();
</script>

<div id="fm-confirm-overlay" class="fm-modal-overlay" style="display:none;">
  <div class="fm-modal-box">
    <h3 id="fm-confirm-title"></h3>
    <div id="fm-confirm-body"></div>
    <div class="fm-modal-actions">
      <button id="fm-confirm-proceed" class="fm-btn-proceed" onclick="proceedFmConfirm()"></button>
      <button id="fm-confirm-alt"     class="fm-btn-secondary" style="display:none;"></button>
      <button class="fm-btn-cancel"   onclick="closeFmConfirm()">Cancel</button>
    </div>
  </div>
</div>


</body>
</html>
