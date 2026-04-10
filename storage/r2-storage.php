<?php
// -------------------------------------------------------
// storage/r2-storage.php — Cloudflare R2 backend (Phase 2)
//
// Stub implementation. Functions return errors until
// Phase 2 implementation is complete.
// -------------------------------------------------------

function r2ListFolder(array $bldCfg, string $building, string $path, string $tree, string $context): string {
  return json_encode(['error' => 'R2 storage not yet implemented']);
}

function r2GetDownloadInfo(string $building, string $fileKey, string $tree): array {
  return ['type' => 'error', 'message' => 'R2 storage not yet implemented'];
}

function r2UploadFile(string $building, string $path, string $tree, string $tmpFile, string $fileName, string $mimeType): string {
  return json_encode(['ok' => false, 'error' => 'R2 storage not yet implemented']);
}

function r2DeleteFile(string $building, string $fileKey, string $tree): string {
  return json_encode(['ok' => false, 'error' => 'R2 storage not yet implemented']);
}

function r2DeleteFolder(string $building, string $folderPath, string $tree): string {
  return json_encode(['ok' => false, 'error' => 'R2 storage not yet implemented']);
}

function r2RenameFile(string $building, string $fileKey, string $newName, string $tree): string {
  return json_encode(['ok' => false, 'error' => 'R2 storage not yet implemented']);
}

function r2CreateFolder(string $building, string $path, string $name, string $tree): string {
  return json_encode(['ok' => false, 'error' => 'R2 storage not yet implemented']);
}

function r2StorageReport(string $building, string $tree): string {
  return json_encode(['error' => 'R2 storage not yet implemented']);
}

function r2StorageUsed(string $building): int {
  return 0;
}
