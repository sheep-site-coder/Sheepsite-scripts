<?php
// -------------------------------------------------------
// file-manager.php
// Admin UI for managing files in Public and Private Drive folders.
// Upload, delete, rename files, and create subfolders.
//
//   https://sheepsite.com/Scripts/file-manager.php?building=LyndhurstH
//
// Admin-authenticated — reuses manage_auth_{building} session.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',   __DIR__ . '/credentials/');
define('CONFIG_DIR',        __DIR__ . '/config/');
define('APPS_SCRIPT_URL',   'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('APPS_SCRIPT_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');
define('MAX_UPLOAD_BYTES',  30 * 1024 * 1024); // 30 MB

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
// Helper: GET request to Apps Script
// -------------------------------------------------------
function appsScriptGet(string $url): string {
  $resp = @file_get_contents($url);
  return $resp !== false ? $resp : json_encode(['error' => 'Could not reach Apps Script']);
}

// -------------------------------------------------------
// Helper: POST JSON to Apps Script (used for uploads)
// -------------------------------------------------------
function appsScriptPost(array $payload): string {
  $json = json_encode($payload);

  if (function_exists('curl_init')) {
    $ch = curl_init(APPS_SCRIPT_URL);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $json,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT        => 270,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp !== false ? $resp : json_encode(['ok' => false, 'error' => 'Request failed']);
  }

  // Fallback: stream_context_create
  $opts = [
    'http' => [
      'method'          => 'POST',
      'header'          => "Content-Type: application/json\r\n",
      'content'         => $json,
      'follow_location' => 1,
      'ignore_errors'   => true,
    ]
  ];
  $resp = @file_get_contents(APPS_SCRIPT_URL, false, stream_context_create($opts));
  return $resp !== false ? $resp : json_encode(['ok' => false, 'error' => 'Request failed']);
}

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

  $pubUrl  = APPS_SCRIPT_URL . '?action=storageReport'
           . '&folderId=' . urlencode($config['publicFolderId'])
           . '&token='    . urlencode(APPS_SCRIPT_TOKEN);
  $privUrl = APPS_SCRIPT_URL . '?action=storageReport'
           . '&folderId=' . urlencode($config['privateFolderId'])
           . '&token='    . urlencode(APPS_SCRIPT_TOKEN);

  // Fetch both in parallel via curl_multi
  $mh   = curl_multi_init();
  $chs  = [];
  foreach ([$pubUrl, $privUrl] as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT        => 55,
    ]);
    curl_multi_add_handle($mh, $ch);
    $chs[] = $ch;
  }

  do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running);

  $total = 0;
  foreach ($chs as $ch) {
    $resp = curl_multi_getcontent($ch);
    $data = $resp ? json_decode($resp, true) : null;
    if (isset($data['total'])) $total += (int)$data['total'];
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }
  curl_multi_close($mh);

  // Write to building config
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
// JSON: set up BigUploads quarantine subfolder in Drive
// Returns {ok, folderId} — the Drive folder to open
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'setup_big_upload') {
  header('Content-Type: application/json');
  $tree       = ($_GET['tree'] ?? '') === 'private' ? 'Private' : 'Public';
  $path       = trim($_GET['path'] ?? '', '/');
  $targetPath = $path ? $tree . '/' . $path : $tree;

  $url = APPS_SCRIPT_URL
       . '?action=setupBigUploadFolder'
       . '&token='          . urlencode(APPS_SCRIPT_TOKEN)
       . '&publicFolderId=' . urlencode($config['publicFolderId'])
       . '&targetPath='     . urlencode($targetPath);

  echo appsScriptGet($url);
  exit;
}

// -------------------------------------------------------
// JSON: list all quarantined files (BigUploads tree)
// Returns {ok, files:[{id,name,bytes,size,targetPath}]}
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'list_big_uploads') {
  header('Content-Type: application/json');
  $url = APPS_SCRIPT_URL
       . '?action=listBigUploads'
       . '&token='          . urlencode(APPS_SCRIPT_TOKEN)
       . '&publicFolderId=' . urlencode($config['publicFolderId']);
  echo appsScriptGet($url);
  exit;
}

// -------------------------------------------------------
// POST: publish checked quarantine files, delete unchecked
// Body: publish[]=fileId&targetPath[]=path&delete[]=fileId
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'publish_quarantine') {
  header('Content-Type: application/json');
  $toPublish    = $_POST['publish']    ?? [];
  $targetPaths  = $_POST['targetPath'] ?? [];
  $toDelete     = $_POST['delete']     ?? [];
  $published = $deleted = $errors = 0;

  foreach ($toPublish as $i => $fileId) {
    $fileId     = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileId);
    $targetPath = $targetPaths[$i] ?? '';
    if (!$fileId || !$targetPath) continue;

    $url = APPS_SCRIPT_URL
         . '?action=publishBigUpload'
         . '&token='           . urlencode(APPS_SCRIPT_TOKEN)
         . '&fileId='          . urlencode($fileId)
         . '&targetPath='      . urlencode($targetPath)
         . '&publicFolderId='  . urlencode($config['publicFolderId'])
         . '&privateFolderId=' . urlencode($config['privateFolderId']);

    $result = json_decode(appsScriptGet($url), true);
    if ($result['ok'] ?? false) { $published++; } else { $errors++; }
  }

  foreach ($toDelete as $fileId) {
    $fileId = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileId);
    if (!$fileId) continue;
    $url = APPS_SCRIPT_URL
         . '?action=deleteFile'
         . '&token='  . urlencode(APPS_SCRIPT_TOKEN)
         . '&fileId=' . urlencode($fileId);
    $result = json_decode(appsScriptGet($url), true);
    if ($result['ok'] ?? false) { $deleted++; } else { $errors++; }
  }

  echo json_encode(['ok' => $errors === 0, 'published' => $published, 'deleted' => $deleted, 'errors' => $errors]);
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
  $tree     = ($_GET['tree'] ?? '') === 'private' ? 'private' : 'public';
  $path     = trim($_GET['path'] ?? '', '/');
  $folderId = $tree === 'private' ? $config['privateFolderId'] : $config['publicFolderId'];

  $url = APPS_SCRIPT_URL
       . '?action=listAdmin'
       . '&token='    . urlencode(APPS_SCRIPT_TOKEN)
       . '&folderId=' . urlencode($folderId)
       . ($path ? '&subdir=' . urlencode($path) : '');

  $raw  = appsScriptGet($url);
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

  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

// -------------------------------------------------------
// POST endpoints — all return JSON
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';

  // ---- Upload ----
  if ($action === 'upload') {
    set_time_limit(300);
    $folderId = trim($_POST['folderId'] ?? '');

    if (!$folderId || !preg_match('/^[a-zA-Z0-9_-]+$/', $folderId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid folder ID']);
      exit;
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      $code = $_FILES['file']['error'] ?? -1;
      echo json_encode(['ok' => false, 'error' => 'Upload error (code ' . $code . ')']);
      exit;
    }

    $file = $_FILES['file'];
    if ($file['size'] > MAX_UPLOAD_BYTES) {
      echo json_encode(['ok' => false, 'error' => 'File exceeds 30 MB limit']);
      exit;
    }

    // Storage limit check (uses cached value from storage-report.php or cron)
    $bCfg     = loadBuildingCfg($building);
    $storageUsed = (int)($bCfg['storageUsed'] ?? 0);
    if ($storageUsed > 0) {
      $pricingFile  = CONFIG_DIR . 'pricing.json';
      $pricing      = file_exists($pricingFile) ? json_decode(file_get_contents($pricingFile), true) ?? [] : [];
      $defaultLimit = (int)($pricing['storageDefaultLimit'] ?? 524288000);
      $storageLimit = (int)($bCfg['storageLimit'] ?? $defaultLimit);
      if (($storageUsed + $file['size']) > $storageLimit) {
        require_once __DIR__ . '/billing-helpers.php';
        checkStorageThreshold($building);
        echo json_encode(['ok' => false, 'error' => 'Storage limit reached. Your administrator has been notified to add more storage.']);
        exit;
      }
    }

    $fileName = basename($file['name']);
    $mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
    $data     = base64_encode(file_get_contents($file['tmp_name']));

    echo appsScriptPost([
      'action'   => 'uploadFile',
      'token'    => APPS_SCRIPT_TOKEN,
      'folderId' => $folderId,
      'fileName' => $fileName,
      'mimeType' => $mimeType,
      'data'     => $data,
    ]);
    exit;
  }

  // ---- Delete ----
  if ($action === 'delete') {
    $fileId = trim($_POST['fileId'] ?? '');
    if (!$fileId || !preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid file ID']);
      exit;
    }
    $url = APPS_SCRIPT_URL
         . '?action=deleteFile'
         . '&token='  . urlencode(APPS_SCRIPT_TOKEN)
         . '&fileId=' . urlencode($fileId);
    echo appsScriptGet($url);
    exit;
  }

  // ---- Rename ----
  if ($action === 'rename') {
    $fileId  = trim($_POST['fileId']  ?? '');
    $newName = trim($_POST['newName'] ?? '');
    if (!$fileId || !preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid file ID']);
      exit;
    }
    if (!$newName || strlen($newName) > 255) {
      echo json_encode(['ok' => false, 'error' => 'Invalid file name']);
      exit;
    }
    $url = APPS_SCRIPT_URL
         . '?action=renameFile'
         . '&token='   . urlencode(APPS_SCRIPT_TOKEN)
         . '&fileId='  . urlencode($fileId)
         . '&newName=' . urlencode($newName);
    echo appsScriptGet($url);
    exit;
  }

  // ---- Migrate tags (old file ID → new file ID after replace) ----
  if ($action === 'migrateTags') {
    $oldFileId = trim($_POST['oldFileId'] ?? '');
    $newFileId = trim($_POST['newFileId'] ?? '');
    if (!$oldFileId || !preg_match('/^[a-zA-Z0-9_-]+$/', $oldFileId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid old file ID']);
      exit;
    }
    if (!$newFileId || !preg_match('/^[a-zA-Z0-9_-]+$/', $newFileId)) {
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
    if (!$parentFolderId || !preg_match('/^[a-zA-Z0-9_-]+$/', $parentFolderId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid parent folder ID']);
      exit;
    }
    if (!$name || strlen($name) > 255) {
      echo json_encode(['ok' => false, 'error' => 'Invalid folder name']);
      exit;
    }
    $url = APPS_SCRIPT_URL
         . '?action=createFolder'
         . '&token='          . urlencode(APPS_SCRIPT_TOKEN)
         . '&parentFolderId=' . urlencode($parentFolderId)
         . '&name='           . urlencode($name);
    $raw  = appsScriptGet($url);
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
    if (!$folderId || !preg_match('/^[a-zA-Z0-9_-]+$/', $folderId)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid folder ID']);
      exit;
    }
    // Only allow deletion if this folder was created via the app
    $ids = loadCreatedFolders($building);
    if (!in_array($folderId, $ids, true)) {
      echo json_encode(['ok' => false, 'error' => 'Folder cannot be deleted']);
      exit;
    }
    $url = APPS_SCRIPT_URL
         . '?action=deleteFolder'
         . '&token='    . urlencode(APPS_SCRIPT_TOKEN)
         . '&folderId=' . urlencode($folderId);
    $raw  = appsScriptGet($url);
    $resp = json_decode($raw, true);
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
  Maximum file size is 30 MB.
</p>

<div class="tabs">
  <button class="tab active" id="tab-public"      onclick="switchTree('public')">Public</button>
  <button class="tab"        id="tab-private"     onclick="switchTree('private')">Private</button>
  <button class="tab"        id="tab-quarantine"  onclick="switchTree('quarantine')" style="color:#b45309;">Quarantined</button>
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

<div id="quarantine-section" style="display:none;">
  <div id="quarantine-body"><p class="loading">Loading quarantined files&hellip;</p></div>
</div>

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
    document.getElementById('tab-public').classList.toggle('active',      tree === 'public');
    document.getElementById('tab-private').classList.toggle('active',     tree === 'private');
    document.getElementById('tab-quarantine').classList.toggle('active',  tree === 'quarantine');

    var isQ = tree === 'quarantine';
    document.getElementById('breadcrumb').style.display   = isQ ? 'none' : '';
    document.getElementById('toolbar').style.display      = isQ ? 'none' : '';
    document.getElementById('drop-zone').style.display    = isQ ? 'none' : '';
    document.getElementById('listing').style.display      = isQ ? 'none' : '';
    document.getElementById('quarantine-section').style.display = isQ ? '' : 'none';

    if (isQ) loadQuarantine();
    else loadListing();
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
    fd.append('action',   'upload');
    fd.append('folderId', currentFolderId);
    fd.append('tree',     currentTree);
    fd.append('file',     file);

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

    post({ action: 'rename', fileId: fileId, newName: newName })
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

    post({ action: 'rename', fileId: folderId, newName: newName })
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

    post({ action: 'delete', fileId: fileId })
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
    post({ action: 'deleteFolder', folderId: folderId })
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

    post({ action: 'createFolder', parentFolderId: currentFolderId, name: name.trim() })
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
  // -------------------------------------------------------
  // Quarantine tab
  // -------------------------------------------------------
  function loadQuarantine() {
    document.getElementById('quarantine-body').innerHTML = '<p class="loading">Fetching pending files and storage usage&hellip;</p>';

    // Fire storage refresh + file list in parallel
    var refreshDone = false, listDone = false;
    var freshUsed = storageUsed;
    var qFiles    = [];

    function tryRender() {
      if (!refreshDone || !listDone) return;
      renderQuarantine(qFiles, freshUsed);
    }

    fetch(base + '&json=storage_refresh')
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.total) freshUsed = d.total; })
      .catch(function() {})
      .then(function() { refreshDone = true; tryRender(); });

    fetch(base + '&json=list_big_uploads')
      .then(function(r) { return r.json(); })
      .then(function(d) { qFiles = d.files || []; })
      .catch(function() {})
      .then(function() { listDone = true; tryRender(); });
  }

  function renderQuarantine(files, usedBytes) {
    var body = document.getElementById('quarantine-body');

    if (!files.length) {
      body.innerHTML = '<p style="color:#555;margin-top:1rem;">No large files are waiting in quarantine.</p>';
      return;
    }

    var remaining = storageLimit > 0 ? storageLimit - usedBytes : Infinity;

    var rows = files.map(function(f, i) {
      return '<tr id="qrow-' + i + '">'
        + '<td style="padding:7px 8px;"><input type="checkbox" id="qchk-' + i + '" checked onchange="updateQuarantineMath()"></td>'
        + '<td style="padding:7px 8px;">' + esc(f.name) + '</td>'
        + '<td style="padding:7px 8px;white-space:nowrap;">' + esc(f.size) + '</td>'
        + '<td style="padding:7px 8px;color:#777;font-size:0.82rem;">' + (f.targetPath ? '\u2192 ' + esc(f.targetPath) : '\u2014') + '</td>'
        + '</tr>';
    }).join('');

    body.innerHTML = ''
      + '<div id="q-storage-bar" style="margin:1rem 0 0.5rem;font-size:0.88rem;color:#555;"></div>'
      + '<table style="width:100%;border-collapse:collapse;font-size:0.9rem;margin-bottom:1rem;">'
      + '<thead><tr>'
      + '<th style="padding:6px 8px;text-align:left;border-bottom:2px solid #eee;width:32px;"></th>'
      + '<th style="padding:6px 8px;text-align:left;border-bottom:2px solid #eee;">File</th>'
      + '<th style="padding:6px 8px;text-align:left;border-bottom:2px solid #eee;">Size</th>'
      + '<th style="padding:6px 8px;text-align:left;border-bottom:2px solid #eee;">Publishes to</th>'
      + '</tr></thead>'
      + '<tbody>' + rows + '</tbody>'
      + '</table>'
      + '<div id="q-over-msg" style="display:none;background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;padding:0.6rem 0.85rem;font-size:0.875rem;color:#92400e;margin-bottom:0.75rem;">'
      + 'The checked files exceed your available storage. Uncheck files to fit within your limit, or upgrade your storage.'
      + '</div>'
      + '<div style="display:flex;gap:0.75rem;flex-wrap:wrap;">'
      + '<button id="q-publish-btn" class="toolbar-btn" onclick="confirmQuarantinePublish()">Publish checked &amp; delete unchecked</button>'
      + '<button id="q-buy-btn" class="toolbar-btn" style="display:none;background:#b45309;" onclick="buyMoreStorage()">Buy More Storage &rarr;</button>'
      + '</div>';

    // Store file data for publish step
    window._qFiles    = files;
    window._qRemaining = remaining;
    updateQuarantineMath();
  }

  window.updateQuarantineMath = function() {
    var files     = window._qFiles || [];
    var remaining = window._qRemaining !== undefined ? window._qRemaining : Infinity;
    var checkedTotal = 0;
    files.forEach(function(f, i) {
      if (document.getElementById('qchk-' + i) && document.getElementById('qchk-' + i).checked) {
        checkedTotal += f.bytes;
      }
    });

    var overLimit = storageLimit > 0 && checkedTotal > remaining;
    var barColor  = overLimit ? '#dc2626' : '#1a7f37';
    var barText   = storageLimit > 0
      ? fmtSize(checkedTotal) + ' of ' + fmtSize(remaining) + ' remaining'
      : fmtSize(checkedTotal) + ' selected';

    var bar = document.getElementById('q-storage-bar');
    if (bar) bar.innerHTML = '<span style="color:' + barColor + ';font-weight:600;">' + barText + '</span>';

    var publishBtn = document.getElementById('q-publish-btn');
    var buyBtn     = document.getElementById('q-buy-btn');
    var overMsg    = document.getElementById('q-over-msg');
    if (publishBtn) publishBtn.disabled = overLimit;
    if (buyBtn)     buyBtn.style.display = overLimit ? '' : 'none';
    if (overMsg)    overMsg.style.display = overLimit ? '' : 'none';
  };

  window.confirmQuarantinePublish = function() {
    var files    = window._qFiles || [];
    var toPublish = [], toDelete = [];
    files.forEach(function(f, i) {
      var chk = document.getElementById('qchk-' + i);
      if (chk && chk.checked) toPublish.push(f); else toDelete.push(f);
    });

    var pubList = toPublish.map(function(f) {
      return '<li><strong>' + esc(f.name) + '</strong>' + (f.targetPath ? ' \u2192 ' + esc(f.targetPath) : '') + '</li>';
    }).join('');
    var delList = toDelete.map(function(f) {
      return '<li>' + esc(f.name) + '</li>';
    }).join('');

    var bodyHtml = '<p><strong>Will be published (' + toPublish.length + '):</strong></p>'
      + (pubList ? '<ul style="margin:0.25rem 0 0.75rem;">' + pubList + '</ul>' : '<p style="color:#777;font-size:0.88rem;">None</p>')
      + (toDelete.length ? '<p><strong>Will be deleted (' + toDelete.length + '):</strong></p><ul style="margin:0.25rem 0;">' + delList + '</ul>' : '');

    showFmConfirm('Confirm Publish', bodyHtml, 'Publish', function() {
      executeQuarantinePublish(toPublish, toDelete);
    });
  };

  function executeQuarantinePublish(toPublish, toDelete) {
    document.getElementById('quarantine-body').innerHTML = '<p class="loading">Publishing&hellip;</p>';
    var fd = new FormData();
    toPublish.forEach(function(f) {
      fd.append('publish[]',    f.id);
      fd.append('targetPath[]', f.targetPath);
    });
    toDelete.forEach(function(f) { fd.append('delete[]', f.id); });

    fetch(base + '&json=publish_quarantine', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.errors) {
          document.getElementById('quarantine-body').innerHTML =
            '<p style="color:#c00;">' + d.errors + ' operation(s) failed. Published: ' + d.published + ', Deleted: ' + d.deleted + '.</p>';
        }
        loadQuarantine();
      })
      .catch(function() {
        document.getElementById('quarantine-body').innerHTML = '<p style="color:#c00;">Network error — please try again.</p>';
      });
  }

  window.buyMoreStorage = function() {
    var win = window.open('', '_blank'); // open immediately while in user gesture
    fetch(base + '&json=billing_url')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok && d.url) { win.location.href = d.url; }
        else { win.close(); alert(d.error || 'Could not generate billing link.'); }
      })
      .catch(function() { win.close(); alert('Network error — please try again'); });
  };

  // -------------------------------------------------------
  // Upload queue — processes files one at a time
  // -------------------------------------------------------
  function uploadQueue(files) {
    var MAX     = 30 * 1024 * 1024;
    var total   = files.length;
    var index   = 0;
    var errors  = [];

    // Validate all files up front — catch oversized files before upload attempt
    var tooBig     = files.filter(function (f) { return f.size > MAX; });
    var smallFiles = files.filter(function (f) { return f.size <= MAX; });
    if (tooBig.length) {
      // Storage check: would big files + small files exceed the remaining space?
      if (storageUsed > 0 && storageLimit > 0) {
        var smallTotal    = smallFiles.reduce(function (s, f) { return s + f.size; }, 0);
        var tooBigTotal   = tooBig.reduce(function (s, f) { return s + f.size; }, 0);
        var effectiveLeft = storageLimit - storageUsed - smallTotal;
        if (tooBigTotal > effectiveLeft) {
          showFmConfirm(
            'Storage limit reached',
            '<p>These files cannot be uploaded — they would exceed your storage limit.</p>',
            'Buy More Storage \u2192',
            function () { buyMoreStorage(); },
            null, null
          );
          return;
        }
      }

      var fileList = tooBig.map(function (f) {
        return '<li><strong>' + esc(f.name) + '</strong> (' + fmtSize(f.size) + ')</li>';
      }).join('');
      showFmConfirm(
        tooBig.length === 1 ? 'File too large' : 'Files too large',
        (tooBig.length === 1
          ? '<p>This file exceeds the 30 MB limit and cannot be uploaded through the file manager:</p>'
          : '<p>These files exceed the 30 MB limit and cannot be uploaded through the file manager:</p>')
        + '<ul style="margin:0.5rem 0;">' + fileList + '</ul>'
        + '<p style="font-size:0.88rem;color:#555;margin-top:0.75rem;">Click <strong>Open in Google Drive</strong> to upload directly there. Afterwards, return here and open the <strong>Quarantined</strong> tab to review and publish the file(s).</p>',
        'Open in Google Drive \u2192',
        function () {
          var win = window.open('', '_blank'); // open immediately while in user gesture
          var url = base + '&json=setup_big_upload&tree=' + currentTree
                  + (currentPath ? '&path=' + encodeURIComponent(currentPath) : '');
          fetch(url).then(function(r) { return r.json(); }).then(function(d) {
            if (d.ok && d.folderId) {
              win.location.href = 'https://drive.google.com/drive/folders/' + d.folderId;
            } else {
              win.close();
              alert('Could not prepare upload folder: ' + (d.error || 'Unknown error'));
            }
          }).catch(function() { win.close(); alert('Network error — please try again'); });
        },
        smallFiles.length ? ('Upload ' + (smallFiles.length === 1 ? '1 other file' : smallFiles.length + ' other files')) : null,
        smallFiles.length ? function () { continueWithFiles(smallFiles); } : null
      );
      return;
    }

    continueWithFiles(files);

  function continueWithFiles(files) {
    if (!currentFolderId) { alert('Loading \u2014 please wait'); return; }

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
      fd.append('action',   'upload');
      fd.append('folderId', currentFolderId);
      fd.append('tree',     currentTree);
      fd.append('file',     file);

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
