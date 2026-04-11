<?php
// -------------------------------------------------------
// get-doc-byname.php
// Looks up a file by name in a building's public folder
// and serves or redirects to a viewable URL.
//
// Supports both Drive and R2 storage backends.
//
// Usage (iframe src or window.open):
//   get-doc-byname.php?building=QGscratch&subdir=Page1Docs&filename=Announcement+Page1
// -------------------------------------------------------

$buildings = require __DIR__ . '/buildings.php';
require_once __DIR__ . '/storage/storage.php';

$building = $_GET['building'] ?? '';
$subdir   = trim($_GET['subdir'] ?? '', '/');
$filename = $_GET['filename'] ?? '';

if (!$building || !array_key_exists($building, $buildings) || !$filename) {
  http_response_code(400);
  die('Invalid parameters.');
}

// -------------------------------------------------------
// Get folder listing via storage abstraction
// -------------------------------------------------------
$raw  = stListFolder($building, $subdir, 'public', 'pub');
$data = json_decode($raw, true);

// -------------------------------------------------------
// Find the file by name.
// Match exact name first, then base name without extension
// (R2 files have extensions, Drive native files may not).
// -------------------------------------------------------
$matched = null;
foreach ($data['files'] ?? [] as $file) {
  if ($file['name'] === $filename) {
    $matched = $file;
    break;
  }
  // Base-name match: "Announcement Page1" matches "Announcement Page1.pdf"
  $base = preg_replace('/\.[^.]+$/', '', $file['name']);
  if ($base === $filename) {
    $matched = $file;
    // keep looking for an exact match
  }
}

if (!$matched) {
  http_response_code(404);
  die('File "' . htmlspecialchars($filename) . '" not found in '
    . htmlspecialchars($building) . '/' . htmlspecialchars($subdir) . '.');
}

// -------------------------------------------------------
// Serve the file via storage abstraction
// -------------------------------------------------------
$info = stGetDownloadInfo($building, $matched['id'], 'public');

// Info mode — return URL + display name as JSON (used by building-site.php card)
if (($_GET['mode'] ?? '') === 'info') {
  header('Content-Type: application/json');
  $displayName = preg_replace('/\.[^.]+$/', '', $matched['name']);
  if ($info['type'] === 'redirect') {
    echo json_encode(['url' => $info['url'], 'displayName' => $displayName]);
  } else {
    // Drive fallback: link back to this same script (it will proxy inline)
    $selfUrl = 'get-doc-byname.php?building=' . urlencode($building)
             . ($subdir ? '&subdir=' . urlencode($subdir) : '')
             . '&filename=' . urlencode($filename);
    echo json_encode(['url' => $selfUrl, 'displayName' => $displayName]);
  }
  exit;
}

if ($info['type'] === 'redirect') {
  // R2: redirect to pre-signed URL (browser handles inline display)
  header('Location: ' . $info['url']);
  exit;
}

if ($info['type'] === 'proxy') {
  // Drive: stream the file inline (GAS-proxied download)
  header('Content-Type: ' . $info['mimeType']);
  header('Content-Disposition: inline; filename="' . str_replace('"', '\\"', $info['name']) . '"');
  echo base64_decode($info['data']);
  exit;
}

http_response_code(502);
die('Could not retrieve file: ' . htmlspecialchars($info['message'] ?? 'unknown error'));
