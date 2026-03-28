<?php
// -------------------------------------------------------
// database-admin.php
// Admin CRUD for resident database (Database, CarDB, Emergency tabs).
// Auth: manage_auth_{building} session (same as admin.php / manage-users.php).
//
//   https://sheepsite.com/Scripts/database-admin.php?building=LyndhurstH
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('OWNER_IMPORT_TOKEN', 'QRF*!v2r2KgJEesq&P');  // must match building-script.gs
define('CONFIG_DIR', __DIR__ . '/config/');

$buildings = require __DIR__ . '/buildings.php';

// -------------------------------------------------------
// Validate building
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildingConfig = $buildings[$building];
$webAppURL      = $buildingConfig['webAppURL'];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));
$sessionKey     = 'manage_auth_' . $building;

$adminCredFile  = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
  header('Location: forgot-password.php?building=' . urlencode($building) . '&role=admin&setup=1');
  exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------

function gasGet(string $url, array $params): array {
  $fullUrl = $url . '?' . http_build_query($params);
  $ctx     = stream_context_create(['http' => ['timeout' => 30]]);
  $body    = @file_get_contents($fullUrl, false, $ctx);
  if ($body === false) return ['error' => 'Could not reach Apps Script'];
  return json_decode($body, true) ?? ['error' => 'Invalid response'];
}

function gasPost(string $url, array $data): array {
  if (!function_exists('curl_init')) return ['error' => 'curl not available'];
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  curl_close($ch);
  if ($resp === false) return ['error' => 'Request failed'];
  return json_decode($resp, true) ?? ['error' => 'Invalid response'];
}

function loadUsers(string $building): array {
  $file = CREDENTIALS_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveUsers(string $building, array $users): bool {
  return file_put_contents(
    CREDENTIALS_DIR . $building . '.json',
    json_encode(array_values($users), JSON_PRETTY_PRINT)
  ) !== false;
}

function generateUsername(string $first, string $last, array $existing): string {
  $base = strtolower(preg_replace('/[^a-z]/i', '', substr($first, 0, 1)) .
                     preg_replace('/[^a-z]/i', '', $last));
  if (!$base) $base = 'resident';
  $username = $base;
  $n = 2;
  while (in_array($username, $existing)) {
    $username = $base . $n++;
  }
  return $username;
}

function generateTempPassword(int $length = 8): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
  $pw = '';
  for ($i = 0; $i < $length; $i++) {
    $pw .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $pw;
}

function loadConfig(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveConfig(string $building, array $config): bool {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  return file_put_contents(
    CONFIG_DIR . $building . '.json',
    json_encode($config, JSON_PRETTY_PRINT)
  ) !== false;
}

// -------------------------------------------------------
// Auth check (before any AJAX handling)
// -------------------------------------------------------
if (empty($_SESSION[$sessionKey])) {
  header('Location: admin.php?building=' . urlencode($building));
  exit;
}

// -------------------------------------------------------
// AJAX handlers — return JSON and exit
// -------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action) {
  header('Content-Type: application/json');

  switch ($action) {

    // --- Read: unit list ---
    case 'listDatabase':
      echo json_encode(gasGet($webAppURL, [
        'page'  => 'listDatabase',
        'token' => OWNER_IMPORT_TOKEN,
      ]));
      exit;

    // --- Read: single unit detail ---
    case 'getUnit':
      $unit = trim($_GET['unit'] ?? '');
      echo json_encode(gasGet($webAppURL, [
        'page'  => 'getUnit',
        'token' => OWNER_IMPORT_TOKEN,
        'unit'  => $unit,
      ]));
      exit;

    // --- Read: all emails ---
    case 'getAllEmails':
      echo json_encode(gasGet($webAppURL, [
        'page'  => 'getAllEmails',
        'token' => OWNER_IMPORT_TOKEN,
      ]));
      exit;

    // --- Write: add resident (GAS row + web account) ---
    case 'addDatabaseRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      // 1. Write to Google Sheet
      $gasResult = gasPost($webAppURL, array_merge($body, [
        'action' => 'addDatabaseRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ]));
      if (!empty($gasResult['error'])) { echo json_encode($gasResult); exit; }

      // 2. Create web account only if resident has an email address
      $first = trim($body['First Name'] ?? '');
      $last  = trim($body['Last Name']  ?? '');
      $email = trim($body['eMail']      ?? '');

      if (!$email) {
        echo json_encode(['ok' => true, 'noEmail' => true]);
        exit;
      }

      $users    = loadUsers($building);
      $existing = array_column($users, 'user');
      $username = generateUsername($first, $last, $existing);
      $tempPw   = generateTempPassword();
      $users[]  = ['user' => $username, 'pass' => password_hash($tempPw, PASSWORD_DEFAULT), 'mustChange' => true];
      saveUsers($building, $users);

      // Email the temp password via Apps Script
      $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $dir      = rtrim(dirname($_SERVER['PHP_SELF']), '/');
      $siteURL  = loadConfig($building)['siteURL'] ?? '';
      $loginURL = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir
                . '/display-private-dir.php?building=' . urlencode($building)
                . ($siteURL ? '&return=' . urlencode($siteURL) : '');
      $resetURL = $webAppURL
                . '?page=resetpw'
                . '&token='       . urlencode(OWNER_IMPORT_TOKEN)
                . '&username='    . urlencode($username)
                . '&building='    . urlencode($building)
                . '&tmppw='       . urlencode($tempPw)
                . '&loginurl='    . urlencode($loginURL)
                . '&directemail=' . urlencode($email ?? $newEmail ?? '');
      $emailResp = @file_get_contents($resetURL);
      $emailSent = false;
      if ($emailResp !== false) {
        $emailData = json_decode($emailResp, true);
        $emailSent = ($emailData['status'] ?? '') === 'ok';
      }

      echo json_encode(['ok' => true, 'username' => $username, 'emailSent' => $emailSent]);
      exit;
    }

    // --- Write: edit resident (GAS row + optional username rename) ---
    case 'editDatabaseRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $gasResult = gasPost($webAppURL, array_merge($body, [
        'action' => 'editDatabaseRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ]));
      if (!empty($gasResult['error'])) { echo json_encode($gasResult); exit; }

      // Handle username rename if name changed
      $oldFirst = trim($body['matchFirst'] ?? '');
      $oldLast  = trim($body['matchLast']  ?? '');
      $newFirst = trim($body['First Name'] ?? $oldFirst);
      $newLast  = trim($body['Last Name']  ?? $oldLast);
      $response = ['ok' => true];

      if (($newFirst !== $oldFirst || $newLast !== $oldLast) && $oldFirst && $oldLast) {
        $users       = loadUsers($building);
        $existing    = array_column($users, 'user');
        $oldUsername = generateUsername($oldFirst, $oldLast, array_diff($existing, [generateUsername($oldFirst, $oldLast, $existing)]));
        // Find actual old username by regenerating
        $oldBase = strtolower(preg_replace('/[^a-z]/i', '', substr($oldFirst, 0, 1)) .
                              preg_replace('/[^a-z]/i', '', $oldLast));
        $oldIdx  = null;
        foreach ($users as $i => $u) {
          if (str_starts_with($u['user'], $oldBase === '' ? 'resident' : $oldBase)) {
            // Check if it would have been generated from oldFirst/oldLast
            $oldUsername = $u['user'];
            $oldIdx      = $i;
            break;
          }
        }
        if ($oldIdx !== null) {
          $existingWithout = array_diff($existing, [$users[$oldIdx]['user']]);
          $newUsername     = generateUsername($newFirst, $newLast, array_values($existingWithout));
          if ($newUsername !== $users[$oldIdx]['user']) {
            $users[$newIdx = $oldIdx]['user'] = $newUsername;
            saveUsers($building, $users);
            $response['usernameChanged'] = true;
            $response['oldUsername']     = $oldUsername;
            $response['newUsername']     = $newUsername;
          }
        }
      }

      // If an email was just added and no web account exists yet, create one now
      $newEmail = trim($body['eMail'] ?? '');
      if ($newEmail) {
        $currentFirst = $newFirst ?: $oldFirst;
        $currentLast  = $newLast  ?: $oldLast;
        $users        = loadUsers($building);
        $base         = strtolower(preg_replace('/[^a-z]/i', '', substr($currentFirst, 0, 1)) .
                                   preg_replace('/[^a-z]/i', '', $currentLast));
        $hasAccount   = false;
        foreach ($users as $u) {
          if (preg_match('/^' . preg_quote($base ?: 'resident', '/') . '\d*$/', $u['user'])) {
            $hasAccount = true;
            break;
          }
        }
        if (!$hasAccount) {
          $existing = array_column($users, 'user');
          $username = generateUsername($currentFirst, $currentLast, $existing);
          $tempPw   = generateTempPassword();
          $users[]  = ['user' => $username, 'pass' => password_hash($tempPw, PASSWORD_DEFAULT), 'mustChange' => true];
          saveUsers($building, $users);

          $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $dir      = rtrim(dirname($_SERVER['PHP_SELF']), '/');
          $loginURL = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir
                    . '/display-private-dir.php?building=' . urlencode($building);
          $resetURL = $webAppURL
                    . '?page=resetpw'
                    . '&token='       . urlencode(OWNER_IMPORT_TOKEN)
                    . '&username='    . urlencode($username)
                    . '&building='    . urlencode($building)
                    . '&tmppw='       . urlencode($tempPw)
                    . '&loginurl='    . urlencode($loginURL)
                    . '&directemail=' . urlencode($newEmail);
          $emailResp = @file_get_contents($resetURL);
          $emailSent = ($emailResp !== false && ($emailData = json_decode($emailResp, true)) && ($emailData['status'] ?? '') === 'ok');

          $response['accountCreated'] = true;
          $response['username']       = $username;
          $response['emailSent']      = $emailSent;
        }
      }

      echo json_encode($response);
      exit;
    }

    // --- Write: delete resident (GAS row + web credential) ---
    case 'deleteDatabaseRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      $gasResult = gasPost($webAppURL, array_merge($body, [
        'action' => 'deleteDatabaseRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ]));
      if (!empty($gasResult['error'])) { echo json_encode($gasResult); exit; }

      // Remove web credential
      $first = trim($body['matchFirst'] ?? '');
      $last  = trim($body['matchLast']  ?? '');
      if ($first && $last) {
        $users   = loadUsers($building);
        $base    = strtolower(preg_replace('/[^a-z]/i', '', substr($first, 0, 1)) .
                              preg_replace('/[^a-z]/i', '', $last));
        $users   = array_filter($users, fn($u) => !str_starts_with($u['user'], $base ?: 'resident'));
        saveUsers($building, array_values($users));
      }

      echo json_encode(['ok' => true]);
      exit;
    }

    // --- Write: car row ---
    case 'editCarRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      echo json_encode(gasPost($webAppURL, array_merge($body, [
        'action' => 'editCarRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ])));
      exit;
    }

    // --- Write: emergency rows ---
    case 'addEmergencyRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      echo json_encode(gasPost($webAppURL, array_merge($body, [
        'action' => 'addEmergencyRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ])));
      exit;
    }

    case 'editEmergencyRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      echo json_encode(gasPost($webAppURL, array_merge($body, [
        'action' => 'editEmergencyRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ])));
      exit;
    }

    case 'deleteEmergencyRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      echo json_encode(gasPost($webAppURL, array_merge($body, [
        'action' => 'deleteEmergencyRow',
        'token'  => OWNER_IMPORT_TOKEN,
      ])));
      exit;
    }

    // --- Config: floor grouping ---
    case 'getConfig':
      echo json_encode(loadConfig($building));
      exit;

    case 'saveConfig': {
      $body   = json_decode(file_get_contents('php://input'), true) ?? [];
      $config = loadConfig($building);
      foreach (['floorGrouping', 'floorDigits'] as $key) {
        if (array_key_exists($key, $body)) $config[$key] = $body[$key];
      }
      echo json_encode(['ok' => saveConfig($building, $config)]);
      exit;
    }
  }

  echo json_encode(['error' => 'Unknown action']);
  exit;
}

// -------------------------------------------------------
// Main page
// -------------------------------------------------------
$config = loadConfig($building);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Manage Residents/Owners</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body        { font-family: sans-serif; max-width: 900px; margin: 0 auto; padding: 1.5rem 1rem; }
    .top-bar    { display: flex; align-items: baseline; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    h1          { margin: 0; font-size: 1.4rem; flex: 1; }
    .top-links  { display: flex; gap: 1rem; font-size: 0.85rem; }
    .top-links a { color: #0070f3; text-decoration: none; }
    .top-links a:hover { text-decoration: underline; }

    .toolbar    { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; }
    input[type=search], input[type=text] {
                  padding: 0.45rem 0.7rem; border: 1px solid #ccc; border-radius: 4px;
                  font-size: 0.95rem; width: 260px; }
    .btn        { padding: 0.45rem 1rem; border: none; border-radius: 4px; font-size: 0.875rem;
                  cursor: pointer; white-space: nowrap; }
    .btn-primary  { background: #0070f3; color: #fff; }
    .btn-primary:hover { background: #005bb5; }
    .btn-secondary { background: #f0f0f0; color: #333; border: 1px solid #ccc; }
    .btn-secondary:hover { background: #e0e0e0; }
    .btn-danger   { background: #dc2626; color: #fff; }
    .btn-danger:hover { background: #b91c1c; }
    .btn-sm     { padding: 0.3rem 0.7rem; font-size: 0.8rem; }

    /* Floor accordion */
    .floor-group    { margin-bottom: 0.5rem; border: 1px solid #e0e0e0; border-radius: 6px; overflow: hidden; }
    .floor-header   { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem;
                      background: #f8f8f8; cursor: pointer; user-select: none; }
    .floor-header:hover { background: #f0f0f0; }
    .floor-toggle   { font-size: 0.75rem; color: #888; width: 12px; }
    .floor-title    { font-weight: 600; font-size: 0.95rem; }
    .floor-count    { font-size: 0.82rem; color: #888; }
    .floor-body     { display: none; }
    .floor-body.open { display: block; }

    /* Unit rows */
    .unit-row       { display: flex; align-items: baseline; gap: 0.75rem; padding: 0.55rem 1rem;
                      border-bottom: 1px solid #f0f0f0; cursor: pointer; }
    .unit-row:last-child { border-bottom: none; }
    .unit-row:hover { background: #fafafa; }
    .unit-num       { font-weight: 600; min-width: 60px; font-size: 0.9rem; }
    .unit-names     { font-size: 0.875rem; color: #555; flex: 1; }
    .unit-expand-icon { font-size: 0.75rem; color: #bbb; }

    /* Unit detail panel */
    .unit-detail    { background: #f9f9f9; border-top: 1px solid #e8e8e8;
                      padding: 1.25rem 1rem; display: none; }
    .unit-detail.open { display: block; }
    .detail-loading { color: #888; font-size: 0.9rem; padding: 0.5rem 0; }

    /* Tabs */
    .tabs           { display: flex; gap: 0; border-bottom: 2px solid #ddd; margin-bottom: 1rem; }
    .tab-btn        { padding: 0.5rem 1.1rem; font-size: 0.875rem; border: none; background: none;
                      cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px;
                      color: #666; }
    .tab-btn.active { color: #0070f3; border-bottom-color: #0070f3; font-weight: 600; }
    .tab-panel      { display: none; }
    .tab-panel.active { display: block; }

    /* Resident cards */
    .person-card    { border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;
                      margin-bottom: 0.75rem; background: #fff; }
    .person-name    { font-weight: 600; margin-bottom: 0.75rem; font-size: 0.95rem; }
    .field-grid     { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.6rem; }
    .field-pair     { display: flex; flex-direction: column; gap: 0.15rem; }
    .field-label    { font-size: 0.75rem; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    .field-val      { font-size: 0.875rem; color: #333; }
    .field-val.empty { color: #bbb; font-style: italic; }
    .person-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; }

    /* Inline edit form */
    .edit-form      { border: 1px solid #b3d1ff; border-radius: 6px; padding: 1rem;
                      margin-bottom: 0.75rem; background: #f0f7ff; }
    .edit-form .field-grid input,
    .edit-form .field-grid select,
    .edit-form .field-grid textarea {
                      width: 100%; padding: 0.35rem 0.5rem; border: 1px solid #ccc;
                      border-radius: 4px; font-size: 0.875rem; }
    .edit-form .field-grid label { font-size: 0.78rem; color: #555; font-weight: 600; margin-bottom: 0.15rem; display: block; }
    .checkbox-row   { display: flex; align-items: center; gap: 0.4rem; margin-top: 0.25rem; }
    .checkbox-row input { width: auto; }
    .form-actions   { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
    .msg            { padding: 0.5rem 0.75rem; border-radius: 4px; font-size: 0.875rem; margin-bottom: 0.75rem; }
    .msg.ok         { background: #e6f4ea; color: #1a7f37; }
    .msg.error      { background: #ffeef0; color: #c00; }
    .msg.info       { background: #eff6ff; color: #1d4ed8; }

    /* Add resident new-person form */
    #add-person-form { border: 2px dashed #b3d1ff; border-radius: 6px; padding: 1rem;
                       margin-bottom: 0.75rem; background: #f8faff; display: none; }

    /* No results */
    .no-results     { color: #888; font-size: 0.9rem; padding: 1rem 0; }

    /* Toast notification */
    #toast          { position: fixed; top: 1.25rem; left: 50%; transform: translateX(-50%);
                      background: #1a7f37; color: #fff; padding: 0.65rem 1.25rem;
                      border-radius: 6px; font-size: 0.9rem; box-shadow: 0 4px 16px rgba(0,0,0,0.18);
                      z-index: 200; opacity: 0; transition: opacity 0.25s;
                      max-width: 90vw; text-align: center; pointer-events: none; }
    #toast.error    { background: #c00; }
    #toast.visible  { opacity: 1; }

    /* Floor setup prompt */
    #floor-setup-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,0.4);
      display: flex; align-items: center; justify-content: center; z-index: 100;
    }
    #floor-setup-box {
      background: #fff; border-radius: 8px; padding: 1.5rem; max-width: 480px; width: 90%;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    }
    #floor-setup-box h3 { margin: 0 0 0.5rem; font-size: 1.1rem; }
    #floor-setup-box p  { font-size: 0.875rem; color: #666; margin: 0 0 1rem; }
    .floor-option       { display: flex; align-items: flex-start; gap: 0.6rem; padding: 0.65rem 0.75rem;
                          border: 1px solid #ddd; border-radius: 6px; margin-bottom: 0.5rem;
                          cursor: pointer; transition: background 0.12s; }
    .floor-option:hover { background: #f5f5f5; }
    .floor-option input[type=radio] { margin-top: 0.15rem; flex-shrink: 0; }
    .floor-option-title { font-weight: 600; font-size: 0.9rem; }
    .floor-option-desc  { font-size: 0.8rem; color: #888; margin-top: 0.1rem; }
    #floor-setup-actions { display: flex; gap: 0.75rem; margin-top: 1rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – Residents &amp; Owners</h1>
  <div class="top-links">
    <a href="admin.php?building=<?= urlencode($building) ?>">← Admin</a>
    <a href="admin.php?building=<?= urlencode($building) ?>&logout=1">Log out</a>
  </div>
</div>

<div class="toolbar">
  <input type="search" id="unit-search" placeholder="Search units or names…" oninput="filterUnits(this.value)">
  <button class="btn btn-secondary" id="copy-emails-btn" onclick="copyAllEmails()"
    title="Copies all resident emails to the clipboard. Then simply Paste into the CC or BCC field of your email.">Get Email List</button>
  <button class="btn btn-primary" onclick="showAddUnit()">+ Add to Unit…</button>
</div>

<div id="toast"></div>
<div id="units-container">
  <div class="no-results">Loading…</div>
</div>

<!-- Floor grouping setup overlay (shown once on first visit) -->
<div id="floor-setup-overlay" style="display:none;">
  <div id="floor-setup-box">
    <h3>How should units be grouped?</h3>
    <p>Choose how to organize the unit list. You can change this later in Building Settings.</p>

    <label class="floor-option">
      <input type="radio" name="floor-choice" value="1" checked>
      <div>
        <div class="floor-option-title">Group by first digit</div>
        <div class="floor-option-desc">1001, 1002… → Floor 1 &nbsp;·&nbsp; 2001, 2002… → Floor 2</div>
      </div>
    </label>

    <label class="floor-option">
      <input type="radio" name="floor-choice" value="2">
      <div>
        <div class="floor-option-title">Group by first two digits</div>
        <div class="floor-option-desc">1001, 1002… → Floor 10 &nbsp;·&nbsp; 1101, 1102… → Floor 11</div>
      </div>
    </label>

    <label class="floor-option">
      <input type="radio" name="floor-choice" value="0">
      <div>
        <div class="floor-option-title">No grouping — flat list</div>
        <div class="floor-option-desc">All units in a single sorted list</div>
      </div>
    </label>

    <div id="floor-setup-actions">
      <button class="btn btn-primary" onclick="confirmFloorGrouping()">Save preference</button>
    </div>
  </div>
</div>

<script>
const BUILDING     = <?= json_encode($building) ?>;
const BUILD_LABEL  = <?= json_encode($buildLabel) ?>;
const SCRIPT_BASE  = 'database-admin.php?building=' + encodeURIComponent(BUILDING);
const BOARD_ROLES  = ['', 'President', 'Vice President', 'Treasurer', 'Secretary', 'Director'];

let allRows     = [];   // flat DB rows from listDatabase
let unitMap     = {};   // unit# -> [{resident}, ...]  (built from allRows)
let unitOrder   = [];   // sorted unit numbers
let floorConfig = <?= json_encode($config) ?>;
let unitCache   = {};   // unit# -> {unit, residents, car, emergency} — avoids re-fetching after writes

// -------------------------------------------------------
// Boot
// -------------------------------------------------------
async function init() {
  const [dbRes, cfgRes] = await Promise.all([
    apiFetch('listDatabase'),
    apiFetch('getConfig'),
  ]);
  if (dbRes.error) { showError(dbRes.error); return; }
  floorConfig = cfgRes || floorConfig;

  allRows    = dbRes.rows || [];
  unitMap    = {};
  allRows.forEach(r => {
    const u = String(r['Unit #']).trim();
    if (u) (unitMap[u] = unitMap[u] || []).push(r);
  });
  unitOrder = Object.keys(unitMap).sort((a, b) =>
    a.localeCompare(b, undefined, { numeric: true })
  );

  // Floor grouping: show setup prompt on first visit (config not yet saved)
  if (floorConfig.floorGrouping === undefined) {
    document.getElementById('floor-setup-overlay').style.display = 'flex';
    return; // render after admin saves preference
  }

  renderUnits(unitOrder);
}

async function confirmFloorGrouping() {
  const digits = parseInt(document.querySelector('input[name="floor-choice"]:checked').value, 10);
  document.getElementById('floor-setup-overlay').style.display = 'none';
  if (digits === 0) {
    await apiPost('saveConfig', { floorGrouping: false });
    floorConfig.floorGrouping = false;
  } else {
    await apiPost('saveConfig', { floorGrouping: true, floorDigits: digits });
    floorConfig.floorGrouping = true;
    floorConfig.floorDigits   = digits;
  }
  renderUnits(unitOrder);
}

// -------------------------------------------------------
// Render
// -------------------------------------------------------
function renderUnits(units) {
  const container = document.getElementById('units-container');
  if (!units.length) { container.innerHTML = '<div class="no-results">No residents found.</div>'; return; }

  if (floorConfig.floorGrouping && floorConfig.floorDigits) {
    const d      = floorConfig.floorDigits;
    const groups = {};
    units.forEach(u => { const p = u.slice(0, d); (groups[p] = groups[p] || []).push(u); });
    container.innerHTML = Object.entries(groups).map(([prefix, us]) => `
      <div class="floor-group">
        <div class="floor-header" onclick="toggleFloor(this)">
          <span class="floor-toggle">▶</span>
          <span class="floor-title">Floor ${prefix}</span>
          <span class="floor-count">${us.length} unit${us.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="floor-body">
          ${us.map(u => unitRowHtml(u)).join('')}
        </div>
      </div>`).join('');
  } else {
    container.innerHTML = `<div class="floor-group" style="border:none;">
      ${units.map(u => unitRowHtml(u)).join('')}
    </div>`;
  }
}

function unitRowHtml(unit) {
  const people = unitMap[unit] || [];
  const names  = people.map(p => `${p['Last Name']}, ${p['First Name']}`).join(' · ');
  return `<div class="unit-row" data-unit="${esc(unit)}" onclick="toggleUnit(this, '${esc(unit)}')">
    <span class="unit-num">${esc(unit)}</span>
    <span class="unit-names">${esc(names) || '<span style="color:#bbb;font-style:italic;">No residents</span>'}</span>
    <span class="unit-expand-icon">▸</span>
  </div>
  <div class="unit-detail" id="detail-${esc(unit)}"></div>`;
}

function toggleFloor(header) {
  const body   = header.nextElementSibling;
  const toggle = header.querySelector('.floor-toggle');
  const open   = body.classList.toggle('open');
  toggle.textContent = open ? '▼' : '▶';
}

// -------------------------------------------------------
// Unit expand / collapse
// -------------------------------------------------------
async function toggleUnit(row, unit) {
  const detail = document.getElementById('detail-' + unit);
  const icon   = row.querySelector('.unit-expand-icon');
  if (detail.classList.contains('open')) {
    detail.classList.remove('open');
    icon.textContent = '▸';
    return;
  }
  // Close any other open unit
  document.querySelectorAll('.unit-detail.open').forEach(d => {
    d.classList.remove('open');
    const r = d.previousElementSibling;
    if (r) r.querySelector('.unit-expand-icon').textContent = '▸';
  });

  detail.classList.add('open');
  icon.textContent = '▾';

  if (!detail.dataset.loaded) {
    detail.innerHTML = '<div class="detail-loading">Loading…</div>';
    const res = await apiFetch('getUnit', { unit });
    if (res.error) { detail.innerHTML = `<div class="msg error">${esc(res.error)}</div>`; return; }
    detail.dataset.loaded = '1';
    unitCache[unit] = res;
    renderUnitDetail(detail, res);
  }
}

// -------------------------------------------------------
// Unit detail rendering
// -------------------------------------------------------
function renderUnitDetail(container, data) {
  const { unit, residents, car, emergency } = data;
  container.innerHTML = `
    <div class="tabs">
      <button class="tab-btn active" onclick="switchTab(this, 'tab-residents-${esc(unit)}')">
        Residents (${residents.length})
      </button>
      <button class="tab-btn" onclick="switchTab(this, 'tab-car-${esc(unit)}')">Vehicle &amp; Parking</button>
      <button class="tab-btn" onclick="switchTab(this, 'tab-emergency-${esc(unit)}')">
        Emergency (${emergency.length})
      </button>
    </div>

    <div class="tab-panel active" id="tab-residents-${esc(unit)}">
      ${renderResidentTab(unit, residents)}
    </div>
    <div class="tab-panel" id="tab-car-${esc(unit)}">
      ${renderCarTab(unit, car)}
    </div>
    <div class="tab-panel" id="tab-emergency-${esc(unit)}">
      ${renderEmergencyTab(unit, emergency)}
    </div>`;
}

function switchTab(btn, panelId) {
  const detail = btn.closest('.unit-detail');
  detail.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  detail.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(panelId).classList.add('active');
}

// -------------------------------------------------------
// Residents tab
// -------------------------------------------------------
function renderResidentTab(unit, residents) {
  const cards = residents.map(r => residentCardHtml(unit, r)).join('');
  return `${cards}
    <div id="add-person-form-${esc(unit)}" style="display:none;">
      ${addPersonFormHtml(unit)}
    </div>
    <button class="btn btn-secondary btn-sm" onclick="toggleAddPerson('${esc(unit)}')">+ Add Resident</button>`;
}

function residentCardHtml(unit, r) {
  const name       = `${r['First Name']} ${r['Last Name']}`.trim();
  const flags      = [r['Full Time'] ? 'Full Time' : '', r['Resident'] ? 'Resident' : '', r['Owner'] ? 'Owner' : '']
                      .filter(Boolean).join(', ');
  const board      = r['Board'] ? `<span style="color:#7c3aed;font-size:0.78rem;font-weight:600;">${esc(r['Board'])}</span>` : '';
  const id         = `card-${esc(unit)}-${esc(r['First Name'])}-${esc(r['Last Name'])}`.replace(/[^a-z0-9-]/gi, '_');

  return `<div class="person-card" id="${id}">
    <div class="person-name">${esc(name)} ${board}</div>
    <div class="field-grid">
      ${fieldPair('Status', flags || '—')}
      ${fieldPair('Email',  r['eMail'])}
      ${fieldPair('Phone 1', r['Phone #1'])}
      ${fieldPair('Phone 2', r['Phone #2'])}
      ${fieldPair('Insurance', r['Insurance'])}
      ${fieldPair('Policy #', r['Policy #'])}
      ${fieldPair('A/C Replaced', r['AC Replaced'])}
      ${fieldPair('Water Heater', r['Water Tank'])}
    </div>
    <div class="person-actions">
      <button class="btn btn-secondary btn-sm"
        onclick="showEditResident('${esc(unit)}', '${esc(r['First Name'])}', '${esc(r['Last Name'])}')">Edit</button>
      <button class="btn btn-danger btn-sm"
        onclick="deleteResident('${esc(unit)}', '${esc(r['First Name'])}', '${esc(r['Last Name'])}', '${esc(name)}')">Delete</button>
    </div>
    <div id="edit-form-${id}" style="display:none;">
      ${editResidentFormHtml(unit, r)}
    </div>
  </div>`;
}

function fieldPair(label, val) {
  const empty = !val || val === '—';
  return `<div class="field-pair">
    <div class="field-label">${label}</div>
    <div class="field-val${empty ? ' empty' : ''}">${esc(val || '—')}</div>
  </div>`;
}

function editResidentFormHtml(unit, r) {
  const id = `card-${esc(unit)}-${esc(r['First Name'])}-${esc(r['Last Name'])}`.replace(/[^a-z0-9-]/gi, '_');
  return `<div class="edit-form" style="margin-top:0.75rem;">
    <div class="field-grid">
      <div><label>Unit #</label><input type="text" value="${esc(r['Unit #'])}" disabled></div>
      <div><label>First Name</label><input type="text" id="ef-first-${id}" value="${esc(r['First Name'])}"></div>
      <div><label>Last Name</label><input type="text" id="ef-last-${id}" value="${esc(r['Last Name'])}"></div>
      <div><label>Email</label><input type="text" id="ef-email-${id}" value="${esc(r['eMail'])}"></div>
      <div><label>Phone #1</label><input type="text" id="ef-ph1-${id}" value="${esc(r['Phone #1'])}"></div>
      <div><label>Phone #2</label><input type="text" id="ef-ph2-${id}" value="${esc(r['Phone #2'])}"></div>
      <div><label>Insurance</label><input type="text" id="ef-ins-${id}" value="${esc(r['Insurance'])}"></div>
      <div><label>Policy #</label><input type="text" id="ef-pol-${id}" value="${esc(r['Policy #'])}"></div>
      <div><label>A/C Replaced</label><input type="date" id="ef-ac-${id}" value="${esc(r['AC Replaced'])}"></div>
      <div><label>Water Heater</label><input type="date" id="ef-wt-${id}" value="${esc(r['Water Tank'])}"></div>
      <div><label>Board Role</label>
        <select id="ef-board-${id}">
          ${BOARD_ROLES.map(role => `<option value="${esc(role)}"${r['Board'] === role ? ' selected' : ''}>${esc(role) || '—'}</option>`).join('')}
        </select>
      </div>
    </div>
    <div style="margin-top:0.6rem;">
      <label style="font-size:0.78rem;color:#555;font-weight:600;">Status</label>
      <div style="display:flex;gap:1rem;margin-top:0.25rem;">
        <label class="checkbox-row"><input type="checkbox" id="ef-ft-${id}"${r['Full Time'] ? ' checked' : ''}> Full Time</label>
        <label class="checkbox-row"><input type="checkbox" id="ef-res-${id}"${r['Resident'] ? ' checked' : ''}> Resident</label>
        <label class="checkbox-row"><input type="checkbox" id="ef-own-${id}"${r['Owner'] ? ' checked' : ''}> Owner</label>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary btn-sm"
        onclick="saveEditResident('${esc(unit)}', '${esc(r['First Name'])}', '${esc(r['Last Name'])}', '${id}')">Save</button>
      <button class="btn btn-secondary btn-sm"
        onclick="document.getElementById('edit-form-${id}').style.display='none'">Cancel</button>
    </div>
    <div id="ef-msg-${id}" style="margin-top:0.5rem;"></div>
  </div>`;
}

function addPersonFormHtml(unit) {
  return `<div class="edit-form">
    <div style="font-weight:600;margin-bottom:0.75rem;">Add Resident to Unit ${esc(unit)}</div>
    <div class="field-grid">
      <div><label>First Name *</label><input type="text" id="ap-first-${esc(unit)}" placeholder="Required"></div>
      <div><label>Last Name *</label><input type="text" id="ap-last-${esc(unit)}" placeholder="Required"></div>
      <div><label>Email <span style="color:#888;font-weight:400;font-size:0.75rem;">(required for web access)</span></label><input type="text" id="ap-email-${esc(unit)}"></div>
      <div><label>Phone #1</label><input type="text" id="ap-ph1-${esc(unit)}"></div>
      <div><label>Phone #2</label><input type="text" id="ap-ph2-${esc(unit)}"></div>
      <div><label>Insurance</label><input type="text" id="ap-ins-${esc(unit)}"></div>
      <div><label>Policy #</label><input type="text" id="ap-pol-${esc(unit)}"></div>
      <div><label>A/C Replaced</label><input type="date" id="ap-ac-${esc(unit)}"></div>
      <div><label>Water Heater</label><input type="date" id="ap-wt-${esc(unit)}"></div>
      <div><label>Board Role</label>
        <select id="ap-board-${esc(unit)}">
          ${BOARD_ROLES.map(role => `<option value="${esc(role)}">${esc(role) || '—'}</option>`).join('')}
        </select>
      </div>
    </div>
    <div style="margin-top:0.6rem;">
      <label style="font-size:0.78rem;color:#555;font-weight:600;">Status</label>
      <div style="display:flex;gap:1rem;margin-top:0.25rem;">
        <label class="checkbox-row"><input type="checkbox" id="ap-ft-${esc(unit)}"> Full Time</label>
        <label class="checkbox-row"><input type="checkbox" id="ap-res-${esc(unit)}" checked> Resident</label>
        <label class="checkbox-row"><input type="checkbox" id="ap-own-${esc(unit)}" checked> Owner</label>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary btn-sm" onclick="saveAddPerson('${esc(unit)}')">Create Person</button>
      <button class="btn btn-secondary btn-sm" onclick="toggleAddPerson('${esc(unit)}')">Cancel</button>
    </div>
    <div id="ap-msg-${esc(unit)}" style="margin-top:0.5rem;"></div>
  </div>`;
}

// -------------------------------------------------------
// Vehicle & Parking tab
// -------------------------------------------------------
function renderCarTab(unit, car) {
  const c   = car || {};
  const fid = `car-${esc(unit)}`;
  return `<div class="person-card">
    <div class="field-grid">
      ${fieldPair('Parking Spot', c['Parking Spot'])}
      ${fieldPair('Make',         c['Car Make'])}
      ${fieldPair('Model',        c['Car Model'])}
      ${fieldPair('Color',        c['Car Color'])}
      ${fieldPair('Plate',        c['Lic #'])}
      ${fieldPair('Notes',        c['Notes'])}
    </div>
    <div class="person-actions">
      <button class="btn btn-secondary btn-sm" onclick="showEditCar('${esc(unit)}')">Edit</button>
    </div>
    <div id="car-edit-${esc(unit)}" style="display:none;">
      <div class="edit-form" style="margin-top:0.75rem;">
        <div class="field-grid">
          <div><label>Parking Spot</label><input type="text" id="cf-spot-${esc(unit)}" value="${esc(c['Parking Spot'])}"></div>
          <div><label>Car Make</label><input type="text" id="cf-make-${esc(unit)}" value="${esc(c['Car Make'])}"></div>
          <div><label>Car Model</label><input type="text" id="cf-model-${esc(unit)}" value="${esc(c['Car Model'])}"></div>
          <div><label>Car Color</label><input type="text" id="cf-color-${esc(unit)}" value="${esc(c['Car Color'])}"></div>
          <div><label>Plate</label><input type="text" id="cf-lic-${esc(unit)}" value="${esc(c['Lic #'])}"></div>
          <div><label>Notes</label><input type="text" id="cf-notes-${esc(unit)}" value="${esc(c['Notes'])}"></div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary btn-sm" onclick="saveCarRow('${esc(unit)}')">Save</button>
          <button class="btn btn-secondary btn-sm"
            onclick="document.getElementById('car-edit-${esc(unit)}').style.display='none'">Cancel</button>
        </div>
        <div id="cf-msg-${esc(unit)}" style="margin-top:0.5rem;"></div>
      </div>
    </div>
  </div>`;
}

// -------------------------------------------------------
// Emergency contacts tab
// -------------------------------------------------------
function renderEmergencyTab(unit, contacts) {
  const cards = contacts.map(c => emergencyCardHtml(unit, c)).join('');
  return `${cards}
    <div id="em-add-form-${esc(unit)}" style="display:none;">
      ${addEmergencyFormHtml(unit)}
    </div>
    <button class="btn btn-secondary btn-sm" onclick="toggleAddEmergency('${esc(unit)}')">+ Add Contact</button>`;
}

function emergencyCardHtml(unit, c) {
  const name  = `${c['First Name']} ${c['Last Name']}`.trim();
  const label = c['Condo Sitter'] ? 'Condo Sitter' : 'Emergency Contact';
  const id    = `em-${esc(unit)}-${esc(c['First Name'])}-${esc(c['Last Name'])}`.replace(/[^a-z0-9-]/gi, '_');
  return `<div class="person-card" id="${id}">
    <div class="person-name">${esc(name)} <span style="font-size:0.78rem;color:#888;font-weight:400;">${label}</span></div>
    <div class="field-grid">
      ${fieldPair('Email',   c['eMail'])}
      ${fieldPair('Phone 1', c['Phone1'])}
      ${fieldPair('Phone 2', c['Phone2'])}
    </div>
    <div class="person-actions">
      <button class="btn btn-secondary btn-sm"
        onclick="showEditEmergency('${esc(unit)}', '${esc(c['First Name'])}', '${esc(c['Last Name'])}')">Edit</button>
      <button class="btn btn-danger btn-sm"
        onclick="deleteEmergency('${esc(unit)}', '${esc(c['First Name'])}', '${esc(c['Last Name'])}', '${esc(name)}')">Delete</button>
    </div>
    <div id="em-edit-${id}" style="display:none;">
      ${editEmergencyFormHtml(unit, c)}
    </div>
  </div>`;
}

function editEmergencyFormHtml(unit, c) {
  const id = `em-${esc(unit)}-${esc(c['First Name'])}-${esc(c['Last Name'])}`.replace(/[^a-z0-9-]/gi, '_');
  return `<div class="edit-form" style="margin-top:0.75rem;">
    <div class="field-grid">
      <div><label>First Name</label><input type="text" id="emf-first-${id}" value="${esc(c['First Name'])}"></div>
      <div><label>Last Name</label><input type="text" id="emf-last-${id}" value="${esc(c['Last Name'])}"></div>
      <div><label>Email</label><input type="text" id="emf-email-${id}" value="${esc(c['eMail'])}"></div>
      <div><label>Phone 1</label><input type="text" id="emf-ph1-${id}" value="${esc(c['Phone1'])}"></div>
      <div><label>Phone 2</label><input type="text" id="emf-ph2-${id}" value="${esc(c['Phone2'])}"></div>
    </div>
    <div style="margin-top:0.5rem;">
      <label class="checkbox-row"><input type="checkbox" id="emf-cs-${id}"${c['Condo Sitter'] ? ' checked' : ''}> Condo Sitter</label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary btn-sm"
        onclick="saveEditEmergency('${esc(unit)}', '${esc(c['First Name'])}', '${esc(c['Last Name'])}', '${id}')">Save</button>
      <button class="btn btn-secondary btn-sm"
        onclick="document.getElementById('em-edit-${id}').style.display='none'">Cancel</button>
    </div>
    <div id="emf-msg-${id}" style="margin-top:0.5rem;"></div>
  </div>`;
}

function addEmergencyFormHtml(unit) {
  return `<div class="edit-form">
    <div style="font-weight:600;margin-bottom:0.75rem;">Add Emergency Contact / Condo Sitter</div>
    <div class="field-grid">
      <div><label>First Name *</label><input type="text" id="emn-first-${esc(unit)}" placeholder="Required"></div>
      <div><label>Last Name *</label><input type="text" id="emn-last-${esc(unit)}" placeholder="Required"></div>
      <div><label>Email</label><input type="text" id="emn-email-${esc(unit)}"></div>
      <div><label>Phone 1</label><input type="text" id="emn-ph1-${esc(unit)}"></div>
      <div><label>Phone 2</label><input type="text" id="emn-ph2-${esc(unit)}"></div>
    </div>
    <div style="margin-top:0.5rem;">
      <label class="checkbox-row"><input type="checkbox" id="emn-cs-${esc(unit)}"> Condo Sitter</label>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary btn-sm" onclick="saveAddEmergency('${esc(unit)}')">Add</button>
      <button class="btn btn-secondary btn-sm" onclick="toggleAddEmergency('${esc(unit)}')">Cancel</button>
    </div>
    <div id="emn-msg-${esc(unit)}" style="margin-top:0.5rem;"></div>
  </div>`;
}

// -------------------------------------------------------
// "Add to Unit" (global toolbar button — for units not yet in list)
// -------------------------------------------------------
function showAddUnit() {
  const unit = prompt('Enter the unit number to add a resident to:');
  if (!unit) return;
  // If unit exists, open it
  if (unitMap[unit.trim()]) {
    const row = document.querySelector(`.unit-row[data-unit="${unit.trim()}"]`);
    if (row) row.click();
  } else {
    // Add as new unit stub then show add form
    unitOrder.push(unit.trim());
    unitMap[unit.trim()] = [];
    renderUnits(unitOrder);
    const row = document.querySelector(`.unit-row[data-unit="${unit.trim()}"]`);
    if (row) {
      row.click();
      setTimeout(() => toggleAddPerson(unit.trim()), 300);
    }
  }
}

// -------------------------------------------------------
// Toggle add/edit forms
// -------------------------------------------------------
function toggleAddPerson(unit) {
  const el = document.getElementById('add-person-form-' + unit);
  const opening = el.style.display === 'none';
  el.style.display = opening ? 'block' : 'none';
  if (opening) {
    // Clear previous message and reset fields
    const msgEl = document.getElementById('ap-msg-' + unit);
    if (msgEl) msgEl.innerHTML = '';
    el.querySelectorAll('input[type=text], input[type=date]').forEach(i => i.value = '');
    const boardSel = document.getElementById('ap-board-' + unit);
    if (boardSel) boardSel.selectedIndex = 0;
    const resCb = document.getElementById('ap-res-' + unit);
    const ownCb = document.getElementById('ap-own-' + unit);
    const ftCb  = document.getElementById('ap-ft-'  + unit);
    if (resCb) resCb.checked = true;
    if (ownCb) ownCb.checked = true;
    if (ftCb)  ftCb.checked  = false;
  }
}

function showEditResident(unit, first, last) {
  const id = `card-${esc(unit)}-${esc(first)}-${esc(last)}`.replace(/[^a-z0-9-]/gi, '_');
  document.getElementById('edit-form-' + id).style.display = 'block';
}

function showEditCar(unit) {
  document.getElementById('car-edit-' + unit).style.display = 'block';
}

function toggleAddEmergency(unit) {
  const el = document.getElementById('em-add-form-' + unit);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function showEditEmergency(unit, first, last) {
  const id = `em-${esc(unit)}-${esc(first)}-${esc(last)}`.replace(/[^a-z0-9-]/gi, '_');
  document.getElementById('em-edit-' + id).style.display = 'block';
}

// -------------------------------------------------------
// Save: add resident
// -------------------------------------------------------
async function saveAddPerson(unit) {
  const first = document.getElementById('ap-first-' + unit).value.trim();
  const last  = document.getElementById('ap-last-'  + unit).value.trim();
  const msgEl = document.getElementById('ap-msg-'   + unit);
  if (!first || !last) { showMsg(msgEl, 'First and last name are required.', 'error'); return; }

  showMsg(msgEl, 'Saving…', 'info');
  const payload = {
    'Unit #':      unit,
    'First Name':  first,
    'Last Name':   last,
    'eMail':       document.getElementById('ap-email-' + unit).value.trim(),
    'Phone #1':    document.getElementById('ap-ph1-'   + unit).value.trim(),
    'Phone #2':    document.getElementById('ap-ph2-'   + unit).value.trim(),
    'Insurance':   document.getElementById('ap-ins-'   + unit).value.trim(),
    'Policy #':    document.getElementById('ap-pol-'   + unit).value.trim(),
    'AC Replaced': document.getElementById('ap-ac-'    + unit).value,
    'Water Tank':  document.getElementById('ap-wt-'    + unit).value,
    'Board':       document.getElementById('ap-board-' + unit).value,
    'Full Time':   document.getElementById('ap-ft-'    + unit).checked,
    'Resident':    document.getElementById('ap-res-'   + unit).checked,
    'Owner':       document.getElementById('ap-own-'   + unit).checked,
  };

  const res = await apiPost('addDatabaseRow', payload);
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }

  let successMsg;
  if (res.noEmail) {
    successMsg = '✓ Added to database. No web account created — add an email address first, then create their login from Manage User Accounts.';
  } else if (res.emailSent) {
    successMsg = `✓ Added. Login account created for <strong>${res.username}</strong> and temporary password emailed to resident.`;
  } else {
    successMsg = `✓ Added to database. Login account created for <strong>${res.username}</strong> but email could not be sent — check that the email address is correct.`;
  }
  showMsg(msgEl, successMsg, 'ok');
  if (unitCache[unit]) unitCache[unit].residents.push({ ...payload });
  refreshUnitDetail(unit);
  toast(successMsg);
}

// -------------------------------------------------------
// Save: edit resident
// -------------------------------------------------------
async function saveEditResident(unit, origFirst, origLast, id) {
  const msgEl = document.getElementById('ef-msg-' + id);
  showMsg(msgEl, 'Saving…', 'info');

  const payload = {
    matchUnit:     unit,
    matchFirst:    origFirst,
    matchLast:     origLast,
    'Unit #':      unit,
    'First Name':  document.getElementById('ef-first-' + id).value.trim(),
    'Last Name':   document.getElementById('ef-last-'  + id).value.trim(),
    'eMail':       document.getElementById('ef-email-' + id).value.trim(),
    'Phone #1':    document.getElementById('ef-ph1-'   + id).value.trim(),
    'Phone #2':    document.getElementById('ef-ph2-'   + id).value.trim(),
    'Insurance':   document.getElementById('ef-ins-'   + id).value.trim(),
    'Policy #':    document.getElementById('ef-pol-'   + id).value.trim(),
    'AC Replaced': document.getElementById('ef-ac-'    + id).value,
    'Water Tank':  document.getElementById('ef-wt-'    + id).value,
    'Board':       document.getElementById('ef-board-' + id).value,
    'Full Time':   document.getElementById('ef-ft-'    + id).checked,
    'Resident':    document.getElementById('ef-res-'   + id).checked,
    'Owner':       document.getElementById('ef-own-'   + id).checked,
  };

  const res = await apiPost('editDatabaseRow', payload);
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }

  let msg = '✓ Saved.';
  if (res.accountCreated) {
    msg += res.emailSent
      ? ` Web account created for <strong>${res.username}</strong> — temporary password emailed to resident.`
      : ` Web account created for <strong>${res.username}</strong> but email could not be sent — check the email address.`;
  }
  if (res.usernameChanged) {
    msg += ` Web login username changed from <strong>${res.oldUsername}</strong> to <strong>${res.newUsername}</strong> — notify the resident.`;
  }
  showMsg(msgEl, msg, 'ok');
  if (unitCache[unit]) {
    const idx = unitCache[unit].residents.findIndex(r =>
      r['First Name'] === origFirst && r['Last Name'] === origLast
    );
    if (idx >= 0) Object.assign(unitCache[unit].residents[idx], payload);
  }
  refreshUnitDetail(unit);
  toast(msg);
}

// -------------------------------------------------------
// Delete resident
// -------------------------------------------------------
async function deleteResident(unit, first, last, displayName) {
  if (!confirm(`Delete ${displayName} from Unit ${unit}?\n\nThis removes them from the database and removes their web login. The parking spot is NOT affected.`)) return;
  toast('Deleting…', 'ok', 30000);
  const res = await apiPost('deleteDatabaseRow', { matchUnit: unit, matchFirst: first, matchLast: last });
  if (res.error) { toast('Error: ' + res.error, 'error'); return; }
  if (unitCache[unit]) {
    unitCache[unit].residents = unitCache[unit].residents.filter(r =>
      !(r['First Name'] === first && r['Last Name'] === last)
    );
  }
  refreshUnitDetail(unit);
  toast(`✓ ${displayName} removed from Unit ${unit}.`);
}

// -------------------------------------------------------
// Save car row
// -------------------------------------------------------
async function saveCarRow(unit) {
  const msgEl  = document.getElementById('cf-msg-' + unit);
  showMsg(msgEl, 'Saving…', 'info');
  const carData = {
    'Unit #':       unit,
    'Parking Spot': document.getElementById('cf-spot-'  + unit).value.trim(),
    'Car Make':     document.getElementById('cf-make-'  + unit).value.trim(),
    'Car Model':    document.getElementById('cf-model-' + unit).value.trim(),
    'Car Color':    document.getElementById('cf-color-' + unit).value.trim(),
    'Lic #':        document.getElementById('cf-lic-'   + unit).value.trim(),
    'Notes':        document.getElementById('cf-notes-' + unit).value.trim(),
  };
  const res = await apiPost('editCarRow', carData);
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  if (unitCache[unit]) unitCache[unit].car = carData;
  refreshUnitDetail(unit);
  toast('✓ Vehicle info saved.');
}

// -------------------------------------------------------
// Emergency contact actions
// -------------------------------------------------------
async function saveAddEmergency(unit) {
  const first = document.getElementById('emn-first-' + unit).value.trim();
  const last  = document.getElementById('emn-last-'  + unit).value.trim();
  const msgEl = document.getElementById('emn-msg-'   + unit);
  if (!first || !last) { showMsg(msgEl, 'First and last name required.', 'error'); return; }
  showMsg(msgEl, 'Saving…', 'info');
  const emRow = {
    'Unit #':       unit,
    'First Name':   first,
    'Last Name':    last,
    'eMail':        document.getElementById('emn-email-' + unit).value.trim(),
    'Phone1':       document.getElementById('emn-ph1-'   + unit).value.trim(),
    'Phone2':       document.getElementById('emn-ph2-'   + unit).value.trim(),
    'Condo Sitter': document.getElementById('emn-cs-'    + unit).checked,
  };
  const res = await apiPost('addEmergencyRow', emRow);
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  if (unitCache[unit]) unitCache[unit].emergency.push(emRow);
  refreshUnitDetail(unit);
  toast('✓ Emergency contact added.');
}

async function saveEditEmergency(unit, origFirst, origLast, id) {
  const msgEl = document.getElementById('emf-msg-' + id);
  showMsg(msgEl, 'Saving…', 'info');
  const payload = {
    matchUnit:      unit,
    matchFirst:     origFirst,
    matchLast:      origLast,
    'Unit #':       unit,
    'First Name':   document.getElementById('emf-first-' + id).value.trim(),
    'Last Name':    document.getElementById('emf-last-'  + id).value.trim(),
    'eMail':        document.getElementById('emf-email-' + id).value.trim(),
    'Phone1':       document.getElementById('emf-ph1-'   + id).value.trim(),
    'Phone2':       document.getElementById('emf-ph2-'   + id).value.trim(),
    'Condo Sitter': document.getElementById('emf-cs-'    + id).checked,
  };
  const res = await apiPost('editEmergencyRow', payload);
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  if (unitCache[unit]) {
    const idx = unitCache[unit].emergency.findIndex(c =>
      c['First Name'] === origFirst && c['Last Name'] === origLast
    );
    if (idx >= 0) Object.assign(unitCache[unit].emergency[idx], payload);
  }
  refreshUnitDetail(unit);
  toast('✓ Emergency contact saved.');
}

async function deleteEmergency(unit, first, last, displayName) {
  if (!confirm(`Delete ${displayName} from Unit ${unit}'s emergency contacts?`)) return;
  const res = await apiPost('deleteEmergencyRow', { matchUnit: unit, matchFirst: first, matchLast: last });
  if (res.error) { toast('Error: ' + res.error, 'error'); return; }
  if (unitCache[unit]) {
    unitCache[unit].emergency = unitCache[unit].emergency.filter(c =>
      !(c['First Name'] === first && c['Last Name'] === last)
    );
  }
  refreshUnitDetail(unit);
  toast(`✓ ${displayName} removed from emergency contacts.`);
}

// -------------------------------------------------------
// Re-render a unit panel from local cache — no network call
// -------------------------------------------------------
function refreshUnitDetail(unit) {
  const detail = document.getElementById('detail-' + unit);
  if (!detail || !unitCache[unit]) return;

  const activePanel  = detail.querySelector('.tab-panel.active');
  const activeTabKey = activePanel
    ? activePanel.id.replace('tab-', '').replace(new RegExp('-' + unit.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'), '')
    : 'residents';

  renderUnitDetail(detail, unitCache[unit]);

  const targetId  = 'tab-' + activeTabKey + '-' + unit;
  const targetBtn = [...detail.querySelectorAll('.tab-btn')]
    .find(b => (b.getAttribute('onclick') || '').includes(targetId));
  if (targetBtn) switchTab(targetBtn, targetId);

  const names = (unitCache[unit].residents || []).map(p => `${p['Last Name']}, ${p['First Name']}`).join(' · ');
  const row   = document.querySelector(`.unit-row[data-unit="${unit}"]`);
  if (row) {
    row.querySelector('.unit-names').innerHTML = names
      ? esc(names)
      : '<span style="color:#bbb;font-style:italic;">No residents</span>';
  }
}

// -------------------------------------------------------
// Reload a unit's detail panel in-place (panel stays open, active tab preserved)
// -------------------------------------------------------
async function reloadUnitDetail(unit) {
  const detail = document.getElementById('detail-' + unit);
  if (!detail) return;

  // Remember which tab is active before re-rendering
  const activePanel  = detail.querySelector('.tab-panel.active');
  const activeTabKey = activePanel
    ? activePanel.id.replace('tab-', '').replace(new RegExp('-' + unit.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'), '')
    : 'residents';

  const res = await apiFetch('getUnit', { unit });
  if (res.error) return;

  renderUnitDetail(detail, res);

  // Restore the active tab
  const targetId  = 'tab-' + activeTabKey + '-' + unit;
  const targetBtn = [...detail.querySelectorAll('.tab-btn')]
    .find(b => (b.getAttribute('onclick') || '').includes(targetId));
  if (targetBtn) switchTab(targetBtn, targetId);

  // Update the resident names shown in the unit row
  const names = (res.residents || []).map(p => `${p['Last Name']}, ${p['First Name']}`).join(' · ');
  const row   = document.querySelector(`.unit-row[data-unit="${unit}"]`);
  if (row) {
    row.querySelector('.unit-names').innerHTML = names
      ? esc(names)
      : '<span style="color:#bbb;font-style:italic;">No residents</span>';
  }
}

// -------------------------------------------------------
// Copy All Emails
// -------------------------------------------------------
async function copyAllEmails() {
  const btn = document.getElementById('copy-emails-btn');
  btn.textContent = 'Loading…';
  btn.disabled = true;
  const res = await apiFetch('getAllEmails');
  btn.disabled = false;
  if (res.error) { btn.textContent = 'Get Email List'; alert('Error: ' + res.error); return; }
  const text = (res.emails || []).join('\n');
  try {
    await navigator.clipboard.writeText(text);
    btn.textContent = '✓ Copied!';
    setTimeout(() => { btn.textContent = 'Get Email List'; }, 2000);
    toast(`${(res.emails || []).length} email addresses copied to clipboard. Open your email app, create a new message, and paste into the BCC field.`, 'ok', 8000);
  } catch (_) {
    btn.textContent = 'Get Email List';
    prompt('Copy these emails:', text);
  }
}

// -------------------------------------------------------
// Search / filter
// -------------------------------------------------------
function filterUnits(query) {
  const q = query.trim().toLowerCase();
  if (!q) { renderUnits(unitOrder); return; }
  const filtered = unitOrder.filter(unit => {
    if (unit.toLowerCase().includes(q)) return true;
    return (unitMap[unit] || []).some(r =>
      (r['First Name'] + ' ' + r['Last Name']).toLowerCase().includes(q)
    );
  });
  renderUnits(filtered);
}

// -------------------------------------------------------
// API helpers
// -------------------------------------------------------
async function apiFetch(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params });
  const r  = await fetch(SCRIPT_BASE + '&' + qs);
  return r.json();
}

async function apiPost(action, body = {}) {
  const r = await fetch(SCRIPT_BASE + '&action=' + encodeURIComponent(action), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return r.json();
}

// -------------------------------------------------------
// Utilities
// -------------------------------------------------------
function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function showMsg(el, html, type) {
  el.className = 'msg ' + type;
  el.innerHTML = html;
}

let toastTimer = null;
function toast(html, type = 'ok', durationMs = 5000) {
  const el = document.getElementById('toast');
  el.innerHTML    = html;
  el.className    = type === 'error' ? 'error visible' : 'visible';
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.classList.remove('visible'); }, durationMs);
}

function showError(msg) {
  document.getElementById('units-container').innerHTML =
    `<div class="msg error">${esc(msg)}</div>`;
}

// Boot
init();
</script>
</body>
</html>
