<?php
// -------------------------------------------------------
// storage/storage.php — Storage abstraction dispatch layer
//
// Include this file to get all st*() functions. Never call
// drive-storage.php or r2-storage.php directly.
//
// Per-building backend is set in buildings.php:
//   'storage' => 'r2'    — Cloudflare R2
//   'storage' => 'drive' — Google Drive via Apps Script (default)
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

require_once __DIR__ . '/drive-storage.php';
require_once __DIR__ . '/r2-storage.php';

function _stBuildings(): array {
  static $b = null;
  if ($b === null) $b = require __DIR__ . '/../buildings.php';
  return $b;
}

function _stDriver(string $building): string {
  $b = _stBuildings();
  return ($b[$building]['storage'] ?? 'drive');
}

function _stBldCfg(string $building): array {
  return _stBuildings()[$building] ?? [];
}

// -------------------------------------------------------
// stListFolder
// $context: 'pub' | 'priv' | 'adm'
// Returns JSON string: {folders:[...], files:[...], currentFolderId?}
// -------------------------------------------------------
function stListFolder(string $building, string $path, string $tree, string $context): string {
  if (_stDriver($building) === 'r2') {
    return r2ListFolder(_stBldCfg($building), $building, $path, $tree, $context);
  }
  return driveListFolder(_stBldCfg($building), $path, $tree, $context);
}

// -------------------------------------------------------
// stGetDownloadInfo
// Returns array:
//   ['type' => 'proxy',    'mimeType' => ..., 'name' => ..., 'data' => ...base64...]
//   ['type' => 'redirect', 'url' => ...]
//   ['type' => 'error',    'message' => ...]
// -------------------------------------------------------
function stGetDownloadInfo(string $building, string $fileId, string $tree): array {
  if (_stDriver($building) === 'r2') {
    return r2GetDownloadInfo($building, $fileId, $tree);
  }
  return driveGetDownloadInfo($fileId);
}

// -------------------------------------------------------
// stUploadFile
// $folderId: Drive folder ID (Drive) or ignored (R2 uses $path)
// $path:     folder path within tree (R2) or ignored (Drive)
// Returns JSON string: {ok, id, name, ...}
// -------------------------------------------------------
function stUploadFile(string $building, string $tree, string $folderId, string $path,
                      string $tmpFile, string $fileName, string $mimeType): string {
  if (_stDriver($building) === 'r2') {
    return r2UploadFile($building, $path, $tree, $tmpFile, $fileName, $mimeType);
  }
  return driveUploadFile($folderId, $tmpFile, $fileName, $mimeType);
}

// -------------------------------------------------------
// stDeleteFile
// $fileId: Drive file ID (Drive) or R2 object key (R2)
// Returns JSON string: {ok}
// -------------------------------------------------------
function stDeleteFile(string $building, string $fileId, string $tree): string {
  if (_stDriver($building) === 'r2') {
    return r2DeleteFile($building, $fileId, $tree);
  }
  return driveDeleteFile($fileId);
}

// -------------------------------------------------------
// stDeleteFolder
// $folderId: Drive folder ID (Drive) or folder path (R2)
// Returns JSON string: {ok}
// -------------------------------------------------------
function stDeleteFolder(string $building, string $folderId, string $tree): string {
  if (_stDriver($building) === 'r2') {
    return r2DeleteFolder($building, $folderId, $tree);
  }
  return driveDeleteFolder($folderId);
}

// -------------------------------------------------------
// stRenameFile
// $fileId: Drive file ID (Drive) or R2 object key (R2)
// Returns JSON string: {ok}
// -------------------------------------------------------
function stRenameFile(string $building, string $fileId, string $newName, string $tree): string {
  if (_stDriver($building) === 'r2') {
    return r2RenameFile($building, $fileId, $newName, $tree);
  }
  return driveRenameFile($fileId, $newName);
}

// -------------------------------------------------------
// stCreateFolder
// $parentFolderId: Drive ID of parent folder (Drive only)
// $path:           parent path within tree (R2 only)
// Returns JSON string: {ok, id}
// -------------------------------------------------------
function stCreateFolder(string $building, string $tree, string $parentFolderId,
                        string $path, string $name): string {
  if (_stDriver($building) === 'r2') {
    return r2CreateFolder($building, $path, $name, $tree);
  }
  return driveCreateFolder($parentFolderId, $name);
}

// -------------------------------------------------------
// stStorageReport
// Returns JSON string: {subfolders:[{name,size}], total}
// -------------------------------------------------------
function stStorageReport(string $building, string $tree): string {
  if (_stDriver($building) === 'r2') {
    return r2StorageReport($building, $tree);
  }
  return driveStorageReport(_stBldCfg($building), $tree);
}

// -------------------------------------------------------
// stStorageUsed
// Returns total bytes across both trees.
// -------------------------------------------------------
function stStorageUsed(string $building): int {
  if (_stDriver($building) === 'r2') {
    return r2StorageUsed($building);
  }
  return driveStorageUsed(_stBldCfg($building));
}
