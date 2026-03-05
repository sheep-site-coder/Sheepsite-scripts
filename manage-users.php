<?php
// -------------------------------------------------------
// manage-users.php
// Place this file on sheepsite.com/Scripts/
//
// Each building has its own admin URL, username, and password:
//   https://sheepsite.com/Scripts/manage-users.php?building=cvelyndhursth
//
// The master credentials can access any building.
// Change all passwords before deploying.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

// Master override — can access any building
define('MASTER_USER', 'sheepsite');
define('MASTER_PASS', 'LeMaster');

$buildings = [
  'QGscratch'     => ['adminUser' => 'admin', 'adminPass' => 'AlainQG'],
  'cvelyndhursth' => ['adminUser' => 'admin', 'adminPass' => 'u8ssLJAX'],
  'cvelyndhursti' => ['adminUser' => 'admin', 'adminPass' => 'xttMQ4nX'],
  // add more buildings here...
];

// -------------------------------------------------------
// Validate building parameter
// -------------------------------------------------------
$building = $_GET['building'] ?? '';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey = 'manage_auth_' . $building;
$adminUser  = $buildings[$building]['adminUser'];
$adminPass  = $buildings[$building]['adminPass'];

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

  $isMaster   = ($submittedUser === MASTER_USER && $submittedPass === MASTER_PASS);
  $isBuilding = ($submittedUser === $adminUser   && $submittedPass === $adminPass);

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
  <title><?= htmlspecialchars($buildLabel) ?> – Manage Users</title>
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
  <div class="subtitle">User management — admin login required</div>

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
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
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
      $users = loadUsers($building);
      foreach ($users as $u) {
        if ($u['user'] === $user) {
          $message = "User \"$user\" already exists.";
          $messageType = 'error';
          break;
        }
      }
      if ($messageType !== 'error') {
        $users[] = ['user' => $user, 'pass' => password_hash($pass, PASSWORD_DEFAULT)];
        if (saveUsers($building, $users)) {
          $message = "User \"$user\" added.";
        } else {
          $message = 'Could not save — check that the credentials/ folder exists on the server and is writable.';
          $messageType = 'error';
        }
      }
    }
  }

  elseif (isset($_POST['remove_user'])) {
    $user  = $_POST['username'] ?? '';
    $users = loadUsers($building);
    $users = array_filter($users, fn($u) => $u['user'] !== $user);
    saveUsers($building, $users);
    $message = "User \"$user\" removed.";
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
        $message = "User \"$user\" not found.";
        $messageType = 'error';
      }
    }
  }
}

$users = loadUsers($building);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Manage Users</title>
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
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Users</h1>
  <a href="?building=<?= urlencode($building) ?>&logout=1" class="logout-btn">Log out</a>
</div>

<?php if ($message): ?>
  <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
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

<h2>Add user</h2>
<form class="add-form" method="post">
  <input type="text"     name="username" placeholder="Username"    autocomplete="off">
  <input type="password" name="password" placeholder="Password"    autocomplete="new-password">
  <button type="submit" name="add_user" class="add-btn">Add</button>
</form>

<script>
function togglePass(uid) {
  var row = document.getElementById('pass-' + uid);
  row.classList.toggle('open');
}
</script>
</body>
</html>
