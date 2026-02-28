<?php
// -------------------------------------------------------
// display-private-dir.php
// Place this file on sheepsite.com/Scripts/
// Building name → credentials + token + dir-list URL
// -------------------------------------------------------
$buildings = [
  'cvelyndhursth' => [
    'url'   => 'https://cvelyndhursth.com/Scripts/dir-list-private.php',
    'user'  => 'LyndhurstH',
    'pass'  => 'Owners##$447',
    'token' => 'REPLACE_WITH_UNIQUE_RANDOM_TOKEN',  // must match dir-list-private.php on cvelyndhursth.com
  ],
  'cvelyndhursti' => [
    'url'   => 'https://cvelyndhursti.com/Scripts/dir-list-private.php',
    'user'  => 'LyndhurstI',
    'pass'  => 'Owners2025##@',
    'token' => 'REPLACE_WITH_UNIQUE_RANDOM_TOKEN',  // must match dir-list-private.php on cvelyndhursti.com
  ],
  'QGscratch' => [
    'url'   => 'https://qgscratch.website/Scripts/dir-list-private.php',
    'user'  => 'QGscratch',
    'pass'  => 'QGTest4510!@#',
    'token' => 'REPLACE_WITH_UNIQUE_RANDOM_TOKEN',  // must match dir-list-private.php on qgscratch.website
  ],
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

$buildingConfig = $buildings[$building];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));

// -------------------------------------------------------
// HTTP Basic Auth — prompt user for credentials
// -------------------------------------------------------
$authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$authPass = $_SERVER['PHP_AUTH_PW']   ?? '';

if ($authUser !== $buildingConfig['user'] || $authPass !== $buildingConfig['pass']) {
  header('WWW-Authenticate: Basic realm="' . $buildLabel . ' – Private Files"');
  header('HTTP/1.0 401 Unauthorized');
  echo '<p>Login required.</p>';
  exit;
}

// -------------------------------------------------------
// Proxy download — stream file through this page
// Token is never exposed to the user
// -------------------------------------------------------
if (isset($_GET['download'])) {
  $filename    = basename($_GET['download'] ?? '');
  $downloadURL = $buildingConfig['url']
               . '?token=' . urlencode($buildingConfig['token'])
               . '&path='  . urlencode($path)
               . '&download=' . urlencode($filename);

  $content = @file_get_contents($downloadURL);
  if ($content === false) {
    die('<p style="color:red;">Could not retrieve file.</p>');
  }

  foreach ($http_response_header as $h) {
    if (preg_match('/^(Content-Type|Content-Disposition|Content-Length):/i', $h)) {
      header($h);
    }
  }
  echo $content;
  exit;
}

// -------------------------------------------------------
// Fetch folder + file listing from dir-list-private.php
// -------------------------------------------------------
$listURL  = $buildingConfig['url']
          . '?token=' . urlencode($buildingConfig['token'])
          . ($path ? '&path=' . urlencode($path) : '');

$response = file_get_contents($listURL);
$data     = json_decode($response, true);
$folders  = $data['folders'] ?? [];
$files    = $data['files']   ?? [];
$error    = $data['error']   ?? null;

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
        <?php
          $downloadURL = $baseURL
            . ($path ? '&path=' . urlencode($path) : '')
            . '&download=' . urlencode($file['name']);
        ?>
        <div class="file-card">
          <span class="file-name"><?= htmlspecialchars($file['name']) ?></span>
          <span class="file-info"><?= htmlspecialchars($file['size']) ?></span>
          <a href="<?= htmlspecialchars($downloadURL) ?>" class="download-btn">Download</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($folders) && empty($files)): ?>
      <p class="empty">No files or folders found.</p>
    <?php endif; ?>

  <?php endif; ?>
</body>
</html>
