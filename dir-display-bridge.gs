// Secret token — must match APPS_SCRIPT_TOKEN in display-private-dir.php
const SECRET_TOKEN = 'REPLACE_WITH_SECRET_TOKEN';

// keepWarm is called by a time-driven trigger every few minutes to prevent cold starts
function keepWarm() {
  DriveApp.getRootFolder();
}

function doGet(e) {
  const action = e.parameter.action || 'list';

  if (action === 'list')          return handleList(e);
  if (action === 'listPrivate')   return handleListPrivate(e);
  if (action === 'download')      return handleDownload(e);
  if (action === 'storageReport') return handleStorageReport(e);

  return jsonError('Unknown action');
}

// -------------------------------------------------------
// Public folder listing — no token required
// -------------------------------------------------------
function handleList(e) {
  const folderId = e.parameter.folderId;
  const subdir   = e.parameter.subdir || '';

  if (!folderId) return jsonError('No folderId provided');

  const folder = navigateToSubdir(DriveApp.getFolderById(folderId), subdir);
  if (folder.error) return jsonError(folder.error);

  return ContentService
    .createTextOutput(JSON.stringify(listFolder(folder, false)))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Private folder listing — token required
// Returns file IDs (not URLs) so downloads must be proxied
// -------------------------------------------------------
function handleListPrivate(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const folderId = e.parameter.folderId;
  const subdir   = e.parameter.subdir || '';

  if (!folderId) return jsonError('No folderId provided');

  const folder = navigateToSubdir(DriveApp.getFolderById(folderId), subdir);
  if (folder.error) return jsonError(folder.error);

  return ContentService
    .createTextOutput(JSON.stringify(listFolder(folder, true)))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Private file download — token required
// Returns base64-encoded file content for PHP to stream
// -------------------------------------------------------
function handleDownload(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const fileId = e.parameter.fileId;
  if (!fileId) return jsonError('No fileId provided');

  const file = DriveApp.getFileById(fileId);
  const blob = file.getBlob();

  return ContentService
    .createTextOutput(JSON.stringify({
      name:     file.getName(),
      mimeType: blob.getContentType(),
      data:     Utilities.base64Encode(blob.getBytes())
    }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Storage report — token required
// Returns total size + per-subfolder breakdown for a folder
// -------------------------------------------------------
function handleStorageReport(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const folderId = e.parameter.folderId;
  if (!folderId) return jsonError('No folderId provided');

  const folder = DriveApp.getFolderById(folderId);

  // Sum any files sitting directly in the root (counts toward total, not listed separately)
  let total = 0;
  const rootFiles = folder.getFiles();
  while (rootFiles.hasNext()) {
    total += rootFiles.next().getSize();
  }

  // First-level subfolders — size each one recursively
  const subfolders = [];
  const folders = folder.getFolders();
  while (folders.hasNext()) {
    const sub  = folders.next();
    const size = getFolderSize(sub);
    total += size;
    subfolders.push({ name: sub.getName(), size: size });
  }

  subfolders.sort(function(a, b) { return b.size - a.size; });

  return ContentService
    .createTextOutput(JSON.stringify({ total: total, subfolders: subfolders }))
    .setMimeType(ContentService.MimeType.JSON);
}

// Recursively sum all file sizes within a folder tree
function getFolderSize(folder) {
  let size = 0;
  const files = folder.getFiles();
  while (files.hasNext()) {
    size += files.next().getSize();
  }
  const subfolders = folder.getFolders();
  while (subfolders.hasNext()) {
    size += getFolderSize(subfolders.next());
  }
  return size;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function validateToken(e) {
  return e.parameter.token === SECRET_TOKEN;
}

function jsonError(msg) {
  return ContentService
    .createTextOutput(JSON.stringify({ error: msg }))
    .setMimeType(ContentService.MimeType.JSON);
}

// Navigate into a subdir path like "Forms/2024"
// Returns the folder, or { error: '...' } on failure
function navigateToSubdir(folder, subdir) {
  if (!subdir) return folder;
  for (const part of subdir.split('/')) {
    const matches = folder.getFoldersByName(part);
    if (!matches.hasNext()) return { error: 'Folder not found: ' + part };
    folder = matches.next();
  }
  return folder;
}

// List subfolders and files from a folder
// privateMode=true  → files get { id } instead of { url }
function listFolder(folder, privateMode) {
  const folderList = [];
  const folders = folder.getFolders();
  while (folders.hasNext()) {
    const f = folders.next();
    folderList.push({ name: f.getName() });
  }

  const fileList = [];
  const files = folder.getFiles();
  while (files.hasNext()) {
    const file = files.next();
    const entry = {
      name: file.getName(),
      size: Math.round(file.getSize() / 1024) + ' KB',
      type: file.getMimeType()
    };
    entry.id = file.getId();
    if (!privateMode) {
      entry.url = file.getDownloadUrl();
    }
    fileList.push(entry);
  }

  return { folders: folderList, files: fileList };
}
