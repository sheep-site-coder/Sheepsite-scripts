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

  // Document iframes (get-doc-byname)
  const DOC_URL = 'https://sheepsite.com/Scripts/get-doc-byname.php';
  document.querySelectorAll('iframe[data-script="get-doc-byname"]').forEach(function (iframe) {
    var url = DOC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = iframe.getAttribute('data-subdir');
    var filename = iframe.getAttribute('data-filename');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    if (filename) url += '&filename=' + encodeURIComponent(filename);
    iframe.src = url;
  });
});

// Report page buttons — call openReport('parking'), openReport('elevator'), openReport('board')
// from the button's JS onclick (building name comes from BUILDING_NAME above)
function openReport(page) {
  window.location.href = 'https://sheepsite.com/Scripts/protected-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page=' + encodeURIComponent(page)
    + '&return=' + encodeURIComponent(window.location.href);
}
</script>

*/


// ===================================================
// BUTTON FORMATS — paste into a Custom HTML block
// ===================================================

// --- Public folder button (class="gdrive-link") ---
// Points to the root Public folder:
/*
<a href="#" class="gdrive-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/

// Points to a specific subfolder (change data-subdir value):
/*
<a href="#" class="gdrive-link" data-subdir="Forms"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/


// --- Private folder button (class="local-link") ---
// Points to the root Private folder:
/*
<a href="#" class="local-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/

// Points to a specific subfolder (change data-path value):
/*
<a href="#" class="local-link" data-path="Financials"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/
