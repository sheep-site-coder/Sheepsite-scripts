<?php
// -------------------------------------------------------
// warm-cache.php — Pre-warm the server-side Drive listing
// cache for a building's public and private folder trees.
//
// Called automatically after new association creation.
// Can also be run manually:
//   POST building=X (+ optional publicFolderId, privateFolderId)
//
// Requires master admin session.
// -------------------------------------------------------
session_start();
require_once __DIR__ . '/listing-cache.php';

define('WC_SCRIPT_URL',   'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('WC_SCRIPT_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');

header('Content-Type: application/json');

// Master admin auth
if (empty($_SESSION['master_admin_auth'])) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$building        = trim($_REQUEST['building']        ?? '');
$publicFolderId  = trim($_REQUEST['publicFolderId']  ?? '');
$privateFolderId = trim($_REQUEST['privateFolderId'] ?? '');

if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid building key']);
  exit;
}

// If folder IDs not supplied, look them up from buildings.php
if (!$publicFolderId || !$privateFolderId) {
  $buildings = require __DIR__ . '/buildings.php';
  if (!isset($buildings[$building])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Building not in buildings.php — supply publicFolderId and privateFolderId directly']);
    exit;
  }
  $publicFolderId  = $buildings[$building]['publicFolderId'];
  $privateFolderId = $buildings[$building]['privateFolderId'];
}

set_time_limit(300);
ignore_user_abort(true);

// -------------------------------------------------------
// BFS warm: fetch all folders level by level via curl_multi
// $prefix     — 'pub' or 'priv'
// $action     — GAS action name ('list' or 'listPrivate')
// $rootId     — root Drive folder ID for this tree
// $useToken   — whether to include the APPS_SCRIPT_TOKEN
// Returns count of cache entries written
// -------------------------------------------------------
function warmTree(string $prefix, string $building, string $action, string $rootId, bool $useToken): int {
  $count = 0;
  $queue = ['']; // subdirs to fetch; '' = root

  while (!empty($queue)) {
    $mh  = curl_multi_init();
    $chs = [];

    foreach ($queue as $subdir) {
      $url = WC_SCRIPT_URL . '?action=' . $action
           . ($useToken ? '&token=' . urlencode(WC_SCRIPT_TOKEN) : '')
           . '&folderId=' . urlencode($rootId)
           . ($subdir !== '' ? '&subdir=' . urlencode($subdir) : '');

      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 60,
      ]);
      curl_multi_add_handle($mh, $ch);
      $chs[$subdir] = $ch;
    }

    do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running);

    $nextQueue = [];
    foreach ($queue as $subdir) {
      $ch   = $chs[$subdir];
      $body = curl_multi_getcontent($ch);
      curl_multi_remove_handle($mh, $ch);
      curl_close($ch);

      if (!$body) continue;

      lcSet($prefix, $building, $subdir, $body);
      $count++;

      $data = json_decode($body, true);
      foreach ($data['folders'] ?? [] as $folder) {
        $child = $subdir !== '' ? $subdir . '/' . $folder['name'] : $folder['name'];
        $nextQueue[] = $child;
      }
    }

    curl_multi_close($mh);
    $queue = $nextQueue;
  }

  return $count;
}

$pubCount  = warmTree('pub',  $building, 'list',        $publicFolderId,  false);
$privCount = warmTree('priv', $building, 'listPrivate', $privateFolderId, true);

echo json_encode([
  'ok'      => true,
  'public'  => $pubCount,
  'private' => $privCount,
  'total'   => $pubCount + $privCount,
]);
