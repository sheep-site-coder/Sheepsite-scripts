<?php
// -------------------------------------------------------
// dir-list-private.php
// Place this file on each building site at:
//   buildingsite.com/Scripts/dir-list-private.php
//
// It reads files and folders from the site's SiteFolders/
// directory and returns them as JSON for display-private-dir.php
// -------------------------------------------------------

// Allow sheepsite.com to fetch this
header('Access-Control-Allow-Origin: https://sheepsite.com');
header('Content-Type: application/json');

$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/SiteFolders';
$baseURL = 'https://' . $_SERVER['HTTP_HOST'] . '/SiteFolders';
$path    = trim($_GET['path'] ?? '', '/');

// Prevent path traversal attacks
if ($path && (strpos($path, '..') !== false || strpos($path, "\0") !== false)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid path']);
  exit;
}

$fullPath = $path ? $baseDir . '/' . $path : $baseDir;

if (!is_dir($fullPath)) {
  http_response_code(404);
  echo json_encode(['error' => 'Directory not found: ' . $path]);
  exit;
}

$folderList = [];
$fileList   = [];

foreach (scandir($fullPath) as $item) {
  if ($item === '.' || $item === '..') continue;

  $itemFullPath = $fullPath . '/' . $item;
  $itemURL      = $baseURL . ($path ? '/' . $path : '') . '/' . rawurlencode($item);

  if (is_dir($itemFullPath)) {
    $folderList[] = ['name' => $item];
  } else {
    $fileList[] = [
      'name' => $item,
      'url'  => $itemURL,
      'size' => round(filesize($itemFullPath) / 1024) . ' KB',
      'type' => mime_content_type($itemFullPath)
    ];
  }
}

echo json_encode(['folders' => $folderList, 'files' => $fileList]);
