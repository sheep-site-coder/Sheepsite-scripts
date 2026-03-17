<?php
// -------------------------------------------------------
// master-admin.php
// SheepSite operator admin panel — card hub.
//
//   https://sheepsite.com/Scripts/master-admin.php
//
// Auth: credentials/_master.json (bcrypt)
// Session shared with woolsy-admin.php and other sub-pages.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('SESSION_KEY',     'master_admin_auth');

// -------------------------------------------------------
// Load credentials
// -------------------------------------------------------
$masterCredFile = CREDENTIALS_DIR . '_master.json';
if (!file_exists($masterCredFile)) {
    die('<p style="color:red;">Master credentials not configured. Run setup-admin.php first.</p>');
}
$masterCred = json_decode(file_get_contents($masterCredFile), true);

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if (isset($_GET['logout'])) {
    unset($_SESSION[SESSION_KEY]);
    header('Location: master-admin.php');
    exit;
}

// -------------------------------------------------------
// Login — handle POST
// -------------------------------------------------------
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_pass'])) {
    $submittedUser = trim($_POST['master_user'] ?? '');
    $submittedPass = $_POST['master_pass'] ?? '';

    if ($submittedUser === $masterCred['user'] && password_verify($submittedPass, $masterCred['pass'])) {
        $_SESSION[SESSION_KEY] = true;
        header('Location: master-admin.php');
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

// -------------------------------------------------------
// Show login form if not authenticated
// -------------------------------------------------------
if (empty($_SESSION[SESSION_KEY])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SheepSite — Master Admin</title>
  <style>
    body       { font-family: sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
    h1         { margin-bottom: 0.25rem; font-size: 1.4rem; }
    .subtitle  { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label      { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text], input[type=password] {
                 width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                 border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
    .login-btn { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                 border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .login-btn:hover { background: #005bb5; }
    .error     { color: red; font-size: 0.9rem; margin-bottom: 1rem; }
  </style>
</head>
<body>
  <h1>SheepSite — Master Admin</h1>
  <div class="subtitle">Operator access only</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post">
    <label for="master_user">Username</label>
    <input type="text" id="master_user" name="master_user" autocomplete="username" autofocus>

    <label for="master_pass">Password</label>
    <input type="password" id="master_pass" name="master_pass" autocomplete="current-password">

    <button type="submit" class="login-btn">Log in</button>
  </form>
</body>
</html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SheepSite — Master Admin</title>
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
  </style>
</head>
<body>

<div class="top-bar">
  <h1>SheepSite — Master Admin</h1>
  <a href="master-admin.php?logout=1" class="logout">Log out</a>
</div>

<div class="subtitle">Operator management tools</div>

<a href="woolsy-admin.php" class="card">
  <div class="card-icon">🐑</div>
  <div>
    <div class="card-title">Woolsy Management</div>
    <div class="card-desc">
      View credit usage across all buildings, top up credit balances,
      and monitor Woolsy availability.
    </div>
  </div>
</a>

<!-- Future cards go here (Building Renewals, etc.) -->

</body>
</html>
