<?php
// -------------------------------------------------------
// dir-list-private.php
// Place on each building site at: buildingsite.com/Scripts/
//
// Set SECRET_TOKEN to a unique random string for this site.
// Must match the token stored for this building in
// display-private-dir.php on sheepsite.com
// -------------------------------------------------------

define('SECRET_TOKEN', 'REPLACE_WITH_UNIQUE_RANDOM_TOKEN');

// Validate token — reject all requests without the correct token
$token = $_GET['token'] ?? '';
if ($token !== SECRET_TOKEN) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/SiteFolders';
$path    = trim($_GET['path'] ?? '', '/');

// Prevent path traversal
if ($path && (strpos($path, '..') !== false || strpos($path, "\0") !== false)) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'Invalid path']);
  exit;
}

$fullPath = $path ? $baseDir . '/' . $path : $baseDir;

if (!is_dir($fullPath)) {
  http_response_code(404);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'Directory not found: ' . $path]);
  exit;
}

// -------------------------------------------------------
// Handle download request — stream file directly
// -------------------------------------------------------
if (isset($_GET['download'])) {
  $filename = basename($_GET['download']); // basename() prevents path traversal
  $filePath = $fullPath . '/' . $filename;

  if (!file_exists($filePath) || is_dir($filePath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found']);
    exit;
  }

  header('Content-Type: ' . mime_content_type($filePath));
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Content-Length: ' . filesize($filePath));
  header('Cache-Control: no-cache');
  readfile($filePath);
  exit;
}

// -------------------------------------------------------
// Return folder and file listing (no direct file URLs)
// display-private-dir.php constructs proxy download URLs
// -------------------------------------------------------
header('Content-Type: application/json');

$folderList = [];
$fileList   = [];

foreach (scandir($fullPath) as $item) {
  if ($item === '.' || $item === '..') continue;

  $itemFullPath = $fullPath . '/' . $item;

  if (is_dir($itemFullPath)) {
    $folderList[] = ['name' => $item];
  } else {
    $fileList[] = [
      'name' => $item,
      'size' => round(filesize($itemFullPath) / 1024) . ' KB',
      'type' => mime_content_type($itemFullPath),
    ];
  }
}

echo json_encode(['folders' => $folderList, 'files' => $fileList]);
