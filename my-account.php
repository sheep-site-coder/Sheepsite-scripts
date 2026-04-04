<?php
// -------------------------------------------------------
// my-account.php
// Resident-facing account hub.
// Auth: private_auth_{building} session (same as display-private-dir.php).
//
//   https://sheepsite.com/Scripts/my-account.php?building=LyndhurstH
// -------------------------------------------------------
session_start();

$buildings = require __DIR__ . '/buildings.php';

$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildLabel  = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey  = 'private_auth_' . $building;

// Redirect to login if not authenticated
if (empty($_SESSION[$sessionKey])) {
  $returnURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  header('Location: display-private-dir.php?building=' . urlencode($building)
       . '&return=' . urlencode($returnURL));
  exit;
}

$username = is_array($_SESSION[$sessionKey]) ? ($_SESSION[$sessionKey]['user'] ?? '') : $_SESSION[$sessionKey];
$buildings_config = $buildings[$building];
$returnURL = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

// Fall back to stored site URL if no return param was passed
if (!$returnURL) {
  $configFile = __DIR__ . '/config/' . $building . '.json';
  $cfg = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
  $returnURL = $cfg['siteURL'] ?? '';
}
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
    <?php if ($returnURL): ?>
      <a href="<?= htmlspecialchars($returnURL) ?>">← Back to site</a>
    <?php endif; ?>
    <a href="display-private-dir.php?building=<?= urlencode($building) ?>">Private files</a>
    <a href="display-private-dir.php?building=<?= urlencode($building) ?>&logout=1">Log out</a>
  </div>
</div>
<div class="subtitle">Welcome, <?= htmlspecialchars($username) ?></div>

<a href="my-unit.php?building=<?= urlencode($building) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>" class="card">
  <div class="card-icon">🏠</div>
  <div>
    <div class="card-title">My Unit Info</div>
    <div class="card-desc">
      Update your contact information, vehicle details, and emergency contacts for your unit.
    </div>
  </div>
</a>

<a href="change-password.php?building=<?= urlencode($building) ?>&redirect=<?= urlencode('my-account.php?building=' . $building . ($returnURL ? '&return=' . urlencode($returnURL) : '')) ?>" class="card">
  <div class="card-icon">🔑</div>
  <div>
    <div class="card-title">Change Password</div>
    <div class="card-desc">
      Update your login password.
    </div>
  </div>
</a>

<a href="chatbot-page.php?building=<?= urlencode($building) ?>" class="card">
  <div class="card-icon"><img src="https://sheepsite.com/Scripts/assets/Woolsy-standing-transparent.png" height="36" alt="Woolsy" style="display:block;"></div>
  <div>
    <div class="card-title">Ask Woolsy</div>
    <div class="card-desc">
      Get answers to your condo questions from Woolsy, the building's AI assistant.
    </div>
  </div>
</a>

</body>
</html>
