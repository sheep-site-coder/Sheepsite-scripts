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
  unset($_SESSION['sync_orphans_' . $building], $_SESSION['sync_missing_' . $building]);
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
            $users    = loadUsers($building);
            $tempHash = password_hash($tempPass, PASSWORD_DEFAULT);
            $added    = 0;
            $skipped  = 0;
            foreach ($data['owners'] as $owner) {
              $base     = makeUsername($owner['firstName'], $owner['lastName']);
              $existing = array_column($users, 'user');
              if (in_array($base, $existing)) { $skipped++; continue; }
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

  elseif (isset($_POST['import_csv'])) {
    $tempPass = $_POST['temp_password'] ?? '';
    $rowsJson = $_POST['csv_rows']      ?? '';
    if (strlen($tempPass) < 8) {
      $message = 'Temporary password must be at least 8 characters.';
      $messageType = 'error';
    } elseif (!$rowsJson) {
      $message = 'No CSV data received. Please select a file first.';
      $messageType = 'error';
    } else {
      $rows = json_decode($rowsJson, true);
      if (!$rows || !is_array($rows)) {
        $message = 'Invalid CSV data.';
        $messageType = 'error';
      } else {
        $users    = loadUsers($building);
        $tempHash = password_hash($tempPass, PASSWORD_DEFAULT);
        $added    = 0;
        $skipped  = 0;
        foreach ($rows as $row) {
          $firstName = trim($row['firstName'] ?? '');
          $lastName  = trim($row['lastName']  ?? '');
          if (!$firstName && !$lastName) continue;
          $base     = makeUsername($firstName, $lastName);
          $existing = array_column($users, 'user');
          if (in_array($base, $existing)) { $skipped++; continue; }
          $username = uniqueUsername($base, $users);
          $users[]  = ['user' => $username, 'pass' => $tempHash, 'mustChange' => true];
          $added++;
        }
        if (saveUsers($building, $users)) {
          $message = "$added account(s) created from CSV, $skipped skipped (already exist). "
                   . "Distribute the temporary password to residents — they will be required to change it on first login.";
        } else {
          $message = 'Could not save credentials file.';
          $messageType = 'error';
        }
      }
    }
  }

  elseif (isset($_POST['sync_only'])) {
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
          $users   = loadUsers($building);
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
        // Email the new temp password via Apps Script
        if ($webAppURL) {
          $resetURL = $webAppURL
                    . '?page=resetpw'
                    . '&token='    . urlencode(OWNER_IMPORT_TOKEN)
                    . '&username=' . urlencode($m['user'])
                    . '&building=' . urlencode($building)
                    . '&tmppw='    . urlencode($tmpPw)
                    . '&loginurl=' . urlencode($loginURL);
          $resp = @file_get_contents($resetURL);
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
    .bulk-section  { border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 1.5rem; }
    .bulk-subsec   { padding: 1rem 1.2rem; border-bottom: 1px solid #eee; }
    .bulk-subsec:last-child { border-bottom: none; }
    .bulk-subsec h3 { font-size: 0.95rem; margin: 0 0 0.35rem; }
    .subsec-desc   { font-size: 0.82rem; color: #666; margin: 0 0 0.75rem; }
    .drop-zone     { border: 2px dashed #ccc; border-radius: 6px; padding: 1rem;
                     text-align: center; color: #888; font-size: 0.85rem; cursor: pointer;
                     transition: border-color 0.15s, background 0.15s; margin-bottom: 0.75rem; }
    .drop-zone:hover    { border-color: #0070f3; color: #0070f3; }
    .drop-zone.drag-over { border-color: #0070f3; color: #0070f3; background: #f0f7ff; }
    .drop-zone.has-file  { border-color: #1a7f37; color: #1a7f37; background: #f0fff4; }
    .csv-preview-wrap   { margin-bottom: 0.75rem; overflow-x: auto; }
    .csv-preview   { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .csv-preview th { background: #f5f5f5; padding: 4px 8px; border: 1px solid #ddd;
                      font-weight: 600; white-space: nowrap; }
    .csv-preview td { padding: 4px 8px; border: 1px solid #eee; }
    .csv-preview tr:nth-child(even) td { background: #fafafa; }
    .csv-error     { color: #c00; font-size: 0.85rem; margin: 0 0 0.5rem; }
    .csv-count     { font-size: 0.82rem; color: #555; margin: 0.4rem 0 0.75rem; }
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

<h2>Bulk Account Management</h2>

<div class="bulk-section">

  <div class="bulk-subsec">
    <h3>Import from CSV</h3>
    <p class="subsec-desc">
      For new communities with existing resident records in any property management system.
      Export a CSV with at minimum <strong>First Name</strong> and <strong>Last Name</strong> columns
      (Unit #, Email, and Phone are also recognized if present). Usernames are generated automatically
      (first initial + last name). All new accounts will require a password change on first login.
      Existing accounts are skipped — safe to re-run.
    </p>
    <div id="csv-drop-zone" class="drop-zone">Drag a CSV file here, or click to browse</div>
    <input type="file" id="csv-file-input" accept=".csv,.txt" style="display:none">
    <div id="csv-preview-wrap" class="csv-preview-wrap" style="display:none"></div>
    <form class="add-form" method="post" id="csv-import-form">
      <input type="hidden" name="csv_rows" id="csv-rows-input">
      <input type="password" name="temp_password" placeholder="Temporary password (8+ chars)"
             autocomplete="new-password" style="width:260px;">
      <button type="submit" name="import_csv" id="csv-import-btn" class="add-btn" disabled
              onclick="return confirm('Create accounts for all CSV residents with this temporary password?')">Import from CSV</button>
    </form>
  </div>

  <div class="bulk-subsec">
    <h3>Import from Association Database Sheet</h3>
    <p class="subsec-desc">
      Creates accounts for any residents in the Google Sheet Database tab who don't have one yet.
      Useful when onboarding a building that already has a populated sheet, or as a one-time catch-up
      if accounts were not auto-created. Usernames are generated from first initial + last name.
      All new accounts will require a password change on first login. Existing accounts are skipped.
    </p>
    <form class="add-form" method="post">
      <input type="password" name="temp_password" placeholder="Temporary password (8+ chars)"
             autocomplete="new-password" style="width:260px;">
      <button type="submit" name="import_owners" class="add-btn"
              onclick="return confirm('Import residents from the Google Sheet with this temporary password?')">Import from Sheet</button>
    </form>
  </div>

  <div class="bulk-subsec">
    <h3>Sync — Find Orphaned or Missing Accounts</h3>
    <p class="subsec-desc">
      Compares all web login accounts against the association database in both directions.
      <strong>Orphans</strong> — web accounts with no matching database resident (e.g. someone moved out) — are flagged for removal.
      <strong>Missing accounts</strong> — database residents with no web account (e.g. accidentally deleted) — are flagged for recreation.
      <strong>Run this whenever a resident moves out</strong> and periodically as a routine check.
      You review and confirm all changes before anything is modified.
    </p>
    <form class="add-form" method="post">
      <button type="submit" name="sync_only" class="add-btn"
              onclick="return confirm('Check for orphaned or missing accounts?')">Sync Now</button>
    </form>
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
      <button type="submit" name="remove_orphans" class="remove-checked-btn">Remove checked</button>
      <a href="?building=<?= urlencode($building) ?>&dismiss_sync=1" class="keep-all-link">Keep all / dismiss</a>
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
      <button type="submit" name="recreate_missing" class="add-btn" style="background:#1d4ed8;">Recreate checked</button>
      <a href="?building=<?= urlencode($building) ?>&dismiss_sync=1" class="keep-all-link">Dismiss</a>
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
          <form class="action-form" method="post"
                onsubmit="return confirm('Remove <?= htmlspecialchars($u['user']) ?>?')">
            <input type="hidden" name="username" value="<?= htmlspecialchars($u['user']) ?>">
            <button type="submit" name="remove_user" class="remove-btn">Remove</button>
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
function togglePass(uid) {
  var row = document.getElementById('pass-' + uid);
  row.classList.toggle('open');
}
<?php if ($alertMessage): ?>
window.addEventListener('DOMContentLoaded', function() {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>

// ---- CSV Import ----
(function() {
  var dropZone    = document.getElementById('csv-drop-zone');
  var fileInput   = document.getElementById('csv-file-input');
  var previewWrap = document.getElementById('csv-preview-wrap');
  var rowsInput   = document.getElementById('csv-rows-input');
  var importBtn   = document.getElementById('csv-import-btn');
  if (!dropZone) return;

  dropZone.addEventListener('click', function() { fileInput.click(); });

  dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    dropZone.classList.add('drag-over');
  });
  dropZone.addEventListener('dragleave', function() {
    dropZone.classList.remove('drag-over');
  });
  dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    var file = e.dataTransfer.files[0];
    if (file) processFile(file);
  });
  fileInput.addEventListener('change', function() {
    if (fileInput.files[0]) processFile(fileInput.files[0]);
  });

  function processFile(file) {
    var reader = new FileReader();
    reader.onload = function(e) { parseCSV(e.target.result); };
    reader.readAsText(file);
    dropZone.textContent = '\u2713 ' + file.name;
    dropZone.classList.add('has-file');
  }

  function parseCSV(text) {
    var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n')
                    .filter(function(l) { return l.trim(); });
    if (lines.length < 2) { showError('CSV must have a header row and at least one data row.'); return; }

    var headers = parseLine(lines[0]).map(function(h) { return h.toLowerCase().trim(); });

    var colFirst = findCol(headers, ['first name','first','firstname','given name','given']);
    var colLast  = findCol(headers, ['last name','last','lastname','surname','family name','family']);
    var colUnit  = findCol(headers, ['unit','unit #','unit number','apt','apartment','suite']);
    var colEmail = findCol(headers, ['email','e-mail','email address','emailaddress']);
    var colPhone = findCol(headers, ['phone','phone 1','phone1','phone number','cell','mobile','telephone']);

    if (colFirst === -1 || colLast === -1) {
      showError('CSV must have "First Name" and "Last Name" columns.'); return;
    }

    var rows = [];
    for (var i = 1; i < lines.length; i++) {
      var cells = parseLine(lines[i]);
      var row = {
        firstName: cells[colFirst] || '',
        lastName:  cells[colLast]  || '',
        unit:      colUnit  !== -1 ? (cells[colUnit]  || '') : '',
        email:     colEmail !== -1 ? (cells[colEmail] || '') : '',
        phone:     colPhone !== -1 ? (cells[colPhone] || '') : ''
      };
      if (row.firstName || row.lastName) rows.push(row);
    }
    if (!rows.length) { showError('No valid rows found in CSV.'); return; }

    rowsInput.value = JSON.stringify(rows);
    renderPreview(rows);
    importBtn.disabled = false;
  }

  function findCol(headers, names) {
    for (var n = 0; n < names.length; n++) {
      var idx = headers.indexOf(names[n]);
      if (idx !== -1) return idx;
    }
    return -1;
  }

  function parseLine(line) {
    var result = [], cur = '', inQuote = false;
    for (var i = 0; i < line.length; i++) {
      var c = line[i];
      if (c === '"') {
        if (inQuote && line[i+1] === '"') { cur += '"'; i++; }
        else inQuote = !inQuote;
      } else if (c === ',' && !inQuote) {
        result.push(cur.trim()); cur = '';
      } else {
        cur += c;
      }
    }
    result.push(cur.trim());
    return result;
  }

  function renderPreview(rows) {
    var taken = {};
    var html = '<table class="csv-preview"><thead><tr>'
             + '<th>#</th><th>Username (preview)</th><th>First</th><th>Last</th>'
             + '<th>Unit</th><th>Email</th></tr></thead><tbody>';
    for (var i = 0; i < rows.length; i++) {
      var r    = rows[i];
      var base = (r.firstName.charAt(0) + r.lastName).toLowerCase().replace(/[^a-z]/g, '');
      taken[base] = (taken[base] || 0) + 1;
      var uname = taken[base] === 1 ? base : base + taken[base];
      html += '<tr><td>' + (i+1) + '</td><td><code>' + esc(uname || '?') + '</code></td>'
            + '<td>' + esc(r.firstName) + '</td><td>' + esc(r.lastName) + '</td>'
            + '<td>' + esc(r.unit) + '</td><td>' + esc(r.email) + '</td></tr>';
    }
    html += '</tbody></table>'
          + '<p class="csv-count">' + rows.length + ' resident(s) ready to import. '
          + 'Existing accounts will be skipped.</p>';
    previewWrap.innerHTML = html;
    previewWrap.style.display = 'block';
  }

  function showError(msg) {
    previewWrap.innerHTML = '<p class="csv-error">' + msg + '</p>';
    previewWrap.style.display = 'block';
    rowsInput.value = '';
    importBtn.disabled = true;
  }

  function esc(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
})();
</script>
</body>
</html>
