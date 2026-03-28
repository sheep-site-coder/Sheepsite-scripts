/** @OnlyCurrentDoc */

// ---------------------------------------------------------------------------
// NOTE: All building reports are available live on the building website.
// There is no need to generate or maintain any list tabs in this spreadsheet.
//
// Reports available on the site:
//   • Board of Directors  — public, no login required
//   • Elevator List       — login required; includes a Print button
//                           (scales to fit one letter page, no browser headers)
//   • Parking List        — login required
//   • Resident List       — login required; sortable by Unit # or Last Name
//
// All reports read live from the Database and CarDB tabs. Any edit to
// those tabs is reflected immediately the next time a report is opened on
// the site, and also automatically updates the list tabs in this spreadsheet
// within about 1 minute (30-second debounce + up to 1-minute check interval).
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
// Web App entry point — only doGet() is triggered by a URL in Apps Script.
// Use ?page= to route to the correct page:
//   .../exec               → Board of Directors
//   .../exec?page=parking  → Parking List
//   .../exec?page=elevator → Elevator List
//   .../exec?page=resident → Resident List
//   .../exec?page=owners&token=... → Owner list JSON (for manage-users.php import)
// ---------------------------------------------------------------------------

// Shared secret — same value used in all buildings and in manage-users.php / forgot-password.php.
// Prevents unauthorized calls to the owners import and password reset endpoints.
// This is NOT building-specific; one universal token covers all buildings.
const OWNER_IMPORT_TOKEN = 'QRF*!v2r2KgJEesq&P';

// ---------------------------------------------------------------------------
// Auto-update list tabs
//
// Install TWO triggers in Apps Script → Triggers:
//   1. onEditHandler      — From spreadsheet → On edit
//   2. runScheduledUpdate — Time-driven → Minutes timer → Every minute
//
// When Database or CarDB is edited, a 30-second debounce window starts.
// runScheduledUpdate checks every minute; once 30+ seconds have passed
// since the last edit, all four list tabs are regenerated automatically.
//
// Also delete the old trigger pointing to generateElevatorList (if any).
// ---------------------------------------------------------------------------

const DEBOUNCE_MS = 30 * 1000; // 30 seconds of inactivity before updating

function onEditHandler(e) {
  const sheet = e && e.range && e.range.getSheet();
  if (!sheet) return;
  const name = sheet.getName();
  if (name !== 'Database' && name !== 'CarDB') return;
  PropertiesService.getScriptProperties()
    .setProperty('pendingUpdateMs', Date.now().toString());
}

function runScheduledUpdate() {
  const props = PropertiesService.getScriptProperties();
  const pending = props.getProperty('pendingUpdateMs');
  if (!pending) return;
  if (Date.now() - parseInt(pending) < DEBOUNCE_MS) return; // still within debounce window

  props.deleteProperty('pendingUpdateMs');

  try { DatabaseSheetMaster.generateElevatorList(null); } catch(err) { console.error('Elevator list:', err); }
  try { DatabaseSheetMaster.generateParkingList();      } catch(err) { console.error('Parking list:', err); }
  try { DatabaseSheetMaster.generateResidentList();     } catch(err) { console.error('Resident list:', err); }
  try { DatabaseSheetMaster.generateBoardList();        } catch(err) { console.error('Board list:', err); }
}

// ---------------------------------------------------------------------------

function doGet(e) {
  const page  = e && e.parameter && e.parameter.page  ? e.parameter.page  : 'board';
  const token = e && e.parameter && e.parameter.token ? e.parameter.token : '';
  switch (page) {
    case 'parking':      return DatabaseSheetMaster.doGetParking();
    case 'elevator':     return DatabaseSheetMaster.doGetElevator();
    case 'resident':     return DatabaseSheetMaster.doGetResident();
    case 'owners':       return DatabaseSheetMaster.doGetOwners(token, OWNER_IMPORT_TOKEN);
    case 'resetpw':      return DatabaseSheetMaster.doResetPassword(e.parameter, OWNER_IMPORT_TOKEN);
    case 'listDatabase': return DatabaseSheetMaster.doListDatabase(token, OWNER_IMPORT_TOKEN);
    case 'getUnit':      return DatabaseSheetMaster.doGetUnit(e.parameter, OWNER_IMPORT_TOKEN);
    case 'getAllEmails':  return DatabaseSheetMaster.doGetAllEmails(token, OWNER_IMPORT_TOKEN);
    default:             return DatabaseSheetMaster.doGet();
  }
}

function doPost(e) {
  let data = {};
  try {
    data = JSON.parse(e.postData.contents);
  } catch (_) {
    return ContentService.createTextOutput(JSON.stringify({ error: 'Invalid JSON body' }))
      .setMimeType(ContentService.MimeType.JSON);
  }
  const action = data.action || '';
  switch (action) {
    case 'addDatabaseRow':    return DatabaseSheetMaster.doAddDatabaseRow(data,    OWNER_IMPORT_TOKEN);
    case 'editDatabaseRow':   return DatabaseSheetMaster.doEditDatabaseRow(data,   OWNER_IMPORT_TOKEN);
    case 'deleteDatabaseRow': return DatabaseSheetMaster.doDeleteDatabaseRow(data, OWNER_IMPORT_TOKEN);
    case 'editCarRow':        return DatabaseSheetMaster.doEditCarRow(data,        OWNER_IMPORT_TOKEN);
    case 'addEmergencyRow':   return DatabaseSheetMaster.doAddEmergencyRow(data,   OWNER_IMPORT_TOKEN);
    case 'editEmergencyRow':  return DatabaseSheetMaster.doEditEmergencyRow(data,  OWNER_IMPORT_TOKEN);
    case 'deleteEmergencyRow':return DatabaseSheetMaster.doDeleteEmergencyRow(data,OWNER_IMPORT_TOKEN);
    case 'sendChangeRequest': return DatabaseSheetMaster.doSendChangeRequest(data, OWNER_IMPORT_TOKEN);
    default:
      return ContentService.createTextOutput(JSON.stringify({ error: 'Unknown action: ' + action }))
        .setMimeType(ContentService.MimeType.JSON);
  }
}
