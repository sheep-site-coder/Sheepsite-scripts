// -------------------------------------------------------
// footer-for-sites.js
//
// Documents the script and button HTML for building sites.
//
// In Namecheap Website Builder:
//   Settings (top right) → Pages → Default →
//   paste into "After <body>" on the right
//
// Change BUILDING_NAME to match the building — that is the
// ONLY line that changes from site to site.
// -------------------------------------------------------


// ===================================================
// PASTE THIS INTO THE FOOTER OF EACH BUILDING SITE
// ===================================================

/*

<script>
const BUILDING_NAME = 'QGscratch';

document.addEventListener('DOMContentLoaded', function () {
  const PUBLIC_URL  = 'https://sheepsite.com/Scripts/display-public-dir.php';
  const PRIVATE_URL = 'https://sheepsite.com/Scripts/display-private-dir.php';

  // Public Google Drive buttons
  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = PUBLIC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    url += '&return=' + encodeURIComponent(window.location.href);
    btn.href = url;
  });

  // Private directory buttons
  document.querySelectorAll('.local-link').forEach(function (btn) {
    var url = PRIVATE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var path = btn.getAttribute('data-path');
    if (path) url += '&path=' + encodeURIComponent(path);
    url += '&return=' + encodeURIComponent(window.location.href);
    btn.href = url;
  });

  // Protected report iframes (protected-report.php)
  const REPORT_URL = 'https://sheepsite.com/Scripts/protected-report.php';
  document.querySelectorAll('iframe[data-script="protected-report"]').forEach(function (iframe) {
    var url = REPORT_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var page = iframe.getAttribute('data-page');
    if (page) url += '&page=' + encodeURIComponent(page);
    url += '&return=' + encodeURIComponent(window.location.href);
    iframe.onload = function () {
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });

  // Document iframes (get-doc-byname)
  const DOC_URL = 'https://sheepsite.com/Scripts/get-doc-byname.php';
  document.querySelectorAll('iframe[data-script="get-doc-byname"]').forEach(function (iframe) {
    var url = DOC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = iframe.getAttribute('data-subdir');
    var filename = iframe.getAttribute('data-filename');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    if (filename) url += '&filename=' + encodeURIComponent(filename);
    iframe.onload = function () {
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });
});

// Public folder buttons — call openFolder() or openFolder('SubfolderName') from button onclick
function openFolder(subdir) {
  var url = 'https://sheepsite.com/Scripts/display-public-dir.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return=' + encodeURIComponent(window.location.href);
  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
  window.location.href = url;
}

// Private folder buttons — call openPrivateFolder() or openPrivateFolder('SubfolderName') from button onclick
function openPrivateFolder(subdir) {
  var url = 'https://sheepsite.com/Scripts/display-private-dir.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return=' + encodeURIComponent(window.location.href);
  if (subdir) url += '&path=' + encodeURIComponent(subdir);
  window.location.href = url;
}

// Report page buttons — call openReport('parking'), openReport('elevator'), openReport('board')
// from the button's JS onclick (building name comes from BUILDING_NAME above)
function openReport(page) {
  window.location.href = 'https://sheepsite.com/Scripts/protected-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page=' + encodeURIComponent(page)
    + '&return=' + encodeURIComponent(window.location.href);
}

// Admin page — call openAdmin() from the menu link or button onclick
function openAdmin() {
  window.location.href = 'https://sheepsite.com/Scripts/admin.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME);
}

// Document buttons — call openDoc('SubfolderName', 'File Name') from button onclick
// subdir is optional — pass '' if file is in the root public folder
function openDoc(subdir, filename) {
  var url = 'https://sheepsite.com/Scripts/get-doc-byname.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&filename=' + encodeURIComponent(filename);
  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
  window.open(url, '_blank');
}
</script>

*/


// ===================================================
// BUTTON FORMATS — paste into a Custom HTML block
// ===================================================

// --- Public folder button ---
// Preferred: use a website builder button with onclick:
//   Root folder:       openFolder()
//   Specific subfolder: openFolder('RulesDocs')

// Legacy custom HTML button (still works):
/*
<a href="#" class="gdrive-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/
/*
<a href="#" class="gdrive-link" data-subdir="Forms"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/


// --- Private folder button ---
// Preferred: use a website builder button with onclick:
//   Root folder:        openPrivateFolder()
//   Specific subfolder: openPrivateFolder('Financials')

// Legacy custom HTML button (still works):
/*
<a href="#" class="local-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/
/*
<a href="#" class="local-link" data-path="Financials"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/


// --- Protected report iframe — embeds a report page with spinner ---
// Use data-page values: parking, elevator, resident
// Building name comes from BUILDING_NAME automatically.
// NOTE: no onload on the iframe — the footer script handles it after setting src.
/*
<div style="position:relative; width:100%; height:80vh;">
  <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f5f5f5;" id="doc-loader">
    <div style="width:48px; height:48px; border:5px solid #e0c0f0; border-top-color:#7A0099; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
    <p style="margin-top:14px; font-family:'Roboto',sans-serif; font-size:14px; color:#888;">Loading...</p>
  </div>
  <style>#doc-loader { transition: opacity 0.3s; } @keyframes spin { to { transform: rotate(360deg); } }</style>
  <iframe
    data-script="protected-report"
    data-page="parking"
    style="width:100%; height:100%; border:none; display:block;"
    title="Parking List">
  </iframe>
</div>
*/


// --- Document button — opens a specific file in a new tab ---
// Use openDoc() in the website builder's "onclick" field.
// First argument: subfolder name (or '' if file is in the root public folder)
// Second argument: exact file name

// With subfolder:
//   openDoc('Page1Docs', 'Mid Year Report')

// Without subfolder (file is in root public folder):
//   openDoc('', 'Welcome Letter')


// --- Document iframe — embeds a file with a loading spinner ---
// Paste into a Custom HTML block. Update data-subdir and data-filename.
// Omit data-subdir if the file is in the root public folder.
// NOTE: no onload on the iframe — the footer script handles it after setting src.
/*
<div style="position:relative; width:100%; height:80vh;">
  <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f5f5f5;"
       id="doc-loader">
    <div style="width:48px; height:48px; border:5px solid #e0c0f0; border-top-color:#7A0099; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
    <p style="margin-top:14px; font-family:'Roboto',sans-serif; font-size:14px; color:#888;">Loading document...</p>
  </div>
  <style>#doc-loader { transition: opacity 0.3s; } @keyframes spin { to { transform: rotate(360deg); } }</style>
  <iframe
    data-script="get-doc-byname"
    data-subdir="Page1Docs"
    data-filename="Announcement Page1"
    style="width:100%; height:100%; border:none; display:block;"
    title="Document">
  </iframe>
</div>
*/
