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
// All reports read live from the Database and CarDB tabs — any edit to
// those tabs is reflected immediately the next time the report is opened.
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

// Must match OWNER_IMPORT_TOKEN in manage-users.php and forgot-password.php
const OWNER_IMPORT_TOKEN = 'QRF*!v2r2KgJEesq&P';

function doGet(e) {
  const page  = e && e.parameter && e.parameter.page  ? e.parameter.page  : 'board';
  const token = e && e.parameter && e.parameter.token ? e.parameter.token : '';
  switch (page) {
    case 'parking':  return DatabaseSheetMaster.doGetParking();
    case 'elevator': return DatabaseSheetMaster.doGetElevator();
    case 'resident': return DatabaseSheetMaster.doGetResident();
    case 'owners':   return DatabaseSheetMaster.doGetOwners(token, OWNER_IMPORT_TOKEN);
    case 'resetpw':  return DatabaseSheetMaster.doResetPassword(e.parameter, OWNER_IMPORT_TOKEN);
    default:         return DatabaseSheetMaster.doGet();
  }
}
