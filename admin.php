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
define('APPS_SCRIPT_URL',      'https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec');
define('APPS_SCRIPT_TOKEN',    'wX7#mK2$pN9vQ4@hR6jT1!uL8eB3sF5c');

function getWoolsyCredits(string $building): array {
    if (!file_exists(CREDITS_FILE)) {
        return ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
    }
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

// -------------------------------------------------------
// AJAX: doc status endpoints (docStatus = cached result,
// docCheck = on-demand scan). Returns JSON and exits.
// -------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'buildDocIndex') {
  header('Content-Type: application/json');
  if (empty($_SESSION[$sessionKey])) { echo json_encode(['error' => 'Unauthorized']); exit; }
  $buildings      = require __DIR__ . '/buildings.php';
  $publicFolderId = $buildings[$building]['publicFolderId'] ?? '';
  $url = APPS_SCRIPT_URL . '?' . http_build_query([
    'action' => 'buildDocIndex', 'publicFolderId' => $publicFolderId, 'token' => APPS_SCRIPT_TOKEN
  ]);
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
  $resp = curl_exec($ch); curl_close($ch);
  $data = json_decode($resp ?: '{}', true);
  if (!empty($data['sections'])) {
    $lines = ["DOCUMENT INDEX — {$building}", "Generated: " . date('F j, Y'), "", "PUBLIC DOCUMENTS", "================", ""];
    foreach ($data['sections'] as $section) {
      $lines[] = $section['path'] . '/';
      foreach ($section['files'] as $file) { $lines[] = "  \u{2022} " . $file; }
      $lines[] = '';
    }
    file_put_contents(__DIR__ . '/faqs/' . $building . '_docindex.txt', implode("\n", $lines));
    echo json_encode(['ok' => true, 'generated' => date('F j, Y'), 'sectionCount' => count($data['sections'])]);
  } else {
    echo json_encode(['error' => $data['error'] ?? 'Failed to build index']);
  }
  exit;
}

if (isset($_GET['action']) && in_array($_GET['action'], ['docStatus', 'docCheck'], true)) {
  header('Content-Type: application/json');
  if (empty($_SESSION[$sessionKey])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }
  $buildings      = require __DIR__ . '/buildings.php';
  $publicFolderId = $buildings[$building]['publicFolderId'] ?? '';
  $apAction       = $_GET['action'] === 'docCheck' ? 'docCheck' : 'docCheckResult';
  $params         = ['action' => $apAction, 'building' => $building, 'token' => APPS_SCRIPT_TOKEN];
  if ($apAction === 'docCheck') $params['publicFolderId'] = $publicFolderId;

  $url = APPS_SCRIPT_URL . '?' . http_build_query($params);
  $ch  = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
  $resp = curl_exec($ch);
  curl_close($ch);
  echo $resp ?: json_encode(['error' => 'Could not fetch status']);
  exit;
}

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
    .woolsy-card    { padding: 1.25rem; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 1rem; }
    .woolsy-header  { display: flex; align-items: flex-start; gap: 1.25rem; }
    .woolsy-icon    { font-size: 1.8rem; line-height: 1; flex-shrink: 0; }
    .woolsy-title   { font-size: 1rem; font-weight: bold; margin-bottom: 0.3rem; color: #0070f3; }
    .woolsy-desc    { font-size: 0.875rem; color: #555; line-height: 1.45; }
    .woolsy-status  { margin-top: 0.75rem; font-size: 0.85rem; color: #555; }
    .woolsy-status .ok   { color: #1a7f37; font-weight: bold; }
    .woolsy-status .warn { color: #b45309; font-weight: bold; }
    .credit-bar     { margin-top: 0.5rem; background: #eee; border-radius: 4px; height: 6px; }
    .credit-fill    { height: 6px; border-radius: 4px; background: #0070f3; }
    .credit-fill.warn { background: #f59e0b; }
    .credit-fill.danger { background: #dc2626; }
    .low-credit-warn { margin-top: 0.6rem; padding: 0.4rem 0.7rem; background: #fffbeb;
                       border: 1px solid #f59e0b; border-radius: 4px; font-size: 0.82rem; color: #92400e; }
    .woolsy-docstatus  { margin: 0.6rem 0 0.3rem; font-size: 0.85rem; color: #444; line-height: 1.5; }
    .woolsy-index-row  { margin: 0.3rem 0 0.5rem; font-size: 0.85rem; color: #444; }
    .woolsy-docstatus .ds-ok   { color: #1a7f37; font-weight: bold; }
    .woolsy-docstatus .ds-warn { color: #b45309; font-weight: bold; }
    .woolsy-docstatus .ds-muted { color: #888; }
    .ds-check-btn { margin-left: 0.5rem; padding: 0.15rem 0.5rem; font-size: 0.8rem;
                    background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 3px;
                    cursor: pointer; color: #374151; }
    .ds-check-btn:hover { background: #e5e7eb; }
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
    $wc        = getWoolsyCredits($building);
    $wAlloc    = (float)($wc['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
    $wUsed     = (float)($wc['used']      ?? 0);
    $wRemain   = max(0, $wAlloc - $wUsed);
    $wPct      = $wAlloc > 0 ? min(100, round($wUsed / $wAlloc * 100)) : 100;
    $wLow      = $wPct >= 80;
    $wBarClass = $wPct >= 100 ? 'danger' : ($wPct >= 80 ? 'warn' : '');
    $docIndexFile   = __DIR__ . '/faqs/' . $building . '_docindex.txt';
    $docIndexExists = file_exists($docIndexFile);
    $docIndexDate   = $docIndexExists ? date('F j, Y', filemtime($docIndexFile)) : '';
  ?>
  <div class="woolsy-card">
    <div class="woolsy-header">
      <div class="woolsy-icon">🐑</div>
      <div>
        <div class="woolsy-title">Woolsy Knowledge Base</div>
        <div class="woolsy-desc">
          AI-powered assistant for residents. Answers questions about building rules,
          Florida condo law, and community policies.
        </div>
        <div class="woolsy-docstatus" id="woolsy-docstatus">
          <span class="ds-muted">Checking knowledge base status…</span>
        </div>
        <div class="woolsy-index-row">
          📄 <strong>Document Index:</strong>
          <?php if ($docIndexExists): ?>
            Built <?= htmlspecialchars($docIndexDate) ?>
            &nbsp;<button class="ds-check-btn" id="build-index-btn" onclick="buildDocIndex()">Rebuild</button>
          <?php else: ?>
            <span class="ds-warn">Not built</span>
            &nbsp;<button class="ds-check-btn" id="build-index-btn" onclick="buildDocIndex()">Build Index</button>
          <?php endif; ?>
          <span id="index-status-msg"></span>
        </div>
        <div class="woolsy-status">
          Credits used: <strong><?= number_format($wUsed, 4) ?></strong>
          of <strong><?= number_format($wAlloc, 2) ?></strong>
          &nbsp;(<?= number_format($wRemain, 4) ?> remaining)
          <div class="credit-bar">
            <div class="credit-fill <?= $wBarClass ?>" style="width:<?= $wPct ?>%"></div>
          </div>
          <?php if ($wLow && $wPct < 100): ?>
            <div class="low-credit-warn">
              ⚠️ Running low on credits. Contact SheepSite to add more.
            </div>
          <?php elseif ($wPct >= 100): ?>
            <div class="low-credit-warn" style="background:#fef2f2;border-color:#dc2626;color:#7f1d1d;">
              ⛔ Credits exhausted — Woolsy is currently unavailable to residents.
              Contact SheepSite to top up.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('woolsy-docstatus')) loadDocStatus();
});

function loadDocStatus() {
  fetchAndRender('admin.php?building=<?= urlencode($building) ?>&action=docStatus');
}

function checkDocNow() {
  var el = document.getElementById('woolsy-docstatus');
  if (el) el.innerHTML = '<span class="ds-muted">Checking…</span>';
  fetchAndRender('admin.php?building=<?= urlencode($building) ?>&action=docCheck');
}

function fetchAndRender(url) {
  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var el = document.getElementById('woolsy-docstatus');
      if (el) el.innerHTML = buildDocStatusHtml(data);
    })
    .catch(function() {
      var el = document.getElementById('woolsy-docstatus');
      if (el) el.innerHTML = '<span class="ds-muted">Status unavailable.</span>';
    });
}

function buildDocStatusHtml(data) {
  var b         = '<?= urlencode($building) ?>';
  var updateUrl = 'woolsy-update.php?building=' + b;

  if (data.error || data.notInitialized) {
    return '📋 <strong>Knowledge Base:</strong> Not set up — ' +
      '<a href="' + updateUrl + '" style="color:#0070f3">Set Up Woolsy →</a>';
  }
  if (data.notCheckedYet) {
    return '📋 <strong>Knowledge Base:</strong> Initialized — ' +
      '<a href="' + updateUrl + '" style="color:#0070f3">Manage →</a>';
  }

  var checked = data.checkedAt ? 'Checked ' + fmtDate(data.checkedAt) : '';
  var total   = data.fileCounts
    ? (data.fileCounts.IncorporationDocs + data.fileCounts.RulesDocs) + ' files'
    : '';

  if (data.status === 'changes') {
    var n = (data.changes || []).length;
    return '📋 <strong>Knowledge Base:</strong> ' +
      '<span class="ds-warn">⚠️ ' + n + ' file' + (n !== 1 ? 's' : '') + ' changed</span>' +
      (checked ? ' &middot; ' + checked : '') +
      ' &mdash; <a href="' + updateUrl + '" style="color:#0070f3">Review &amp; Update →</a>';
  }
  if (data.status === 'ok') {
    return '📋 <strong>Knowledge Base:</strong> ' +
      '<span class="ds-ok">✅ Up to date</span>' +
      (checked ? ' &middot; ' + checked : '') +
      (total   ? ' &middot; ' + total   : '') +
      ' <button class="ds-check-btn" onclick="checkDocNow()">Check now</button>';
  }
  return '📋 <strong>Knowledge Base:</strong> <a href="' + updateUrl + '" style="color:#0070f3">Manage →</a>';
}

function buildDocIndex() {
  var btn = document.getElementById('build-index-btn');
  var msg = document.getElementById('index-status-msg');
  if (btn) btn.disabled = true;
  if (msg) { msg.style.color = '#888'; msg.textContent = ' Building…'; }
  fetch('admin.php?building=<?= urlencode($building) ?>&action=buildDocIndex')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.ok) {
        if (msg) { msg.style.color = '#1a7f37'; msg.textContent = ' ✅ Built ' + data.generated + ' (' + data.sectionCount + ' folder' + (data.sectionCount !== 1 ? 's' : '') + ')'; }
        if (btn) { btn.disabled = false; btn.textContent = 'Rebuild'; }
      } else {
        if (msg) { msg.style.color = '#c00'; msg.textContent = ' ⚠️ ' + (data.error || 'Unknown error'); }
        if (btn) btn.disabled = false;
      }
    })
    .catch(function() {
      if (msg) { msg.style.color = '#c00'; msg.textContent = ' ⚠️ Request failed.'; }
      if (btn) btn.disabled = false;
    });
}

function fmtDate(iso) {
  try {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  } catch(e) { return iso; }
}
</script>
</body>
</html>
