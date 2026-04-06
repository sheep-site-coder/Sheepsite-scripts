/** @OnlyCurrentDoc */

// ---------------------------------------------------------------------------
// database-admin.gs — CRUD endpoints for resident database management
//
// Part of the DatabaseSheetMaster library.
// Routed via building-script.gs:
//   GET  ?page=listDatabase&token=...
//   GET  ?page=getUnit&token=...&unit=1001
//   GET  ?page=getAllEmails&token=...
//   POST { action: 'addDatabaseRow',    token, ...fields }
//   POST { action: 'editDatabaseRow',   token, matchUnit, matchFirst, matchLast, ...fields }
//   POST { action: 'deleteDatabaseRow', token, matchUnit, matchFirst, matchLast }
//   POST { action: 'editCarRow',        token, 'Unit #', ...fields }
//   POST { action: 'addEmergencyRow',   token, ...fields }
//   POST { action: 'editEmergencyRow',  token, matchUnit, matchFirst, matchLast, ...fields }
//   POST { action: 'deleteEmergencyRow',token, matchUnit, matchFirst, matchLast }
//   POST { action: 'sendChangeRequest', token, unit, residentName, reqType, firstName, lastName, notes, buildingName, contactEmail }
// ---------------------------------------------------------------------------

// Database tab columns exposed via the API
const DB_COLS_ = [
  'Unit #', 'Full Time', 'Resident', 'Owner',
  'First Name', 'Last Name', 'eMail', 'Phone #1', 'Phone #2', 'Board'
];

// UnitDB tab columns — unit-level data (one row per unit)
const UNITDB_COLS_ = [
  'Unit #', 'Insurance', 'Policy #', 'AC Replaced', 'Water Tank'
];

const CAR_COLS_ = [
  'Unit #', 'Parking Spot', 'Car Make', 'Car Model', 'Car Color', 'Lic #', 'Notes'
];

const EM_COLS_ = [
  'Unit #', 'Condo Sitter', 'First Name', 'Last Name', 'eMail', 'Phone1', 'Phone2'
];

const EMERGENCY_TAB_ = 'Emergency & Condo Sitter';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function dbHeaders_(sheet) {
  return sheet.getRange(1, 1, 1, sheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
}

function col_(headers, name) {
  return headers.indexOf(name);
}

function jsonOut_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function jsonError_(msg) {
  return ContentService.createTextOutput(JSON.stringify({ error: msg }))
    .setMimeType(ContentService.MimeType.JSON);
}

function formatVal_(val) {
  if (val instanceof Date) {
    return Utilities.formatDate(val, Session.getScriptTimeZone(), 'yyyy-MM-dd');
  }
  return val === undefined || val === null ? '' : val;
}

function readSheet_(sheet, cols) {
  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return { headers: dbHeaders_(sheet), rows: [], values: [] };
  const headers = dbHeaders_(sheet);
  const values  = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const rows    = values.map((r, i) => {
    const obj = { _row: i + 2 };
    cols.forEach(col => {
      const idx = col_(headers, col);
      obj[col] = idx >= 0 ? formatVal_(r[idx]) : '';
    });
    return obj;
  });
  return { headers, rows, values };
}

// ---------------------------------------------------------------------------
// listDatabase — all Database rows (for unit list + copy-all-emails)
// ---------------------------------------------------------------------------
function doListDatabase(token, expectedToken) {
  if (token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) return jsonError_('No "Database" tab found');

  const { rows } = readSheet_(sheet, DB_COLS_);
  const filtered = rows.filter(r => String(r['Last Name']).trim() || String(r['Unit #']).trim());

  return jsonOut_({ rows: filtered });
}

// ---------------------------------------------------------------------------
// getUnit — all data for one unit across Database, CarDB, Emergency tabs
// ---------------------------------------------------------------------------
function doGetUnit(params, expectedToken) {
  if (params.token !== expectedToken) return jsonError_('Unauthorized');
  const unit = String(params.unit || '').trim();
  if (!unit) return jsonError_('Missing unit parameter');

  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Database rows for this unit
  const dbSheet = ss.getSheetByName('Database');
  let residents = [];
  if (dbSheet && dbSheet.getLastRow() >= 2) {
    const { rows } = readSheet_(dbSheet, DB_COLS_);
    residents = rows.filter(r => String(r['Unit #']).trim() === unit);
  }

  // CarDB row for this unit
  const carSheet = ss.getSheetByName('CarDB');
  let car = null;
  if (carSheet && carSheet.getLastRow() >= 2) {
    const { rows } = readSheet_(carSheet, CAR_COLS_);
    car = rows.find(r => String(r['Unit #']).trim() === unit) || null;
  }

  // UnitDB row for this unit
  const unitDbSheet = ss.getSheetByName('UnitDB');
  let unitInfo = null;
  if (unitDbSheet && unitDbSheet.getLastRow() >= 2) {
    const { rows } = readSheet_(unitDbSheet, UNITDB_COLS_);
    unitInfo = rows.find(r => String(r['Unit #']).trim() === unit) || null;
  }

  // Emergency rows for this unit
  const emSheet = ss.getSheetByName(EMERGENCY_TAB_);
  let emergency = [];
  if (emSheet && emSheet.getLastRow() >= 2) {
    const { rows } = readSheet_(emSheet, EM_COLS_);
    emergency = rows.filter(r => String(r['Unit #']).trim() === unit);
  }

  return jsonOut_({ unit, residents, car, unitInfo, emergency });
}

// ---------------------------------------------------------------------------
// getAllEmails — deduplicated emails from Database tab only
// ---------------------------------------------------------------------------
function doGetAllEmails(token, expectedToken) {
  if (token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet || sheet.getLastRow() < 2) return jsonOut_({ emails: [] });

  const headers = dbHeaders_(sheet);
  const iEmail  = col_(headers, 'eMail');
  if (iEmail < 0) return jsonError_('No "eMail" column in Database');

  const rows = sheet.getRange(2, 1, sheet.getLastRow() - 1, sheet.getLastColumn()).getValues();
  const seen = new Set();
  const emails = [];
  rows.forEach(r => {
    const raw = String(r[iEmail]).trim();
    const key = raw.toLowerCase();
    if (raw && raw.includes('@') && !seen.has(key)) {
      seen.add(key);
      emails.push(raw);
    }
  });

  return jsonOut_({ emails });
}

// ---------------------------------------------------------------------------
// importResidents — bulk insert rows into Database tab (idempotent)
//   POST { action: 'importResidents', token, rows: [{firstName, lastName, unit, email, phone}] }
//   Returns { ok: true, added, skipped }
// ---------------------------------------------------------------------------
function doImportResidents(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const rows = data.rows;
  if (!Array.isArray(rows) || !rows.length) return jsonError_('No rows provided');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) return jsonError_('No "Database" tab found');

  const headers  = dbHeaders_(sheet);
  const lastRow  = sheet.getLastRow();

  // Build a set of existing First+Last combos (case-insensitive) for duplicate detection
  const existing = new Set();
  if (lastRow >= 2) {
    const iFirst = col_(headers, 'First Name');
    const iLast  = col_(headers, 'Last Name');
    const vals   = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
    vals.forEach(r => {
      existing.add((String(r[iFirst]).trim() + '|' + String(r[iLast]).trim()).toLowerCase());
    });
  }

  let added = 0, skipped = 0;
  const numCols = sheet.getLastColumn();

  rows.forEach(row => {
    const first = String(row.firstName || '').trim();
    const last  = String(row.lastName  || '').trim();
    if (!first && !last) return;

    const key = (first + '|' + last).toLowerCase();
    if (existing.has(key)) { skipped++; return; }

    const newRow = new Array(numCols).fill('');
    const mapping = {
      'Unit #':    String(row.unit  || '').trim(),
      'First Name': first,
      'Last Name':  last,
      'eMail':      String(row.email || '').trim(),
      'Phone #1':   String(row.phone || '').trim(),
    };
    Object.entries(mapping).forEach(([colName, val]) => {
      const idx = col_(headers, colName);
      if (idx >= 0) newRow[idx] = val;
    });
    sheet.appendRow(newRow);
    existing.add(key); // prevent within-batch duplicates
    added++;
  });

  return jsonOut_({ ok: true, added, skipped });
}

// ---------------------------------------------------------------------------
// addDatabaseRow — append a new row to Database
// ---------------------------------------------------------------------------
function doAddDatabaseRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) return jsonError_('No "Database" tab found');

  const headers = dbHeaders_(sheet);
  const newRow  = new Array(sheet.getLastColumn()).fill('');
  DB_COLS_.forEach(col => {
    const idx = col_(headers, col);
    if (idx >= 0 && data[col] !== undefined) newRow[idx] = data[col];
  });
  sheet.appendRow(newRow);
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// editDatabaseRow — update row matched by Unit # + First Name + Last Name
// ---------------------------------------------------------------------------
function doEditDatabaseRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) return jsonError_('No "Database" tab found');

  const headers = dbHeaders_(sheet);
  const iUnit   = col_(headers, 'Unit #');
  const iFirst  = col_(headers, 'First Name');
  const iLast   = col_(headers, 'Last Name');
  if (iUnit < 0 || iFirst < 0 || iLast < 0) return jsonError_('Missing required columns');

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return jsonError_('No rows to edit');

  const values     = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const matchUnit  = String(data.matchUnit  || '').trim();
  const matchFirst = String(data.matchFirst || '').trim();
  const matchLast  = String(data.matchLast  || '').trim();

  const rowIdx = values.findIndex(r =>
    String(r[iUnit]).trim()  === matchUnit &&
    String(r[iFirst]).trim() === matchFirst &&
    String(r[iLast]).trim()  === matchLast
  );
  if (rowIdx < 0) return jsonError_('Row not found');

  const sheetRow = rowIdx + 2;
  DB_COLS_.forEach(col => {
    const idx = col_(headers, col);
    if (idx >= 0 && data[col] !== undefined) {
      sheet.getRange(sheetRow, idx + 1).setValue(data[col]);
    }
  });
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// deleteDatabaseRow — remove row matched by Unit # + First Name + Last Name
// ---------------------------------------------------------------------------
function doDeleteDatabaseRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) return jsonError_('No "Database" tab found');

  const headers = dbHeaders_(sheet);
  const iUnit   = col_(headers, 'Unit #');
  const iFirst  = col_(headers, 'First Name');
  const iLast   = col_(headers, 'Last Name');

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return jsonError_('No rows found');

  const values     = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const matchUnit  = String(data.matchUnit  || '').trim();
  const matchFirst = String(data.matchFirst || '').trim();
  const matchLast  = String(data.matchLast  || '').trim();

  const rowIdx = values.findIndex(r =>
    String(r[iUnit]).trim()  === matchUnit &&
    String(r[iFirst]).trim() === matchFirst &&
    String(r[iLast]).trim()  === matchLast
  );
  if (rowIdx < 0) return jsonError_('Row not found');

  sheet.deleteRow(rowIdx + 2);
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// editCarRow — update (or create) CarDB row for a unit
// ---------------------------------------------------------------------------
function doEditCarRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('CarDB');
  if (!sheet) return jsonError_('No "CarDB" tab found');

  const headers = dbHeaders_(sheet);
  const iUnit   = col_(headers, 'Unit #');
  if (iUnit < 0) return jsonError_('No "Unit #" column in CarDB');

  const unit    = String(data['Unit #'] || '').trim();
  if (!unit) return jsonError_('Missing Unit #');

  const lastRow = sheet.getLastRow();
  let rowIdx = -1;
  if (lastRow >= 2) {
    const values = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
    rowIdx = values.findIndex(r => String(r[iUnit]).trim() === unit);
  }

  if (rowIdx < 0) {
    // No existing row — append
    const newRow = new Array(sheet.getLastColumn()).fill('');
    CAR_COLS_.forEach(col => {
      const idx = col_(headers, col);
      if (idx >= 0 && data[col] !== undefined) newRow[idx] = data[col];
    });
    sheet.appendRow(newRow);
  } else {
    const sheetRow = rowIdx + 2;
    CAR_COLS_.forEach(col => {
      const idx = col_(headers, col);
      if (idx >= 0 && data[col] !== undefined) {
        sheet.getRange(sheetRow, idx + 1).setValue(data[col]);
      }
    });
  }
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// addEmergencyRow — append to Emergency & Condo Sitter tab
// ---------------------------------------------------------------------------
function doAddEmergencyRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName(EMERGENCY_TAB_);
  if (!sheet) return jsonError_('No "' + EMERGENCY_TAB_ + '" tab found');

  const headers = dbHeaders_(sheet);
  const newRow  = new Array(sheet.getLastColumn()).fill('');
  EM_COLS_.forEach(col => {
    const idx = col_(headers, col);
    if (idx >= 0 && data[col] !== undefined) newRow[idx] = data[col];
  });
  sheet.appendRow(newRow);
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// editEmergencyRow — update row matched by Unit # + First Name + Last Name
// ---------------------------------------------------------------------------
function doEditEmergencyRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName(EMERGENCY_TAB_);
  if (!sheet) return jsonError_('No "' + EMERGENCY_TAB_ + '" tab found');

  const headers    = dbHeaders_(sheet);
  const iUnit      = col_(headers, 'Unit #');
  const iFirst     = col_(headers, 'First Name');
  const iLast      = col_(headers, 'Last Name');
  const lastRow    = sheet.getLastRow();
  if (lastRow < 2) return jsonError_('No rows found');

  const values     = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const matchUnit  = String(data.matchUnit  || '').trim();
  const matchFirst = String(data.matchFirst || '').trim();
  const matchLast  = String(data.matchLast  || '').trim();

  const rowIdx = values.findIndex(r =>
    String(r[iUnit]).trim()  === matchUnit &&
    String(r[iFirst]).trim() === matchFirst &&
    String(r[iLast]).trim()  === matchLast
  );
  if (rowIdx < 0) return jsonError_('Row not found');

  const sheetRow = rowIdx + 2;
  EM_COLS_.forEach(col => {
    const idx = col_(headers, col);
    if (idx >= 0 && data[col] !== undefined) {
      sheet.getRange(sheetRow, idx + 1).setValue(data[col]);
    }
  });
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// deleteEmergencyRow — remove row matched by Unit # + First Name + Last Name
// ---------------------------------------------------------------------------
function doDeleteEmergencyRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName(EMERGENCY_TAB_);
  if (!sheet) return jsonError_('No "' + EMERGENCY_TAB_ + '" tab found');

  const headers    = dbHeaders_(sheet);
  const iUnit      = col_(headers, 'Unit #');
  const iFirst     = col_(headers, 'First Name');
  const iLast      = col_(headers, 'Last Name');
  const lastRow    = sheet.getLastRow();
  if (lastRow < 2) return jsonError_('No rows found');

  const values     = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  const matchUnit  = String(data.matchUnit  || '').trim();
  const matchFirst = String(data.matchFirst || '').trim();
  const matchLast  = String(data.matchLast  || '').trim();

  const rowIdx = values.findIndex(r =>
    String(r[iUnit]).trim()  === matchUnit &&
    String(r[iFirst]).trim() === matchFirst &&
    String(r[iLast]).trim()  === matchLast
  );
  if (rowIdx < 0) return jsonError_('Row not found');

  sheet.deleteRow(rowIdx + 2);
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// editUnitRow — upsert a row in the UnitDB tab for a given unit
// ---------------------------------------------------------------------------
function doEditUnitRow(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('UnitDB');
  if (!sheet) return jsonError_('No "UnitDB" tab found');

  const headers = dbHeaders_(sheet);
  const iUnit   = col_(headers, 'Unit #');
  if (iUnit < 0) return jsonError_('No "Unit #" column in UnitDB');

  const unit = String(data['Unit #'] || '').trim();
  if (!unit) return jsonError_('Missing Unit #');

  const lastRow = sheet.getLastRow();
  let rowIdx = -1;
  if (lastRow >= 2) {
    const values = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
    rowIdx = values.findIndex(r => String(r[iUnit]).trim() === unit);
  }

  if (rowIdx < 0) {
    const newRow = new Array(sheet.getLastColumn()).fill('');
    UNITDB_COLS_.forEach(col => {
      const idx = col_(headers, col);
      if (idx >= 0 && data[col] !== undefined) newRow[idx] = data[col];
    });
    sheet.appendRow(newRow);
  } else {
    const sheetRow = rowIdx + 2;
    UNITDB_COLS_.forEach(col => {
      const idx = col_(headers, col);
      if (idx >= 0 && data[col] !== undefined) {
        sheet.getRange(sheetRow, idx + 1).setValue(data[col]);
      }
    });
  }
  return jsonOut_({ ok: true });
}

// ---------------------------------------------------------------------------
// sendChangeRequest — email a resident change request
// Recipient: contactEmail if provided, else President from Database tab
// ---------------------------------------------------------------------------
function doSendChangeRequest(data, expectedToken) {
  if (data.token !== expectedToken) return jsonError_('Unauthorized');

  const unit          = String(data.unit          || '').trim();
  const residentName  = String(data.residentName  || '').trim();
  const reqType       = String(data.reqType       || '').trim();
  const firstName     = String(data.firstName     || '').trim();
  const lastName      = String(data.lastName      || '').trim();
  const email         = String(data.email         || '').trim();
  const phone1        = String(data.phone1        || '').trim();
  const phone2        = String(data.phone2        || '').trim();
  const fullTime      = data.fullTime ? 'Yes' : 'No';
  const resident      = data.resident ? 'Yes' : 'No';
  const owner         = data.owner    ? 'Yes' : 'No';
  const notes         = String(data.notes         || '').trim();
  const buildingName  = String(data.buildingName  || '').trim();
  const adminUrl      = String(data.adminUrl      || '').trim();
  let   toEmail       = String(data.contactEmail  || '').trim();

  if (!toEmail) {
    // Fall back to President email from Database tab
    const ss    = SpreadsheetApp.getActiveSpreadsheet();
    const sheet = ss.getSheetByName('Database');
    if (sheet && sheet.getLastRow() >= 2) {
      const headers = dbHeaders_(sheet);
      const iBoard  = col_(headers, 'Board');
      const iEmail  = col_(headers, 'eMail');
      if (iBoard >= 0 && iEmail >= 0) {
        const rows = sheet.getRange(2, 1, sheet.getLastRow() - 1, sheet.getLastColumn()).getValues();
        for (const r of rows) {
          if (String(r[iBoard]).trim().toLowerCase() === 'president') {
            toEmail = String(r[iEmail]).trim();
            break;
          }
        }
      }
    }
  }

  if (!toEmail) {
    return jsonError_('No contact email found. Set a Building Contact Email in admin → Building Settings.');
  }

  const subject = `[${buildingName}] Resident change request from Unit ${unit}`;
  const lines   = [
    'A resident change request was submitted via the building website.',
    '',
    `Building:     ${buildingName}`,
    `Unit:         ${unit}`,
    `Submitted by: ${residentName}`,
    '',
    `Request type: ${reqType}`,
    `Name:         ${firstName} ${lastName}`,
  ];
  if (email)  lines.push(`Email:        ${email}`);
  if (phone1) lines.push(`Phone #1:     ${phone1}`);
  if (phone2) lines.push(`Phone #2:     ${phone2}`);
  lines.push(`Full Time:    ${fullTime}`, `Resident:     ${resident}`, `Owner:        ${owner}`);
  if (notes) lines.push(`Notes:        ${notes}`);
  lines.push('', `Please log in to the admin panel and open Manage Residents/Owners → Unit ${unit} to make the change.`);
  if (adminUrl) lines.push('', adminUrl);

  MailApp.sendEmail({ to: toEmail, subject, body: lines.join('\n'), name: 'SheepSite.com' });
  return jsonOut_({ ok: true });
}
