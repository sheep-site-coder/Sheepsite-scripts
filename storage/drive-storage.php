<?php
// -------------------------------------------------------
// storage/drive-storage.php — Google Drive / Apps Script backend
//
// All GAS communication lives here. Called exclusively via
// storage/storage.php — never included directly by callers.
// -------------------------------------------------------

define('GAS_URL',   'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('GAS_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');

// -------------------------------------------------------
// Internal helpers
// -------------------------------------------------------
function _gasGet(string $url): string {
  $resp = @file_get_contents($url);
  return $resp !== false ? $resp : json_encode(['error' => 'Could not reach Apps Script']);
}

function _gasPost(array $payload): string {
  $json = json_encode($payload);
  if (function_exists('curl_init')) {
    $ch = curl_init(GAS_URL);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $json,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT        => 270,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp !== false ? $resp : json_encode(['ok' => false, 'error' => 'Request failed']);
  }
  $opts = ['http' => [
    'method'          => 'POST',
    'header'          => "Content-Type: application/json\r\n",
    'content'         => $json,
    'follow_location' => 1,
    'ignore_errors'   => true,
  ]];
  $resp = @file_get_contents(GAS_URL, false, stream_context_create($opts));
  return $resp !== false ? $resp : json_encode(['ok' => false, 'error' => 'Request failed']);
}

// -------------------------------------------------------
// driveListFolder
// $context: 'pub' | 'priv' | 'adm'
// Returns JSON string: {folders:[...], files:[...], currentFolderId?}
// -------------------------------------------------------
function driveListFolder(array $bldCfg, string $path, string $tree, string $context): string {
  if ($context === 'pub') {
    $folderId = $bldCfg['publicFolderId'];
    $url = GAS_URL
         . '?action=list'
         . '&folderId=' . urlencode($folderId)
         . ($path ? '&subdir=' . urlencode($path) : '');
    return _gasGet($url);
  }

  if ($context === 'priv') {
    $folderId = $bldCfg['privateFolderId'];
    $url = GAS_URL
         . '?action=listPrivate'
         . '&token='    . urlencode(GAS_TOKEN)
         . '&folderId=' . urlencode($folderId)
         . ($path ? '&subdir=' . urlencode($path) : '');
    return _gasGet($url);
  }

  // 'adm'
  $folderId = $tree === 'private' ? $bldCfg['privateFolderId'] : $bldCfg['publicFolderId'];
  $url = GAS_URL
       . '?action=listAdmin'
       . '&token='    . urlencode(GAS_TOKEN)
       . '&folderId=' . urlencode($folderId)
       . ($path ? '&subdir=' . urlencode($path) : '');
  return _gasGet($url);
}

// -------------------------------------------------------
// driveGetDownloadInfo
// Returns array describing how to serve the file:
//   ['type' => 'proxy', 'mimeType' => ..., 'name' => ..., 'data' => ...base64...]
//   ['type' => 'error', 'message' => ...]
// -------------------------------------------------------
function driveGetDownloadInfo(string $fileId): array {
  $url = GAS_URL
       . '?action=download'
       . '&token='  . urlencode(GAS_TOKEN)
       . '&fileId=' . urlencode($fileId);
  $response = @file_get_contents($url);
  if ($response === false) {
    return ['type' => 'error', 'message' => 'Could not retrieve file.'];
  }
  $data = json_decode($response, true);
  if (!empty($data['error'])) {
    return ['type' => 'error', 'message' => $data['error']];
  }
  return [
    'type'     => 'proxy',
    'mimeType' => $data['mimeType'],
    'name'     => $data['name'],
    'data'     => $data['data'],
  ];
}

// -------------------------------------------------------
// driveUploadFile
// $folderId: Drive ID of the target folder (from listAdmin currentFolderId)
// Returns JSON string: {ok, id, name, ...}
// -------------------------------------------------------
function driveUploadFile(string $folderId, string $tmpFile, string $fileName, string $mimeType): string {
  return _gasPost([
    'action'   => 'uploadFile',
    'token'    => GAS_TOKEN,
    'folderId' => $folderId,
    'fileName' => $fileName,
    'mimeType' => $mimeType,
    'data'     => base64_encode(file_get_contents($tmpFile)),
  ]);
}

// -------------------------------------------------------
// driveDeleteFile
// Returns JSON string: {ok}
// -------------------------------------------------------
function driveDeleteFile(string $fileId): string {
  return _gasGet(GAS_URL
    . '?action=deleteFile'
    . '&token='  . urlencode(GAS_TOKEN)
    . '&fileId=' . urlencode($fileId));
}

// -------------------------------------------------------
// driveDeleteFolder
// Returns JSON string: {ok}
// -------------------------------------------------------
function driveDeleteFolder(string $folderId): string {
  return _gasGet(GAS_URL
    . '?action=deleteFolder'
    . '&token='    . urlencode(GAS_TOKEN)
    . '&folderId=' . urlencode($folderId));
}

// -------------------------------------------------------
// driveRenameFile
// Returns JSON string: {ok}
// -------------------------------------------------------
function driveRenameFile(string $fileId, string $newName): string {
  return _gasGet(GAS_URL
    . '?action=renameFile'
    . '&token='   . urlencode(GAS_TOKEN)
    . '&fileId='  . urlencode($fileId)
    . '&newName=' . urlencode($newName));
}

// -------------------------------------------------------
// driveCreateFolder
// $parentFolderId: Drive ID of the parent folder
// Returns JSON string: {ok, id}
// -------------------------------------------------------
function driveCreateFolder(string $parentFolderId, string $name): string {
  return _gasGet(GAS_URL
    . '?action=createFolder'
    . '&token='          . urlencode(GAS_TOKEN)
    . '&parentFolderId=' . urlencode($parentFolderId)
    . '&name='           . urlencode($name));
}

// -------------------------------------------------------
// driveStorageReport
// Returns JSON string: {subfolders:[{name,size}], total}
// -------------------------------------------------------
function driveStorageReport(array $bldCfg, string $tree): string {
  $folderId = $tree === 'private' ? $bldCfg['privateFolderId'] : $bldCfg['publicFolderId'];
  return _gasGet(GAS_URL
    . '?action=storageReport'
    . '&folderId=' . urlencode($folderId)
    . '&token='    . urlencode(GAS_TOKEN));
}

// -------------------------------------------------------
// driveStorageUsed
// Returns total bytes across both trees using parallel curl.
// -------------------------------------------------------
function driveStorageUsed(array $bldCfg): int {
  $urls = [
    GAS_URL . '?action=storageReport&folderId=' . urlencode($bldCfg['publicFolderId'])  . '&token=' . urlencode(GAS_TOKEN),
    GAS_URL . '?action=storageReport&folderId=' . urlencode($bldCfg['privateFolderId']) . '&token=' . urlencode(GAS_TOKEN),
  ];
  $mh  = curl_multi_init();
  $chs = [];
  foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 55]);
    curl_multi_add_handle($mh, $ch);
    $chs[] = $ch;
  }
  do { curl_multi_exec($mh, $running); curl_multi_select($mh); } while ($running);
  $total = 0;
  foreach ($chs as $ch) {
    $data = json_decode(curl_multi_getcontent($ch), true);
    if (isset($data['total'])) $total += (int)$data['total'];
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }
  curl_multi_close($mh);
  return $total;
}
