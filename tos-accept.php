<?php
// -------------------------------------------------------
// tos-accept.php
// Terms of Service click-through gate.
// Shown when an admin logs into a building that is in scope
// but has not yet accepted the current ToS version.
//
//   https://sheepsite.com/Scripts/tos-accept.php?building=LyndhurstH
//
// Requires an active admin session. Accept writes to
// config/{building}.json and appends to config/tos_signatures.json.
// Decline logs the admin out.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');

$building = $_GET['building'] ?? '';
if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
  die('<p style="color:red;">Invalid building.</p>');
}

// Must be logged in as admin for this building
$sessionKey = 'manage_auth_' . $building;
if (empty($_SESSION[$sessionKey])) {
  header('Location: admin.php?building=' . urlencode($building));
  exit;
}

// ToS config must exist
$tosFile = CONFIG_DIR . 'tos.json';
if (!file_exists($tosFile)) {
  header('Location: admin.php?building=' . urlencode($building));
  exit;
}
$tos        = json_decode(file_get_contents($tosFile), true);
$tosVersion = (int)($tos['version'] ?? 1);
$tosDate    = $tos['effectiveDate'] ?? '';
$docPath    = $tos['documentPath'] ?? 'docs/terms-of-service.html';

function loadBuildingConfig(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveBuildingConfig(string $building, array $config): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . $building . '.json', json_encode($config, JSON_PRETTY_PRINT));
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$declined   = false;

// -------------------------------------------------------
// Handle POST
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['accept'])) {
    $now    = date('c');
    $config = loadBuildingConfig($building);
    $config['tosAccepted'] = [
      'version' => $tosVersion,
      'date'    => $now,
      'who'     => 'admin',
    ];
    saveBuildingConfig($building, $config);

    // Append to signature archive
    $sigFile = CONFIG_DIR . 'tos_signatures.json';
    $sigs    = file_exists($sigFile) ? json_decode(file_get_contents($sigFile), true) ?? [] : [];
    $sigs[]  = [
      'building' => $building,
      'version'  => $tosVersion,
      'date'     => $now,
      'who'      => 'admin',
    ];
    file_put_contents($sigFile, json_encode($sigs, JSON_PRETTY_PRINT));

    header('Location: admin.php?building=' . urlencode($building));
    exit;
  }

  if (isset($_POST['decline'])) {
    unset($_SESSION[$sessionKey]);
    $declined = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Terms of Service</title>
  <style>
    * { box-sizing: border-box; }
    body        { font-family: sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
    .wrap       { max-width: 820px; margin: 0 auto; padding: 2rem 1rem 3rem; }
    .header     { margin-bottom: 1.25rem; }
    .header h1  { font-size: 1.3rem; margin: 0 0 0.25rem; }
    .header p   { font-size: 0.9rem; color: #555; margin: 0; }
    .version-bar { display: flex; gap: 1.5rem; font-size: 0.85rem; color: #666;
                   background: #fff; border: 1px solid #ddd; border-radius: 6px;
                   padding: 0.6rem 1rem; margin-bottom: 1rem; }
    .version-bar strong { color: #333; }
    .doc-frame  { width: 100%; height: 520px; border: 1px solid #ccc; border-radius: 6px;
                  background: #fff; overflow: hidden; }
    .doc-frame iframe { width: 100%; height: 100%; border: none; }
    .action-bar { display: flex; align-items: center; gap: 1rem; margin-top: 1.25rem;
                  padding: 1.1rem 1.25rem; background: #fff; border: 1px solid #ddd;
                  border-radius: 8px; }
    .action-bar p { flex: 1; font-size: 0.875rem; color: #555; margin: 0; line-height: 1.45; }
    .btn-accept { padding: 0.6rem 1.5rem; background: #0070f3; color: #fff; border: none;
                  border-radius: 5px; font-size: 0.95rem; cursor: pointer; white-space: nowrap; }
    .btn-accept:hover { background: #005bb5; }
    .btn-decline { padding: 0.6rem 1.1rem; background: #fff; color: #c00; border: 1px solid #f0b0b0;
                   border-radius: 5px; font-size: 0.9rem; cursor: pointer; white-space: nowrap; }
    .btn-decline:hover { background: #fff0f0; }
    .declined-box { background: #fff8f0; border: 1px solid #f0c080; border-radius: 8px;
                    padding: 1.5rem; text-align: center; }
    .declined-box h2 { margin: 0 0 0.5rem; font-size: 1.1rem; color: #92400e; }
    .declined-box p  { font-size: 0.9rem; color: #555; margin: 0 0 1rem; }
    .declined-box a  { color: #0070f3; font-size: 0.9rem; text-decoration: none; }
    .declined-box a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1><?= htmlspecialchars($buildLabel) ?> – Terms of Service</h1>
    <p>You must review and accept the SheepSite Terms of Service before accessing the admin panel.</p>
  </div>

  <?php if ($declined): ?>

  <div class="declined-box">
    <h2>Access Declined</h2>
    <p>You must accept the Terms of Service to use the SheepSite admin panel.<br>
       You have been logged out.</p>
    <a href="admin.php?building=<?= urlencode($building) ?>">← Return to login</a>
  </div>

  <?php else: ?>

  <div class="version-bar">
    <span><strong>Version:</strong> <?= (int)$tosVersion ?></span>
    <?php if ($tosDate): ?>
      <span><strong>Effective Date:</strong> <?= htmlspecialchars($tosDate) ?></span>
    <?php endif; ?>
  </div>

  <div class="doc-frame">
    <iframe src="<?= htmlspecialchars($docPath) ?>" title="Terms of Service"></iframe>
  </div>

  <form method="post" action="tos-accept.php?building=<?= urlencode($building) ?>">
    <div class="action-bar">
      <p>By clicking <strong>I Accept</strong> you confirm that you are the authorized administrator for
         <strong><?= htmlspecialchars($buildLabel) ?></strong> and that you accept these Terms of Service
         on behalf of the association. Your acceptance will be recorded with a timestamp.</p>
      <button type="submit" name="accept" class="btn-accept">I Accept</button>
      <button type="submit" name="decline" class="btn-decline">Decline &amp; Log Out</button>
    </div>
  </form>

  <?php endif; ?>

</div>
</body>
</html>
