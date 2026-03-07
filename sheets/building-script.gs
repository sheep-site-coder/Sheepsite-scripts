/** @OnlyCurrentDoc */

// Generate a new Menu on the tool bar with options
function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('SheepSite')
    .addItem('Elevator List Update', 'generateElevatorList')
    .addItem('Board List Update', 'generateBoardList')
    .addItem('Parking List Update', 'generateParkingList')
    .addItem('Resident List Update', 'generateResidentList')
    .addToUi();
}

// ---------------------------------------------------------------------------
// Menu action wrappers — addItem() requires simple local function names
// ---------------------------------------------------------------------------

function generateElevatorList() {
  DatabaseSheetMaster.generateElevatorList();
}

function generateBoardList() {
  DatabaseSheetMaster.generateBoardList();
}

function generateParkingList() {
  DatabaseSheetMaster.generateParkingList();
}

function generateResidentList() {
  DatabaseSheetMaster.generateResidentList();
}

// ---------------------------------------------------------------------------
// Web App entry point — only doGet() is triggered by a URL in Apps Script.
// Use ?page= to route to the correct page:
//   .../exec              → Board of Directors
//   .../exec?page=parking → Parking List
//   .../exec?page=elevator → Elevator List
//   .../exec?page=resident → Resident List
// ---------------------------------------------------------------------------

function doGet(e) {
  const page = e && e.parameter && e.parameter.page ? e.parameter.page : 'board';
  switch (page) {
    case 'parking':  return DatabaseSheetMaster.doGetParking();
    case 'elevator': return DatabaseSheetMaster.doGetElevator();
    case 'resident': return DatabaseSheetMaster.doGetResident();
    default:         return DatabaseSheetMaster.doGet();
  }
}
