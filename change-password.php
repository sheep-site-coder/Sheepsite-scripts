<?php
// -------------------------------------------------------
// change-password.php
// Place this file on sheepsite.com/Scripts/
//
// Allows a logged-in owner to change their own password.
// Requires an active session from display-private-dir.php.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

// -------------------------------------------------------
// Validate building and session
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)
    || !file_exists(CREDENTIALS_DIR . $building . '.json')) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildLabel  = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey  = 'private_auth_' . $building;
$returnURL   = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

// Must be logged in via display-private-dir.php
if (empty($_SESSION[$sessionKey])) {
  header('Location: display-private-dir.php?building=' . urlencode($building)
       . ($returnURL ? '&return=' . urlencode($returnURL) : ''));
  exit;
}

$currentUser  = $_SESSION[$sessionKey];
$mustChange   = isset($_GET['mustchange']);
$redirectURL  = $_GET['redirect'] ?? '';
// Only allow relative redirects to our own scripts
if ($redirectURL && !preg_match('/^[a-zA-Z0-9_\-\.]+\.php[\?&]/', $redirectURL)) $redirectURL = '';
$dirURL       = 'display-private-dir.php?building=' . urlencode($building)
              . ($returnURL ? '&return=' . urlencode($returnURL) : '');

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function loadUsers(string $building): array {
  $file = CREDENTIALS_DIR . $building . '.json';
  if (!file_exists($file)) return [];
  return json_decode(file_get_contents($file), true) ?? [];
}

function saveUsers(string $building, array $users): bool {
  $result = file_put_contents(
    CREDENTIALS_DIR . $building . '.json',
    json_encode(array_values($users), JSON_PRETTY_PRINT)
  );
  return $result !== false;
}

// -------------------------------------------------------
// Handle form submission
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $currentPass = $_POST['current_password'] ?? '';
  $newPass     = $_POST['new_password']     ?? '';
  $confirmPass = $_POST['confirm_password'] ?? '';

  if ($newPass === '' || $currentPass === '') {
    $message = 'All fields are required.';
    $messageType = 'error';
  } elseif ($newPass !== $confirmPass) {
    $message = 'New passwords do not match.';
    $messageType = 'error';
  } elseif (strlen($newPass) < 8) {
    $message = 'New password must be at least 8 characters.';
    $messageType = 'error';
  } else {
    $users = loadUsers($building);
    $found = false;
    foreach ($users as &$u) {
      if ($u['user'] === $currentUser) {
        $found = true;
        if (!password_verify($currentPass, $u['pass'])) {
          $message = 'Current password is incorrect.';
          $messageType = 'error';
        } else {
          $u['pass'] = password_hash($newPass, PASSWORD_DEFAULT);
          unset($u['mustChange']);
          if (saveUsers($building, $users)) {
            if ($mustChange && $redirectURL) {
              header('Location: ' . $redirectURL);
              exit;
            }
            $message = 'Password updated successfully.';
          } else {
            $message = 'Could not save — please contact your administrator.';
            $messageType = 'error';
          }
        }
        break;
      }
    }
    unset($u);
    if (!$found) {
      $message = 'User not found — please contact your administrator.';
      $messageType = 'error';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Change Password</title>
  <style>
    body       { font-family: sans-serif; max-width: 420px; margin: 4rem auto; padding: 0 1rem; }
    .top-bar   { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
    h1         { margin: 0; font-size: 1.4rem; }
    .back-btn  { font-size: 0.9rem; color: #0070f3; text-decoration: none; }
    .back-btn:hover { text-decoration: underline; }
    .subtitle  { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label      { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=password] { width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                 border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
    .save-btn  { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                 border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .save-btn:hover { background: #005bb5; }
    .message   { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    .message.warn  { background: #fff8e1; color: #7a5800; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1>Change Password</h1>
  <a href="<?= htmlspecialchars($dirURL) ?>" class="back-btn">← Back to files</a>
</div>
<div class="subtitle">Logged in as <strong><?= htmlspecialchars($currentUser) ?></strong></div>

<?php if ($mustChange && !$message): ?>
  <div class="message warn">Your account was set up with a temporary password. Please choose a new password to continue.</div>
<?php endif; ?>
<?php if ($message): ?>
  <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($messageType !== 'ok' || !$message): ?>
<form method="post">
  <label for="current_password">Current password</label>
  <input type="password" id="current_password" name="current_password" autocomplete="current-password" autofocus>

  <label for="new_password">New password</label>
  <input type="password" id="new_password" name="new_password" autocomplete="new-password">

  <label for="confirm_password">Confirm new password</label>
  <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">

  <button type="submit" class="save-btn">Update password</button>
</form>
<?php endif; ?>

</body>
</html>
