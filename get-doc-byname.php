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

$buildings = [
  'QGscratch'  => '1Vgnk3XTKta33deoOWUfOp9Z666jHpM1c',  // QGscratch/WebSite/Public
  'LyndhurstH' => '1nJyAbZ8vCAMSKKheU-39DDZB2hXvC97g',  // LyndhurstH/WebSite/Public
  'LyndhurstI' => '1zL9-FMMKn1uufMZWUw24lywflCVL44Rc',  // LyndhurstI/WebSite/Public
  // add more buildings here...
];

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
     . '&folderId=' . urlencode($buildings[$building])
     . ($subdir ? '&subdir=' . urlencode($subdir) : '');

$response = @file_get_contents($url);
$data     = json_decode($response, true);

// -------------------------------------------------------
// Find the file by name and redirect to preview URL
// -------------------------------------------------------
foreach ($data['files'] ?? [] as $file) {
  if ($file['name'] === $filename) {
    header('Location: https://docs.google.com/document/d/' . urlencode($file['id']) . '/preview');
    exit;
  }
}

http_response_code(404);
die('File "' . htmlspecialchars($filename) . '" not found in ' . htmlspecialchars($building) . '/' . htmlspecialchars($subdir) . '.');
