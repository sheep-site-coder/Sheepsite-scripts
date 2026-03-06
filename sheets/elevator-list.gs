/** @OnlyCurrentDoc */

function generateElevatorList(e) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // 1. DYNAMIC SOURCE IDENTIFICATION
  // File must be named "<Building Name> Owner DB"
  // First tab must be named "Database"
  const sourceSheet = ss.getSheetByName('Database');
  if (!sourceSheet) {
    SpreadsheetApp.getUi().alert('Error: No tab named "Database" found.');
    return;
  }
  const fileName = ss.getName();
  if (!fileName.includes('Owner DB')) {
    SpreadsheetApp.getUi().alert('Error: File must be named "<Building Name> Owner DB".');
    return;
  }
  const buildingName = fileName.split('Owner DB')[0].trim();
  const targetSheet = ss.getSheetByName("Elevator List");

  // 2. TRIGGER SAFEGUARD
  // Only runs if you edit the building's data tab
  if (e && e.source.getActiveSheet().getName() !== 'Database') {
    return;
  }

  // 3. 10 SECOND DELAY & CONCURRENCY LOCK
  Utilities.sleep(10000);
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
  } catch (err) {
    return;
  }

  // Define the title based on the building name
  const titleText = buildingName + " Residents ";

  // 4. DATA PROCESSING
  const lastRow = sourceSheet.getLastRow();
  if (lastRow < 2) {
    lock.releaseLock();
    return;
  }

  const headers = sourceSheet.getRange(1, 1, 1, sourceSheet.getLastColumn()).getValues()[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);
  const iUnit  = col('Unit #');
  const iFirst = col('First Name');
  const iLast  = col('Last Name');

  const missing = [['Unit #', iUnit], ['First Name', iFirst], ['Last Name', iLast]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    lock.releaseLock();
    SpreadsheetApp.getUi().alert(`Error: Missing columns in Database: ${missing.join(', ')}`);
    return;
  }

  const rawData = sourceSheet.getRange(2, 1, lastRow - 1, sourceSheet.getLastColumn()).getValues();

  const groups = {};
  for (let i = 0; i < rawData.length; i++) {
    const unit = String(rawData[i][iUnit]).trim();
    const first = String(rawData[i][iFirst]).trim();
    const last = String(rawData[i][iLast]).trim();

    if (!unit || !last) continue;

    // Grouping key: Unique to Unit + Last Name
    const key = unit + "_" + last;
    if (!groups[key]) {
      groups[key] = { unit: unit, last: last, firstNames: [first] };
    } else {
      if (!groups[key].firstNames.includes(first)) {
        groups[key].firstNames.push(first);
      }
    }
  }

  // Map and Sort Alphabetically
  const list = Object.values(groups).map(item => {
    return { unit: item.unit, last: item.last, combinedFirst: item.firstNames.join(" & ") };
  });

  list.sort((a, b) => a.last.localeCompare(b.last) || a.unit.localeCompare(b.unit));

  // Prepare Dual Stack Output
  const half = Math.ceil(list.length / 2);
  const output = [];
  for (let i = 0; i < half; i++) {
    const left = list[i];
    const right = list[i + half];
    output.push([
      left.last, left.combinedFirst, left.unit, "",
      right ? right.last : "", right ? right.combinedFirst : "", right ? right.unit : ""
    ]);
  }

  // 5. REWRITE THE ELEVATOR LIST
  targetSheet.clear();
  targetSheet.setHiddenGridlines(true);

  const dateText = "(" + Utilities.formatDate(new Date(), ss.getSpreadsheetTimeZone(), "MMMM d, yyyy") + ")";
  const fullText = titleText + dateText;

  // Title Formatting (Bold Title, Small Date)
  const titleCell = targetSheet.getRange("A1:G1");
  const richText = SpreadsheetApp.newRichTextValue()
    .setText(fullText)
    .setTextStyle(0, titleText.length, SpreadsheetApp.newTextStyle().setFontSize(22).setBold(true).build())
    .setTextStyle(titleText.length, fullText.length, SpreadsheetApp.newTextStyle().setFontSize(11).setBold(false).build())
    .build();

  titleCell.merge().setRichTextValue(richText)
           .setHorizontalAlignment("center").setVerticalAlignment("middle");

  // Data Formatting (Top Aligned, Wrapped)
  if (output.length > 0) {
    const dataRange = targetSheet.getRange(3, 1, output.length, 7);
    dataRange.setValues(output)
             .setFontSize(12)
             .setVerticalAlignment("top")
             .setWrap(true);

    // Column Widths
    const nameWidth = 150;
    const unitWidth = 60;
    const gutterWidth = 75;

    [1, 5].forEach(col => targetSheet.setColumnWidth(col, nameWidth));
    [2, 6].forEach(col => targetSheet.setColumnWidth(col, nameWidth));
    [3, 7].forEach(col => targetSheet.setColumnWidth(col, unitWidth));
    targetSheet.setColumnWidth(4, gutterWidth);
  }

  lock.releaseLock();
  ss.toast("List updated for " + buildingName, "Success", 5);
}

// ---------------------------------------------------------------------------
// Web App entry point — called via doGet(e) with ?page=elevator
// ---------------------------------------------------------------------------

function doGetElevator() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  const sourceSheet = ss.getSheetByName('Database');
  if (!sourceSheet) {
    return HtmlService.createHtmlOutput('<p style="color:red">Error: No tab named "Database" found.</p>');
  }

  const fileName = ss.getName();
  if (!fileName.includes('Owner DB')) {
    return HtmlService.createHtmlOutput('<p style="color:red">Error: File must be named "&lt;Building Name&gt; Owner DB".</p>');
  }
  const buildingName = fileName.split('Owner DB')[0].trim();

  const lastRow = sourceSheet.getLastRow();
  if (lastRow < 2) {
    return HtmlService.createHtmlOutput('<p>No data found in Database.</p>');
  }

  const headers = sourceSheet.getRange(1, 1, 1, sourceSheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);
  const iUnit  = col('Unit #');
  const iFirst = col('First Name');
  const iLast  = col('Last Name');

  const missing = [['Unit #', iUnit], ['First Name', iFirst], ['Last Name', iLast]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    return HtmlService.createHtmlOutput(`<p style="color:red">Error: Missing columns in Database: ${missing.join(', ')}</p>`);
  }

  const rawData = sourceSheet.getRange(2, 1, lastRow - 1, sourceSheet.getLastColumn()).getValues();

  const groups = {};
  for (let i = 0; i < rawData.length; i++) {
    const unit = String(rawData[i][iUnit]).trim();
    const first = String(rawData[i][iFirst]).trim();
    const last = String(rawData[i][iLast]).trim();
    if (!unit || !last) continue;
    const key = unit + '_' + last;
    if (!groups[key]) {
      groups[key] = { unit, last, firstNames: [first] };
    } else if (!groups[key].firstNames.includes(first)) {
      groups[key].firstNames.push(first);
    }
  }

  const list = Object.values(groups)
    .map(item => ({ unit: item.unit, last: item.last, name: item.firstNames.join(' & ') + ' ' + item.last }))
    .sort((a, b) => a.last.localeCompare(b.last) || a.unit.localeCompare(b.unit, undefined, { numeric: true }));

  // Two-column layout matching the sheet version
  const half = Math.ceil(list.length / 2);
  const rows = [];
  for (let i = 0; i < half; i++) {
    const left  = list[i];
    const right = list[i + half];
    rows.push({ left, right });
  }

  const rowsHtml = rows.map(({ left, right }) => `
    <tr>
      <td class="last">${left.last}</td>
      <td class="first">${left.unit === right?.unit ? left.name.split(' ').slice(0,-1).join(' ') : left.name.split(' ').slice(0,-1).join(' ')}</td>
      <td class="unit">${left.unit}</td>
      <td class="gutter"></td>
      <td class="last">${right ? right.last : ''}</td>
      <td class="first">${right ? right.name.split(' ').slice(0,-1).join(' ') : ''}</td>
      <td class="unit">${right ? right.unit : ''}</td>
    </tr>`).join('');

  const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${buildingName} Residents</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, sans-serif;
      background: #fff;
      color: #222;
      padding: 1.5rem 1rem;
      max-width: 900px;
      margin: 0 auto;
    }
    h1 {
      font-size: clamp(1.3rem, 5vw, 1.6rem);
      font-weight: bold;
    }
    .date {
      font-size: 0.85rem;
      color: #666;
      margin-bottom: 1.2rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: clamp(0.8rem, 2.5vw, 0.95rem);
    }
    td { padding: 0.3rem 0.4rem; vertical-align: top; }
    td.last  { font-weight: bold; width: 20%; }
    td.first { width: 22%; }
    td.unit  { color: #555; width: 8%; }
    td.gutter { width: 8%; }
    @media (max-width: 480px) {
      body { padding: 1rem 0.6rem; }
    }
  </style>
</head>
<body>
  <h1>${buildingName} Residents</h1>
  <div class="date">(${today})</div>
  <table><tbody>
    ${rowsHtml}
  </tbody></table>
</body>
</html>`;

  return HtmlService.createHtmlOutput(html)
    .setTitle(`${buildingName} Residents`)
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}
