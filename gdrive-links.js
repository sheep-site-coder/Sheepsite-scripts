// -------------------------------------------------------
// gdrive-links.js
// Auto-fills building name into all Google Drive file
// listing buttons on the page.
//
// SETUP:
// 1. Add this to your site template/header (change the name per site):
//
//      <script>const BUILDING_NAME = 'QGscratch';</script>
//
// 2. Add this script to your template/footer:
//
//      <script src="https://sheepsite.com/Scripts/gdrive-links.js"></script>
//
// 3. Use this format for any button/link on the page:
//
//      <!-- Root Public folder -->
//      <a href="#" class="gdrive-link">View All Files</a>
//
//      <!-- Specific subfolder -->
//      <a href="#" class="gdrive-link" data-subdir="Forms">View Forms</a>
// -------------------------------------------------------

(function () {
  const BASE_URL = 'https://sheepsite.com/Scripts/display-gdrive-sites.php';

  if (typeof BUILDING_NAME === 'undefined' || !BUILDING_NAME) {
    console.warn('gdrive-links.js: BUILDING_NAME is not defined.');
    return;
  }

  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = BASE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) {
      url += '&subdir=' + encodeURIComponent(subdir);
    }
    btn.href = url;
  });
})();
