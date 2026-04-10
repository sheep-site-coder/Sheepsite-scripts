<?php
// -------------------------------------------------------
// r2-test.php — Smoke test for R2 connectivity
// Master admin only. Delete from server after use.
// -------------------------------------------------------
session_start();
define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

$sessionKey = 'master_admin_auth';
if (empty($_SESSION[$sessionKey])) {
  die('<p style="color:red;">Not authenticated. Log in to master-admin.php first.</p>');
}

require_once __DIR__ . '/storage/r2-storage.php';
$cfg = _r2Cfg();

echo '<h2>R2 Config</h2>';
echo '<p>Account ID: ' . htmlspecialchars($cfg['accountId'] ?? '(missing)') . '</p>';
echo '<p>Bucket: '     . htmlspecialchars($cfg['bucket']    ?? '(missing)') . '</p>';
echo '<p>Access Key: ' . htmlspecialchars(substr($cfg['accessKey'] ?? '', 0, 8) . '…') . '</p>';

echo '<h2>List Bucket (root)</h2>';
[$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], ['list-type' => '2', 'max-keys' => '10']);
echo '<p>HTTP Status: ' . $status . '</p>';
if ($status === 200) {
  echo '<p style="color:green;">✓ Connected to R2 successfully</p>';
  $sx = @simplexml_load_string($body);
  if ($sx) {
    $count = isset($sx->Contents) ? count($sx->Contents) : 0;
    echo '<p>Objects at root: ' . $count . '</p>';
  }
} else {
  echo '<p style="color:red;">✗ Connection failed</p>';
  echo '<pre>' . htmlspecialchars(substr($body, 0, 500)) . '</pre>';
}

echo '<h2>Write Test</h2>';
$testKey  = '_r2test/.keep';
$testPath = '/' . $cfg['bucket'] . '/' . $testKey;
[$wStatus, ] = _r2Request('PUT', $testPath, [], ['content-type' => 'text/plain'], 'r2 test');
echo '<p>PUT ' . htmlspecialchars($testKey) . ': HTTP ' . $wStatus . ' ' . (($wStatus === 200 || $wStatus === 204) ? '✓' : '✗') . '</p>';

[$dStatus, ] = _r2Request('DELETE', $testPath);
echo '<p>DELETE ' . htmlspecialchars($testKey) . ': HTTP ' . $dStatus . ' ' . (($dStatus === 204 || $dStatus === 200) ? '✓' : '✗') . '</p>';

echo '<hr><p style="color:#888;font-size:0.85rem;">Delete this file from the server after testing.</p>';
