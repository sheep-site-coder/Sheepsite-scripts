// -------------------------------------------------------
// gdrive-links.js
// Auto-fills building name into all Google Drive file
// listing buttons on the page.
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
//    const BASE_URL = 'https://sheepsite.com/Scripts/display-gdrive-sites.php';
//    document.querySelectorAll('.gdrive-link').forEach(function (btn) {
//      var url = BASE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
//      var subdir = btn.getAttribute('data-subdir');
//      if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
//      btn.href = url;
//    });
//  });
//  </script>
//
// BUTTON FORMAT (Custom HTML block anywhere on the page):
//
//  <!-- Root Public folder -->
//  <a href="#" class="gdrive-link"
//     style="display:inline-block; padding:0.5rem 1.2rem; background:#0070f3; color:#fff; text-decoration:none; border-radius:4px; font-family:sans-serif;">
//    View All Files
//  </a>
//
//  <!-- Specific subfolder -->
//  <a href="#" class="gdrive-link" data-subdir="Forms"
//     style="display:inline-block; padding:0.5rem 1.2rem; background:#0070f3; color:#fff; text-decoration:none; border-radius:4px; font-family:sans-serif;">
//    View Forms
//  </a>
// -------------------------------------------------------
