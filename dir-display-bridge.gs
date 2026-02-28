function doGet(e) {
  const folderId = e.parameter.folderId;
  const subdir   = e.parameter.subdir || '';

  if (!folderId) {
    return ContentService.createTextOutput(JSON.stringify({ error: 'No folderId provided' }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  // Start at the Public folder for this building
  let folder = DriveApp.getFolderById(folderId);

  // If a subdirectory name was supplied, navigate into it
  if (subdir) {
    const subfolders = folder.getFoldersByName(subdir);
    if (subfolders.hasNext()) {
      folder = subfolders.next();
    } else {
      return ContentService.createTextOutput(JSON.stringify({ error: 'Subdirectory not found: ' + subdir }))
        .setMimeType(ContentService.MimeType.JSON);
    }
  }

  const files    = folder.getFiles();
  const fileList = [];

  while (files.hasNext()) {
    const file = files.next();
    fileList.push({
      name: file.getName(),
      url:  file.getDownloadUrl(),
      size: Math.round(file.getSize() / 1024) + " KB",
      type: file.getMimeType()
    });
  }

  return ContentService.createTextOutput(JSON.stringify(fileList))
    .setMimeType(ContentService.MimeType.JSON);
}
