<?php
// -------------------------------------------------------
// display-private-dir.php
// Place this file on sheepsite.com/Scripts/
// Building name → URL of that building's dir-list-private.php
// -------------------------------------------------------
$buildings = [
  'cvelyndhursth' => 'https://cvelyndhursth.com/Scripts/dir-list-private.php',
  'QGscratch'     => 'https://qgscratch.website/Scripts/dir-list-private.php',
  // add more buildings here...
];

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$path     = trim($_GET['path'] ?? '', '/');

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$dirListURL = $buildings[$building];
$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));

// -------------------------------------------------------
// Build breadcrumb from path
// -------------------------------------------------------
$parts      = $path ? explode('/', $path) : [];
$breadcrumb = [['label' => 'SiteFolders', 'path' => '']];
$pathSoFar  = '';
foreach ($parts as $part) {
  $pathSoFar    = $pathSoFar ? $pathSoFar . '/' . $part : $part;
  $breadcrumb[] = ['label' => $part, 'path' => $pathSoFar];
}

// -------------------------------------------------------
// Fetch file + folder list from building's dir-list-private.php
// -------------------------------------------------------
$url = $dirListURL . ($path ? '?path=' . urlencode($path) : '');

$response = file_get_contents($url);
$data     = json_decode($response, true);
$folders  = $data['folders'] ?? [];
$files    = $data['files']   ?? [];
$error    = $data['error']   ?? null;

$baseURL = '?building=' . urlencode($building);
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
    .file-name     { flex: 1; font-weight: bold; }
    .file-info     { color: #666; font-size: 0.85rem; }
    .download-btn  { padding: 0.4rem 0.9rem; background: #0070f3; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem; }
    .download-btn:hover { background: #005bb5; }
    .error         { color: red; }
    .empty         { color: #999; font-style: italic; }
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <?php foreach ($breadcrumb as $i => $crumb): ?>
      <?= $i > 0 ? ' / ' : '' ?>
      <?php if ($i < count($breadcrumb) - 1): ?>
        <a href="<?= $baseURL . ($crumb['path'] ? '&path=' . urlencode($crumb['path']) : '') ?>">
          <?= htmlspecialchars($crumb['label']) ?>
        </a>
      <?php else: ?>
        <strong><?= htmlspecialchars($crumb['label']) ?></strong>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php else: ?>

    <!-- Subfolders -->
    <?php if (!empty($folders)): ?>
      <div class="section-title">Folders</div>
      <?php foreach ($folders as $folder): ?>
        <?php
          $folderPath = $path ? $path . '/' . $folder['name'] : $folder['name'];
          $folderURL  = $baseURL . '&path=' . urlencode($folderPath);
        ?>
        <a href="<?= htmlspecialchars($folderURL) ?>" class="folder-card">
          <span class="folder-icon">📁</span>
          <span class="folder-name"><?= htmlspecialchars($folder['name']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Files -->
    <?php if (!empty($files)): ?>
      <div class="section-title">Files</div>
      <?php foreach ($files as $file): ?>
        <div class="file-card">
          <span class="file-name"><?= htmlspecialchars($file['name']) ?></span>
          <span class="file-info"><?= htmlspecialchars($file['size']) ?></span>
          <a href="<?= htmlspecialchars($file['url']) ?>" class="download-btn">Download</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($folders) && empty($files)): ?>
      <p class="empty">No files or folders found.</p>
    <?php endif; ?>

  <?php endif; ?>
</body>
</html>
