<?php
// -------------------------------------------------------
// Building name → Google Drive "Public" folder ID
// Each entry points to:  buildingName/WebSite/Public
// -------------------------------------------------------
$buildings = [
  'oak_manor'    => '1L9jR4pkJGa4_924msS-ZVh8NMXXdgL25',  // oak_manor/WebSite/Public
  'pine_ridge'   => 'FOLDER_ID_FOR_PINE_RIDGE',             // pine_ridge/WebSite/Public
  'sunset_plaza' => 'FOLDER_ID_FOR_SUNSET_PLAZA',           // sunset_plaza/WebSite/Public
  // add more buildings here...
];

$appsScriptURL = 'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec';

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$subdir   = $_GET['subdir']   ?? '';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$folderId   = $buildings[$building];
$buildLabel = ucwords(str_replace('_', ' ', $building));
$pageTitle  = $buildLabel . ($subdir ? ' – ' . htmlspecialchars($subdir) : '') . ' – Files';

// -------------------------------------------------------
// Fetch file list from Apps Script
// -------------------------------------------------------
$url = $appsScriptURL . '?folderId=' . urlencode($folderId);
if ($subdir) {
  $url .= '&subdir=' . urlencode($subdir);
}

$response = file_get_contents($url);
$files    = json_decode($response, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $pageTitle ?></title>
  <style>
    body        { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
    h1          { margin-bottom: 0.25rem; }
    .subdir     { color: #666; font-size: 0.95rem; margin-bottom: 1.25rem; }
    .file-card  { display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.5rem; }
    .file-name  { flex: 1; font-weight: bold; }
    .file-info  { color: #666; font-size: 0.85rem; }
    .download-btn { padding: 0.4rem 0.9rem; background: #0070f3; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem; }
    .download-btn:hover { background: #005bb5; }
    .error      { color: red; }
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>
  <p class="subdir">
    <?= $subdir ? 'Folder: Public / ' . htmlspecialchars($subdir) : 'Folder: Public' ?>
  </p>

  <?php if (!$files || isset($files['error'])): ?>
    <p class="error">
      <?= isset($files['error']) ? htmlspecialchars($files['error']) : 'Could not load files. Please try again later.' ?>
    </p>
  <?php elseif (empty($files)): ?>
    <p>No files found in this folder.</p>
  <?php else: ?>
    <?php foreach ($files as $file): ?>
      <div class="file-card">
        <span class="file-name"><?= htmlspecialchars($file['name']) ?></span>
        <span class="file-info"><?= htmlspecialchars($file['size']) ?></span>
        <a href="<?= htmlspecialchars($file['url']) ?>" class="download-btn">Download</a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
