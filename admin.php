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
define('CONFIG_DIR',           __DIR__ . '/config/');
define('USER_MANUAL_URL',      'docs/Sheepsite-Admin-Manual.html');
define('CREDITS_FILE',         __DIR__ . '/faqs/woolsy_credits.json');
define('CREDITS_DEFAULT_ALLOCATED', 1.0);
define('PROMPT_VERSION',       4);

function getRulesVersion(string $file): int {
    if (!file_exists($file)) return 0;
    $fh   = fopen($file, 'r');
    $line = fgets($fh);
    fclose($fh);
    if (preg_match('/woolsy_prompt_version:\s*(\d+)/', $line, $m)) return (int)$m[1];
    return 1;
}

function fmtAdminBytes(int $bytes): string {
  if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
  return round($bytes / 1024) . ' KB';
}

function loadBuildingConfig(string $building): array {
    $file = CONFIG_DIR . $building . '.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveBuildingConfig(string $building, array $config): bool {
    if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
    return file_put_contents(
        CONFIG_DIR . $building . '.json',
        json_encode($config, JSON_PRETTY_PRINT)
    ) !== false;
}

function getWoolsyCredits(string $building): array {
    if (!file_exists(CREDITS_FILE)) return ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
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

require_once __DIR__ . '/db/admin-helpers.php';

// Load credentials
$adminCreds = loadAdminCreds($adminCredFile);
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

  $isMaster     = $masterCred
                  && $submittedUser === $masterCred['user']
                  && password_verify($submittedPass, $masterCred['pass']);
  $matchedAdmin = $isMaster ? null : findAdminByPassword($adminCreds, $submittedUser, $submittedPass);

  if ($isMaster) {
    $_SESSION[$sessionKey] = ['user' => '_master', 'email' => ''];
    header('Location: admin.php?building=' . urlencode($building));
    exit;
  } elseif ($matchedAdmin) {
    $_SESSION[$sessionKey] = ['user' => $matchedAdmin['user'], 'email' => $matchedAdmin['email'] ?? ''];
    header('Location: admin.php?building=' . urlencode($building));
    exit;
  } else {
    $loginError = 'Invalid username or password.';
  }
}

// -------------------------------------------------------
// Load config early so login page can show Back to site link
// -------------------------------------------------------
$loginPageConfig = loadBuildingConfig($building);
$loginSiteURL    = htmlspecialchars($loginPageConfig['siteURL'] ?? '');

// -------------------------------------------------------
// Show login form if not authenticated
// -------------------------------------------------------
// Invalidate legacy sessions stored as plain `true` (pre-multi-admin format)
if (!empty($_SESSION[$sessionKey]) && !is_array($_SESSION[$sessionKey])) {
  unset($_SESSION[$sessionKey]);
}
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
  <?php if ($loginSiteURL): ?>
    <p style="margin-bottom:1.5rem;"><a href="<?= $loginSiteURL ?>" style="color:#0070f3;text-decoration:none;font-size:0.9rem;">← Back to site</a></p>
  <?php endif; ?>
  <h1><?= htmlspecialchars($buildLabel) ?> – Admin</h1>
  <div class="subtitle">Administrator login required</div>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="admin.php?building=<?= urlencode($building) ?>">
    <label for="admin_user">Username</label>
    <input type="text" id="admin_user" name="admin_user" autocomplete="username" autocapitalize="none" autofocus>

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

// Derive logged-in user identity (used by all POST handlers below)
$loggedInUser = is_array($_SESSION[$sessionKey]) ? ($_SESSION[$sessionKey]['user'] ?? '') : '';

// -------------------------------------------------------
// Manage Admins — handle POST
// -------------------------------------------------------
$adminsMessage     = '';
$adminsMessageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
  $newUser  = strtolower(trim($_POST['new_admin_user']  ?? ''));
  $newEmail = trim($_POST['new_admin_email'] ?? '');
  $newPass  = $_POST['new_admin_pass']  ?? '';
  $newCreds = loadAdminCreds($adminCredFile);
  if (!preg_match('/^[a-z][a-z0-9]{1,29}$/', $newUser)) {
    $adminsMessage     = 'Username must be 2–30 lowercase letters/numbers, starting with a letter.';
    $adminsMessageType = 'error';
  } elseif (strlen($newPass) < 8) {
    $adminsMessage     = 'Password must be at least 8 characters.';
    $adminsMessageType = 'error';
  } elseif (findAdmin($newCreds, $newUser)) {
    $adminsMessage     = 'That username already exists.';
    $adminsMessageType = 'error';
  } else {
    $newCreds[] = ['user' => $newUser, 'pass' => password_hash($newPass, PASSWORD_DEFAULT), 'email' => $newEmail];
    if (saveAdminCreds($adminCredFile, $newCreds)) {
      $adminsMessage = 'Admin account "' . htmlspecialchars($newUser) . '" created.';
    } else {
      $adminsMessage     = 'Could not save — check that the credentials/ folder is writable.';
      $adminsMessageType = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_admin'])) {
  $removeUser = trim($_POST['remove_admin_user'] ?? '');
  $newCreds   = loadAdminCreds($adminCredFile);
  if ($removeUser === $loggedInUser) {
    $adminsMessage     = 'You cannot remove your own account.';
    $adminsMessageType = 'error';
  } elseif (count($newCreds) <= 1) {
    $adminsMessage     = 'Cannot remove the last admin account.';
    $adminsMessageType = 'error';
  } elseif (!findAdmin($newCreds, $removeUser)) {
    $adminsMessage     = 'Admin not found.';
    $adminsMessageType = 'error';
  } else {
    $newCreds = array_values(array_filter($newCreds, fn($a) => $a['user'] !== $removeUser));
    if (saveAdminCreds($adminCredFile, $newCreds)) {
      $adminsMessage = 'Admin account "' . htmlspecialchars($removeUser) . '" removed.';
    } else {
      $adminsMessage     = 'Could not save — check that the credentials/ folder is writable.';
      $adminsMessageType = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_email'])) {
  $editUser  = trim($_POST['edit_admin_user']  ?? '');
  $editEmail = trim($_POST['edit_admin_email'] ?? '');
  $newCreds  = loadAdminCreds($adminCredFile);
  if (!findAdmin($newCreds, $editUser)) {
    $adminsMessage     = 'Admin not found.';
    $adminsMessageType = 'error';
  } else {
    $newCreds = updateAdminEntry($newCreds, $editUser, ['email' => $editEmail]);
    if (saveAdminCreds($adminCredFile, $newCreds)) {
      $adminsMessage = 'Email updated for "' . htmlspecialchars($editUser) . '".';
      // Refresh session email if editing own account
      if ($editUser === $loggedInUser) {
        $_SESSION[$sessionKey]['email'] = $editEmail;
      }
    } else {
      $adminsMessage     = 'Could not save — check that the credentials/ folder is writable.';
      $adminsMessageType = 'error';
    }
  }
}

// -------------------------------------------------------
// Building Settings — handle POST
// -------------------------------------------------------
$settingsMessage     = '';
$settingsMessageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_building_settings'])) {
  $config = loadBuildingConfig($building);
  $config['contactEmail'] = trim($_POST['contact_email'] ?? '');
  if (saveBuildingConfig($building, $config)) {
    $settingsMessage = 'Building settings saved.';
  } else {
    $settingsMessage     = 'Could not save — check that the config/ folder is writable.';
    $settingsMessageType = 'error';
  }
}

// -------------------------------------------------------
// mustChange check — re-read credential file after any POST
// -------------------------------------------------------
$adminCreds    = loadAdminCreds($adminCredFile);
$loggedInAdmin = ($loggedInUser && $loggedInUser !== '_master') ? findAdmin($adminCreds, $loggedInUser) : null;
$mustChange    = $loggedInAdmin && !empty($loggedInAdmin['mustChange']);

// -------------------------------------------------------
// Load config early — needed for testSite check before POST handling
// -------------------------------------------------------
$bldCfg     = loadBuildingConfig($building);
$isTestSite = !empty($bldCfg['testSite']);

// -------------------------------------------------------
// Handle admin password change
// -------------------------------------------------------
$pwMessage     = '';
$pwMessageType = 'ok';

if (!$isTestSite && $loggedInAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_pass'])) {
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
  } elseif (!password_verify($currentPass, $loggedInAdmin['pass'] ?? '')) {
    $pwMessage     = 'Current password is incorrect.';
    $pwMessageType = 'error';
  } else {
    $adminCreds = updateAdminEntry($adminCreds, $loggedInUser, [
      'pass'       => password_hash($newPass, PASSWORD_DEFAULT),
      'mustChange' => null,
    ]);
    if (saveAdminCreds($adminCredFile, $adminCreds)) {
      $loggedInAdmin = findAdmin($adminCreds, $loggedInUser); // refresh
      $mustChange = false;
      $pwMessage  = 'Password updated. You now have full access.';
    } else {
      $pwMessage     = 'Could not save — check that the credentials/ folder is writable.';
      $pwMessageType = 'error';
    }
  }
}
// -------------------------------------------------------
// Load pricing, and invoices for storage + billing cards
// (bldCfg already loaded above)
$bldCfg      = loadBuildingConfig($building); // reload after any saves
$pricingFile = CONFIG_DIR . 'pricing.json';
$pricing     = file_exists($pricingFile) ? json_decode(file_get_contents($pricingFile), true) ?? [] : [];
$storageUsed  = (int)($bldCfg['storageUsed']  ?? 0);
$storageLimit = (int)($bldCfg['storageLimit'] ?? (int)($pricing['storageDefaultLimit'] ?? 524288000));
require_once __DIR__ . '/invoice-helpers.php';
$invoices = loadInvoices($building);

// Billing token — used to generate Pay URL for threshold invoices (no paymentToken)
$billingTok        = $bldCfg['billingToken'] ?? null;
$hasBillingToken   = !empty($billingTok['token']) && strtotime($billingTok['expires'] ?? '0') > time();
$billingTokenPayUrl = $hasBillingToken
  ? 'billing.php?' . http_build_query(['building' => $building, 'type' => $billingTok['type'] ?? '', 'token' => $billingTok['token']])
  : null;


// -------------------------------------------------------
// ToS gate — only when logged in and no mustChange pending
// -------------------------------------------------------
if (!$mustChange) {
  $tosFile = CONFIG_DIR . 'tos.json';
  if (file_exists($tosFile)) {
    $tos      = json_decode(file_get_contents($tosFile), true);
    $scope    = $tos['scope'] ?? [];
    $inScope  = $scope === 'all' || (is_array($scope) && in_array($building, $scope, true));
    if ($inScope) {
      $bldCfg  = loadBuildingConfig($building);
      $accepted = (int)($bldCfg['tosAccepted']['version'] ?? 0);
      if ($accepted < (int)($tos['version'] ?? 1)) {
        header('Location: tos-accept.php?building=' . urlencode($building));
        exit;
      }
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
    .low-credit-warn { padding: 0.4rem 0.7rem; background: #fffbeb;
                       border: 1px solid #f59e0b; border-radius: 4px; font-size: 0.82rem; color: #92400e; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Admin</h1>
  <div style="display:flex;align-items:center;gap:1.5rem;">
    <?php if (!empty($bldCfg['siteURL'])): ?>
      <a href="<?= htmlspecialchars($bldCfg['siteURL']) ?>" style="font-size:0.9rem;color:#555;text-decoration:none;">← Back to site</a>
    <?php endif; ?>
    <a href="admin.php?building=<?= urlencode($building) ?>&logout=1" class="logout">Log out</a>
  </div>
</div>

<?php if ($isTestSite): ?>
  <div style="background:#fef9c3;border:1px solid #fbbf24;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1.5rem;font-size:0.9rem;color:#92400e;">
    <strong>Demo Site</strong> — You are viewing a demonstration site. Password changes, contact email, and Woolsy knowledge base rebuilds are disabled. Contact <a href="https://sheepsite.com" style="color:#92400e;">SheepSite</a> to activate your full account.
  </div>
<?php endif; ?>

<?php if ($mustChange): ?>
  <div class="message error" style="margin-bottom:2rem;">
    You are logged in with a temporary password. Please set a new password to continue.
  </div>
<?php else: ?>
  <div class="subtitle">Building administration tools</div>

  <a href="database-admin.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon">🏘️</div>
    <div>
      <div class="card-title">Manage Residents/Owners</div>
      <div class="card-desc">
        Add, edit, or remove residents across all units. Update contact info, vehicle details,
        and emergency contacts. Bulk import from CSV. Includes Email List capture for community-wide emails.
      </div>
    </div>
  </a>

  <a href="manage-users.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon">👥</div>
    <div>
      <div class="card-title">Manage User Accounts</div>
      <div class="card-desc">
        View login accounts, reset passwords, and remove accounts for residents who have moved out.
        Use Sync to create accounts for all database residents at once, or find and remove orphaned accounts.
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
        View usage for Public and Private folders, broken down by subfolder.
        <?php if ($storageUsed > 0): ?>
          <br><strong><?= fmtAdminBytes($storageUsed) ?></strong> used
          of <strong><?= fmtAdminBytes($storageLimit) ?></strong> allowed.
        <?php endif; ?>
      </div>
    </div>
  </a>

  <?php
    $openInvoices  = array_values(array_filter($invoices, fn($i) => ($i['status'] ?? '') !== 'paid'));
    $totalOwed     = array_sum(array_column($openInvoices, 'total'));
    $renewalDate   = $bldCfg['renewalDate'] ?? null;
    $renewalDays   = $renewalDate ? (int)ceil((strtotime($renewalDate) - time()) / 86400) : null;
    $renewalUrgent = $renewalDays !== null && $renewalDays <= 30;
  ?>
  <details class="card" style="display:block;padding:1.25rem;cursor:default;">
    <summary style="list-style:none;display:flex;gap:1.25rem;align-items:flex-start;cursor:pointer;">
      <div class="card-icon">💳</div>
      <div style="flex:1;">
        <div class="card-title" style="color:inherit;">Billing History</div>
        <div class="card-desc">
          <?php if ($openInvoices): ?>
            <span style="color:#b45309;font-weight:600;">$<?= number_format($totalOwed, 2) ?> due</span>
          <?php elseif ($invoices): ?>
            <span style="color:#1a7f37;font-weight:600;">&#10003; Paid</span>
          <?php else: ?>
            No invoices on record
          <?php endif; ?>
          <?php if ($renewalDate): ?>
            &nbsp;&middot;&nbsp;
            <span style="color:<?= $renewalUrgent ? '#dc2626' : '#666' ?>;font-weight:<?= $renewalUrgent ? '600' : 'normal' ?>;">
              Renews <?= htmlspecialchars($renewalDate) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </summary>

    <?php if ($invoices): ?>
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-top:1rem;">
      <thead>
        <tr>
          <th style="text-align:left;padding:5px 8px;border-bottom:2px solid #eee;color:#777;font-size:0.78rem;font-weight:600;">Invoice</th>
          <th style="text-align:left;padding:5px 8px;border-bottom:2px solid #eee;color:#777;font-size:0.78rem;font-weight:600;">Date</th>
          <th style="text-align:right;padding:5px 8px;border-bottom:2px solid #eee;color:#777;font-size:0.78rem;font-weight:600;">Amount</th>
          <th style="text-align:left;padding:5px 8px;border-bottom:2px solid #eee;color:#777;font-size:0.78rem;font-weight:600;">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($invoices as $inv):
        $isPaid   = ($inv['status'] ?? '') === 'paid';
        $invToken = $inv['paymentToken'] ?? '';
      ?>
        <tr>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;">
            <a href="invoice-view.php?<?= htmlspecialchars(http_build_query(['building' => $building, 'invoice' => $inv['id']])) ?>"
               target="_blank" style="font-size:0.78rem;font-family:monospace;color:#0070f3;text-decoration:none;">
              <?= htmlspecialchars($inv['id']) ?>
            </a>
          </td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;white-space:nowrap;"><?= htmlspecialchars($inv['date']) ?></td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;">$<?= number_format($inv['total'], 2) ?></td>
          <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;">
            <?php if ($isPaid): ?>
              <span style="color:#1a7f37;font-weight:600;">&#10003; Paid</span>
            <?php elseif ($invToken): ?>
              <a href="billing-invoice.php?<?= htmlspecialchars(http_build_query(['building' => $building, 'invoice' => $inv['id'], 'token' => $invToken])) ?>"
                 target="_blank" style="color:#b45309;font-weight:600;">Pay &rarr;</a>
            <?php elseif ($billingTokenPayUrl && ($billingTok['invoiceId'] ?? '') === $inv['id']): ?>
              <a href="<?= htmlspecialchars($billingTokenPayUrl) ?>"
                 target="_blank" style="color:#b45309;font-weight:600;">Pay &rarr;</a>
            <?php else: ?>
              <span style="color:#b45309;font-weight:600;">Open</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </details>

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
    $wc           = getWoolsyCredits($building);
    $wAlloc       = (float)($wc['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
    $wUsed        = (float)($wc['used']      ?? 0);
    $wPct         = $wAlloc > 0 ? min(100, round($wUsed / $wAlloc * 100)) : 100;
    $rulesFile    = __DIR__ . '/faqs/' . $building . '_rules.md';
    $rulesVersion = getRulesVersion($rulesFile);
    $promptOutdated = ($rulesVersion > 0 && $rulesVersion < PROMPT_VERSION);
    $usageFile    = __DIR__ . '/faqs/woolsy_usage.json';
    $usageAll     = file_exists($usageFile) ? (json_decode(file_get_contents($usageFile), true) ?? []) : [];
    $monthCount   = $usageAll[$building][date('Y-m')] ?? 0;
  ?>
  <a href="woolsy-manage.php?building=<?= urlencode($building) ?>" class="card">
    <div class="card-icon"><img src="https://sheepsite.com/Scripts/assets/Woolsy-standing-transparent.png" height="44" alt="Woolsy"></div>
    <div>
      <div class="card-title">Woolsy AI Assistant</div>
      <div class="card-desc">
        Knowledge base status, usage statistics, FAQ editor, and credit tracking.
        <?php if ($wPct >= 100): ?>
          <br><span style="color:#dc2626;font-weight:600;">⛔ Credits exhausted — Woolsy unavailable.</span>
        <?php elseif ($wPct >= 80): ?>
          <br><span style="color:#b45309;font-weight:600;">⚠️ Credits running low.</span>
        <?php else: ?>
          <br><span style="color:#888;"><?= $monthCount ?> question<?= $monthCount !== 1 ? 's' : '' ?> this month</span>
        <?php endif; ?>
      </div>
    </div>
  </a>
  <?php if ($promptOutdated): ?>
  <div class="low-credit-warn" style="margin-top:-0.5rem;margin-bottom:1rem;">
    ⚠️ Woolsy prompt updated (v<?= PROMPT_VERSION ?>) — a rebuild is recommended.
    <a href="woolsy-update.php?building=<?= urlencode($building) ?>" style="color:#92400e;font-weight:600;">Rebuild now →</a>
  </div>
  <?php endif; ?>

  <?php
    $contactEmail = htmlspecialchars($bldCfg['contactEmail'] ?? '');
    $siteURL      = htmlspecialchars($bldCfg['siteURL']      ?? '');
  ?>
  <div class="card" style="flex-direction:column;gap:0.5rem;cursor:default;">
    <div style="display:flex;gap:1.25rem;align-items:flex-start;">
      <div class="card-icon">⚙️</div>
      <div>
        <div class="card-title" style="color:inherit;">Building Settings</div>
        <div class="card-desc">Contact email and website URL for this building.</div>
      </div>
    </div>
    <?php if ($settingsMessage): ?>
      <div class="message <?= $settingsMessageType ?>"><?= htmlspecialchars($settingsMessage) ?></div>
    <?php endif; ?>
    <form method="post" action="admin.php?building=<?= urlencode($building) ?>"
          style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;margin-top:0.25rem;">
      <div style="flex:1;min-width:220px;">
        <label for="contact_email" style="font-size:0.82rem;font-weight:bold;display:block;margin-bottom:0.25rem;">
          Building contact email
        </label>
        <input type="text" id="contact_email" name="contact_email"
               value="<?= $contactEmail ?>"
               placeholder="board@example.com"
               <?= $isTestSite ? 'disabled title="Not available in demo mode"' : '' ?>
               style="width:100%;padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;<?= $isTestSite ? 'background:#f5f5f5;color:#999;' : '' ?>">
      </div>
      <div style="flex:1;min-width:220px;">
        <label style="font-size:0.82rem;font-weight:bold;display:block;margin-bottom:0.25rem;">
          Building website URL
        </label>
        <?php if ($siteURL): ?>
          <a href="<?= $siteURL ?>" target="_blank" style="font-size:0.9rem;"><?= $siteURL ?></a>
        <?php else: ?>
          <span style="font-size:0.9rem;color:#999;">Not set — configure in Master Admin</span>
        <?php endif; ?>
      </div>
      <button type="submit" name="save_building_settings" class="save-btn" style="white-space:nowrap;"
              <?= $isTestSite ? 'disabled title="Not available in demo mode"' : '' ?>>Save</button>
    </form>
    <p style="font-size:0.78rem;color:#999;margin:0.1rem 0 0;">
      Website URL enables "Back to site" links in welcome emails and resident pages.
      Contact email is used for resident change requests (falls back to President's email if blank).
    </p>
  </div>

  <hr>
<?php endif; ?>

<!-- ── Manage Admins ── -->
<h2>Admin Accounts</h2>

<?php if ($adminsMessage): ?>
  <div class="message <?= $adminsMessageType ?>"><?= htmlspecialchars($adminsMessage) ?></div>
<?php endif; ?>

<?php
  $currentAdmins = loadAdminCreds($adminCredFile);
?>
<table style="width:100%;border-collapse:collapse;font-size:0.875rem;margin-bottom:1rem;">
  <thead>
    <tr>
      <th style="text-align:left;padding:5px 8px;border-bottom:2px solid #eee;color:#777;font-size:0.78rem;font-weight:600;">Username</th>
      <th style="text-align:left;padding:5px 8px;border-bottom:2px solid #eee;color:#777;font-size:0.78rem;font-weight:600;">Email</th>
      <th style="padding:5px 8px;border-bottom:2px solid #eee;"></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($currentAdmins as $adm): ?>
    <tr>
      <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;font-family:monospace;">
        <?= htmlspecialchars($adm['user']) ?>
        <?php if ($adm['user'] === $loggedInUser): ?>
          <span style="font-family:sans-serif;font-size:0.75rem;color:#888;">(you)</span>
        <?php endif; ?>
        <?php if (!empty($adm['mustChange'])): ?>
          <span style="font-family:sans-serif;font-size:0.75rem;color:#d97706;">temp pw</span>
        <?php endif; ?>
      </td>
      <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;">
        <form method="post" action="admin.php?building=<?= urlencode($building) ?>"
              style="display:flex;gap:0.4rem;align-items:center;">
          <input type="hidden" name="edit_admin_user" value="<?= htmlspecialchars($adm['user']) ?>">
          <input type="text" name="edit_admin_email"
                 value="<?= htmlspecialchars($adm['email'] ?? '') ?>"
                 placeholder="email@example.com"
                 style="width:220px;padding:0.3rem 0.5rem;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;">
          <button type="submit" name="update_admin_email" class="save-btn" style="padding:0.3rem 0.7rem;font-size:0.8rem;">Save</button>
        </form>
      </td>
      <td style="padding:6px 8px;border-bottom:1px solid #f0f0f0;text-align:right;">
        <?php if ($adm['user'] !== $loggedInUser && count($currentAdmins) > 1): ?>
          <form method="post" action="admin.php?building=<?= urlencode($building) ?>"
                onsubmit="return confirm('Remove admin account <?= htmlspecialchars(addslashes($adm['user'])) ?>?');">
            <input type="hidden" name="remove_admin_user" value="<?= htmlspecialchars($adm['user']) ?>">
            <button type="submit" name="remove_admin" style="background:none;border:none;color:#dc2626;font-size:0.85rem;cursor:pointer;padding:0;">Remove</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<details style="margin-bottom:2rem;">
  <summary style="cursor:pointer;font-size:0.9rem;color:#0070f3;font-weight:600;margin-bottom:0.75rem;">+ Add admin account</summary>
  <form method="post" action="admin.php?building=<?= urlencode($building) ?>"
        style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:flex-end;margin-top:0.75rem;max-width:640px;">
    <div style="flex:1;min-width:140px;">
      <label style="font-size:0.82rem;font-weight:bold;display:block;margin-bottom:0.25rem;">Username</label>
      <input type="text" name="new_admin_user" autocapitalize="none" autocomplete="off"
             placeholder="jsmith"
             style="width:100%;padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;">
    </div>
    <div style="flex:2;min-width:180px;">
      <label style="font-size:0.82rem;font-weight:bold;display:block;margin-bottom:0.25rem;">Email</label>
      <input type="text" name="new_admin_email" autocomplete="off" placeholder="jsmith@example.com"
             style="width:100%;padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;">
    </div>
    <div style="flex:2;min-width:160px;">
      <label style="font-size:0.82rem;font-weight:bold;display:block;margin-bottom:0.25rem;">Password</label>
      <input type="password" name="new_admin_pass" autocomplete="new-password"
             style="width:100%;padding:0.4rem 0.6rem;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;">
    </div>
    <button type="submit" name="add_admin" class="save-btn">Add</button>
  </form>
</details>

<hr>

<h2>Change Admin Password</h2>

<?php if ($isTestSite): ?>
  <p style="color:#999;font-size:0.9rem;font-style:italic;">Password changes are disabled in demo mode.</p>
<?php elseif (!$loggedInAdmin): ?>
  <p style="color:#999;font-size:0.9rem;font-style:italic;">Logged in as master admin — use master credentials to change your own password.</p>
<?php else: ?>

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
<?php endif; ?>


</body>
</html>
