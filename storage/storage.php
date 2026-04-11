<?php
// -------------------------------------------------------
// storage/storage.php — Storage abstraction (R2 backend)
//
// Include this file to get all st*() functions.
//
// Interface:
//   stListFolder($building, $path, $tree, $context)
//   stGetDownloadInfo($building, $fileId, $tree)
//   stUploadFile($building, $tree, $folderId, $path, $tmpFile, $fileName, $mimeType)
//   stDeleteFile($building, $fileId, $tree)
//   stDeleteFolder($building, $folderId, $tree)
//   stRenameFile($building, $fileId, $newName, $tree)
//   stCreateFolder($building, $tree, $parentFolderId, $path, $name)
//   stStorageReport($building, $tree)
//   stStorageUsed($building)
// -------------------------------------------------------

require_once __DIR__ . '/r2-storage.php';

function _stBldCfg(string $building): array {
  static $b = null;
  if ($b === null) $b = require __DIR__ . '/../buildings.php';
  return $b[$building] ?? [];
}

// -------------------------------------------------------
// stListFolder
// $context: 'pub' | 'priv' | 'adm'
// Returns JSON string: {folders:[...], files:[...], currentFolderId?}
// -------------------------------------------------------
function stListFolder(string $building, string $path, string $tree, string $context): string {
  return r2ListFolder(_stBldCfg($building), $building, $path, $tree, $context);
}

// -------------------------------------------------------
// stGetDownloadInfo
// Returns array:
//   ['type' => 'redirect', 'url' => ...]
//   ['type' => 'error',    'message' => ...]
// -------------------------------------------------------
function stGetDownloadInfo(string $building, string $fileId, string $tree): array {
  return r2GetDownloadInfo($building, $fileId, $tree);
}

// -------------------------------------------------------
// stUploadFile
// $folderId: ignored for R2 (uses $path)
// $path:     folder path within tree
// Returns JSON string: {ok, id, name}
// -------------------------------------------------------
function stUploadFile(string $building, string $tree, string $folderId, string $path,
                      string $tmpFile, string $fileName, string $mimeType): string {
  return r2UploadFile($building, $path, $tree, $tmpFile, $fileName, $mimeType);
}

// -------------------------------------------------------
// stDeleteFile
// $fileId: R2 object key
// Returns JSON string: {ok}
// -------------------------------------------------------
function stDeleteFile(string $building, string $fileId, string $tree): string {
  return r2DeleteFile($building, $fileId, $tree);
}

// -------------------------------------------------------
// stDeleteFolder
// $folderId: R2 folder prefix
// Returns JSON string: {ok}
// -------------------------------------------------------
function stDeleteFolder(string $building, string $folderId, string $tree): string {
  return r2DeleteFolder($building, $folderId, $tree);
}

// -------------------------------------------------------
// stRenameFile
// $fileId: R2 object key
// Returns JSON string: {ok}
// -------------------------------------------------------
function stRenameFile(string $building, string $fileId, string $newName, string $tree): string {
  return r2RenameFile($building, $fileId, $newName, $tree);
}

// -------------------------------------------------------
// stCreateFolder
// $parentFolderId: ignored for R2 (uses $path)
// $path:           parent path within tree
// Returns JSON string: {ok, id}
// -------------------------------------------------------
function stCreateFolder(string $building, string $tree, string $parentFolderId,
                        string $path, string $name): string {
  return r2CreateFolder($building, $path, $name, $tree);
}

// -------------------------------------------------------
// stStorageReport
// Returns JSON string: {subfolders:[{name,size}], total}
// -------------------------------------------------------
function stStorageReport(string $building, string $tree): string {
  return r2StorageReport($building, $tree);
}

// -------------------------------------------------------
// stStorageUsed
// Returns total bytes across both trees.
// -------------------------------------------------------
function stStorageUsed(string $building): int {
  return r2StorageUsed($building);
}

// -------------------------------------------------------
// stPresignedUploadUrl
// Returns a presigned PUT URL for direct browser → R2 upload.
// PHP validates storage limits before issuing; the URL signs
// content-length + content-type so R2 enforces the approved size.
// Returns array: ['url' => ..., 'key' => ...]
// -------------------------------------------------------
function stPresignedUploadUrl(string $building, string $tree, string $path,
                              string $fileName, int $fileSize, string $contentType): array {
  $key = _r2Prefix($building, $tree, $path) . $fileName;
  $url = _r2PresignedPutUrl($key, $fileSize, $contentType, 1800); // 30 min
  return ['url' => $url, 'key' => $key];
}
