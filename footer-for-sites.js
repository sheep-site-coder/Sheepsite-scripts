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
  const GDRIVE_URL = 'https://sheepsite.com/Scripts/display-gdrive-sites.php';
  const LOCAL_URL  = 'https://sheepsite.com/Scripts/display-private-dir.php';

  // Google Drive buttons
  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = GDRIVE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    btn.href = url;
  });

  // Private cPanel directory buttons
  document.querySelectorAll('.local-link').forEach(function (btn) {
    var url = LOCAL_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var path = btn.getAttribute('data-path');
    if (path) url += '&path=' + encodeURIComponent(path);
    btn.href = url;
  });
});
</script>

*/


// ===================================================
// BUTTON FORMATS — paste into a Custom HTML block
// ===================================================

// --- Google Drive button (class="gdrive-link") ---
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


// --- Private cPanel directory button (class="local-link") ---
// Points to the root SiteFolders:
/*
<a href="#" class="local-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/

// Points to a specific subdirectory (change data-path value):
/*
<a href="#" class="local-link" data-path="Private"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
*/
