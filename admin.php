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

define('CREDENTIALS_DIR',      __DIR__ . '/credentials/');
define('USER_MANUAL_URL',      'docs/Sheepsite-Admin-Manual.html');
define('CREDITS_FILE',         __DIR__ . '/faqs/woolsy_credits.json');
define('CREDITS_DEFAULT_ALLOCATED', 1.0);
define('PROMPT_VERSION',       4);

function getRulesVersion(string $file): int {
    if (!file_exists($file)) return 0;
    $fh   = fopen($file, 'r');
    $line = fgets($fh);
    fclose($fh);
    if (preg_match('/woolsy_prompt_version:\s*(\d+)/', $line, $m)) return (int)$m[1];
    return 1;
}

function getWoolsyCredits(string $building): array {
    if (!file_exists(CREDITS_FILE)) return ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
    $all = json_decode(file_get_contents(CREDITS_FILE), true) ?? [];
    return $all[$building] ?? ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
}

// -------------------------------------------------------
// Validate building
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$adminCredFile = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
  header('Location: forgot-password.php?building=' . urlencode($building) . '&role=admin&setup=1');
  exit;
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
  <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
    <a href="forgot-password.php?building=<?= urlencode($building) ?>&role=admin" style="color:#0070f3;text-decoration:none;">Forgot password?</a>
  </p>
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// mustChange check — re-read credential file after any POST
// -------------------------------------------------------
$adminCred  = json_decode(file_get_contents($adminCredFile), true);
$mustChange = !empty($adminCred['mustChange']);

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
    unset($adminCred['mustChange']);
    if (file_put_contents($adminCredFile, json_encode($adminCred, JSON_PRETTY_PRINT)) !== false) {
      $mustChange = false;
      $pwMessage  = 'Password updated. You now have full access.';
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
    .low-credit-warn { padding: 0.4rem 0.7rem; background: #fffbeb;
                       border: 1px solid #f59e0b; border-radius: 4px; font-size: 0.82rem; color: #92400e; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Admin</h1>
  <a href="admin.php?building=<?= urlencode($building) ?>&logout=1" class="logout">Log out</a>
</div>

<?php if ($mustChange): ?>
  <div class="message error" style="margin-bottom:2rem;">
    You are logged in with a temporary password. Please set a new password to continue.
  </div>
<?php else: ?>
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

  <a href="file-manager.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon">📁</div>
    <div>
      <div class="card-title">Manage Files</div>
      <div class="card-desc">
        Upload, delete, rename, and organize files in Public and Private folders.
        Drag and drop one or more files to upload, or browse to select.
      </div>
    </div>
  </a>

  <a href="tag-admin.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon">🏷️</div>
    <div>
      <div class="card-title">Manage Tags</div>
      <div class="card-desc">
        Browse Public and Private folders and assign searchable tags to files.
        Tags power the file search feature for owners.
      </div>
    </div>
  </a>

  <a href="storage-report.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon">📊</div>
    <div>
      <div class="card-title">Storage Report</div>
      <div class="card-desc">
        View storage usage for the building's Public and Private folders,
        broken down by subfolder.
      </div>
    </div>
  </a>

  <a href="<?= htmlspecialchars(USER_MANUAL_URL) ?>" target="_blank" class="card">
    <div class="card-icon">📖</div>
    <div>
      <div class="card-title">Admin User Manual</div>
      <div class="card-desc">
        Step-by-step guide covering all admin tasks — initial setup, importing owners,
        managing passwords, and troubleshooting. Opens in a new tab.
      </div>
    </div>
  </a>

  <?php
    $wc           = getWoolsyCredits($building);
    $wAlloc       = (float)($wc['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
    $wUsed        = (float)($wc['used']      ?? 0);
    $wPct         = $wAlloc > 0 ? min(100, round($wUsed / $wAlloc * 100)) : 100;
    $rulesFile    = __DIR__ . '/faqs/' . $building . '_rules.md';
    $rulesVersion = getRulesVersion($rulesFile);
    $promptOutdated = ($rulesVersion > 0 && $rulesVersion < PROMPT_VERSION);
    $usageFile    = __DIR__ . '/faqs/woolsy_usage.json';
    $usageAll     = file_exists($usageFile) ? (json_decode(file_get_contents($usageFile), true) ?? []) : [];
    $monthCount   = $usageAll[$building][date('Y-m')] ?? 0;
  ?>
  <a href="woolsy-manage.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon">🐑</div>
    <div>
      <div class="card-title">Woolsy AI Assistant</div>
      <div class="card-desc">
        Knowledge base status, usage statistics, FAQ editor, and credit tracking.
        <?php if ($wPct >= 100): ?>
          <br><span style="color:#dc2626;font-weight:600;">⛔ Credits exhausted — Woolsy unavailable.</span>
        <?php elseif ($wPct >= 80): ?>
          <br><span style="color:#b45309;font-weight:600;">⚠️ Credits running low.</span>
        <?php else: ?>
          <br><span style="color:#888;"><?= $monthCount ?> question<?= $monthCount !== 1 ? 's' : '' ?> this month</span>
        <?php endif; ?>
      </div>
    </div>
  </a>
  <?php if ($promptOutdated): ?>
  <div class="low-credit-warn" style="margin-top:-0.5rem;margin-bottom:1rem;">
    ⚠️ Woolsy prompt updated (v<?= PROMPT_VERSION ?>) — a rebuild is recommended.
    <a href="woolsy-update.php?building=<?= urlencode($building) ?>" style="color:#92400e;font-weight:600;">Rebuild now →</a>
  </div>
  <?php endif; ?>

  <hr>
<?php endif; ?>

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
