/**
 * board-list.gs
 * Two functions:
 *   generateBoardList() — writes a formatted BoardList tab in the sheet
 *   doGet()             — serves a responsive HTML page (deploy as Web App)
 *
 * To publish as a web page:
 *   Deploy > New deployment > Web App
 *   Execute as: Me
 *   Who has access: Anyone (or Anyone within your org)
 */

const ROLE_ORDER = ['President', 'Vice President', 'Treasurer', 'Secretary', 'Director'];

function generateBoardList() {
  const members = getBoardMembers();
  if (typeof members === 'string') {
    SpreadsheetApp.getUi().alert(members);
    return;
  }

  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const boardSheet = getOrCreateSheet(ss, 'BoardList');

  // --- Clear & format BoardList sheet ---
  boardSheet.clear();
  boardSheet.clearFormats();

  // Hide gridlines
  boardSheet.setHiddenGridlines(true);

  // Set a single-column layout (use column A for all text)
  boardSheet.setColumnWidth(1, 500);

  // --- Write content ---
  let row = 1;

  // Title
  boardSheet.getRange(row, 1).setValue('Board of Directors');
  boardSheet.getRange(row, 1).setFontSize(18).setFontWeight('bold');
  row += 2; // blank line after title

  for (const m of members) {
    // Line 1: Role  |  First Last
    const line1 = `${m.role}   ${m.first} ${m.last}`.trim();
    const cell1 = boardSheet.getRange(row, 1);
    cell1.setValue(line1);
    cell1.setFontWeight('bold').setFontSize(11);
    row++;

    // Line 2: indented contact info
    const parts = [];
    if (m.email) parts.push(m.email);
    if (m.phone) parts.push(m.phone);
    if (m.unit)  parts.push(`Unit ${m.unit}`);
    const line2 = '        ' + parts.join('   |   ');
    const cell2 = boardSheet.getRange(row, 1);
    cell2.setValue(line2);
    cell2.setFontSize(10).setFontColor('#444444');
    row++;

    // Blank line between people
    row++;
  }

  // Freeze nothing, no headers needed
  SpreadsheetApp.getUi().alert('BoardList updated successfully.');
}

// --- Helpers ---

function roleIndex(role) {
  const normalized = role.trim();
  const exact = ROLE_ORDER.indexOf(normalized);
  if (exact !== -1) return exact;
  // Any variant of "Director" sorts last
  if (normalized.toLowerCase().includes('director')) return ROLE_ORDER.length - 1;
  return ROLE_ORDER.length; // unknown roles go to the very end
}

function getOrCreateSheet(ss, name) {
  let sheet = ss.getSheetByName(name);
  if (!sheet) {
    sheet = ss.insertSheet(name);
  }
  return sheet;
}

// ---------------------------------------------------------------------------
// Web App entry point — deploy as Web App to get a public URL
// ---------------------------------------------------------------------------

function doGet() {
  const members = getBoardMembers();
  if (typeof members === 'string') {
    // Error message returned
    return HtmlService.createHtmlOutput(`<p style="color:red">${members}</p>`)
      .setTitle('Board of Directors');
  }

  const rows = members.map(m => {
    const contact = [];
    if (m.email) contact.push(`<a href="mailto:${m.email}">${m.email}</a>`);
    if (m.phone) contact.push(m.phone);
    if (m.unit)  contact.push(`Unit ${m.unit}`);
    return `
      <div class="person">
        <div class="line1"><span class="role">${m.role}</span> &nbsp; ${m.first} ${m.last}</div>
        <div class="line2">${contact.join('<span class="sep"> | </span>')}</div>
      </div>`;
  }).join('\n');

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Board of Directors</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Georgia, serif;
      background: #fff;
      color: #222;
      padding: 2rem 1.5rem;
      max-width: 680px;
      margin: 0 auto;
    }
    h1 {
      font-size: clamp(1.4rem, 5vw, 2rem);
      font-weight: bold;
      margin-bottom: 1.8rem;
      border-bottom: 2px solid #ccc;
      padding-bottom: 0.5rem;
    }
    .person {
      margin-bottom: 1.4rem;
    }
    .line1 {
      font-size: clamp(0.95rem, 3vw, 1.05rem);
      font-weight: bold;
    }
    .role {
      color: #555;
    }
    .line2 {
      font-size: clamp(0.82rem, 2.5vw, 0.92rem);
      color: #444;
      padding-left: 1.2rem;
      margin-top: 0.2rem;
    }
    .line2 a {
      color: #1a5276;
      text-decoration: none;
    }
    .line2 a:hover { text-decoration: underline; }
    .sep { color: #aaa; }
    @media (max-width: 400px) {
      body { padding: 1.2rem 1rem; }
    }
  </style>
</head>
<body>
  <h1>Board of Directors</h1>
  ${rows}
</body>
</html>`;

  return HtmlService.createHtmlOutput(html)
    .setTitle('Board of Directors')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

// Shared data-reading logic used by both generateBoardList() and doGet()
function getBoardMembers() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const dbSheet = ss.getSheetByName('Database');
  if (!dbSheet) {
    const available = ss.getSheets().map(s => s.getName()).join(', ');
    return `Error: No tab named "Database" found. Available tabs: ${available}`;
  }

  const data = dbSheet.getDataRange().getValues();
  const headers = data[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);

  const iBoard = col('Board');
  const iFirst = col('First Name');
  const iLast  = col('Last Name');
  const iEmail = col('eMail');
  const iPhone = col('Phone #1');
  const iUnit  = col('Unit #');

  const members = [];
  for (let r = 1; r < data.length; r++) {
    const role = data[r][iBoard] ? data[r][iBoard].toString().trim() : '';
    if (!role) continue;
    members.push({
      role,
      first: data[r][iFirst] ? data[r][iFirst].toString().trim() : '',
      last:  data[r][iLast]  ? data[r][iLast].toString().trim()  : '',
      email: data[r][iEmail] ? data[r][iEmail].toString().trim() : '',
      phone: data[r][iPhone] ? data[r][iPhone].toString().trim() : '',
      unit:  data[r][iUnit]  ? data[r][iUnit].toString().trim()  : '',
    });
  }

  members.sort((a, b) => roleIndex(a.role) - roleIndex(b.role));
  return members;
}
