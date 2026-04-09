<?php
// -------------------------------------------------------
// listing-cache.php — Server-side Drive listing cache
//
// Caches GAS folder listing responses as JSON files on the
// server filesystem. No TTL — cache is valid indefinitely
// and busted explicitly on admin write operations (upload,
// delete, rename, folder create/delete, quarantine publish).
//
// Cache keys:
//   pub  — public browser  (display-public-dir.php)
//   priv — private browser (display-private-dir.php)
//   adm  — admin file mgr  (file-manager.php ?json=list)
// -------------------------------------------------------

define('LISTING_CACHE_DIR', __DIR__ . '/cache/');

function _lcFile(string $prefix, string $building, string $path): string {
  $safe = preg_replace('/[^a-zA-Z0-9]/', '', $building);
  return LISTING_CACHE_DIR . $prefix . '_' . $safe . '_' . md5($path) . '.json';
}

function lcGet(string $prefix, string $building, string $path): ?string {
  $file = _lcFile($prefix, $building, $path);
  if (!file_exists($file)) return null;
  $data = file_get_contents($file);
  return ($data !== false && $data !== '') ? $data : null;
}

function lcSet(string $prefix, string $building, string $path, string $json): void {
  if (!is_dir(LISTING_CACHE_DIR)) mkdir(LISTING_CACHE_DIR, 0755, true);
  file_put_contents(_lcFile($prefix, $building, $path), $json);
}

function lcBust(string $prefix, string $building, string $path): void {
  $file = _lcFile($prefix, $building, $path);
  if (file_exists($file)) @unlink($file);
}

// Bust all cache entries for a folder after an admin write.
// $tree = 'public' or 'private', $path = subdir within that tree.
function lcBustFolder(string $building, string $tree, string $path): void {
  lcBust('adm',  $building, $tree . ':' . $path);
  if ($tree === 'public') {
    lcBust('pub',  $building, $path);
  } else {
    lcBust('priv', $building, $path);
  }
}
