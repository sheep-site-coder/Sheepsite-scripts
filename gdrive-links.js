// -------------------------------------------------------
// gdrive-links.js
// Auto-fills building name into all file listing buttons.
// Handles both Google Drive buttons (.gdrive-link) and
// private cPanel directory buttons (.local-link).
//
// NOTE: Namecheap Website Builder does not support loading
// this as an external script. Use the inline approach below.
//
// SETUP:
// Add a single Custom HTML block to your site FOOTER
// (change BUILDING_NAME per site — that's the only edit needed):
//
//  <script>
//  const BUILDING_NAME = 'QGscratch';
//
//  document.addEventListener('DOMContentLoaded', function () {
//    const GDRIVE_URL = 'https://sheepsite.com/Scripts/display-gdrive-sites.php';
//    const LOCAL_URL  = 'https://sheepsite.com/Scripts/display-private-dir.php';
//
//    // Google Drive buttons
//    document.querySelectorAll('.gdrive-link').forEach(function (btn) {
//      var url = GDRIVE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
//      var subdir = btn.getAttribute('data-subdir');
//      if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
//      btn.href = url;
//    });
//
//    // Private cPanel directory buttons
//    document.querySelectorAll('.local-link').forEach(function (btn) {
//      var url = LOCAL_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
//      var path = btn.getAttribute('data-path');
//      if (path) url += '&path=' + encodeURIComponent(path);
//      btn.href = url;
//    });
//  });
//  </script>
//
// -------------------------------------------------------
// GOOGLE DRIVE BUTTON FORMAT (.gdrive-link):
//
//  <!-- Root Public folder -->
//  <a href="#" class="gdrive-link"
//     style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
//    &#128196; Click to open
//  </a>
//
//  <!-- Specific subfolder -->
//  <a href="#" class="gdrive-link" data-subdir="Forms"
//     style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
//    &#128196; Click to open
//  </a>
//
// -------------------------------------------------------
// PRIVATE DIRECTORY BUTTON FORMAT (.local-link):
//
//  <!-- Root SiteFolders -->
//  <a href="#" class="local-link"
//     style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
//    &#128196; Click to open
//  </a>
//
//  <!-- Specific subdirectory -->
//  <a href="#" class="local-link" data-path="Private"
//     style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
//    &#128196; Click to open
//  </a>
// -------------------------------------------------------
