<?php
// -------------------------------------------------------
// admin.php
// Admin landing page — links to all admin tools for a building.
//
//   https://sheepsite.com/Scripts/admin.php?building=LyndhurstH
//
// Uses the same admin credentials and session as manage-users.php.
// If already logged into manage-users.php, no re-login needed here,
// and vice versa.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('USER_MANUAL_URL', 'https://docs.google.com/document/d/e/2PACX-1vTO2Ytf_g3mbn1v7irbOsgu2akpOvR-bcqYZ8TiWSUX67_1R5h3PR_ZPzToiALXa4GZoiLuycZUpWrR/pub');

// -------------------------------------------------------
// Validate building
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$adminCredFile = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
  die('<p style="color:red;">Admin credentials not configured for this building.</p>');
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey = 'manage_auth_' . $building;

// Load credentials
$adminCred  = json_decode(file_get_contents($adminCredFile), true);
$masterCredFile = CREDENTIALS_DIR . '_master.json';
$masterCred = file_exists($masterCredFile) ? json_decode(file_get_contents($masterCredFile), true) : null;

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if (isset($_GET['logout'])) {
  unset($_SESSION[$sessionKey]);
  header('Location: admin.php?building=' . urlencode($building));
  exit;
}

// -------------------------------------------------------
// Login — handle POST
// -------------------------------------------------------
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
  $submittedUser = trim($_POST['admin_user'] ?? '');
  $submittedPass = $_POST['admin_pass'] ?? '';

  $isMaster   = $masterCred
                && $submittedUser === $masterCred['user']
                && password_verify($submittedPass, $masterCred['pass']);
  $isBuilding = $submittedUser === $adminCred['user']
                && password_verify($submittedPass, $adminCred['pass']);

  if ($isMaster || $isBuilding) {
    $_SESSION[$sessionKey] = true;
    header('Location: admin.php?building=' . urlencode($building));
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
  <title><?= htmlspecialchars($buildLabel) ?> – Admin Login</title>
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
  <h1><?= htmlspecialchars($buildLabel) ?> – Admin</h1>
  <div class="subtitle">Administrator login required</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="admin.php?building=<?= urlencode($building) ?>">
    <label for="admin_user">Username</label>
    <input type="text" id="admin_user" name="admin_user" autocomplete="username" autofocus>

    <label for="admin_pass">Password</label>
    <input type="password" id="admin_pass" name="admin_pass" autocomplete="current-password">

    <button type="submit" class="login-btn">Log in</button>
  </form>
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// Handle admin password change
// -------------------------------------------------------
$pwMessage     = '';
$pwMessageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_pass'])) {
  $currentPass = $_POST['current_pass'] ?? '';
  $newPass     = $_POST['new_pass']     ?? '';
  $confirmPass = $_POST['confirm_pass'] ?? '';

  if (!$currentPass || !$newPass || !$confirmPass) {
    $pwMessage     = 'All fields are required.';
    $pwMessageType = 'error';
  } elseif ($newPass !== $confirmPass) {
    $pwMessage     = 'New passwords do not match.';
    $pwMessageType = 'error';
  } elseif (strlen($newPass) < 8) {
    $pwMessage     = 'New password must be at least 8 characters.';
    $pwMessageType = 'error';
  } elseif (!password_verify($currentPass, $adminCred['pass'])) {
    $pwMessage     = 'Current password is incorrect.';
    $pwMessageType = 'error';
  } else {
    $adminCred['pass'] = password_hash($newPass, PASSWORD_DEFAULT);
    if (file_put_contents($adminCredFile, json_encode($adminCred, JSON_PRETTY_PRINT)) !== false) {
      $pwMessage = 'Admin password updated successfully.';
    } else {
      $pwMessage     = 'Could not save — check that the credentials/ folder is writable.';
      $pwMessageType = 'error';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Admin</title>
  <style>
    body        { font-family: sans-serif; max-width: 680px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar    { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1          { margin: 0; font-size: 1.5rem; }
    .logout     { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .logout:hover { text-decoration: underline; }
    .subtitle   { color: #666; font-size: 0.95rem; margin-bottom: 2rem; }
    .card       { display: flex; gap: 1.25rem; align-items: flex-start; padding: 1.25rem;
                  border: 1px solid #ddd; border-radius: 8px; margin-bottom: 1rem;
                  text-decoration: none; color: inherit; transition: background 0.15s; }
    .card:hover { background: #f5f5f5; }
    .card-icon  { font-size: 1.8rem; line-height: 1; flex-shrink: 0; }
    .card-title { font-size: 1rem; font-weight: bold; margin-bottom: 0.3rem; color: #0070f3; }
    .card-desc  { font-size: 0.875rem; color: #555; line-height: 1.45; }
    hr          { margin: 2rem 0; border: none; border-top: 1px solid #eee; }
    h2          { font-size: 1rem; margin: 0 0 0.75rem; }
    label       { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=password] { width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
    .save-btn   { padding: 0.5rem 1.2rem; background: #0070f3; color: #fff; border: none;
                border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
    .save-btn:hover { background: #005bb5; }
    .message    { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Admin</h1>
  <a href="admin.php?building=<?= urlencode($building) ?>&logout=1" class="logout">Log out</a>
</div>
<div class="subtitle">Building administration tools</div>

<a href="manage-users.php?building=<?= urlencode($building) ?>" class="card">
  <div class="card-icon">👥</div>
  <div>
    <div class="card-title">Manage Users</div>
    <div class="card-desc">
      Import owners from the building's Google Sheet, add or remove individual accounts,
      and reset owner passwords.
    </div>
  </div>
</a>

<a href="<?= htmlspecialchars(USER_MANUAL_URL) ?>" target="_blank" class="card">
  <div class="card-icon">📖</div>
  <div>
    <div class="card-title">User Manual</div>
    <div class="card-desc">
      Step-by-step guide covering all admin tasks — initial setup, importing owners,
      managing passwords, and troubleshooting. Opens in a new tab.
    </div>
  </div>
</a>

<hr>

<h2>Change Admin Password</h2>

<?php if ($pwMessage): ?>
  <div class="message <?= $pwMessageType ?>"><?= htmlspecialchars($pwMessage) ?></div>
<?php endif; ?>

<form method="post" action="admin.php?building=<?= urlencode($building) ?>" style="max-width:320px;">
  <label for="current_pass">Current password</label>
  <input type="password" id="current_pass" name="current_pass" autocomplete="current-password">

  <label for="new_pass">New password</label>
  <input type="password" id="new_pass" name="new_pass" autocomplete="new-password">

  <label for="confirm_pass">Confirm new password</label>
  <input type="password" id="confirm_pass" name="confirm_pass" autocomplete="new-password">

  <button type="submit" name="change_admin_pass" class="save-btn">Update password</button>
</form>

</body>
</html>
