<?php
// -------------------------------------------------------
// display-private-dir.php
// Place this file on sheepsite.com/Scripts/
// -------------------------------------------------------
session_start();

define('APPS_SCRIPT_URL',   'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('APPS_SCRIPT_TOKEN', 'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');  // must match SECRET_TOKEN in dir-display-bridge.gs
define('CREDENTIALS_DIR',   __DIR__ . '/credentials/');

// Building name → Google Drive Private folder ID
$buildings = [
  'QGscratch' => [
    'folderId' => '1cnHRemgPPNWbY9QlyrsHXq6Mzdu09tSu',  // QGscratch/WebSite/Private
  ],
  'LyndhurstH' => [
    'folderId' => '11WXnAU2P-ShZPtj9p5PG0bFR7ehDXUSS',  // LyndhurstH/WebSite/Private
  ],
  'LyndhurstI' => [
    'folderId' => '1xNEXK2qcGoISKaNoChbTDn2FOWnUmSFP',  // LyndhurstI/WebSite/Private
  ],
  // add more buildings here...
];

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$path     = trim($_GET['path'] ?? '', '/');

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildingConfig = $buildings[$building];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));
$returnURL      = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

$sessionKey = 'private_auth_' . $building;
$baseURL    = '?building=' . urlencode($building) . ($returnURL ? '&return=' . urlencode($returnURL) : '');

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if (isset($_GET['logout'])) {
  unset($_SESSION[$sessionKey]);
  header('Location: ' . $baseURL);
  exit;
}

// -------------------------------------------------------
// Login — handle POST
// -------------------------------------------------------
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  $credFile = CREDENTIALS_DIR . $building . '.json';
  $users    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];

  $authenticated = false;
  foreach ($users as $u) {
    if ($u['user'] === $username && password_verify($password, $u['pass'])) {
      $authenticated = true;
      break;
    }
  }

  if ($authenticated) {
    $_SESSION[$sessionKey] = $username;
    header('Location: ' . $baseURL . ($path ? '&path=' . urlencode($path) : ''));
    exit;
  } else {
    $loginError = 'Invalid username or password.';
  }
}

// -------------------------------------------------------
// Login — show form if not authenticated
// -------------------------------------------------------
if (empty($_SESSION[$sessionKey])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Login</title>
  <style>
    body       { font-family: sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
    h1         { margin-bottom: 0.25rem; font-size: 1.4rem; }
    .subtitle  { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label      { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text], input[type=password] {
                 width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                 border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;
                 margin-bottom: 1rem; }
    .login-btn { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                 border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .login-btn:hover { background: #005bb5; }
    .error     { color: red; font-size: 0.9rem; margin-bottom: 1rem; }
    .back-btn  { display: inline-block; margin-bottom: 1.5rem; font-size: 0.9rem;
                 color: #0070f3; text-decoration: none; }
    .back-btn:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <?php if ($returnURL): ?>
    <a href="<?= htmlspecialchars($returnURL) ?>" class="back-btn">← Back to site</a>
  <?php endif; ?>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>
  <div class="subtitle">Private files — login required</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($baseURL . ($path ? '&path=' . urlencode($path) : '')) ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password">

    <button type="submit" class="login-btn">Log in</button>
  </form>
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// JSON mode — called by browser fetch, returns listing
// Auth check above already passed before reaching here
// -------------------------------------------------------
if (isset($_GET['json'])) {
  $scriptURL = APPS_SCRIPT_URL
             . '?action=listPrivate'
             . '&token='    . urlencode(APPS_SCRIPT_TOKEN)
             . '&folderId=' . urlencode($buildingConfig['folderId'])
             . ($path ? '&subdir=' . urlencode($path) : '');
  $response = @file_get_contents($scriptURL);
  header('Content-Type: application/json');
  echo $response ?: json_encode(['error' => 'Could not reach Apps Script']);
  exit;
}

// -------------------------------------------------------
// Proxy download — fetch file via Apps Script, stream to browser
// Token is never exposed to the user
// -------------------------------------------------------
if (isset($_GET['fileId'])) {
  $fileId = $_GET['fileId'];

  if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
    die('<p style="color:red;">Invalid file ID.</p>');
  }

  $scriptURL = APPS_SCRIPT_URL
             . '?action=download'
             . '&token='  . urlencode(APPS_SCRIPT_TOKEN)
             . '&fileId=' . urlencode($fileId);

  $response = @file_get_contents($scriptURL);
  if ($response === false) {
    die('<p style="color:red;">Could not retrieve file.</p>');
  }

  $data = json_decode($response, true);
  if (!empty($data['error'])) {
    die('<p style="color:red;">' . htmlspecialchars($data['error']) . '</p>');
  }

  $disposition = isset($_GET['inline']) ? 'inline' : 'attachment';
  header('Content-Type: ' . $data['mimeType']);
  header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '\\"', $data['name']) . '"');
  echo base64_decode($data['data']);
  exit;
}

// -------------------------------------------------------
// Build breadcrumb from path
// -------------------------------------------------------
$parts      = $path ? explode('/', $path) : [];
$breadcrumb = [['label' => 'Private', 'path' => '']];
$pathSoFar  = '';
foreach ($parts as $part) {
  $pathSoFar    = $pathSoFar ? $pathSoFar . '/' . $part : $part;
  $breadcrumb[] = ['label' => $part, 'path' => $pathSoFar];
}

$currentUser = $_SESSION[$sessionKey];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Private Files</title>
  <style>
    body           { font-family: sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
    h1             { margin-bottom: 0.25rem; }
    .top-bar       { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .user-info     { font-size: 0.85rem; color: #666; }
    .user-actions  { font-size: 0.85rem; }
    .user-actions a { color: #0070f3; text-decoration: none; margin-left: 0.75rem; }
    .user-actions a:hover { text-decoration: underline; }
    .breadcrumb    { font-size: 0.9rem; color: #666; margin-bottom: 1.5rem; }
    .breadcrumb a  { color: #0070f3; text-decoration: none; }
    .breadcrumb a:hover { text-decoration: underline; }
    .section-title { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #999; margin: 1.25rem 0 0.4rem; }
    .folder-card   { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; text-decoration: none; color: inherit; }
    .folder-card:hover { background: #f5f5f5; }
    .folder-icon   { font-size: 1.2rem; }
    .folder-name   { flex: 1; font-weight: bold; }
    .file-card     { display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.4rem; }
    .file-name     { flex: 1; font-weight: bold; color: #333; text-decoration: none; }
    .file-name:hover { text-decoration: underline; color: #0070f3; }
    .file-info     { color: #666; font-size: 0.85rem; }
    .download-btn  { padding: 0.4rem 0.9rem; background: #0070f3; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem; }
    .download-btn:hover { background: #005bb5; }
    .error         { color: red; }
    .empty         { color: #999; font-style: italic; }
    .loading       { color: #999; font-style: italic; padding: 2rem 0; }
    .back-btn      { display:inline-block; margin-bottom:1rem; font-size:0.9rem; color:#0070f3; text-decoration:none; }
    .back-btn:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <?php if ($returnURL): ?>
    <a href="<?= htmlspecialchars($returnURL) ?>" class="back-btn">← Back to site</a>
  <?php endif; ?>

  <div class="top-bar">
    <h1><?= htmlspecialchars($buildLabel) ?> – Private Files</h1>
    <div>
      <span class="user-info"><?= htmlspecialchars($currentUser) ?></span>
      <span class="user-actions">
        <a href="change-password.php?building=<?= urlencode($building) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>">Change password</a>
        <a href="<?= htmlspecialchars($baseURL) ?>&logout=1">Log out</a>
      </span>
    </div>
  </div>

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <?php foreach ($breadcrumb as $i => $crumb): ?>
      <?= $i > 0 ? ' / ' : '' ?>
      <?php if ($i < count($breadcrumb) - 1): ?>
        <a href="<?= $baseURL . ($crumb['path'] ? '&path=' . urlencode($crumb['path']) : '') ?>">
          <?= htmlspecialchars($crumb['label']) ?>
        </a>
      <?php else: ?>
        <strong><?= htmlspecialchars($crumb['label']) ?></strong>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div id="listing"><p class="loading">Loading…</p></div>

  <script>
  (function () {
    var building  = <?= json_encode($building) ?>;
    var path      = <?= json_encode($path) ?>;
    var returnURL = <?= json_encode($returnURL) ?>;
    var baseURL   = '?building=' + encodeURIComponent(building)
                  + (returnURL ? '&return=' + encodeURIComponent(returnURL) : '');
    var fetchURL  = baseURL + (path ? '&path=' + encodeURIComponent(path) : '') + '&json=1';

    fetch(fetchURL)
      .then(function (r) { return r.json(); })
      .then(function (data) { render(data); })
      .catch(function ()   { showError('Could not load directory.'); });

    function render(data) {
      if (data.error) { showError(data.error); return; }

      var html = '';

      if (data.folders && data.folders.length) {
        html += '<div class="section-title">Folders</div>';
        data.folders.forEach(function (f) {
          var folderPath = path ? path + '/' + f.name : f.name;
          var href = baseURL + '&path=' + encodeURIComponent(folderPath);
          html += '<a href="' + esc(href) + '" class="folder-card">'
                + '<span class="folder-icon">📁</span>'
                + '<span class="folder-name">' + esc(f.name) + '</span>'
                + '</a>';
        });
      }

      if (data.files && data.files.length) {
        html += '<div class="section-title">Files</div>';
        data.files.forEach(function (f) {
          var fileBase    = baseURL + (path ? '&path=' + encodeURIComponent(path) : '') + '&fileId=' + encodeURIComponent(f.id);
          var previewURL  = fileBase + '&inline=1';
          var downloadURL = fileBase;
          html += '<div class="file-card">'
                + '<a href="' + esc(previewURL) + '" target="_blank" class="file-name">' + esc(f.name) + '</a>'
                + '<span class="file-info">' + esc(f.size) + '</span>'
                + '<a href="' + esc(downloadURL) + '" class="download-btn">Download</a>'
                + '</div>';
        });
      }

      if (!html) html = '<p class="empty">No files or folders found.</p>';
      document.getElementById('listing').innerHTML = html;
    }

    function showError(msg) {
      document.getElementById('listing').innerHTML = '<p class="error">' + esc(msg) + '</p>';
    }

    function esc(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
  })();
  </script>
</body>
</html>
