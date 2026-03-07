/** @OnlyCurrentDoc */

// ---------------------------------------------------------------------------
// Web App entry point — deploy as Web App to get a public URL
// ---------------------------------------------------------------------------

function doGetResident() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Building name from file: "<Building Name> Owner DB"
  const fileName = ss.getName();
  if (!fileName.includes('Owner DB')) {
    return HtmlService.createHtmlOutput('<p style="color:red">Error: File must be named "&lt;Building Name&gt; Owner DB".</p>');
  }
  const buildingName = fileName.split('Owner DB')[0].trim();

  // Source sheet
  const sourceSheet = ss.getSheetByName('Database');
  if (!sourceSheet) {
    return HtmlService.createHtmlOutput('<p style="color:red">Error: No tab named "Database" found.</p>');
  }

  // Read headers
  const headers = sourceSheet.getRange(1, 1, 1, sourceSheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);

  const iUnit  = col('Unit #');
  const iLast  = col('Last Name');
  const iFirst = col('First Name');
  const iPhone = col('Phone #1');

  const missing = [['Unit #', iUnit], ['Last Name', iLast], ['First Name', iFirst], ['Phone #1', iPhone]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    return HtmlService.createHtmlOutput(`<p style="color:red">Error: Missing columns in Database: ${missing.join(', ')}</p>`);
  }

  // Read data
  const lastRow = sourceSheet.getLastRow();
  const rows = lastRow < 2 ? [] :
    sourceSheet.getRange(2, 1, lastRow - 1, sourceSheet.getLastColumn()).getValues()
      .filter(r => String(r[iUnit]).trim() || String(r[iLast]).trim())
      .map(r => ({
        unit:  String(r[iUnit]).trim(),
        last:  String(r[iLast]).trim(),
        first: String(r[iFirst]).trim(),
        phone: String(r[iPhone]).trim(),
      }));

  // Embed data as JSON for client-side sorting
  const jsonData = JSON.stringify(rows);

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resident List — ${buildingName}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Arial, sans-serif;
      background: #fff;
      color: #222;
      padding: 1.5rem 1rem;
      max-width: 800px;
      margin: 0 auto;
    }
    h1 {
      font-size: clamp(1.3rem, 5vw, 1.8rem);
      font-weight: bold;
      margin-bottom: 1rem;
    }
    .sort-bar {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.2rem;
      flex-wrap: wrap;
    }
    .sort-bar button {
      padding: 0.4rem 1rem;
      font-size: 0.9rem;
      border: 1px solid #aaa;
      border-radius: 4px;
      background: #f0f0f0;
      cursor: pointer;
    }
    .sort-bar button.active {
      background: #1a5276;
      color: #fff;
      border-color: #1a5276;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: clamp(0.8rem, 2.5vw, 0.95rem);
    }
    thead th {
      text-align: left;
      padding: 0.5rem 0.6rem;
      border-bottom: 2px solid #555;
      font-weight: bold;
      white-space: nowrap;
    }
    tbody td {
      padding: 0.4rem 0.6rem;
      border-bottom: 1px solid #e0e0e0;
    }
    tbody tr:last-child td { border-bottom: none; }
    @media (max-width: 480px) {
      body { padding: 1rem 0.6rem; }
    }
  </style>
</head>
<body>
  <h1>Resident List &nbsp; ${buildingName}</h1>
  <div class="sort-bar">
    <button id="btn-unit" class="active" onclick="sortBy('unit')">Sort by Unit #</button>
    <button id="btn-last" onclick="sortBy('last')">Sort by Last Name</button>
  </div>
  <table>
    <thead>
      <tr>
        <th>Unit #</th>
        <th>Last Name</th>
        <th>First Name</th>
        <th>Phone #1</th>
      </tr>
    </thead>
    <tbody id="table-body"></tbody>
  </table>

  <script>
    const data = ${jsonData};
    let currentSort = 'unit';

    function naturalCompare(a, b) {
      return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
    }

    function sortBy(key) {
      currentSort = key;
      document.getElementById('btn-unit').classList.toggle('active', key === 'unit');
      document.getElementById('btn-last').classList.toggle('active', key === 'last');

      const sorted = [...data].sort((a, b) =>
        key === 'last'
          ? naturalCompare(a.last, b.last) || naturalCompare(a.first, b.first) || naturalCompare(a.unit, b.unit)
          : naturalCompare(a.unit, b.unit) || naturalCompare(a.last, b.last)
      );

      const tbody = document.getElementById('table-body');
      tbody.innerHTML = sorted.map(r =>
        \`<tr>
          <td>\${r.unit}</td>
          <td>\${r.last}</td>
          <td>\${r.first}</td>
          <td>\${r.phone}</td>
        </tr>\`
      ).join('');
    }

    sortBy('unit'); // initial render
  </script>
</body>
</html>`;

  return HtmlService.createHtmlOutput(html)
    .setTitle('Resident List — ' + buildingName)
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

// ---------------------------------------------------------------------------
// Sheet tab generator — run manually to write a Resident List tab
// ---------------------------------------------------------------------------

function generateResidentList() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

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

  const headers = sourceSheet.getRange(1, 1, 1, sourceSheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);

  const iUnit  = col('Unit #');
  const iLast  = col('Last Name');
  const iFirst = col('First Name');
  const iPhone = col('Phone #1');

  const missing = [['Unit #', iUnit], ['Last Name', iLast], ['First Name', iFirst], ['Phone #1', iPhone]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    SpreadsheetApp.getUi().alert(`Error: Missing columns in Database: ${missing.join(', ')}`);
    return;
  }

  const lastRow = sourceSheet.getLastRow();
  if (lastRow < 2) {
    SpreadsheetApp.getUi().alert('No data found in Database.');
    return;
  }

  const rows = sourceSheet.getRange(2, 1, lastRow - 1, sourceSheet.getLastColumn()).getValues()
    .filter(r => String(r[iUnit]).trim() || String(r[iLast]).trim())
    .map(r => [
      String(r[iUnit]).trim(), String(r[iLast]).trim(),
      String(r[iFirst]).trim(), String(r[iPhone]).trim()
    ]);

  rows.sort((a, b) => a[0].localeCompare(b[0], undefined, { numeric: true })
                   || a[1].localeCompare(b[1], undefined, { numeric: true }));

  const targetSheet = ss.getSheetByName('Resident List') || ss.insertSheet('Resident List');
  targetSheet.clear();
  targetSheet.clearFormats();
  targetSheet.setHiddenGridlines(true);

  // Title
  const titleText = 'Resident List  ';
  const fullTitle = titleText + buildingName;
  const titleCell = targetSheet.getRange(1, 1, 1, 4);
  titleCell.merge()
    .setRichTextValue(
      SpreadsheetApp.newRichTextValue()
        .setText(fullTitle)
        .setTextStyle(0, fullTitle.length, SpreadsheetApp.newTextStyle().setFontSize(18).setBold(true).build())
        .build()
    )
    .setVerticalAlignment('middle');
  targetSheet.setRowHeight(1, 36);

  // Column headers
  targetSheet.getRange(2, 1, 1, 4)
    .setValues([['Unit #', 'Last Name', 'First Name', 'Phone #1']])
    .setFontWeight('bold').setFontSize(11)
    .setBorder(false, false, true, false, false, false);

  // Data
  if (rows.length > 0) {
    targetSheet.getRange(3, 1, rows.length, 4)
      .setValues(rows).setFontSize(11).setVerticalAlignment('top');
  }

  targetSheet.setColumnWidth(1, 80);
  targetSheet.setColumnWidth(2, 140);
  targetSheet.setColumnWidth(3, 130);
  targetSheet.setColumnWidth(4, 120);

  ss.toast('Resident List tab updated — sorted by Unit #', 'Success', 5);
}
