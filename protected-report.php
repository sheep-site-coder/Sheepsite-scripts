<?php
// -------------------------------------------------------
// protected-report.php
// Place this file on sheepsite.com/Scripts/
//
// Password-protects the Google Sheets Web App reports
// (Parking List, Elevator List, Board of Directors).
// Reuses the same session as display-private-dir.php —
// if a user is already logged in for private files,
// they won't need to log in again.
//
// Usage:
//   ?building=BUILDING_NAME&page=parking
//   ?building=BUILDING_NAME&page=elevator
//   ?building=BUILDING_NAME&page=board
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

$buildings = require __DIR__ . '/buildings.php';

$pages = [
  'board'    => ['suffix' => '',                'title' => 'Board of Directors'],
  'elevator' => ['suffix' => '?page=elevator',  'title' => 'Elevator List'],
  'parking'  => ['suffix' => '?page=parking',   'title' => 'Parking List'],
  'resident' => ['suffix' => '?page=resident',  'title' => 'Resident List'],
];

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$page     = $_GET['page'] ?? 'parking';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

if (!array_key_exists($page, $pages)) {
  die('<p style="color:red;">Invalid page.</p>');
}

$buildingConfig = $buildings[$building];
$pageConfig     = $pages[$page];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));
$returnURL      = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

$sessionKey = 'private_auth_' . $building;
$baseURL    = '?building=' . urlencode($building) . '&page=' . urlencode($page)
            . ($returnURL ? '&return=' . urlencode($returnURL) : '');

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
    header('Location: ' . $baseURL);
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
  <div class="subtitle"><?= htmlspecialchars($pageConfig['title']) ?> — login required</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($baseURL) ?>">
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
// mustChange check
// -------------------------------------------------------
$credFile = CREDENTIALS_DIR . $building . '.json';
$allUsers = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];
foreach ($allUsers as $u) {
  if ($u['user'] === $_SESSION[$sessionKey] && !empty($u['mustChange'])) {
    $reportRedirect = 'protected-report.php?building=' . urlencode($building)
                    . '&page=' . urlencode($page)
                    . ($returnURL ? '&return=' . urlencode($returnURL) : '');
    header('Location: change-password.php?building=' . urlencode($building)
         . '&mustchange=1'
         . '&redirect=' . urlencode($reportRedirect)
         . ($returnURL ? '&return=' . urlencode($returnURL) : ''));
    exit;
  }
}

// -------------------------------------------------------
// Authenticated — show the report in an iframe
// -------------------------------------------------------
$currentUser = $_SESSION[$sessionKey];
$iframeSrc   = $buildingConfig['webAppURL'] . $pageConfig['suffix'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – <?= htmlspecialchars($pageConfig['title']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body     { font-family: sans-serif; display: flex; flex-direction: column; height: 100vh; }
    .top-bar { display: flex; justify-content: space-between; align-items: center;
               padding: 0.5rem 1rem; background: #f5f5f5; border-bottom: 1px solid #ddd;
               font-size: 0.85rem; flex-shrink: 0; }
    .top-bar a { color: #0070f3; text-decoration: none; margin-left: 0.75rem; }
    .top-bar a:hover { text-decoration: underline; }
    .nav-links a { font-weight: bold; }
    .nav-links a.active { color: #333; pointer-events: none; text-decoration: none; }
    .iframe-wrap { position: relative; flex: 1; }
    #doc-loader  { position: absolute; inset: 0; display: flex; flex-direction: column;
                   align-items: center; justify-content: center; background: #f5f5f5;
                   transition: opacity 0.3s; }
    #doc-loader .spinner { width: 48px; height: 48px; border: 5px solid #e0c0f0;
                   border-top-color: #7A0099; border-radius: 50%;
                   animation: spin 0.8s linear infinite; }
    #doc-loader p { margin-top: 14px; font-size: 14px; color: #888; }
    @keyframes spin { to { transform: rotate(360deg); } }
    iframe   { border: none; width: 100%; height: 100%; }
  </style>
</head>
<body>
  <div class="top-bar">
    <div>
      <?php if ($returnURL): ?>
        <a href="<?= htmlspecialchars($returnURL) ?>">← Back to site</a>
      <?php endif; ?>
    </div>
    <div class="nav-links">
      <?php foreach (['elevator' => 'Elevator List', 'parking' => 'Parking List', 'resident' => 'Resident List'] as $p => $label):
        $navURL = '?building=' . urlencode($building) . '&page=' . urlencode($p) . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      ?>
        <a href="<?= htmlspecialchars($navURL) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <span style="color:#666;"><?= htmlspecialchars($currentUser) ?></span>
      <?php
        $reportRedirect = 'protected-report.php?building=' . urlencode($building)
                        . '&page=' . urlencode($page)
                        . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      ?>
      <a href="change-password.php?building=<?= urlencode($building) ?>&redirect=<?= urlencode($reportRedirect) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>">Change password</a>
      <a href="<?= htmlspecialchars($baseURL) ?>&logout=1">Log out</a>
    </div>
  </div>
  <div class="iframe-wrap">
    <div id="doc-loader">
      <div class="spinner"></div>
      <p>Loading...</p>
    </div>
    <iframe src="<?= htmlspecialchars($iframeSrc) ?>"
            title="<?= htmlspecialchars($pageConfig['title']) ?>"
            onload="document.getElementById('doc-loader').style.display='none'"></iframe>
  </div>
</body>
</html>
