<?php
// -------------------------------------------------------
// my-account.php
// Resident-facing account hub.
// Auth: private_auth_{building} session (same as display-private-dir.php).
//
//   https://sheepsite.com/Scripts/my-account.php?building=LyndhurstH
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');

$buildings = require __DIR__ . '/buildings.php';

$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey = 'private_auth_' . $building;

// Load config for siteURL (needed on both login and main page)
$configFile = CONFIG_DIR . $building . '.json';
$cfg        = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
$siteURL    = $cfg['siteURL'] ?? '';

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
  $mustChange    = false;
  foreach ($users as $u) {
    if ($u['user'] === $username && password_verify($password, $u['pass'])) {
      $authenticated = true;
      $mustChange    = !empty($u['mustChange']);
      break;
    }
  }

  if ($authenticated) {
    $_SESSION[$sessionKey] = $username;
    if ($mustChange) {
      header('Location: change-password.php?building=' . urlencode($building)
           . '&mustchange=1'
           . '&redirect=' . urlencode('my-account.php?building=' . $building)
           . ($siteURL ? '&return=' . urlencode($siteURL) : ''));
    } else {
      header('Location: my-account.php?building=' . urlencode($building));
    }
    exit;
  } else {
    $loginError = 'Invalid username or password.';
  }
}

// -------------------------------------------------------
// Show login form if not authenticated
// -------------------------------------------------------
if (empty($_SESSION[$sessionKey])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($buildLabel) ?> – My Account</title>
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
  </style>
</head>
<body>
  <?php if ($siteURL): ?>
    <p style="margin-bottom:1.5rem;"><a href="<?= htmlspecialchars($siteURL) ?>" style="color:#0070f3;text-decoration:none;font-size:0.9rem;">← Back to site</a></p>
  <?php endif; ?>
  <h1><?= htmlspecialchars($buildLabel) ?> – My Account</h1>
  <div class="subtitle">Resident login required</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="my-account.php?building=<?= urlencode($building) ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" autocapitalize="none" autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password">

    <button type="submit" class="login-btn">Log in</button>
  </form>
  <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
    <a href="forgot-password.php?building=<?= urlencode($building) ?>" style="color:#0070f3;text-decoration:none;">Forgot password?</a>
  </p>
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// Authenticated — show account hub
// -------------------------------------------------------
$username = is_array($_SESSION[$sessionKey]) ? ($_SESSION[$sessionKey]['user'] ?? '') : $_SESSION[$sessionKey];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($buildLabel) ?> – My Account</title>
  <style>
    body        { font-family: sans-serif; max-width: 640px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar    { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem; }
    h1          { margin: 0; font-size: 1.4rem; }
    .top-links  { font-size: 0.85rem; display: flex; gap: 1rem; }
    .top-links a { color: #0070f3; text-decoration: none; }
    .top-links a:hover { text-decoration: underline; }
    .subtitle   { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    .card       { display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.25rem;
                  border: 1px solid #ddd; border-radius: 8px; margin-bottom: 1rem;
                  text-decoration: none; color: inherit; transition: background 0.15s; }
    .card:hover { background: #f5f5f5; }
    .card-icon  { font-size: 1.8rem; line-height: 1; flex-shrink: 0; }
    .card-title { font-size: 1rem; font-weight: bold; margin-bottom: 0.3rem; color: #0070f3; }
    .card-desc  { font-size: 0.875rem; color: #555; line-height: 1.45; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – My Account</h1>
  <div class="top-links">
    <?php if ($siteURL): ?>
      <a href="<?= htmlspecialchars($siteURL) ?>">← Back to site</a>
    <?php endif; ?>
    <a href="display-private-dir.php?building=<?= urlencode($building) ?>">Private files</a>
    <a href="display-private-dir.php?building=<?= urlencode($building) ?>&logout=1<?= $siteURL ? '&return=' . urlencode($siteURL) : '' ?>">Log out</a>
  </div>
</div>
<div class="subtitle">Welcome, <?= htmlspecialchars($username) ?></div>

<a href="my-unit.php?building=<?= urlencode($building) ?><?= $siteURL ? '&return=' . urlencode($siteURL) : '' ?>" class="card">
  <div class="card-icon">🏠</div>
  <div>
    <div class="card-title">My Unit Info</div>
    <div class="card-desc">
      Update your contact information, vehicle details, and emergency contacts for your unit.
    </div>
  </div>
</a>

<a href="change-password.php?building=<?= urlencode($building) ?>&redirect=<?= urlencode('my-account.php?building=' . $building) ?><?= $siteURL ? '&return=' . urlencode($siteURL) : '' ?>" class="card">
  <div class="card-icon">🔑</div>
  <div>
    <div class="card-title">Change Password</div>
    <div class="card-desc">
      Update your login password.
    </div>
  </div>
</a>

<a href="https://sheepsite.com/Scripts/docs/Sheepsite-Resident-Manual.html" target="_blank" class="card">
  <div class="card-icon"><img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" height="36" alt="Woolsy" style="display:block;"></div>
  <div>
    <div class="card-title">Resident Manual</div>
    <div class="card-desc">
      Your guide to using the building website &mdash; login, documents, Woolsy, and your account.
    </div>
  </div>
</a>

</body>
</html>
