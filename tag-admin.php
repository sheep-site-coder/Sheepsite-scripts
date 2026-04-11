<?php
// -------------------------------------------------------
// tag-admin.php
// Admin UI for tagging files in Public and Private Drive trees.
// Tags are stored in tags/{building}.json and used by search.php.
//
//   https://sheepsite.com/Scripts/tag-admin.php?building=LyndhurstH
//
// Admin-authenticated — reuses manage_auth_{building} session.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',  __DIR__ . '/credentials/');
define('TAGS_DIR',         __DIR__ . '/tags/');
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
$tagsFile   = TAGS_DIR . $building . '.json';

// Ensure tags directory and .htaccess exist
if (!is_dir(TAGS_DIR)) {
  mkdir(TAGS_DIR, 0755, true);
}
$htaccess = TAGS_DIR . '.htaccess';
if (!file_exists($htaccess)) {
  file_put_contents($htaccess, "Require all denied\n");
}

// -------------------------------------------------------
// JSON: list folder contents
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'list') {
  $tree = ($_GET['tree'] ?? '') === 'private' ? 'private' : 'public';
  $path = trim($_GET['path'] ?? '', '/');
  header('Content-Type: application/json');
  echo stListFolder($building, $path, $tree, 'adm');
  exit;
}

// -------------------------------------------------------
// JSON: return current tags
// -------------------------------------------------------
if (isset($_GET['json']) && $_GET['json'] === 'tags') {
  header('Content-Type: application/json');
  echo file_exists($tagsFile) ? file_get_contents($tagsFile) : '{}';
  exit;
}

// -------------------------------------------------------
// POST: save tags for a file
// Stores { tags, name, tree } so search.php can display
// results without a separate file-name lookup.
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
  header('Content-Type: application/json');

  $fileId   = $_POST['fileId']   ?? '';
  $tagsRaw  = $_POST['tags']     ?? '';
  $fileName = trim($_POST['fileName'] ?? '');
  $tree     = ($_POST['tree'] ?? '') === 'private' ? 'private' : 'public';

  if (!$fileId || str_contains($fileId, '..') || !preg_match('/^[a-zA-Z0-9_.() \/@&\/-]+$/', $fileId)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid fileId']);
    exit;
  }

  // Normalize: trim, lowercase, remove empty, deduplicate
  $newTags = array_values(array_unique(array_filter(
    array_map(fn($t) => trim(strtolower($t)), explode(',', $tagsRaw)),
    fn($t) => $t !== ''
  )));

  $allTags = file_exists($tagsFile) ? json_decode(file_get_contents($tagsFile), true) : [];
  if (!is_array($allTags)) $allTags = [];

  if (empty($newTags)) {
    unset($allTags[$fileId]);
  } else {
    $allTags[$fileId] = [
      'tags' => $newTags,
      'name' => $fileName,
      'tree' => $tree,
    ];
  }

  if (file_put_contents($tagsFile, json_encode($allTags, JSON_PRETTY_PRINT)) !== false) {
    echo json_encode(['ok' => true]);
  } else {
    echo json_encode(['ok' => false, 'error' => 'Could not write tags file — check that tags/ is writable']);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – File Tags</title>
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
    .breadcrumb   { font-size: 0.9rem; color: #666; margin-bottom: 1rem; }
    .breadcrumb a { color: #0070f3; text-decoration: none; cursor: pointer; }
    .breadcrumb a:hover { text-decoration: underline; }

    /* File/folder rows */
    .section-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;
                     color: #999; margin: 1.25rem 0 0.4rem; }
    .folder-row   { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem;
                    border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem;
                    cursor: pointer; }
    .folder-row:hover { background: #f5f5f5; }
    .folder-icon  { font-size: 1.1rem; }
    .folder-name  { font-weight: bold; }

    .file-row     { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; overflow: hidden; }
    .file-row.has-tags { border-color: #b3d4fb; }
    .file-main    { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.75rem; cursor: pointer; }
    .file-main:hover { background: #f5f5f5; }
    .file-icon    { font-size: 1.1rem; flex-shrink: 0; }
    .file-name    { flex: 1; font-weight: bold; color: #333; }
    .preview-tags { display: flex; flex-wrap: wrap; gap: 0.3rem; }
    .preview-chip { background: #e8f0fe; color: #1a56db; font-size: 0.75rem;
                    padding: 0.15rem 0.5rem; border-radius: 10px; white-space: nowrap; }
    .edit-hint    { font-size: 0.8rem; color: #aaa; flex-shrink: 0; }

    /* Tag editor */
    .tag-editor   { padding: 0.75rem; background: #f8faff; border-top: 1px solid #dde8fd; }
    .chips-wrap   { display: flex; flex-wrap: wrap; gap: 0.4rem; min-height: 1.6rem; margin-bottom: 0.6rem; }
    .chip         { display: flex; align-items: center; gap: 0.3rem; background: #1a56db; color: #fff;
                    font-size: 0.8rem; padding: 0.2rem 0.4rem 0.2rem 0.6rem; border-radius: 10px; }
    .chip-x       { background: none; border: none; color: #fff; font-size: 0.9rem; cursor: pointer;
                    padding: 0; line-height: 1; opacity: 0.8; }
    .chip-x:hover { opacity: 1; }
    .no-tags      { font-size: 0.85rem; color: #aaa; font-style: italic; }
    .input-row    { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.6rem; }
    .tag-input    { flex: 1; padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px;
                    font-size: 0.9rem; }
    .add-btn      { padding: 0.4rem 0.8rem; background: #eee; border: 1px solid #ccc;
                    border-radius: 4px; font-size: 0.85rem; cursor: pointer; }
    .add-btn:hover { background: #e0e0e0; }
    .editor-actions { display: flex; gap: 0.5rem; }
    .save-btn     { padding: 0.4rem 1rem; background: #0070f3; color: #fff; border: none;
                    border-radius: 4px; font-size: 0.85rem; cursor: pointer; }
    .save-btn:hover:not(:disabled) { background: #005bb5; }
    .save-btn:disabled { opacity: 0.6; cursor: default; }
    .cancel-btn   { padding: 0.4rem 0.8rem; background: none; border: 1px solid #ccc;
                    border-radius: 4px; font-size: 0.85rem; cursor: pointer; color: #555; }
    .cancel-btn:hover { background: #f0f0f0; }

    .loading      { color: #999; font-style: italic; padding: 2rem 0; }
    .error        { color: #c00; padding: 1rem 0; }
    .empty        { color: #999; font-style: italic; padding: 1rem 0; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – File Tags</h1>
  <a href="admin.php?building=<?= urlencode($building) ?>" class="back">← Admin</a>
</div>

<p style="font-size:0.9rem;color:#555;margin-bottom:1.25rem;">
  Assign searchable tags to files in the Public and Private folders. Tags allow residents
  to find documents by topic or keyword, even when they do not know the exact file name.
  Browse to a file, click the edit icon to open the tag editor, enter one or more tags,
  and click Save.
</p>

<div class="tabs">
  <button class="tab active" id="tab-public"   onclick="switchTree('public')">Public</button>
  <button class="tab"        id="tab-private"  onclick="switchTree('private')">Private</button>
</div>

<div class="breadcrumb" id="breadcrumb"></div>

<div id="listing"><p class="loading">Loading…</p></div>

<datalist id="known-tags"></datalist>

<script>
(function () {
  var building     = <?= json_encode($building) ?>;
  var base         = 'tag-admin.php?building=' + encodeURIComponent(building);
  var currentTree  = 'public';
  var currentPath  = '';
  // tags: { fileId: { tags: [...], name: '...', tree: 'public|private' } }
  var tags         = {};
  var editingId    = null; // fileId currently open in editor
  var editTags     = [];   // working copy of tags for the open editor

  // -------------------------------------------------------
  // Bootstrap: load tags then load listing
  // -------------------------------------------------------
  fetch(base + '&json=tags')
    .then(function (r) { return r.json(); })
    .then(function (d) {
      tags = d || {};
      rebuildDatalist();
      loadListing();
    })
    .catch(function () { loadListing(); });

  // -------------------------------------------------------
  // Tree switching
  // -------------------------------------------------------
  window.switchTree = function (tree) {
    currentTree = tree;
    currentPath = '';
    closeEditor();
    document.getElementById('tab-public').classList.toggle('active',  tree === 'public');
    document.getElementById('tab-private').classList.toggle('active', tree === 'private');
    loadListing();
  };

  // -------------------------------------------------------
  // Navigation
  // -------------------------------------------------------
  window.navigate = function (path) {
    currentPath = path;
    closeEditor();
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
      .then(function (d) { renderListing(d); })
      .catch(function ()  {
        document.getElementById('listing').innerHTML = '<p class="error">Could not load folder.</p>';
      });
  }

  function renderBreadcrumb() {
    var label  = currentTree === 'private' ? 'Private' : 'Public';
    var parts  = currentPath ? currentPath.split('/') : [];
    var html   = '<a onclick="navigate(\'\')">&#8962; ' + esc(label) + '</a>';
    var sofar  = '';
    parts.forEach(function (part, i) {
      sofar = sofar ? sofar + '/' + part : part;
      var p = sofar; // capture
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
              + '</div>';
      });
    }

    if (data.files && data.files.length) {
      html += '<div class="section-title">Files</div>';
      data.files.forEach(function (f) {
        html += buildFileRow(f.id, f.name);
      });
    }

    if (!html) html = '<p class="empty">No files or folders found.</p>';
    document.getElementById('listing').innerHTML = html;

    // Attach chip-remove event delegation to each editor
    if (data.files) {
      data.files.forEach(function (f) {
        var chipsEl = document.getElementById('chips-' + f.id);
        if (chipsEl) {
          chipsEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.chip-x');
            if (!btn) return;
            var tag = btn.dataset.tag;
            editTags = editTags.filter(function (t) { return t !== tag; });
            renderChips(f.id);
          });
        }
        // Enter key in tag input
        var input = document.getElementById('tag-input-' + f.id);
        if (input) {
          input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') {
              e.preventDefault();
              addTagFromInput(f.id);
            }
          });
        }
      });
    }
  }

  function buildFileRow(fileId, fileName) {
    var fileTags   = (tags[fileId] && tags[fileId].tags) ? tags[fileId].tags : [];
    var hasTagsCls = fileTags.length ? ' has-tags' : '';

    // Preview chips
    var previewHtml = fileTags.length
      ? fileTags.map(function (t) { return '<span class="preview-chip">' + esc(t) + '</span>'; }).join('')
      : '';

    // Editor chips (built later when opened)
    return '<div class="file-row' + hasTagsCls + '" id="row-' + esc(fileId) + '">'
         +   '<div class="file-main" onclick="toggleEditor(\'' + esc(fileId) + '\')">'
         +     '<span class="file-icon">&#128196;</span>'
         +     '<span class="file-name">' + esc(fileName) + '</span>'
         +     '<span class="preview-tags" id="preview-' + esc(fileId) + '">' + previewHtml + '</span>'
         +     '<span class="edit-hint">&#9998;</span>'
         +   '</div>'
         +   '<div class="tag-editor" id="editor-' + esc(fileId) + '" style="display:none">'
         +     '<div class="chips-wrap" id="chips-' + esc(fileId) + '"></div>'
         +     '<div class="input-row">'
         +       '<input type="text" class="tag-input" id="tag-input-' + esc(fileId) + '" list="known-tags" placeholder="Type a tag, press Enter">'
         +       '<button class="add-btn" onclick="addTagFromInput(\'' + esc(fileId) + '\')">Add</button>'
         +     '</div>'
         +     '<div class="editor-actions">'
         +       '<button class="save-btn" id="save-btn-' + esc(fileId) + '" onclick="saveFileTags(\'' + esc(fileId) + '\',\'' + esc(fileName) + '\')">Save</button>'
         +       '<button class="cancel-btn" onclick="toggleEditor(\'' + esc(fileId) + '\')">Cancel</button>'
         +     '</div>'
         +   '</div>'
         + '</div>';
  }

  // -------------------------------------------------------
  // Tag editor
  // -------------------------------------------------------
  window.toggleEditor = function (fileId) {
    var editorEl = document.getElementById('editor-' + fileId);

    // If this editor is already open, cancel it
    if (editingId === fileId) {
      closeEditor();
      return;
    }

    // Close any open editor first
    closeEditor();

    editingId = fileId;
    editTags  = (tags[fileId] && tags[fileId].tags) ? tags[fileId].tags.slice() : [];
    editorEl.style.display = 'block';
    renderChips(fileId);
    document.getElementById('tag-input-' + fileId).focus();
  };

  function closeEditor() {
    if (editingId) {
      var editorEl = document.getElementById('editor-' + editingId);
      if (editorEl) editorEl.style.display = 'none';
    }
    editingId = null;
    editTags  = [];
  }

  function renderChips(fileId) {
    var chipsEl = document.getElementById('chips-' + fileId);
    if (!chipsEl) return;
    if (!editTags.length) {
      chipsEl.innerHTML = '<span class="no-tags">No tags yet — type below to add some</span>';
      return;
    }
    chipsEl.innerHTML = editTags.map(function (t) {
      return '<span class="chip">' + esc(t)
           + '<button class="chip-x" data-tag="' + esc(t) + '" title="Remove">&times;</button>'
           + '</span>';
    }).join('');
  }

  window.addTagFromInput = function (fileId) {
    var input = document.getElementById('tag-input-' + fileId);
    var raw   = input.value;
    raw.split(',').map(function (t) { return t.trim().toLowerCase(); })
      .filter(function (t) { return t !== ''; })
      .forEach(function (t) {
        if (!editTags.includes(t)) editTags.push(t);
      });
    input.value = '';
    renderChips(fileId);
  };

  window.saveFileTags = function (fileId, fileName) {
    var saveBtn = document.getElementById('save-btn-' + fileId);
    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving\u2026';

    var body = 'action=save'
             + '&fileId='   + encodeURIComponent(fileId)
             + '&tags='     + encodeURIComponent(editTags.join(','))
             + '&fileName=' + encodeURIComponent(fileName)
             + '&tree='     + encodeURIComponent(currentTree);

    fetch(base, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    body
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.ok) {
        // Commit to local state
        if (editTags.length) {
          tags[fileId] = { tags: editTags.slice(), name: fileName, tree: currentTree };
        } else {
          delete tags[fileId];
        }
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save';
        closeEditor();
        updatePreviewRow(fileId);
        rebuildDatalist();
      } else {
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save';
        alert('Error: ' + (d.error || 'Save failed'));
      }
    })
    .catch(function () {
      saveBtn.disabled    = false;
      saveBtn.textContent = 'Save';
      alert('Save failed \u2014 please try again');
    });
  };

  // Update the preview chips and has-tags class on a file row after save
  function updatePreviewRow(fileId) {
    var previewEl = document.getElementById('preview-' + fileId);
    var rowEl     = document.getElementById('row-' + fileId);
    if (!previewEl || !rowEl) return;

    var fileTags = (tags[fileId] && tags[fileId].tags) ? tags[fileId].tags : [];
    previewEl.innerHTML = fileTags.map(function (t) {
      return '<span class="preview-chip">' + esc(t) + '</span>';
    }).join('');
    rowEl.classList.toggle('has-tags', fileTags.length > 0);
  }

  // -------------------------------------------------------
  // Datalist — rebuild from all known tags
  // -------------------------------------------------------
  function rebuildDatalist() {
    var all = {};
    Object.values(tags).forEach(function (entry) {
      var arr = (entry && entry.tags) ? entry.tags : entry;
      if (Array.isArray(arr)) arr.forEach(function (t) { all[t] = true; });
    });
    var dl = document.getElementById('known-tags');
    dl.innerHTML = Object.keys(all).sort().map(function (t) {
      return '<option value="' + esc(t) + '">';
    }).join('');
  }

  // -------------------------------------------------------
  // HTML escape
  // -------------------------------------------------------
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
