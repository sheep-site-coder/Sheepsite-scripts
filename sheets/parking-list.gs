/** @OnlyCurrentDoc */

// ---------------------------------------------------------------------------
// Web App entry point — deploy as Web App to get a public URL
// ---------------------------------------------------------------------------

function doGetParking() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  // Building name from file: "<Building Name> Owner DB"
  const fileName = ss.getName();
  if (!fileName.includes('Owner DB')) {
    return HtmlService.createHtmlOutput('<p style="color:red">Error: File must be named "&lt;Building Name&gt; Owner DB".</p>');
  }
  const buildingName = fileName.split('Owner DB')[0].trim();

  // Source sheet
  const sourceSheet = ss.getSheetByName('CarDB');
  if (!sourceSheet) {
    return HtmlService.createHtmlOutput('<p style="color:red">Error: No tab named "CarDB" found.</p>');
  }

  // Read headers
  const headers = sourceSheet.getRange(1, 1, 1, sourceSheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
  const col = name => headers.indexOf(name);

  const iUnit  = col('Unit #');
  const iSpot  = col('Parking Spot');
  const iMake  = col('Car Make');
  const iModel = col('Car Model');
  const iColor = col('Car Color');

  const missing = [['Unit #', iUnit], ['Parking Spot', iSpot], ['Car Make', iMake], ['Car Model', iModel], ['Car Color', iColor]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    return HtmlService.createHtmlOutput(`<p style="color:red">Error: Missing columns in CarDB: ${missing.join(', ')}</p>`);
  }

  // Read data
  const lastRow = sourceSheet.getLastRow();
  const rows = lastRow < 2 ? [] :
    sourceSheet.getRange(2, 1, lastRow - 1, sourceSheet.getLastColumn()).getValues()
      .filter(r => String(r[iUnit]).trim() || String(r[iSpot]).trim())
      .map(r => ({
        unit:  String(r[iUnit]).trim(),
        spot:  String(r[iSpot]).trim(),
        make:  String(r[iMake]).trim(),
        model: String(r[iModel]).trim(),
        color: String(r[iColor]).trim(),
      }));

  // Embed data as JSON for client-side sorting
  const jsonData = JSON.stringify(rows);

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Parking List — ${buildingName}</title>
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
  <h1>Parking List &nbsp; ${buildingName}</h1>
  <div class="sort-bar">
    <button id="btn-unit" class="active" onclick="sortBy('unit')">Sort by Unit #</button>
    <button id="btn-spot" onclick="sortBy('spot')">Sort by Parking Spot</button>
  </div>
  <table>
    <thead>
      <tr>
        <th>Unit #</th>
        <th>Parking Spot</th>
        <th>Car Make</th>
        <th>Car Model</th>
        <th>Car Color</th>
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
      document.getElementById('btn-spot').classList.toggle('active', key === 'spot');

      const sorted = [...data].sort((a, b) =>
        key === 'spot'
          ? naturalCompare(a.spot, b.spot) || naturalCompare(a.unit, b.unit)
          : naturalCompare(a.unit, b.unit) || naturalCompare(a.spot, b.spot)
      );

      const tbody = document.getElementById('table-body');
      tbody.innerHTML = sorted.map(r =>
        \`<tr>
          <td>\${r.unit}</td>
          <td>\${r.spot}</td>
          <td>\${r.make}</td>
          <td>\${r.model}</td>
          <td>\${r.color}</td>
        </tr>\`
      ).join('');
    }

    sortBy('unit'); // initial render
  </script>
</body>
</html>`;

  return HtmlService.createHtmlOutput(html)
    .setTitle('Parking List — ' + buildingName)
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

// ---------------------------------------------------------------------------
// Sheet tab generator — run manually to write a Parking List tab
// ---------------------------------------------------------------------------

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu('Parking List')
    .addItem('Generate sheet tab', 'generateParkingList')
    .addToUi();
}

function generateParkingList() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();

  const sourceSheet = ss.getSheetByName('CarDB');
  if (!sourceSheet) {
    SpreadsheetApp.getUi().alert('Error: No tab named "CarDB" found.');
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
  const iSpot  = col('Parking Spot');
  const iMake  = col('Car Make');
  const iModel = col('Car Model');
  const iColor = col('Car Color');

  const missing = [['Unit #', iUnit], ['Parking Spot', iSpot], ['Car Make', iMake], ['Car Model', iModel], ['Car Color', iColor]]
    .filter(([, i]) => i === -1).map(([name]) => name);
  if (missing.length) {
    SpreadsheetApp.getUi().alert(`Error: Missing columns in CarDB: ${missing.join(', ')}`);
    return;
  }

  const lastRow = sourceSheet.getLastRow();
  if (lastRow < 2) {
    SpreadsheetApp.getUi().alert('No data found in CarDB.');
    return;
  }

  const rows = sourceSheet.getRange(2, 1, lastRow - 1, sourceSheet.getLastColumn()).getValues()
    .filter(r => String(r[iUnit]).trim() || String(r[iSpot]).trim())
    .map(r => [
      String(r[iUnit]).trim(), String(r[iSpot]).trim(),
      String(r[iMake]).trim(), String(r[iModel]).trim(), String(r[iColor]).trim()
    ]);

  rows.sort((a, b) => a[0].localeCompare(b[0], undefined, { numeric: true })
                   || a[1].localeCompare(b[1], undefined, { numeric: true }));

  const targetSheet = ss.getSheetByName('Parking List') || ss.insertSheet('Parking List');
  targetSheet.clear();
  targetSheet.clearFormats();
  targetSheet.setHiddenGridlines(true);

  // Title
  const titleText = 'Parking List  ';
  const fullTitle = titleText + buildingName;
  const titleCell = targetSheet.getRange(1, 1, 1, 5);
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
  targetSheet.getRange(2, 1, 1, 5)
    .setValues([['Unit #', 'Parking Spot', 'Car Make', 'Car Model', 'Car Color']])
    .setFontWeight('bold').setFontSize(11)
    .setBorder(false, false, true, false, false, false);

  // Data
  if (rows.length > 0) {
    targetSheet.getRange(3, 1, rows.length, 5)
      .setValues(rows).setFontSize(11).setVerticalAlignment('top');
  }

  targetSheet.setColumnWidth(1, 80);
  targetSheet.setColumnWidth(2, 110);
  targetSheet.setColumnWidth(3, 110);
  targetSheet.setColumnWidth(4, 120);
  targetSheet.setColumnWidth(5, 100);

  ss.toast('Parking List tab updated — sorted by Unit #', 'Success', 5);
}
