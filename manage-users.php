<?php
// -------------------------------------------------------
// manage-users.php
// Place this file on sheepsite.com/Scripts/
//
// Each building has its own admin URL, username, and password:
//   https://sheepsite.com/Scripts/manage-users.php?building=LyndhurstH
//
// The master credentials can access any building.
// Change all passwords before deploying.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR',    __DIR__ . '/credentials/');
define('OWNER_IMPORT_TOKEN', 'QRF*!v2r2KgJEesq&P');  // must match building-script.gs

$buildings = require __DIR__ . '/buildings.php';

// -------------------------------------------------------
// Validate building parameter
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey = 'manage_auth_' . $building;

$useLocalDB = file_exists(CREDENTIALS_DIR . '../db/db.php') && file_exists(CREDENTIALS_DIR . 'db.json');
if ($useLocalDB) require_once __DIR__ . '/db/residents.php';

// Load per-building admin credentials
$adminCredFile = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
  die('<p style="color:red;">Admin credentials not configured. Run setup-admin.php first.</p>');
}
$adminCred = json_decode(file_get_contents($adminCredFile), true);

// Load master credentials (optional — if file missing, master login is disabled)
$masterCredFile = CREDENTIALS_DIR . '_master.json';
$masterCred = file_exists($masterCredFile) ? json_decode(file_get_contents($masterCredFile), true) : null;

// -------------------------------------------------------
// Dismiss sync orphan panel (GET action)
// -------------------------------------------------------
if (isset($_GET['dismiss_orphans'])) {
  unset($_SESSION['sync_orphans_' . $building]);
  header('Location: manage-users.php?building=' . urlencode($building));
  exit;
}

if (isset($_GET['dismiss_missing'])) {
  unset($_SESSION['sync_missing_' . $building]);
  header('Location: manage-users.php?building=' . urlencode($building));
  exit;
}

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if (isset($_GET['logout'])) {
  unset($_SESSION[$sessionKey]);
  header('Location: manage-users.php?building=' . urlencode($building));
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
    header('Location: manage-users.php?building=' . urlencode($building));
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
  <title><?= htmlspecialchars($buildLabel) ?> – Resident Admin</title>
  <style>
    body      { font-family: sans-serif; max-width: 360px; margin: 4rem auto; padding: 0 1rem; }
    h1        { font-size: 1.3rem; margin-bottom: 0.25rem; }
    .subtitle { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label     { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text], input[type=password] { width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
    button    { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    button:hover { background: #005bb5; }
    .error    { color: red; font-size: 0.9rem; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>
  <div class="subtitle">Resident management — admin login required</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post">
    <label for="admin_user">Username</label>
    <input type="text" id="admin_user" name="admin_user" autocomplete="username" autocapitalize="none" autofocus>
    <label for="admin_pass">Password</label>
    <input type="password" id="admin_pass" name="admin_pass" autocomplete="current-password">
    <button type="submit">Log in</button>
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
// Helpers
// -------------------------------------------------------
function generateTempPassword(int $length = 10): string {
  $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $result = '';
  for ($i = 0; $i < $length; $i++) {
    $result .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $result;
}

function makeUsername(string $firstName, string $lastName): string {
  $first = strtolower(preg_replace('/[^a-zA-Z]/', '', $firstName));
  $last  = strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName));
  return substr($first, 0, 1) . $last;
}

function uniqueUsername(string $base, array $existingUsers): string {
  $taken = array_column($existingUsers, 'user');
  if (!in_array($base, $taken)) return $base;
  $i = 2;
  while (in_array($base . $i, $taken)) $i++;
  return $base . $i;
}

function isLinkedToDatabase(string $webUsername, array $owners): bool {
  foreach ($owners as $owner) {
    $base = makeUsername($owner['firstName'], $owner['lastName']);
    if ($webUsername === $base || preg_match('/^' . preg_quote($base, '/') . '\d+$/', $webUsername)) {
      return true;
    }
  }
  return false;
}

function loadLoginStats(string $building): array {
  $file = CREDENTIALS_DIR . 'login_stats.json';
  if (!file_exists($file)) return [];
  $all = json_decode(file_get_contents($file), true) ?? [];
  return $all[$building] ?? [];
}

function loginCount(array $userStats, int $days): int {
  $cutoff = date('Y-m-d', strtotime("-{$days} days"));
  $total  = 0;
  foreach ($userStats as $date => $count) {
    if ($date >= $cutoff) $total += $count;
  }
  return $total;
}

function loadUsers(string $building): array {
  $file = CREDENTIALS_DIR . $building . '.json';
  if (!file_exists($file)) return [];
  return json_decode(file_get_contents($file), true) ?? [];
}

function saveUsers(string $building, array $users): bool {
  $dir = CREDENTIALS_DIR;
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
  $result = file_put_contents(
    $dir . $building . '.json',
    json_encode(array_values($users), JSON_PRETTY_PRINT)
  );
  return $result !== false;
}

// -------------------------------------------------------
// Handle actions
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['add_user'])) {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === '' || $pass === '') {
      $message = 'Username and password are required.';
      $messageType = 'error';
    } else {
      $users       = loadUsers($building);
      $existingIdx = null;
      foreach ($users as $i => $u) {
        if ($u['user'] === $user) { $existingIdx = $i; break; }
      }

      // Update existing or create new account
      $newHash = password_hash($pass, PASSWORD_DEFAULT);
      if ($existingIdx !== null) {
        $users[$existingIdx]['pass'] = $newHash;
      } else {
        $users[] = ['user' => $user, 'pass' => $newHash];
        $existingIdx = array_key_last($users);
      }

      // Try to email the temp password via Apps Script (works for both new and existing residents)
      $webAppURL = $buildings[$building]['webAppURL'] ?? '';
      $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $dir       = rtrim(dirname($_SERVER['PHP_SELF']), '/');
      $loginURL  = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir
                 . '/display-private-dir.php?building=' . urlencode($building);

      $emailSent = false;
      if ($webAppURL) {
        $resetURL  = $webAppURL
                   . '?page=resetpw'
                   . '&token='    . urlencode(OWNER_IMPORT_TOKEN)
                   . '&username=' . urlencode($user)
                   . '&building=' . urlencode($building)
                   . '&tmppw='    . urlencode($pass)
                   . '&loginurl=' . urlencode($loginURL);
        $ctx       = stream_context_create(['http' => ['timeout' => 10]]);
        $response  = @file_get_contents($resetURL, false, $ctx);
        if ($response !== false) {
          $data      = json_decode($response, true);
          $emailSent = ($data['status'] ?? '') === 'ok';
        }
      }

      if ($emailSent) {
        $users[$existingIdx]['mustChange'] = true;
        $message      = "Account set for \"$user\" — temporary password emailed to resident.";
      } else {
        unset($users[$existingIdx]['mustChange']);
        $message      = "Account set for \"$user\". No email sent (not found in association database).";
      }
      if (!saveUsers($building, $users)) {
        $message      = 'Could not save — check that the credentials/ folder exists on the server and is writable.';
        $messageType  = 'error';
      }
    }
  }

  elseif (isset($_POST['remove_user'])) {
    $user  = $_POST['username'] ?? '';
    $users = loadUsers($building);
    $users = array_filter($users, fn($u) => $u['user'] !== $user);
    saveUsers($building, $users);
    $message = "Resident \"$user\" removed.";
  }

  elseif (isset($_POST['sync_only'])) {
    if ($useLocalDB) {
      $dbResult = dbListDatabase($building);
      $owners = array_map(fn($r) => [
        'firstName' => $r['First Name'],
        'lastName'  => $r['Last Name'],
      ], $dbResult['rows'] ?? []);
      $data = ['owners' => $owners];
      $syncError = false;
    } else {
      $webAppURL = $buildings[$building]['webAppURL'] ?? '';
      if (!$webAppURL) {
        $message = 'No webAppURL configured for this building.';
        $messageType = 'error';
        $syncError = true;
      } else {
        $url      = $webAppURL . '?page=owners&token=' . urlencode(OWNER_IMPORT_TOKEN);
        $ctx      = stream_context_create(['http' => ['timeout' => 30]]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
          $message = 'Could not reach the Google Sheet. Check the webAppURL for this building.';
          $messageType = 'error';
          $syncError = true;
        } else {
          $data = json_decode($response, true);
          if (!empty($data['error'])) {
            $message = 'Sheet error: ' . $data['error'];
            $messageType = 'error';
            $syncError = true;
          } else {
            $syncError = false;
          }
        }
      }
    }
    if (!($syncError ?? false)) {
      $users    = loadUsers($building);
      $existing = array_column($users, 'user');

      // Orphans: web accounts with no matching database record
      $orphans = [];
      foreach ($users as $u) {
        if (!isLinkedToDatabase($u['user'], $data['owners'])) {
          $orphans[] = $u['user'];
        }
      }
      $_SESSION['sync_orphans_' . $building] = $orphans;

      // Missing: database residents with no web account
      $missing = [];
      $taken   = [];
      foreach ($data['owners'] as $owner) {
        $firstName = $owner['firstName'] ?? '';
        $lastName  = $owner['lastName']  ?? '';
        if (!$lastName) continue;
        $base = makeUsername($firstName, $lastName);
        $taken[$base] = ($taken[$base] ?? 0) + 1;
        $uname = $taken[$base] === 1 ? $base : $base . $taken[$base];
        if (!in_array($uname, $existing)) {
          $missing[] = ['user' => $uname, 'firstName' => $firstName, 'lastName' => $lastName];
        }
      }
      $_SESSION['sync_missing_' . $building] = $missing;

      $parts = [];
      if (count($orphans) > 0) $parts[] = count($orphans) . ' orphaned account(s) found';
      if (count($missing) > 0) $parts[] = count($missing) . ' database resident(s) missing a web account';
      $message = 'Sync complete' . (count($parts) ? ' — ' . implode('; ', $parts) . '. Review below.' : ' — everything looks good.');
    }
  }

  elseif (isset($_POST['recreate_missing'])) {
    $toRecreate  = array_filter($_POST['recreate_list'] ?? [], fn($u) => $u !== '');
    $missingData = $_SESSION['sync_missing_' . $building] ?? [];
    if ($toRecreate) {
      $users     = loadUsers($building);
      $webAppURL = $buildings[$building]['webAppURL'] ?? '';
      $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $dir       = rtrim(dirname($_SERVER['PHP_SELF']), '/');
      $loginURL  = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir
                 . '/display-private-dir.php?building=' . urlencode($building);
      $added     = 0;
      $emailed   = 0;
      foreach ($missingData as $m) {
        if (!in_array($m['user'], $toRecreate)) continue;
        $tmpPw    = generateTempPassword();
        $tmpHash  = password_hash($tmpPw, PASSWORD_DEFAULT);
        $users[]  = ['user' => $m['user'], 'pass' => $tmpHash, 'mustChange' => true];
        $added++;
        // Email the new temp password via Apps Script (skipped in DB mode)
        if (!$useLocalDB && $webAppURL) {
          $resetURL = $webAppURL
                    . '?page=resetpw'
                    . '&token='    . urlencode(OWNER_IMPORT_TOKEN)
                    . '&username=' . urlencode($m['user'])
                    . '&building=' . urlencode($building)
                    . '&tmppw='    . urlencode($tmpPw)
                    . '&loginurl=' . urlencode($loginURL);
          $ctx  = stream_context_create(['http' => ['timeout' => 10]]);
          $resp = @file_get_contents($resetURL, false, $ctx);
          if ($resp !== false && (json_decode($resp, true)['status'] ?? '') === 'ok') {
            $emailed++;
          }
        }
      }
      saveUsers($building, $users);
      $noEmail = $added - $emailed;
      $message = "$added account(s) recreated; $emailed welcome email(s) sent."
               . ($noEmail > 0 ? " $noEmail could not be emailed (no email address on file — use Add/Reset to set a password manually)." : '');
    }
    unset($_SESSION['sync_missing_' . $building]);
  }

  elseif (isset($_POST['remove_orphans'])) {
    $toRemove = array_filter($_POST['remove_list'] ?? [], fn($u) => $u !== '');
    if ($toRemove) {
      $users = loadUsers($building);
      $users = array_filter($users, fn($u) => !in_array($u['user'], $toRemove));
      saveUsers($building, $users);
      $count   = count($toRemove);
      $message = "$count account(s) removed during sync.";
    }
    unset($_SESSION['sync_orphans_' . $building]);
  }

  elseif (isset($_POST['change_pass'])) {
    $user    = $_POST['username'] ?? '';
    $newpass = $_POST['new_password'] ?? '';

    if ($newpass === '') {
      $message = 'New password cannot be empty.';
      $messageType = 'error';
    } else {
      $users = loadUsers($building);
      $found = false;
      foreach ($users as &$u) {
        if ($u['user'] === $user) {
          $u['pass'] = password_hash($newpass, PASSWORD_DEFAULT);
          $found = true;
          break;
        }
      }
      unset($u);
      if ($found) {
        saveUsers($building, $users);
        $message = "Password updated for \"$user\".";
      } else {
        $message = "Resident \"$user\" not found.";
        $messageType = 'error';
      }
    }
  }

  // PRG: store result in session and redirect so a browser refresh doesn't re-submit
  $_SESSION['flash_message'] = $message;
  $_SESSION['flash_type']    = $messageType;
  header('Location: manage-users.php?building=' . urlencode($building));
  exit;
}

// Read flash message left by a POST redirect
$message     = '';
$messageType = 'ok';
if (isset($_SESSION['flash_message'])) {
  $message     = $_SESSION['flash_message'];
  $messageType = $_SESSION['flash_type'] ?? 'ok';
  unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$users = loadUsers($building);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Resident Admin</title>
  <style>
    body           { font-family: sans-serif; max-width: 700px; margin: 2rem auto; padding: 0 1rem; }
    .top-bar       { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    h1             { margin: 0; font-size: 1.4rem; }
    .logout-btn    { font-size: 0.9rem; color: #0070f3; text-decoration: none; }
    .logout-btn:hover { text-decoration: underline; }
    .message       { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    table          { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    th, td         { text-align: left; padding: 0.5rem 0.6rem; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    th             { color: #999; font-weight: normal; font-size: 0.8rem; text-transform: uppercase; }
    .empty         { color: #999; font-style: italic; font-size: 0.9rem; margin-bottom: 1.5rem; }
    .action-form   { display: inline; }
    .remove-btn    { padding: 0.25rem 0.6rem; background: #fff; border: 1px solid #ddd; border-radius: 4px;
                     font-size: 0.8rem; cursor: pointer; color: #c00; }
    .remove-btn:hover { background: #fff0f0; border-color: #c00; }
    .change-btn    { padding: 0.25rem 0.6rem; background: #fff; border: 1px solid #ddd; border-radius: 4px;
                     font-size: 0.8rem; cursor: pointer; color: #333; margin-right: 0.4rem; }
    .change-btn:hover { background: #f5f5f5; }
    .pass-row      { display: none; }
    .pass-row.open { display: table-row; }
    .pass-row td   { background: #fafafa; }
    .pass-inline   { display: flex; gap: 0.5rem; align-items: center; }
    .pass-inline input[type=password] { padding: 0.3rem 0.5rem; border: 1px solid #ccc;
                     border-radius: 4px; font-size: 0.85rem; width: 200px; }
    .save-btn      { padding: 0.3rem 0.7rem; background: #0070f3; color: #fff; border: none;
                     border-radius: 4px; font-size: 0.85rem; cursor: pointer; }
    .cancel-btn    { padding: 0.3rem 0.7rem; background: #fff; border: 1px solid #ccc;
                     border-radius: 4px; font-size: 0.85rem; cursor: pointer; color: #333; }
    h2             { font-size: 1rem; margin: 0 0 0.75rem; }
    .add-form      { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .add-form input[type=text], .add-form input[type=password] {
                     padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px;
                     font-size: 0.9rem; width: 180px; }
    .add-btn       { padding: 0.4rem 0.9rem; background: #0070f3; color: #fff; border: none;
                     border-radius: 4px; font-size: 0.9rem; cursor: pointer; }
    .add-btn:hover { background: #005bb5; }
    .sync-panel    { background: #fff8e1; border: 1px solid #f0c000; border-radius: 6px;
                     padding: 1rem 1.2rem; margin-bottom: 1.5rem; }
    .sync-panel h2 { color: #7a5800; margin-bottom: 0.4rem; }
    .sync-panel p  { font-size: 0.85rem; color: #7a5800; margin: 0 0 0.75rem; }
    .sync-list     { list-style: none; padding: 0; margin: 0 0 0.75rem; }
    .sync-list li  { display: flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0;
                     font-size: 0.9rem; border-bottom: 1px solid #f0e080; }
    .sync-list li:last-child { border-bottom: none; }
    .sync-actions  { display: flex; gap: 0.75rem; align-items: center; margin-top: 0.75rem; }
    .remove-checked-btn { padding: 0.4rem 0.9rem; background: #c00; color: #fff; border: none;
                     border-radius: 4px; font-size: 0.9rem; cursor: pointer; }
    .remove-checked-btn:hover { background: #900; }
    .keep-all-link { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .keep-all-link:hover { text-decoration: underline; }
    .sync-desc     { font-size: 0.85rem; color: #666; margin: 0 0 0.75rem; }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4);
                     display: flex; align-items: center; justify-content: center; z-index: 100; }
    .modal-box     { background: #fff; border-radius: 8px; padding: 1.5rem; max-width: 460px; width: 90%;
                     box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
    .modal-box h3  { margin: 0 0 0.75rem; font-size: 1.05rem; }
    .modal-box p   { font-size: 0.875rem; color: #555; margin: 0 0 0.5rem; }
    .modal-box ul  { font-size: 0.875rem; color: #555; margin: 0 0 1.25rem; padding-left: 1.25rem; }
    .modal-box ul li { margin-bottom: 0.3rem; }
    .modal-actions { display: flex; gap: 0.75rem; }
    .btn-proceed   { padding: 0.5rem 1.2rem; background: #0070f3; color: #fff; border: none;
                     border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
    .btn-proceed:hover { background: #005bb5; }
    .btn-cancel    { padding: 0.5rem 1.2rem; background: #fff; border: 1px solid #ccc;
                     border-radius: 4px; font-size: 0.95rem; cursor: pointer; color: #333; }
    .btn-cancel:hover { background: #f5f5f5; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Resident Admin</h1>
  <div style="display:flex;gap:0.75rem;align-items:center;">
    <a href="admin.php?building=<?= urlencode($building) ?>" class="logout-btn">← Admin</a>
    <a href="?building=<?= urlencode($building) ?>&logout=1" class="logout-btn">Log out</a>
  </div>
</div>

<?php if ($message): ?>
  <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<h2>Add / Reset Resident</h2>
<p style="font-size:0.85rem;color:#666;margin-bottom:0.75rem;">
  If the username already exists, the password is updated and a temporary password email is sent to the resident (they must change it on next login).
  If the username is new, the account is created as-is.
</p>
<form class="add-form" method="post">
  <input type="text"     name="username" placeholder="Username"    autocomplete="off" autocapitalize="none">
  <input type="password" name="password" placeholder="Password"    autocomplete="new-password">
  <button type="submit" name="add_user" class="add-btn">Add / Reset</button>
</form>

<hr style="margin:2rem 0;border:none;border-top:1px solid #eee;">

<h2>Sync</h2>
<p class="sync-desc">
  Compares all web login accounts against the association database in both directions.
  <strong>Orphans</strong> — web accounts with no matching database resident (e.g. someone moved out) — are flagged for removal.
  <strong>Missing accounts</strong> — database residents with no web account (e.g. accidentally deleted) — are flagged for recreation.
  <strong>Run this whenever a resident moves out</strong> and periodically as a routine check.
  You review and confirm all changes before anything is modified.
</p>
<form method="post" action="manage-users.php?building=<?= urlencode($building) ?>">
  <input type="hidden" name="sync_only" value="1">
  <button type="submit" class="add-btn"
          onclick="return confirm('Compare web accounts against the database?\n\nYou will review all changes before anything is modified.')">Sync Now</button>
</form>

<div id="mu-confirm-overlay" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3 id="mu-confirm-title"></h3>
    <p id="mu-confirm-body"></p>
    <div class="modal-actions">
      <button id="mu-confirm-proceed" class="btn-proceed" style="background:#c00;"
              onmouseover="this.style.background='#900'" onmouseout="this.style.background='#c00'"
              onclick="proceedMuConfirm()"></button>
      <button class="btn-cancel" onclick="closeMuConfirm()">Cancel</button>
    </div>
  </div>
</div>

<hr style="margin:2rem 0;border:none;border-top:1px solid #eee;">

<?php
$syncOrphans = $_SESSION['sync_orphans_' . $building] ?? null;
if ($syncOrphans !== null && count($syncOrphans) > 0):
?>
<div class="sync-panel">
  <h2>Sync Check — <?= count($syncOrphans) ?> account(s) not in database</h2>
  <p>The following web accounts have no matching resident in the association database.
     Check the ones you want to remove, then click Remove. Uncheck anyone you want to keep.</p>
  <form method="post">
    <ul class="sync-list">
      <?php foreach ($syncOrphans as $orphan): ?>
      <li>
        <input type="checkbox" name="remove_list[]" value="<?= htmlspecialchars($orphan) ?>" id="orphan-<?= htmlspecialchars($orphan) ?>" checked>
        <label for="orphan-<?= htmlspecialchars($orphan) ?>"><?= htmlspecialchars($orphan) ?></label>
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="sync-actions">
      <input type="hidden" name="remove_orphans" value="1">
      <button type="submit" class="remove-checked-btn"
              onclick="this.disabled=true;this.textContent='Removing\u2026'">Remove checked</button>
      <a href="?building=<?= urlencode($building) ?>&dismiss_orphans=1" class="keep-all-link">Keep all / dismiss</a>
    </div>
  </form>
</div>
<?php elseif ($syncOrphans !== null && count($syncOrphans) === 0): ?>
<div class="message ok" style="margin-bottom:1.5rem;">No orphaned accounts found.</div>
<?php endif; ?>

<?php
$syncMissing = $_SESSION['sync_missing_' . $building] ?? null;
if ($syncMissing !== null && count($syncMissing) > 0):
?>
<div class="sync-panel" style="border-color:#93c5fd;background:#eff6ff;">
  <h2 style="color:#1e40af;">Missing Accounts — <?= count($syncMissing) ?> resident(s) in database with no web account</h2>
  <p style="color:#1e40af;">These residents exist in the association database but have no web login. This may be due to an accidental deletion or a resident added without an email address. Check the ones you want to recreate — a temporary password will be generated automatically and a welcome email sent to each resident.</p>
  <form method="post">
    <ul class="sync-list" style="border-color:#bfdbfe;">
      <?php foreach ($syncMissing as $m): ?>
      <li style="border-color:#bfdbfe;">
        <input type="checkbox" name="recreate_list[]" value="<?= htmlspecialchars($m['user']) ?>"
               id="missing-<?= htmlspecialchars($m['user']) ?>" checked>
        <label for="missing-<?= htmlspecialchars($m['user']) ?>">
          <?= htmlspecialchars($m['user']) ?>
          <span style="color:#64748b;font-size:0.82rem;">(<?= htmlspecialchars($m['firstName'] . ' ' . $m['lastName']) ?>)</span>
        </label>
      </li>
      <?php endforeach; ?>
    </ul>
    <div class="sync-actions">
      <input type="hidden" name="recreate_missing" value="1">
      <button type="submit" class="add-btn" style="background:#1d4ed8;"
              onclick="this.disabled=true;this.textContent='Creating accounts\u2026'">Recreate checked</button>
      <a href="?building=<?= urlencode($building) ?>&dismiss_missing=1" class="keep-all-link">Dismiss</a>
    </div>
  </form>
</div>
<?php elseif ($syncMissing !== null && count($syncMissing) === 0): ?>
<div class="message ok" style="margin-bottom:1.5rem;">All database residents have web accounts.</div>
<?php endif; ?>

<?php
  $loginStats = loadLoginStats($building);
?>
<?php if (empty($users)): ?>
  <p class="empty">No users yet.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Username</th>
        <th style="text-align:center" title="Logins in the last 30 days">30 days</th>
        <th style="text-align:center" title="Logins in the last 12 months">12 months</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u):
        $uid      = htmlspecialchars($u['user']);
        $uStats   = $loginStats[$u['user']] ?? [];
        $cnt30    = loginCount($uStats, 30);
        $cnt365   = loginCount($uStats, 365);
      ?>
      <tr>
        <td><?= htmlspecialchars($u['user']) ?><?php if (!empty($u['mustChange'])): ?> <span style="font-size:0.75rem;color:#b45309;font-weight:600;">pw reset</span><?php endif; ?></td>
        <td style="text-align:center;color:<?= $cnt30 > 0 ? '#1a7f37' : '#bbb' ?>"><?= $cnt30 ?: '—' ?></td>
        <td style="text-align:center;color:<?= $cnt365 > 0 ? '#555' : '#bbb' ?>"><?= $cnt365 ?: '—' ?></td>
        <td>
          <button type="button" class="change-btn" onclick="togglePass('<?= $uid ?>')">Change password</button>
          <form class="action-form" method="post" id="remove-form-<?= $uid ?>">
            <input type="hidden" name="username" value="<?= htmlspecialchars($u['user']) ?>">
            <input type="hidden" name="remove_user" value="1">
            <button type="button" class="remove-btn"
              data-user="<?= htmlspecialchars($u['user']) ?>"
              data-form="remove-form-<?= $uid ?>"
              onclick="confirmRemoveUser(this)">Remove</button>
          </form>
        </td>
      </tr>
      <tr class="pass-row" id="pass-<?= $uid ?>">
        <td colspan="4">
          <form class="pass-inline" method="post">
            <input type="hidden" name="username" value="<?= htmlspecialchars($u['user']) ?>">
            <input type="password" name="new_password" placeholder="New password" autocomplete="new-password">
            <button type="submit" name="change_pass" class="save-btn">Save</button>
            <button type="button" class="cancel-btn" onclick="togglePass('<?= $uid ?>')">Cancel</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<script>
// ---- Confirm modal ----
var confirmCb = null;
function showConfirm(title, bodyHtml, proceedLabel, onProceed) {
  document.getElementById('mu-confirm-title').textContent = title;
  document.getElementById('mu-confirm-body').innerHTML    = bodyHtml;
  document.getElementById('mu-confirm-proceed').textContent = proceedLabel;
  confirmCb = onProceed;
  document.getElementById('mu-confirm-overlay').style.display = 'flex';
}
function closeMuConfirm() {
  document.getElementById('mu-confirm-overlay').style.display = 'none';
  confirmCb = null;
}
function proceedMuConfirm() {
  var cb = confirmCb;
  closeMuConfirm();
  if (cb) cb();
}

function confirmRemoveUser(btn) {
  var user   = btn.getAttribute('data-user');
  var formId = btn.getAttribute('data-form');
  showConfirm(
    'Remove ' + user + '?',
    'This will permanently delete the web login for <strong>' + user + '</strong>. The resident will no longer be able to log in.',
    'Remove',
    function() { document.getElementById(formId).submit(); }
  );
}

function togglePass(uid) {
  var row = document.getElementById('pass-' + uid);
  row.classList.toggle('open');
}

</script>
</body>
</html>
