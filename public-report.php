<?php
// -------------------------------------------------------
// public-report.php
// Place this file on sheepsite.com/Scripts/
//
// Serves public (no login required) Google Sheets Web App
// reports in an iframe. Building name is injected by the
// footer script — no hardcoded URLs needed on the site.
//
// Usage:
//   ?building=BUILDING_NAME&page=board
// -------------------------------------------------------

$buildings = require __DIR__ . '/buildings.php';

// Public pages — add more here as needed
$pages = [
  'board' => ['suffix' => '', 'title' => 'Board of Directors'],
];

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$page     = $_GET['page'] ?? 'board';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

if (!array_key_exists($page, $pages)) {
  die('<p style="color:red;">Invalid page.</p>');
}

$buildingConfig = $buildings[$building];
$pageConfig     = $pages[$page];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));
$returnURL      = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';
$showNav        = ($_GET['nav'] ?? '1') !== '0';

$iframeSrc = $buildingConfig['webAppURL'] . $pageConfig['suffix'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – <?= htmlspecialchars($pageConfig['title']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body     { font-family: sans-serif; display: flex; flex-direction: column; height: 100vh; }
    .top-bar { display: flex; align-items: center;
               padding: 0.5rem 1rem; background: #f5f5f5; border-bottom: 1px solid #ddd;
               font-size: 0.85rem; flex-shrink: 0; }
    .top-bar a { color: #0070f3; text-decoration: none; }
    .top-bar a:hover { text-decoration: underline; }
    .iframe-wrap { position: relative; flex: 1; }
    #doc-loader  { position: absolute; inset: 0; display: flex; flex-direction: column;
                   align-items: center; justify-content: center; background: #f5f5f5;
                   transition: opacity 0.3s; }
    #doc-loader .spinner { width: 48px; height: 48px; border: 5px solid #e0c0f0;
                   border-top-color: #7A0099; border-radius: 50%;
                   animation: spin 0.8s linear infinite; }
    #doc-loader p { margin-top: 14px; font-size: 14px; color: #888; }
    @keyframes spin { to { transform: rotate(360deg); } }
    iframe { border: none; width: 100%; height: 100%; }
  </style>
</head>
<body>
  <?php if ($showNav && $returnURL): ?>
  <div class="top-bar">
    <a href="<?= htmlspecialchars($returnURL) ?>">← Back to site</a>
  </div>
  <?php endif; ?>
  <div class="iframe-wrap">
    <div id="doc-loader">
      <div class="spinner"></div>
      <p>Loading...</p>
    </div>
    <iframe src="<?= htmlspecialchars($iframeSrc) ?>"
            title="<?= htmlspecialchars($pageConfig['title']) ?>"
            onload="document.getElementById('doc-loader').style.display='none'"></iframe>
  </div>
</body>
</html>
