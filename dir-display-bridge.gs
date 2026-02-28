function doGet(e) {
  const folderId = e.parameter.folderId;
  const subdir   = e.parameter.subdir || '';

  if (!folderId) {
    return ContentService.createTextOutput(JSON.stringify({ error: 'No folderId provided' }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  // Start at the Public folder for this building
  let folder = DriveApp.getFolderById(folderId);

  // Navigate into subdir path (supports multiple levels e.g. "Forms/2024")
  if (subdir) {
    const parts = subdir.split('/');
    for (const part of parts) {
      const subfolders = folder.getFoldersByName(part);
      if (subfolders.hasNext()) {
        folder = subfolders.next();
      } else {
        return ContentService.createTextOutput(JSON.stringify({ error: 'Folder not found: ' + part }))
          .setMimeType(ContentService.MimeType.JSON);
      }
    }
  }

  // Collect subfolders
  const folderList = [];
  const folders = folder.getFolders();
  while (folders.hasNext()) {
    const f = folders.next();
    folderList.push({ name: f.getName() });
  }

  // Collect files
  const fileList = [];
  const files = folder.getFiles();
  while (files.hasNext()) {
    const file = files.next();
    fileList.push({
      name: file.getName(),
      url:  file.getDownloadUrl(),
      size: Math.round(file.getSize() / 1024) + " KB",
      type: file.getMimeType()
    });
  }

  return ContentService.createTextOutput(JSON.stringify({ folders: folderList, files: fileList }))
    .setMimeType(ContentService.MimeType.JSON);
}
