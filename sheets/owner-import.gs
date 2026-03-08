/** @OnlyCurrentDoc */

// ---------------------------------------------------------------------------
// doGetOwners — returns owner list from Database tab as JSON
// Called by building-script.gs doGet() with ?page=owners&token=...
// Token is defined as OWNER_IMPORT_TOKEN in building-script.gs and
// must match OWNER_IMPORT_TOKEN in manage-users.php.
// ---------------------------------------------------------------------------

function doGetOwners(token, expectedToken) {
  if (!token || token !== expectedToken) {
    return ContentService.createTextOutput(JSON.stringify({ error: 'Unauthorized' }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) {
    return ContentService.createTextOutput(JSON.stringify({ error: 'No tab named "Database" found' }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) {
    return ContentService.createTextOutput(JSON.stringify({ owners: [] }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);

  const iFirst = col('First Name');
  const iLast  = col('Last Name');
  const iEmail = col('eMail');

  const missing = [['First Name', iFirst], ['Last Name', iLast], ['eMail', iEmail]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    return ContentService.createTextOutput(JSON.stringify({ error: 'Missing columns in Database: ' + missing.join(', ') }))
      .setMimeType(ContentService.MimeType.JSON);
  }

  const rows   = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const owners = rows
    .filter(r => String(r[iLast]).trim())
    .map(r => ({
      firstName: String(r[iFirst]).trim(),
      lastName:  String(r[iLast]).trim(),
      email:     String(r[iEmail]).trim(),
    }));

  return ContentService.createTextOutput(JSON.stringify({ owners }))
    .setMimeType(ContentService.MimeType.JSON);
}
