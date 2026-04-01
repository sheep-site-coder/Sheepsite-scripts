<?php
// -------------------------------------------------------
// get-doc-byname.php
// Place this file on sheepsite.com/Scripts/
//
// Looks up a file by name in a building's Public folder
// and redirects to its Google Doc preview URL.
//
// Usage (iframe src):
//   https://sheepsite.com/Scripts/get-doc-byname.php
//     ?building=QGscratch
//     &subdir=Page1Docs
//     &filename=Announcement+Page1
// -------------------------------------------------------

$buildings = require __DIR__ . '/buildings.php';

$appsScriptURL = 'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec';

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$subdir   = trim($_GET['subdir'] ?? '', '/');
$filename = $_GET['filename'] ?? '';

if (!$building || !array_key_exists($building, $buildings) || !$filename) {
  http_response_code(400);
  die('Invalid parameters.');
}

// -------------------------------------------------------
// Fetch folder listing from Apps Script
// -------------------------------------------------------
$url = $appsScriptURL
     . '?action=list'
     . '&folderId=' . urlencode($buildings[$building]['publicFolderId'])
     . ($subdir ? '&subdir=' . urlencode($subdir) : '');

$response = @file_get_contents($url);
$data     = json_decode($response, true);

// -------------------------------------------------------
// Find the file by name and redirect to preview URL
// -------------------------------------------------------
foreach ($data['files'] ?? [] as $file) {
  if ($file['name'] === $filename) {
    $id       = $file['id'];
    $mimeType = $file['type'] ?? '';
    if ($mimeType === 'application/vnd.google-apps.document') {
      $previewUrl = 'https://docs.google.com/document/d/'     . urlencode($id) . '/preview';
    } elseif ($mimeType === 'application/vnd.google-apps.spreadsheet') {
      $previewUrl = 'https://docs.google.com/spreadsheets/d/' . urlencode($id) . '/preview';
    } elseif ($mimeType === 'application/vnd.google-apps.presentation') {
      $previewUrl = 'https://docs.google.com/presentation/d/' . urlencode($id) . '/preview';
    } else {
      // PDF, Word, or any other uploaded file
      $previewUrl = 'https://drive.google.com/file/d/'        . urlencode($id) . '/preview';
    }
    header('Location: ' . $previewUrl);
    exit;
  }
}

http_response_code(404);
die('File "' . htmlspecialchars($filename) . '" not found in ' . htmlspecialchars($building) . '/' . htmlspecialchars($subdir) . '.');
