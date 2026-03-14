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
define('APPS_SCRIPT_URL',   'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('APPS_SCRIPT_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');
define('MAX_UPLOAD_BYTES',  15 * 1024 * 1024); // 15 MB

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
      CURLOPT_TIMEOUT        => 60,
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

  header('Content-Type: application/json');
  echo appsScriptGet($url);
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
      echo json_encode(['ok' => false, 'error' => 'File exceeds 15 MB limit']);
      exit;
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
    echo appsScriptGet($url);
    exit;
  }

  echo json_encode(['ok' => false, 'error' => 'Unknown action']);
  exit;
}
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
    .save-btn:disabled { opacity: 0.6; cursor: default; }
    .cancel-btn   { color: #555; }

    .loading      { color: #999; font-style: italic; padding: 2rem 0; }
    .error        { color: #c00; padding: 1rem 0; }
    .empty        { color: #999; font-style: italic; padding: 1rem 0; }
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
  Maximum file size is 15 MB.
</p>

<div class="tabs">
  <button class="tab active" id="tab-public"  onclick="switchTree('public')">Public</button>
  <button class="tab"        id="tab-private" onclick="switchTree('private')">Private</button>
</div>

<div class="breadcrumb" id="breadcrumb"></div>

<div class="toolbar">
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

<div id="listing"><p class="loading">Loading&hellip;</p></div>

<script>
(function () {
  var building        = <?= json_encode($building) ?>;
  var base            = 'file-manager.php?building=' + encodeURIComponent(building);
  var currentTree     = 'public';
  var currentPath     = '';
  var currentFolderId = null;
  var currentFiles    = [];   // flat list of { id, name } for duplicate detection
  var renamingId      = null;

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
    document.getElementById('listing').innerHTML = '<p class="loading">Loading\u2026</p>';
    renderBreadcrumb();

    var url = base + '&json=list&tree=' + currentTree
            + (currentPath ? '&path=' + encodeURIComponent(currentPath) : '');

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        currentFolderId = d.currentFolderId || null;
        currentFiles    = d.files || [];
        renderListing(d);
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
        html += '<div class="folder-row" onclick="navigate(\'' + esc(folderPath) + '\')">'
              + '<span class="folder-icon">&#128193;</span>'
              + '<span class="folder-name">' + esc(f.name) + '</span>'
              + '<span class="folder-arrow">&#8250;</span>'
              + '</div>';
      });
    }

    if (data.files && data.files.length) {
      html += '<div class="section-title">Files</div>';
      data.files.forEach(function (f) {
        html += buildFileRow(f.id, f.name, f.size);
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
  }

  function buildFileRow(fileId, fileName, fileSize) {
    var id = esc(fileId);
    var nm = esc(fileName);
    var sz = esc(fileSize || '');
    return '<div class="file-row" id="row-' + id + '">'
         +   '<span class="file-icon">&#128196;</span>'
         +   '<span class="file-info" id="info-' + id + '">'
         +     '<span class="file-name" id="fname-' + id + '">' + nm + '</span>'
         +     '<span class="file-size">' + sz + '</span>'
         +     '<span class="file-actions">'
         +       '<button class="action-btn rename-btn" onclick="startRename(\'' + id + '\',\'' + nm + '\')">Rename</button>'
         +       '<button class="action-btn delete-btn" onclick="deleteFile(\'' + id + '\',\'' + nm + '\')">Delete</button>'
         +     '</span>'
         +   '</span>'
         +   '<span class="rename-form" id="rform-' + id + '">'
         +     '<input type="text" class="rename-input" id="rinput-' + id + '" value="' + nm + '">'
         +     '<button class="action-btn save-btn"   id="rsave-' + id + '" onclick="saveRename(\'' + id + '\')">Save</button>'
         +     '<button class="action-btn cancel-btn" onclick="cancelRename(\'' + id + '\')">Cancel</button>'
         +   '</span>'
         + '</div>';
  }

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
  // Delete
  // -------------------------------------------------------
  window.deleteFile = function (fileId, fileName) {
    if (!confirm('Delete \u201c' + fileName + '\u201d?\n\nThis cannot be undone.')) return;

    var row = document.getElementById('row-' + fileId);
    row.style.opacity       = '0.4';
    row.style.pointerEvents = 'none';

    post({ action: 'delete', fileId: fileId })
      .then(function (d) {
        if (d.ok) {
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
  // Create folder
  // -------------------------------------------------------
  window.promptNewFolder = function () {
    if (!currentFolderId) { alert('Loading \u2014 please wait'); return; }
    var name = prompt('New folder name:');
    if (!name || !name.trim()) return;

    post({ action: 'createFolder', parentFolderId: currentFolderId, name: name.trim() })
      .then(function (d) {
        if (d.ok) {
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
    var MAX     = 15 * 1024 * 1024;
    var total   = files.length;
    var index   = 0;
    var errors  = [];

    // Validate all files up front
    var tooBig = files.filter(function (f) { return f.size > MAX; });
    if (tooBig.length) {
      alert(tooBig.map(function (f) {
        return '"' + f.name + '" (' + fmtSize(f.size) + ') exceeds 15 MB';
      }).join('\n') + '\n\nThese files will be skipped.');
      files = files.filter(function (f) { return f.size <= MAX; });
      if (!files.length) return;
    }

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
      var names = toReplace.map(function (f) { return '\u2022 ' + f.name; }).join('\n');
      var msg   = toReplace.length === 1
        ? '"' + toReplace[0].name + '" already exists.\n\nReplace it? (Cancel will skip it but upload the others)'
        : 'These files already exist:\n' + names + '\n\nReplace all? (Cancel will skip them but upload the others)';
      if (!confirm(msg)) {
        // Remove duplicates from the queue, upload the rest
        files = files.filter(function (f) { return !replaceMap[f.name]; });
        replaceMap = {};
        if (!files.length) return;
        total = files.length;
      }
    }

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
  }

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

})();
</script>
</body>
</html>
