<?php
// -------------------------------------------------------
// storage/r2-storage.php — Cloudflare R2 backend
//
// Uses the S3-compatible API with AWS Signature V4.
// No SDK — pure PHP + curl. Credentials loaded from
// credentials/r2.json: {accountId, accessKey, secretKey, bucket}
// -------------------------------------------------------

define('R2_REGION',  'auto');
define('R2_SERVICE', 's3');

// -------------------------------------------------------
// Config + helpers
// -------------------------------------------------------
function _r2Cfg(): array {
  static $cfg = null;
  if ($cfg === null) {
    $file = __DIR__ . '/../credentials/r2.json';
    $cfg  = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
  }
  return $cfg;
}

function _r2Datetime(): string { return gmdate('Ymd\THis\Z'); }

// Percent-encode each path segment, keep slashes
function _r2EncodePath(string $path): string {
  return '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
}

// Object key prefix for a building + tree + path
function _r2Prefix(string $building, string $tree, string $path): string {
  $base = $building . '/' . $tree . '/';
  return $path ? $base . trim($path, '/') . '/' : $base;
}

// Sum all object sizes under {building}/public/ and {building}/private/.
// Returns total bytes, or null on R2 error.
function _r2SumBytes(string $building, array $cfg): ?int {
  $total = 0;
  foreach (['public', 'private'] as $tree) {
    $prefix = $building . '/' . $tree . '/';
    $token  = null;
    do {
      $q = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
      if ($token) $q['continuation-token'] = $token;
      [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], $q);
      if ($status !== 200) return null;
      $sx = @simplexml_load_string($body);
      if (!$sx) return null;
      foreach ($sx->Contents as $obj) { $total += (int)(string)$obj->Size; }
      $token = (string)($sx->NextContinuationToken ?? '');
    } while ($token !== '');
  }
  return $total;
}

function _r2FmtSize(int $bytes): string {
  if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576)    return number_format($bytes / 1048576, 1)    . ' MB';
  if ($bytes >= 1024)       return round($bytes / 1024) . ' KB';
  return $bytes . ' B';
}

// -------------------------------------------------------
// AWS Signature V4 — signed HTTP request
// Returns [httpStatus, responseBody]
// -------------------------------------------------------
function _r2Request(string $method, string $path, array $query = [], array $extraHeaders = [], string $body = ''): array {
  $cfg = _r2Cfg();
  if (empty($cfg['accountId'])) return [500, json_encode(['error' => 'R2 not configured'])];

  $method      = strtoupper($method);
  $datetime    = _r2Datetime();
  $date        = substr($datetime, 0, 8);
  $host        = $cfg['accountId'] . '.r2.cloudflarestorage.com';
  $payloadHash = hash('sha256', $body);

  // Headers to sign (lowercase keys, sorted)
  $hdrs = ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $datetime];
  foreach ($extraHeaders as $k => $v) $hdrs[strtolower($k)] = trim($v);
  ksort($hdrs);

  $canonHdrs  = '';
  $signedList = [];
  foreach ($hdrs as $k => $v) { $canonHdrs .= "$k:$v\n"; $signedList[] = $k; }
  $signedHdrs = implode(';', $signedList);

  // Canonical query string
  ksort($query);
  $cqs = implode('&', array_map(
    fn($k, $v) => rawurlencode((string)$k) . '=' . rawurlencode((string)$v),
    array_keys($query), $query
  ));

  $encodedPath = _r2EncodePath($path);
  $canonReq    = "$method\n$encodedPath\n$cqs\n$canonHdrs\n$signedHdrs\n$payloadHash";

  // String to sign
  $scope = "$date/" . R2_REGION . '/' . R2_SERVICE . '/aws4_request';
  $sts   = "AWS4-HMAC-SHA256\n$datetime\n$scope\n" . hash('sha256', $canonReq);

  // Signing key chain
  $kDate    = hash_hmac('sha256', $date,          'AWS4' . $cfg['secretKey'], true);
  $kRegion  = hash_hmac('sha256', R2_REGION,      $kDate,    true);
  $kService = hash_hmac('sha256', R2_SERVICE,     $kRegion,  true);
  $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
  $sig      = hash_hmac('sha256', $sts, $kSigning);

  // Build curl headers
  $curlHdrs = [
    'authorization: AWS4-HMAC-SHA256 Credential=' . $cfg['accessKey'] . '/' . $scope
      . ', SignedHeaders=' . $signedHdrs . ', Signature=' . $sig,
  ];
  foreach ($hdrs as $k => $v) {
    if ($k !== 'host') $curlHdrs[] = "$k: $v";
  }
  if ($method === 'PUT' && $body === '') $curlHdrs[] = 'content-length: 0';

  // URL
  $url = 'https://' . $host . $encodedPath;
  if ($query) {
    $url .= '?' . implode('&', array_map(
      fn($k, $v) => rawurlencode((string)$k) . '=' . rawurlencode((string)$v),
      array_keys($query), $query
    ));
  }

  $ch = curl_init($url);
  $opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_HTTPHEADER     => $curlHdrs,
    CURLOPT_CUSTOMREQUEST  => $method,
  ];
  if ($method === 'PUT') $opts[CURLOPT_POSTFIELDS] = $body;
  curl_setopt_array($ch, $opts);
  $resp   = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$status, $resp !== false ? $resp : ''];
}

// -------------------------------------------------------
// Pre-signed GET URL (for file downloads)
// -------------------------------------------------------
function _r2PresignedUrl(string $objectKey, int $expires = 3600): string {
  $cfg      = _r2Cfg();
  $datetime = _r2Datetime();
  $date     = substr($datetime, 0, 8);
  $host     = $cfg['accountId'] . '.r2.cloudflarestorage.com';
  $path     = '/' . $cfg['bucket'] . '/' . ltrim($objectKey, '/');
  $scope    = "$date/" . R2_REGION . '/' . R2_SERVICE . '/aws4_request';

  $q = [
    'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
    'X-Amz-Credential'    => $cfg['accessKey'] . '/' . $scope,
    'X-Amz-Date'          => $datetime,
    'X-Amz-Expires'       => (string)$expires,
    'X-Amz-SignedHeaders' => 'host',
  ];
  ksort($q);
  $cqs = implode('&', array_map(
    fn($k, $v) => rawurlencode($k) . '=' . rawurlencode($v), array_keys($q), $q
  ));

  $encodedPath = _r2EncodePath($path);
  $canonReq    = "GET\n$encodedPath\n$cqs\nhost:$host\n\nhost\nUNSIGNED-PAYLOAD";
  $sts         = "AWS4-HMAC-SHA256\n$datetime\n$scope\n" . hash('sha256', $canonReq);

  $kDate    = hash_hmac('sha256', $date,          'AWS4' . $cfg['secretKey'], true);
  $kRegion  = hash_hmac('sha256', R2_REGION,      $kDate,    true);
  $kService = hash_hmac('sha256', R2_SERVICE,     $kRegion,  true);
  $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

  $q['X-Amz-Signature'] = hash_hmac('sha256', $sts, $kSigning);
  ksort($q);
  $qs = implode('&', array_map(
    fn($k, $v) => rawurlencode($k) . '=' . rawurlencode($v), array_keys($q), $q
  ));

  return 'https://' . $host . $encodedPath . '?' . $qs;
}

// -------------------------------------------------------
// Parse S3 ListObjectsV2 XML into our standard listing format
// -------------------------------------------------------
function _r2ParseListing(string $xml, string $prefix, bool $includeUrls): array {
  $sx = @simplexml_load_string($xml);
  if (!$sx) return ['error' => 'Failed to parse R2 listing response'];

  $folders = [];
  $files   = [];

  if (isset($sx->CommonPrefixes)) {
    foreach ($sx->CommonPrefixes as $cp) {
      $fullPrefix = (string)$cp->Prefix;
      $name       = rtrim(substr($fullPrefix, strlen($prefix)), '/');
      if ($name === '' || $name === '.') continue;
      $folders[] = ['name' => $name, 'id' => $fullPrefix];
    }
  }

  if (isset($sx->Contents)) {
    foreach ($sx->Contents as $obj) {
      $key  = (string)$obj->Key;
      $size = (int)(string)$obj->Size;
      $name = substr($key, strlen($prefix));
      if ($name === '' || $name === '.keep' || str_ends_with($name, '/')) continue;

      $files[] = [
        'name' => $name,
        'id'   => $key,
        'size' => _r2FmtSize($size),
        'url'  => $includeUrls ? _r2PresignedUrl($key, 86400) : '', // 24h for public
      ];
    }
  }

  return ['folders' => $folders, 'files' => $files, 'currentFolderId' => $prefix];
}

// -------------------------------------------------------
// r2ListFolder
// -------------------------------------------------------
function r2ListFolder(array $bldCfg, string $building, string $path, string $tree, string $context): string {
  $cfg    = _r2Cfg();
  $prefix = _r2Prefix($building, $tree, $path);

  [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], [
    'list-type' => '2',
    'prefix'    => $prefix,
    'delimiter' => '/',
  ]);

  if ($status !== 200) return json_encode(['error' => 'R2 listing failed (HTTP ' . $status . ')']);

  $data = _r2ParseListing($body, $prefix, $context !== 'priv');
  return json_encode($data);
}

// -------------------------------------------------------
// r2GetDownloadInfo — redirect to a short-lived pre-signed URL
// -------------------------------------------------------
function r2GetDownloadInfo(string $building, string $fileKey, string $tree): array {
  // Basic path traversal guard — key must belong to this building
  if (!str_starts_with($fileKey, $building . '/')) {
    return ['type' => 'error', 'message' => 'Invalid file key.'];
  }
  return ['type' => 'redirect', 'url' => _r2PresignedUrl($fileKey, 300)]; // 5 min
}

// -------------------------------------------------------
// r2UploadFile
// -------------------------------------------------------
function r2UploadFile(string $building, string $path, string $tree, string $tmpFile, string $fileName, string $mimeType): string {
  $cfg     = _r2Cfg();
  $key     = _r2Prefix($building, $tree, $path) . $fileName;
  $body    = file_get_contents($tmpFile);
  $objPath = '/' . $cfg['bucket'] . '/' . $key;

  [$status, ] = _r2Request('PUT', $objPath, [], ['content-type' => $mimeType], $body);

  return ($status === 200 || $status === 204)
    ? json_encode(['ok' => true, 'id' => $key, 'name' => $fileName])
    : json_encode(['ok' => false, 'error' => 'Upload failed (HTTP ' . $status . ')']);
}

// -------------------------------------------------------
// r2DeleteFile
// -------------------------------------------------------
function r2DeleteFile(string $building, string $fileKey, string $tree): string {
  $cfg = _r2Cfg();
  [$status, ] = _r2Request('DELETE', '/' . $cfg['bucket'] . '/' . ltrim($fileKey, '/'));
  return ($status === 204 || $status === 200)
    ? json_encode(['ok' => true])
    : json_encode(['ok' => false, 'error' => 'Delete failed (HTTP ' . $status . ')']);
}

// -------------------------------------------------------
// r2DeleteFolder — only if empty (just the .keep placeholder)
// -------------------------------------------------------
function r2DeleteFolder(string $building, string $folderPrefix, string $tree): string {
  $cfg = _r2Cfg();

  [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], [
    'list-type' => '2', 'prefix' => $folderPrefix,
  ]);
  if ($status !== 200) return json_encode(['ok' => false, 'error' => 'Could not list folder']);

  $sx = @simplexml_load_string($body);
  if (!$sx) return json_encode(['ok' => false, 'error' => 'Could not parse folder listing']);

  if (isset($sx->Contents)) {
    foreach ($sx->Contents as $obj) {
      if ((string)$obj->Key !== $folderPrefix . '.keep') {
        return json_encode(['ok' => false, 'error' => 'Folder is not empty']);
      }
    }
  }

  [$status, ] = _r2Request('DELETE', '/' . $cfg['bucket'] . '/' . $folderPrefix . '.keep');
  return ($status === 204 || $status === 200 || $status === 404)
    ? json_encode(['ok' => true])
    : json_encode(['ok' => false, 'error' => 'Delete failed (HTTP ' . $status . ')']);
}

// -------------------------------------------------------
// r2RenameFile — copy to new key then delete old
// -------------------------------------------------------
function r2RenameFile(string $building, string $fileKey, string $newName, string $tree): string {
  $cfg     = _r2Cfg();
  $bucket  = $cfg['bucket'];
  $dir     = dirname($fileKey);
  $newKey  = ($dir === '.' ? '' : $dir . '/') . $newName;
  $destPath = '/' . $bucket . '/' . $newKey;

  [$status, ] = _r2Request('PUT', $destPath, [], [
    'x-amz-copy-source' => rawurlencode('/' . $bucket . '/' . $fileKey),
  ]);

  if ($status !== 200 && $status !== 204) {
    return json_encode(['ok' => false, 'error' => 'Copy failed (HTTP ' . $status . ')']);
  }

  _r2Request('DELETE', '/' . $bucket . '/' . $fileKey);
  return json_encode(['ok' => true, 'id' => $newKey]);
}

// -------------------------------------------------------
// r2CreateFolder — write a .keep placeholder
// -------------------------------------------------------
function r2CreateFolder(string $building, string $path, string $name, string $tree): string {
  $cfg       = _r2Cfg();
  $newPrefix = _r2Prefix($building, $tree, $path) . $name . '/';
  $key       = $newPrefix . '.keep';

  [$status, ] = _r2Request('PUT', '/' . $cfg['bucket'] . '/' . $key, [], [
    'content-type' => 'application/octet-stream',
  ]);

  return ($status === 200 || $status === 204)
    ? json_encode(['ok' => true, 'id' => $newPrefix])
    : json_encode(['ok' => false, 'error' => 'Create folder failed (HTTP ' . $status . ')']);
}

// -------------------------------------------------------
// r2StorageReport — grouped by top-level subfolder
// -------------------------------------------------------
function r2StorageReport(string $building, string $tree): string {
  $cfg    = _r2Cfg();
  $prefix = $building . '/' . $tree . '/';
  $totals = [];
  $total  = 0;
  $token  = null;

  do {
    $q = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
    if ($token) $q['continuation-token'] = $token;
    [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], $q);
    if ($status !== 200) return json_encode(['error' => 'R2 storage report failed']);

    $sx = @simplexml_load_string($body);
    if (!$sx) return json_encode(['error' => 'Failed to parse R2 response']);

    if (isset($sx->Contents)) {
      foreach ($sx->Contents as $obj) {
        $key  = (string)$obj->Key;
        $size = (int)(string)$obj->Size;
        $rel  = substr($key, strlen($prefix));
        $sf   = (str_contains($rel, '/')) ? explode('/', $rel, 2)[0] : '(root)';
        $totals[$sf] = ($totals[$sf] ?? 0) + $size;
        $total += $size;
      }
    }

    $token = (string)($sx->NextContinuationToken ?? '');
  } while ($token !== '');

  $subfolders = [];
  foreach ($totals as $name => $size) $subfolders[] = ['name' => $name, 'size' => $size];
  usort($subfolders, fn($a, $b) => strcmp($a['name'], $b['name']));

  return json_encode(['subfolders' => $subfolders, 'total' => $total]);
}

// -------------------------------------------------------
// r2StorageUsed — total bytes for a building (both trees)
// -------------------------------------------------------
function r2StorageUsed(string $building): int {
  $cfg   = _r2Cfg();
  $total = 0;
  $token = null;

  do {
    $q = ['list-type' => '2', 'prefix' => $building . '/', 'max-keys' => '1000'];
    if ($token) $q['continuation-token'] = $token;
    [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], $q);
    if ($status !== 200) break;
    $sx = @simplexml_load_string($body);
    if (!$sx) break;
    if (isset($sx->Contents)) {
      foreach ($sx->Contents as $obj) $total += (int)(string)$obj->Size;
    }
    $token = (string)($sx->NextContinuationToken ?? '');
  } while ($token !== '');

  return $total;
}
