<?php
$buildings = require __DIR__ . '/buildings.php';
require_once __DIR__ . '/storage/storage.php';

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$subdir   = trim($_GET['subdir'] ?? '', '/');

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildLabel = ucwords(str_replace('_', ' ', $building));
require __DIR__ . '/suspension.php';
$returnURL  = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

// -------------------------------------------------------
// JSON mode — called by browser fetch, returns listing
// -------------------------------------------------------
if (isset($_GET['json'])) {
  header('Content-Type: application/json');
  try {
    echo stListFolder($building, $subdir, 'public', 'pub');
  } catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

// -------------------------------------------------------
// Direct file open — ?file=subdir/filename.pdf
// Looks up the file in storage and redirects to it.
// Replaces the old openDoc(driveId) pattern.
// -------------------------------------------------------
if (isset($_GET['file'])) {
  $filePath = trim($_GET['file'], '/');
  if (!$filePath || !preg_match('/^[a-zA-Z0-9_.() \/-]+$/', $filePath)) {
    die('<p style="color:red;">Invalid file path.</p>');
  }
  $info = stGetDownloadInfo($building, $filePath, 'public');
  if ($info['type'] === 'error') {
    die('<p style="color:red;">File not found.</p>');
  }
  if ($info['type'] === 'redirect') {
    header('Location: ' . $info['url']);
    exit;
  }
  // Drive proxy fallback
  header('Content-Type: ' . $info['mimeType']);
  header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', $info['name']) . '"');
  echo base64_decode($info['data']);
  exit;
}

// -------------------------------------------------------
// Build breadcrumb from subdir path
// -------------------------------------------------------
$parts      = $subdir ? explode('/', $subdir) : [];
$breadcrumb = [['label' => 'Public', 'subdir' => '']];
$pathSoFar  = '';
foreach ($parts as $part) {
  $pathSoFar    = $pathSoFar ? $pathSoFar . '/' . $part : $part;
  $breadcrumb[] = ['label' => $part, 'subdir' => $pathSoFar];
}

$baseURL = '?building=' . urlencode($building) . ($returnURL ? '&return=' . urlencode($returnURL) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Files</title>
  <style>
    body           { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
    h1             { margin-bottom: 0.25rem; }
    .breadcrumb    { font-size: 0.9rem; color: #666; margin-bottom: 1.5rem; }
    .breadcrumb a  { color: #0070f3; text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }
    .section-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #999; margin: 1.25rem 0 0.4rem; }
    .folder-card   { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; text-decoration: none; color: inherit; }
    .folder-card:hover { background: #f5f5f5; }
    .folder-icon   { font-size: 1.2rem; }
    .folder-name   { flex: 1; font-weight: bold; }
    .file-card     { display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; }
    .file-name     { flex: 1; font-weight: bold; color: #333; text-decoration: none; }
    .file-name:hover { text-decoration: underline; color: #0070f3; }
    .file-info     { color: #666; font-size: 0.85rem; }
    .download-btn  { padding: 0.4rem 0.9rem; background: #0070f3; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem; }
    .download-btn:hover { background: #005bb5; }
    .error         { color: red; }
    .empty         { color: #999; font-style: italic; }
    .loading       { color: #999; font-style: italic; padding: 2rem 0; }
    .back-btn      { display:inline-block; margin-bottom:1rem; font-size:0.9rem; color:#0070f3; text-decoration:none; }
    .back-btn:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <?php if ($returnURL): ?>
    <a href="<?= htmlspecialchars($returnURL) ?>" class="back-btn">← Back to site</a>
  <?php endif; ?>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <?php foreach ($breadcrumb as $i => $crumb): ?>
      <?= $i > 0 ? ' / ' : '' ?>
      <?php if ($i < count($breadcrumb) - 1): ?>
        <a href="<?= $baseURL . ($crumb['subdir'] ? '&subdir=' . urlencode($crumb['subdir']) : '') ?>">
          <?= htmlspecialchars($crumb['label']) ?>
        </a>
      <?php else: ?>
        <strong><?= htmlspecialchars($crumb['label']) ?></strong>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div id="listing"><p class="loading">Loading…</p></div>

  <script>
  (function () {
    var building  = <?= json_encode($building) ?>;
    var subdir    = <?= json_encode($subdir) ?>;
    var returnURL = <?= json_encode($returnURL) ?>;
    var baseURL   = '?building=' + encodeURIComponent(building)
                  + (returnURL ? '&return=' + encodeURIComponent(returnURL) : '');
    var fetchURL  = baseURL + (subdir ? '&subdir=' + encodeURIComponent(subdir) : '') + '&json=1';

    fetch(fetchURL)
      .then(function (r) { return r.json(); })
      .then(function (data) { render(data); })
      .catch(function ()   { showError('Could not load directory.'); });

    function render(data) {
      if (data.error) { showError(data.error); return; }

      var html = '';

      if (data.folders && data.folders.length) {
        html += '<div class="section-title">Folders</div>';
        data.folders.forEach(function (f) {
          var folderSubdir = subdir ? subdir + '/' + f.name : f.name;
          var href = baseURL + '&subdir=' + encodeURIComponent(folderSubdir);
          html += '<a href="' + esc(href) + '" class="folder-card">'
                + '<span class="folder-icon">📁</span>'
                + '<span class="folder-name">' + esc(f.name) + '</span>'
                + '</a>';
        });
      }

      if (data.files && data.files.length) {
        html += '<div class="section-title">Files</div>';
        data.files.forEach(function (f) {
          html += '<div class="file-card">'
                + '<a href="' + esc(f.url) + '" target="_blank" class="file-name">' + esc(f.name) + '</a>'
                + '<span class="file-info">' + esc(f.size) + '</span>'
                + '<a href="' + esc(f.url) + '" class="download-btn">Download</a>'
                + '</div>';
        });
      }

      if (!html) html = '<p class="empty">No files or folders found.</p>';
      document.getElementById('listing').innerHTML = html;
    }

    function showError(msg) {
      document.getElementById('listing').innerHTML = '<p class="error">' + esc(msg) + '</p>';
    }

    function esc(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
  })();
  </script>
</body>
</html>
