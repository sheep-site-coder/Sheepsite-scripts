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
  if (action === 'listAdmin')     return handleListAdmin(e);
  if (action === 'download')      return handleDownload(e);
  if (action === 'storageReport') return handleStorageReport(e);
  if (action === 'search')        return handleSearch(e);
  if (action === 'deleteFile')    return handleDeleteFile(e);
  if (action === 'renameFile')    return handleRenameFile(e);
  if (action === 'createFolder')  return handleCreateFolder(e);
  if (action === 'deleteFolder')  return handleDeleteFolder(e);
  if (action === 'docCheckResult') return handleDocCheckResult(e);
  if (action === 'docCheck')       return handleDocCheck(e);
  if (action === 'listDocFiles')   return handleListDocFiles(e);
  if (action === 'extractDocText') return handleExtractDocText(e);
  if (action === 'stampBaseline')  return handleStampBaseline(e);
  if (action === 'buildDocIndex')  return handleBuildDocIndex(e);

  return jsonError('Unknown action');
}

// -------------------------------------------------------
// File upload via POST — token required
// Body: { action, token, folderId, fileName, mimeType, data (base64) }
// -------------------------------------------------------
function doPost(e) {
  try {
    const data   = JSON.parse(e.postData.contents);
    const action = data.action || '';
    if (action === 'uploadFile') return handleUploadFile(data);
    return jsonError('Unknown action');
  } catch (err) {
    return jsonError('Invalid request: ' + err.message);
  }
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
// File search — token required
// Searches filename across both Public and Private folder trees.
// Query words are AND-ed: all words must appear in the filename.
// Returns flat list of matches with tree ('public'|'private') label.
// -------------------------------------------------------
function handleSearch(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const publicFolderId  = e.parameter.publicFolderId;
  const privateFolderId = e.parameter.privateFolderId;
  const query           = (e.parameter.query || '').trim();

  if (!publicFolderId || !privateFolderId) return jsonError('Missing folder IDs');
  if (!query) return jsonError('No query provided');

  // Build Drive search expression — AND all words
  const words = query.split(/\s+/).filter(Boolean);
  const expr  = words
    .map(w => "title contains '" + w.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'")
    .join(' and ');

  const results = [];

  // Search public tree recursively
  searchFolderRecursive(DriveApp.getFolderById(publicFolderId), expr, 'public', results);

  // Search private tree recursively
  searchFolderRecursive(DriveApp.getFolderById(privateFolderId), expr, 'private', results);

  results.sort((a, b) => a.name.localeCompare(b.name));

  return ContentService
    .createTextOutput(JSON.stringify({ results: results }))
    .setMimeType(ContentService.MimeType.JSON);
}

// Recursively search a folder tree for files matching expr
function searchFolderRecursive(folder, expr, tree, results) {
  const files = folder.searchFiles(expr);
  while (files.hasNext()) {
    const file = files.next();
    const entry = {
      id:   file.getId(),
      name: file.getName(),
      size: Math.round(file.getSize() / 1024) + ' KB',
      tree: tree
    };
    if (tree === 'public') entry.url = file.getDownloadUrl();
    results.push(entry);
  }
  const subfolders = folder.getFolders();
  while (subfolders.hasNext()) {
    searchFolderRecursive(subfolders.next(), expr, tree, results);
  }
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

// -------------------------------------------------------
// Admin folder listing — token required
// Returns folders with IDs, files with IDs + size, and
// the current folder's own ID (needed for createFolder/upload).
// -------------------------------------------------------
function handleListAdmin(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const folderId = e.parameter.folderId;
  const subdir   = e.parameter.subdir || '';

  if (!folderId) return jsonError('No folderId provided');

  const root   = DriveApp.getFolderById(folderId);
  const folder = navigateToSubdir(root, subdir);
  if (folder.error) return jsonError(folder.error);

  const folderList = [];
  const folderIter = folder.getFolders();
  while (folderIter.hasNext()) {
    const f = folderIter.next();
    folderList.push({ name: f.getName(), id: f.getId() });
  }

  const fileList = [];
  const fileIter = folder.getFiles();
  while (fileIter.hasNext()) {
    const file = fileIter.next();
    const bytes = file.getSize();
    const size  = bytes >= 1024 * 1024
      ? (bytes / 1024 / 1024).toFixed(1) + ' MB'
      : Math.round(bytes / 1024) + ' KB';
    fileList.push({
      id:   file.getId(),
      name: file.getName(),
      size: size
    });
  }

  return ContentService
    .createTextOutput(JSON.stringify({
      folders:         folderList,
      files:           fileList,
      currentFolderId: folder.getId()
    }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Delete file — token required
// param: fileId
// -------------------------------------------------------
function handleDeleteFile(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const fileId = e.parameter.fileId;
  if (!fileId) return jsonError('No fileId provided');

  DriveApp.getFileById(fileId).setTrashed(true);

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Rename file — token required
// params: fileId, newName
// -------------------------------------------------------
function handleRenameFile(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const fileId  = e.parameter.fileId;
  const newName = e.parameter.newName;
  if (!fileId || !newName) return jsonError('Missing fileId or newName');

  DriveApp.getFileById(fileId).setName(newName);

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Create subfolder — token required
// params: parentFolderId, name
// -------------------------------------------------------
function handleCreateFolder(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const parentFolderId = e.parameter.parentFolderId;
  const name           = e.parameter.name;
  if (!parentFolderId || !name) return jsonError('Missing parentFolderId or name');

  const newFolder = DriveApp.getFolderById(parentFolderId).createFolder(name);

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true, id: newFolder.getId(), name: newFolder.getName() }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Delete folder — token required
// param: folderId (must be empty — no files or subfolders)
// -------------------------------------------------------
function handleDeleteFolder(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');

  const folderId = e.parameter.folderId;
  if (!folderId) return jsonError('No folderId provided');

  const folder = DriveApp.getFolderById(folderId);

  // Refuse if folder has any files
  if (folder.getFiles().hasNext()) {
    return jsonError('Folder is not empty — remove all files first');
  }
  // Refuse if folder has any subfolders
  if (folder.getFolders().hasNext()) {
    return jsonError('Folder is not empty — remove all subfolders first');
  }

  folder.setTrashed(true);

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// Upload file — token in body required
// body: { token, folderId, fileName, mimeType, data (base64) }
// -------------------------------------------------------
function handleUploadFile(data) {
  if (data.token !== SECRET_TOKEN) return jsonError('Unauthorized');

  const folderId = data.folderId;
  const fileName = data.fileName;
  const mimeType = data.mimeType || 'application/octet-stream';
  const b64data  = data.data;

  if (!folderId || !fileName || !b64data) return jsonError('Missing required fields');

  const folder = DriveApp.getFolderById(folderId);
  const bytes  = Utilities.base64Decode(b64data);
  const blob   = Utilities.newBlob(bytes, mimeType, fileName);
  const file   = folder.createFile(blob);

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true, id: file.getId(), name: file.getName() }))
    .setMimeType(ContentService.MimeType.JSON);
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

// -------------------------------------------------------
// Woolsy doc check — weekly time-driven trigger entry point
// Checks all registered buildings for changes to their
// IncorporationDocs and RulesDocs folders. No API calls —
// purely Drive file metadata (names + modification times).
// -------------------------------------------------------
function checkAllBuildings() {
  const props    = PropertiesService.getScriptProperties();
  const listJson = props.getProperty('buildings_doccheck_list');
  if (!listJson) return;

  const buildings = JSON.parse(listJson);
  buildings.forEach(function(b) {
    try {
      runDocCheck(b.building, b.publicFolderId);
    } catch (err) {
      console.error('checkAllBuildings [' + b.building + ']: ' + err.message);
    }
  });
}

// Internal: scan both doc folders and compare to stored baseline.
// Writes {building}_doccheck to Script Properties.
function runDocCheck(building, publicFolderId) {
  const props        = PropertiesService.getScriptProperties();
  const publicFolder = DriveApp.getFolderById(publicFolderId);

  // Scan IncorporationDocs, RulesDocs, and Forms
  const current = {};
  ['IncorporationDocs', 'RulesDocs', 'Forms'].forEach(function(folderName) {
    const sub = publicFolder.getFoldersByName(folderName);
    if (!sub.hasNext()) { current[folderName] = []; return; }
    const folder = sub.next();
    const files  = [];
    const iter   = folder.getFiles();
    while (iter.hasNext()) {
      const f = iter.next();
      files.push({
        id:           f.getId(),
        name:         f.getName(),
        modifiedTime: f.getLastUpdated().toISOString(),
        size:         f.getSize()
      });
    }
    current[folderName] = files;
  });

  const baselineJson = props.getProperty(building + '_baseline');

  if (!baselineJson) {
    // No baseline yet — setup not completed; record file counts only
    props.setProperty(building + '_doccheck', JSON.stringify({
      checkedAt:  new Date().toISOString(),
      status:     'nobaseline',
      changes:    [],
      fileCounts: {
        IncorporationDocs: current.IncorporationDocs.length,
        RulesDocs:         current.RulesDocs.length
      }
    }));
    return;
  }

  const baseline = JSON.parse(baselineJson);
  const changes  = [];

  ['IncorporationDocs', 'RulesDocs', 'Forms'].forEach(function(folderName) {
    const curFiles  = current[folderName]  || [];
    const baseFiles = baseline[folderName] || [];

    const baseMap = {};
    baseFiles.forEach(function(f) { baseMap[f.id] = f; });
    const curMap = {};
    curFiles.forEach(function(f) { curMap[f.id] = f; });

    curFiles.forEach(function(f) {
      if (!baseMap[f.id]) {
        changes.push({ name: f.name, folder: folderName, action: 'new' });
      } else if (f.modifiedTime !== baseMap[f.id].modifiedTime) {
        changes.push({ name: f.name, folder: folderName, action: 'modified' });
      }
    });
    baseFiles.forEach(function(f) {
      if (!curMap[f.id]) {
        changes.push({ name: f.name, folder: folderName, action: 'removed' });
      }
    });
  });

  props.setProperty(building + '_doccheck', JSON.stringify({
    checkedAt:  new Date().toISOString(),
    status:     changes.length > 0 ? 'changes' : 'ok',
    changes:    changes,
    fileCounts: {
      IncorporationDocs: current.IncorporationDocs.length,
      RulesDocs:         current.RulesDocs.length,
      Forms:             current.Forms.length
    }
  }));
}

// -------------------------------------------------------
// docCheckResult — returns stored check result for admin card
// -------------------------------------------------------
function handleDocCheckResult(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');
  const building = e.parameter.building;
  if (!building)         return jsonError('No building provided');

  const props = PropertiesService.getScriptProperties();
  if (!props.getProperty(building + '_initialized')) {
    return ContentService
      .createTextOutput(JSON.stringify({ notInitialized: true }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  const result = props.getProperty(building + '_doccheck');
  if (!result) {
    return ContentService
      .createTextOutput(JSON.stringify({ notCheckedYet: true }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  return ContentService
    .createTextOutput(result)
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// docCheck — on-demand scan (called from admin "Check now")
// -------------------------------------------------------
function handleDocCheck(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');
  const building       = e.parameter.building;
  const publicFolderId = e.parameter.publicFolderId;
  if (!building || !publicFolderId) return jsonError('Missing params');

  runDocCheck(building, publicFolderId);

  const result = PropertiesService.getScriptProperties().getProperty(building + '_doccheck');
  return ContentService
    .createTextOutput(result || '{}')
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// listDocFiles — file listing for IncorporationDocs + RulesDocs
// -------------------------------------------------------
function handleListDocFiles(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');
  const publicFolderId = e.parameter.publicFolderId;
  if (!publicFolderId)  return jsonError('Missing publicFolderId');

  const publicFolder = DriveApp.getFolderById(publicFolderId);
  const result       = {};

  ['IncorporationDocs', 'RulesDocs'].forEach(function(folderName) {
    const sub = publicFolder.getFoldersByName(folderName);
    if (!sub.hasNext()) { result[folderName] = []; return; }
    const folder = sub.next();
    const files  = [];
    const iter   = folder.getFiles();
    while (iter.hasNext()) {
      const f = iter.next();
      files.push({
        id:           f.getId(),
        name:         f.getName(),
        size:         f.getSize(),
        modifiedTime: f.getLastUpdated().toISOString()
      });
    }
    result[folderName] = files;
  });

  return ContentService
    .createTextOutput(JSON.stringify(result))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// extractDocText — converts PDF to Google Doc and exports
// plain text. Handles scanned PDFs via Drive's built-in OCR.
// Requires the script to have drive scope authorized.
// -------------------------------------------------------
function handleExtractDocText(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');
  const fileId = e.parameter.fileId;
  if (!fileId)  return jsonError('No fileId provided');

  try {
    // Copy PDF as Google Doc — Drive performs OCR automatically on scanned files
    // Requires Drive Advanced Service (Add via Services in the editor sidebar)
    const copied = Drive.Files.copy(
      { name: 'woolsy_tmp_extract', mimeType: 'application/vnd.google-apps.document' },
      fileId
    );
    const docId = copied.id;

    // Extract text via DocumentApp — no external requests needed
    const text = DocumentApp.openById(docId).getBody().getText();

    // Delete temporary Google Doc
    DriveApp.getFileById(docId).setTrashed(true);

    const trimmed  = text.trim();
    const readable = trimmed.length > 100;

    return ContentService
      .createTextOutput(JSON.stringify({ text: text, readable: readable, charCount: trimmed.length }))
      .setMimeType(ContentService.MimeType.JSON);

  } catch (err) {
    return ContentService
      .createTextOutput(JSON.stringify({ text: '', readable: false, charCount: 0, error: err.message }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

// -------------------------------------------------------
// stampBaseline — called after admin accepts a knowledge base
// build/update. Stores current file state as the new baseline,
// marks building as initialized, and registers it for weekly checks.
// -------------------------------------------------------
function handleStampBaseline(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');
  const building       = e.parameter.building;
  const publicFolderId = e.parameter.publicFolderId;
  if (!building || !publicFolderId) return jsonError('Missing params');

  const props        = PropertiesService.getScriptProperties();
  const publicFolder = DriveApp.getFolderById(publicFolderId);

  // Scan current state for baseline
  const current = {};
  ['IncorporationDocs', 'RulesDocs', 'Forms'].forEach(function(folderName) {
    const sub = publicFolder.getFoldersByName(folderName);
    if (!sub.hasNext()) { current[folderName] = []; return; }
    const folder = sub.next();
    const files  = [];
    const iter   = folder.getFiles();
    while (iter.hasNext()) {
      const f = iter.next();
      files.push({
        id:           f.getId(),
        name:         f.getName(),
        modifiedTime: f.getLastUpdated().toISOString(),
        size:         f.getSize()
      });
    }
    current[folderName] = files;
  });

  props.setProperty(building + '_baseline',    JSON.stringify(current));
  props.setProperty(building + '_initialized', '1');

  // Add to weekly check list if not already present
  const listJson  = props.getProperty('buildings_doccheck_list');
  const list      = listJson ? JSON.parse(listJson) : [];
  if (!list.some(function(b) { return b.building === building; })) {
    list.push({ building: building, publicFolderId: publicFolderId });
    props.setProperty('buildings_doccheck_list', JSON.stringify(list));
  }

  // Record clean status immediately
  props.setProperty(building + '_doccheck', JSON.stringify({
    checkedAt:  new Date().toISOString(),
    status:     'ok',
    changes:    [],
    fileCounts: {
      IncorporationDocs: current.IncorporationDocs.length,
      RulesDocs:         current.RulesDocs.length,
      Forms:             current.Forms.length
    }
  }));

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true }))
    .setMimeType(ContentService.MimeType.JSON);
}

// -------------------------------------------------------
// buildDocIndex — recursively walks the public folder tree
// and returns a flat list of {path, files[]} sections.
// Used by Woolsy to know what documents exist on the site.
// -------------------------------------------------------
function handleBuildDocIndex(e) {
  if (!validateToken(e)) return jsonError('Unauthorized');
  const publicFolderId = e.parameter.publicFolderId;
  if (!publicFolderId) return jsonError('Missing publicFolderId');

  const sections = [];

  function walkFolder(folder, path) {
    const files = [];
    const fileIter = folder.getFiles();
    while (fileIter.hasNext()) {
      files.push(fileIter.next().getName());
    }
    if (files.length > 0) {
      files.sort();
      sections.push({ path: path, files: files });
    }
    const subIter = folder.getFolders();
    while (subIter.hasNext()) {
      const sub = subIter.next();
      walkFolder(sub, path + '/' + sub.getName());
    }
  }

  // Walk each top-level subfolder of the public root
  const root    = DriveApp.getFolderById(publicFolderId);
  const subIter = root.getFolders();
  while (subIter.hasNext()) {
    const sub = subIter.next();
    walkFolder(sub, sub.getName());
  }

  return ContentService
    .createTextOutput(JSON.stringify({ ok: true, sections: sections }))
    .setMimeType(ContentService.MimeType.JSON);
}
