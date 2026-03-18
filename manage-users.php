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
if (isset($_GET['dismiss_sync'])) {
  unset($_SESSION['sync_orphans_' . $building]);
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
    <input type="text" id="admin_user" name="admin_user" autocomplete="username" autofocus>
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
$alertMessage = '';

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
        $response  = @file_get_contents($resetURL);
        if ($response !== false) {
          $data      = json_decode($response, true);
          $emailSent = ($data['status'] ?? '') === 'ok';
        }
      }

      if ($emailSent) {
        $users[$existingIdx]['mustChange'] = true;
        $message      = "Account set for \"$user\" — temporary password emailed to resident.";
        $alertMessage = "Account set for \"$user\".\n\nA temporary password was emailed to the resident. They will be required to change it on next login.";
      } else {
        unset($users[$existingIdx]['mustChange']);
        $message      = "Account set for \"$user\". No email sent (not found in association database).";
        $alertMessage = "Account set for \"$user\".\n\nNo email was sent — this person was not found in the association database.";
      }
      if (!saveUsers($building, $users)) {
        $message      = 'Could not save — check that the credentials/ folder exists on the server and is writable.';
        $messageType  = 'error';
        $alertMessage = '';
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

  elseif (isset($_POST['import_owners'])) {
    $tempPass = $_POST['temp_password'] ?? '';
    if (strlen($tempPass) < 8) {
      $message = 'Temporary password must be at least 8 characters.';
      $messageType = 'error';
    } else {
      $webAppURL = $buildings[$building]['webAppURL'] ?? '';
      if (!$webAppURL) {
        $message = 'No webAppURL configured for this building.';
        $messageType = 'error';
      } else {
        $url      = $webAppURL . '?page=owners&token=' . urlencode(OWNER_IMPORT_TOKEN);
        $response = @file_get_contents($url);
        if ($response === false) {
          $message = 'Could not reach the Google Sheet. Check the webAppURL for this building.';
          $messageType = 'error';
        } else {
          $data = json_decode($response, true);
          if (!empty($data['error'])) {
            $message = 'Sheet error: ' . $data['error'];
            $messageType = 'error';
          } else {
            $users       = loadUsers($building);
            $tempHash    = password_hash($tempPass, PASSWORD_DEFAULT);
            $added       = 0;
            $skipped     = 0;
            foreach ($data['owners'] as $owner) {
              $base     = makeUsername($owner['firstName'], $owner['lastName']);
              $existing = array_column($users, 'user');
              // Skip if an account with this base name already exists
              if (in_array($base, $existing)) {
                $skipped++;
                continue;
              }
              $username = uniqueUsername($base, $users);
              $users[]  = ['user' => $username, 'pass' => $tempHash, 'mustChange' => true];
              $added++;
            }
            // Find web users not linked to any database record
            $orphans = [];
            foreach ($users as $u) {
              if (!isLinkedToDatabase($u['user'], $data['owners'])) {
                $orphans[] = $u['user'];
              }
            }
            $_SESSION['sync_orphans_' . $building] = $orphans;

            if (saveUsers($building, $users)) {
              $message = "$added account(s) created, $skipped skipped (already exist). "
                       . "Distribute the temporary password to residents — they will be required to change it on first login.";
            } else {
              $message = 'Could not save credentials file.';
              $messageType = 'error';
            }
          }
        }
      }
    }
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
  $_SESSION['flash_alert']   = $alertMessage;
  header('Location: manage-users.php?building=' . urlencode($building));
  exit;
}

// Read flash message left by a POST redirect
$message      = '';
$messageType  = 'ok';
$alertMessage = '';
if (isset($_SESSION['flash_message'])) {
  $message      = $_SESSION['flash_message'];
  $messageType  = $_SESSION['flash_type']  ?? 'ok';
  $alertMessage = $_SESSION['flash_alert'] ?? '';
  unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['flash_alert']);
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
  <input type="text"     name="username" placeholder="Username"    autocomplete="off">
  <input type="password" name="password" placeholder="Password"    autocomplete="new-password">
  <button type="submit" name="add_user" class="add-btn">Add / Reset</button>
</form>

<hr style="margin:2rem 0;border:none;border-top:1px solid #eee;">

<h2>Import / Sync from Association Database Sheet</h2>
<p style="font-size:0.85rem;color:#666;margin-bottom:0.75rem;">
  Creates accounts for all residents in the Database tab who don't have one yet.
  Username = first initial + last name. All new accounts will require a password change on first login.
  Also checks for web accounts that no longer exist in the database and prompts you to remove them.
</p>
<form class="add-form" method="post">
  <input type="password" name="temp_password" placeholder="Temporary password (8+ chars)" autocomplete="new-password" style="width:260px;">
  <button type="submit" name="import_owners" class="add-btn"
          onclick="return confirm('Import residents from the Google Sheet and sync accounts with this temporary password?')">Import / Sync</button>
</form>

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
      <button type="submit" name="remove_orphans" class="remove-checked-btn">Remove checked</button>
      <a href="?building=<?= urlencode($building) ?>&dismiss_sync=1" class="keep-all-link">Keep all / dismiss</a>
    </div>
  </form>
</div>
<?php elseif ($syncOrphans !== null && count($syncOrphans) === 0): ?>
<div class="message ok" style="margin-bottom:1.5rem;">All web accounts are in sync with the database.</div>
<?php endif; ?>

<?php if (empty($users)): ?>
  <p class="empty">No users yet.</p>
<?php else: ?>
  <table>
    <thead>
      <tr><th>Username</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u):
        $uid = htmlspecialchars($u['user']);
      ?>
      <tr>
        <td><?= htmlspecialchars($u['user']) ?></td>
        <td>
          <button type="button" class="change-btn" onclick="togglePass('<?= $uid ?>')">Change password</button>
          <form class="action-form" method="post"
                onsubmit="return confirm('Remove <?= htmlspecialchars($u['user']) ?>?')">
            <input type="hidden" name="username" value="<?= htmlspecialchars($u['user']) ?>">
            <button type="submit" name="remove_user" class="remove-btn">Remove</button>
          </form>
        </td>
      </tr>
      <tr class="pass-row" id="pass-<?= $uid ?>">
        <td colspan="2">
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
function togglePass(uid) {
  var row = document.getElementById('pass-' + uid);
  row.classList.toggle('open');
}
<?php if ($alertMessage): ?>
window.addEventListener('DOMContentLoaded', function() {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>
</body>
</html>
