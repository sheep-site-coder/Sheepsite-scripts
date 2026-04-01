<?php
// -------------------------------------------------------
// master-admin.php
// SheepSite operator admin panel.
// Top section: per-building dashboard cards.
// Bottom section: system-wide tools.
//
//   https://sheepsite.com/Scripts/master-admin.php
//
// Auth: credentials/_master.json (bcrypt)
// Session key: master_admin_auth — shared with sub-pages.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');
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

// -------------------------------------------------------
// Load data for dashboard
// -------------------------------------------------------
$buildings = require __DIR__ . '/buildings.php';

// Woolsy credits
$creditsFile = __DIR__ . '/faqs/woolsy_credits.json';
$allCredits  = file_exists($creditsFile) ? json_decode(file_get_contents($creditsFile), true) ?? [] : [];

// ToS config
$tosFile    = CONFIG_DIR . 'tos.json';
$tos        = file_exists($tosFile) ? json_decode(file_get_contents($tosFile), true) ?? [] : [];
$tosVersion = (int)($tos['version'] ?? 0);
$tosScope   = $tos['scope'] ?? [];

// Pricing (for default storage limit)
$pricingFile    = CONFIG_DIR . 'pricing.json';
$pricing        = file_exists($pricingFile) ? json_decode(file_get_contents($pricingFile), true) ?? [] : [];
$defaultLimit   = (int)($pricing['storageDefaultLimit'] ?? 524288000);

// Per-building config loader
function loadBuildingConfig(string $b): array {
  $file = CONFIG_DIR . $b . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function fmtBytes(int $bytes): string {
  if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
  return round($bytes / 1024) . ' KB';
}

function pct(int $used, int $total): int {
  if ($total <= 0) return 0;
  return (int)min(100, round($used / $total * 100));
}

function barColor(int $pct): string {
  if ($pct >= 90) return '#dc2626';
  if ($pct >= 70) return '#f59e0b';
  return '#22c55e';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SheepSite — Master Admin</title>
  <style>
    * { box-sizing: border-box; }
    body         { font-family: sans-serif; max-width: 820px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar     { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem; }
    h1           { margin: 0; font-size: 1.5rem; }
    .logout      { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .logout:hover { text-decoration: underline; }
    .section-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.07em;
                     color: #999; margin: 2rem 0 0.75rem; }

    /* Building cards */
    .bld-card    { border: 1px solid #ddd; border-radius: 8px; padding: 1.1rem 1.25rem;
                   margin-bottom: 0.75rem; display: flex; gap: 1.5rem; align-items: center;
                   flex-wrap: wrap; }
    .bld-card.warn  { border-color: #f59e0b; background: #fffbeb; }
    .bld-card.alert { border-color: #dc2626; background: #fff5f5; }
    .bld-name    { font-weight: bold; font-size: 1rem; color: #111; min-width: 160px; }
    .bld-url     { font-size: 0.8rem; color: #888; margin-top: 0.1rem; }
    .bld-stats   { display: flex; gap: 1.5rem; flex: 1; flex-wrap: wrap; align-items: center; }
    .stat        { min-width: 140px; }
    .stat-label  { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;
                   color: #888; margin-bottom: 0.2rem; }
    .bar-wrap    { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; margin-bottom: 0.2rem; }
    .bar-fill    { height: 100%; border-radius: 3px; transition: width 0.3s; }
    .stat-val    { font-size: 0.78rem; color: #555; }
    .badge       { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 10px;
                   font-size: 0.75rem; font-weight: 600; }
    .badge.ok    { background: #dcfce7; color: #15803d; }
    .badge.warn  { background: #fef3c7; color: #92400e; }
    .badge.none  { background: #f3f4f6; color: #9ca3af; }
    .badge.suspended { background: #fee2e2; color: #991b1b; font-size: 0.8rem; }
    .bld-manage  { margin-left: auto; white-space: nowrap; }
    .manage-btn  { padding: 0.4rem 1rem; background: #0070f3; color: #fff; border: none;
                   border-radius: 4px; font-size: 0.85rem; cursor: pointer; text-decoration: none;
                   display: inline-block; }
    .manage-btn:hover { background: #005bb5; }
    .renewal-val { font-size: 0.78rem; color: #555; }
    .renewal-val.due  { color: #dc2626; font-weight: 600; }

    /* System tool cards */
    .tool-grid   { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem; }
    .tool-card   { display: flex; gap: 0.75rem; align-items: flex-start; padding: 1rem;
                   border: 1px solid #ddd; border-radius: 8px; text-decoration: none;
                   color: inherit; transition: background 0.15s; }
    .tool-card:hover  { background: #f5f5f5; }
    .tool-icon   { font-size: 1.5rem; line-height: 1; flex-shrink: 0; }
    .tool-title  { font-size: 0.9rem; font-weight: bold; color: #0070f3; margin-bottom: 0.2rem; }
    .tool-desc   { font-size: 0.8rem; color: #666; line-height: 1.4; }

    .add-btn     { display: inline-flex; align-items: center; gap: 0.4rem;
                   padding: 0.45rem 1rem; background: #fff; border: 1px solid #0070f3;
                   border-radius: 4px; color: #0070f3; font-size: 0.875rem;
                   text-decoration: none; cursor: pointer; margin-bottom: 0.75rem; }
    .add-btn:hover { background: #f0f7ff; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1>SheepSite — Master Admin</h1>
  <a href="master-admin.php?logout=1" class="logout">Log out</a>
</div>

<!-- ===================== BUILDINGS ===================== -->
<div class="section-label">Associations under management</div>

<?php foreach ($buildings as $b => $cfg):
  $bldCfg     = loadBuildingConfig($b);
  $label      = ucwords(str_replace(['_', '-'], ' ', $b));
  $siteURL    = $bldCfg['siteURL'] ?? '';

  // Woolsy
  $wc         = $allCredits[$b] ?? ['allocated' => 1.0, 'used' => 0];
  $wAlloc     = (float)($wc['allocated'] ?? 1.0);
  $wUsed      = (float)($wc['used']      ?? 0);
  $wPct       = pct((int)($wUsed * 1000), (int)($wAlloc * 1000));

  // Storage
  $storageUsed  = (int)($bldCfg['storageUsed']    ?? 0);
  $storageLimit = (int)($bldCfg['storageLimit']   ?? $defaultLimit);
  $sPct         = pct($storageUsed, $storageLimit);
  $storageKnown = isset($bldCfg['storageUsed']);

  // ToS
  $inScope      = $tosVersion > 0 && ($tosScope === 'all' || (is_array($tosScope) && in_array($b, $tosScope)));
  $tosAccepted  = (int)($bldCfg['tosAccepted']['version'] ?? 0);
  $tosCurrent   = $tosAccepted >= $tosVersion;

  // Renewal + suspension
  $renewalDate  = $bldCfg['renewalDate'] ?? null;
  $renewalDue   = false;
  $renewalLabel = '—';
  if ($renewalDate) {
    $days = (int)((strtotime($renewalDate) - time()) / 86400);
    $renewalLabel = date('M j, Y', strtotime($renewalDate));
    $renewalDue   = $days <= 30;
  }
  $suspended = !empty($bldCfg['suspended']);

  // Card highlight
  $cardClass = '';
  if ($suspended || $wPct >= 90 || $sPct >= 90 || $renewalDue) $cardClass = 'alert';
  elseif ($wPct >= 70 || $sPct >= 70)                           $cardClass = 'warn';
?>
<div class="bld-card <?= $cardClass ?>">

  <div>
    <div class="bld-name">
      <?= htmlspecialchars($label) ?>
      <?php if ($suspended): ?>
        <span class="badge suspended" style="margin-left:0.5rem;">&#9632; SUSPENDED</span>
      <?php elseif ($renewalDue): ?>
        <span class="badge warn" style="margin-left:0.5rem;">⚠ RENEWAL DUE</span>
      <?php endif; ?>
    </div>
    <?php if ($siteURL): ?>
      <div class="bld-url"><?= htmlspecialchars($siteURL) ?></div>
    <?php endif; ?>
  </div>

  <div class="bld-stats">

    <!-- Woolsy -->
    <div class="stat">
      <div class="stat-label">Woolsy Credits</div>
      <div class="bar-wrap"><div class="bar-fill" style="width:<?= $wPct ?>%;background:<?= barColor($wPct) ?>;"></div></div>
      <div class="stat-val"><?= number_format($wUsed, 2) ?> / <?= number_format($wAlloc, 2) ?> (<?= $wPct ?>%)</div>
    </div>

    <!-- Storage -->
    <div class="stat">
      <div class="stat-label">Storage</div>
      <?php if ($storageKnown): ?>
        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $sPct ?>%;background:<?= barColor($sPct) ?>;"></div></div>
        <div class="stat-val"><?= fmtBytes($storageUsed) ?> / <?= fmtBytes($storageLimit) ?> (<?= $sPct ?>%)</div>
      <?php else: ?>
        <div class="stat-val" style="color:#bbb;font-style:italic;">Not yet measured</div>
      <?php endif; ?>
    </div>

    <!-- ToS -->
    <?php if ($inScope): ?>
    <div class="stat">
      <div class="stat-label">Terms of Service</div>
      <?php if ($tosCurrent): ?>
        <span class="badge ok">✓ Current</span>
      <?php else: ?>
        <span class="badge warn">⚠ Pending</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Renewal -->
    <div class="stat">
      <div class="stat-label">Renewal Date</div>
      <div class="renewal-val <?= $renewalDue ? 'due' : '' ?>">
        <?= htmlspecialchars($renewalLabel) ?>
        <?= $renewalDue ? ' ⚠' : '' ?>
      </div>
    </div>

  </div>

  <div class="bld-manage">
    <a href="building-detail.php?building=<?= urlencode($b) ?>" class="manage-btn">Manage →</a>
  </div>

</div>
<?php endforeach; ?>

<!-- ===================== SYSTEM TOOLS ===================== -->
<div class="section-label">System tools</div>

<div class="tool-grid">

  <a href="woolsy-admin.php" class="tool-card">
    <div class="tool-icon">🐑</div>
    <div>
      <div class="tool-title">Woolsy Overview</div>
      <div class="tool-desc">Credit usage across all buildings and top-up.</div>
    </div>
  </a>

  <a href="tos-admin.php" class="tool-card">
    <div class="tool-icon">📜</div>
    <div>
      <div class="tool-title">License Agreements</div>
      <div class="tool-desc">ToS scope, versions, and signature history.</div>
    </div>
  </a>

  <a href="pricing-admin.php" class="tool-card">
    <div class="tool-icon">💰</div>
    <div>
      <div class="tool-title">Pricing</div>
      <div class="tool-desc">Site fees, storage tiers, and credit price.</div>
    </div>
  </a>

  <a href="docs/Sheepsite-Architecture.html" target="_blank" class="tool-card">
    <div class="tool-icon">🏗️</div>
    <div>
      <div class="tool-title">Architecture Manual</div>
      <div class="tool-desc">Technical reference. Opens in a new tab.</div>
    </div>
  </a>

  <a href="building-detail.php?building=new" class="tool-card">
    <div class="tool-icon">➕</div>
    <div>
      <div class="tool-title">Add New Association</div>
      <div class="tool-desc">Create Drive folders, copy sheet, generate setup checklist.</div>
    </div>
  </a>

  <a href="association-remove.php" class="tool-card" style="border-color:#fca5a5;">
    <div class="tool-icon">➖</div>
    <div>
      <div class="tool-title">Remove Association</div>
      <div class="tool-desc">Delete server files for a decommissioned association.</div>
    </div>
  </a>

</div>

</body>
</html>
