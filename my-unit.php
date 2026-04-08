<?php
// -------------------------------------------------------
// my-unit.php
// Resident self-service unit editor.
// Shows their own unit's info (Contact, Vehicle & Parking).
// Name / Owner / Resident fields are read-only for residents.
// Cannot add or remove people — use "Request Change" instead.
//
//   https://sheepsite.com/Scripts/my-unit.php?building=LyndhurstH
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('OWNER_IMPORT_TOKEN', 'QRF*!v2r2KgJEesq&P');
define('CONFIG_DIR', __DIR__ . '/config/');

$buildings = require __DIR__ . '/buildings.php';

$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildingConfig = $buildings[$building];
$webAppURL      = $buildingConfig['webAppURL'];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));

$useLocalDB = file_exists(CREDENTIALS_DIR . '../db/db.php') && file_exists(CREDENTIALS_DIR . 'db.json');
if ($useLocalDB) require_once __DIR__ . '/db/residents.php';
require_once __DIR__ . '/db/admin-helpers.php';
$sessionKey     = 'private_auth_' . $building;
$returnURL      = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';

// -------------------------------------------------------
// Auth
// -------------------------------------------------------
if (empty($_SESSION[$sessionKey])) {
  $self = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
  header('Location: display-private-dir.php?building=' . urlencode($building)
       . '&return=' . urlencode($self));
  exit;
}

$username = is_array($_SESSION[$sessionKey]) ? ($_SESSION[$sessionKey]['user'] ?? '') : $_SESSION[$sessionKey];

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

function loadBuildingConfig(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

// Derive username base (first initial + last name, letters only, lowercase)
function usernameBase(string $first, string $last): string {
  return strtolower(
    preg_replace('/[^a-z]/i', '', substr($first, 0, 1)) .
    preg_replace('/[^a-z]/i', '', $last)
  );
}

// -------------------------------------------------------
// AJAX handlers
// -------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action) {
  header('Content-Type: application/json');

  switch ($action) {

    // Load this resident's unit data
    case 'getMyUnit': {
      // Step 1: list all DB rows to find which unit this username belongs to
      $dbRes = $useLocalDB
        ? dbListDatabase($building)
        : gasGet($webAppURL, ['page' => 'listDatabase', 'token' => OWNER_IMPORT_TOKEN]);
      if (!empty($dbRes['error'])) { echo json_encode($dbRes); exit; }

      $rows = $dbRes['rows'] ?? [];
      $myUnit = null;
      foreach ($rows as $r) {
        $first = trim($r['First Name'] ?? '');
        $last  = trim($r['Last Name']  ?? '');
        if (!$first || !$last) continue;
        $base  = usernameBase($first, $last);
        // Match username exactly or with numeric suffix (jsmith, jsmith2, etc.)
        if ($base && preg_match('/^' . preg_quote($base, '/') . '\d*$/', $username)) {
          $myUnit = trim($r['Unit #'] ?? '');
          break;
        }
      }

      if (!$myUnit) {
        echo json_encode(['error' => 'Could not find your unit. Please contact the building admin.']);
        exit;
      }

      // Step 2: get full unit data
      $unitRes = $useLocalDB
        ? dbGetUnit($building, $myUnit)
        : gasGet($webAppURL, ['page' => 'getUnit', 'token' => OWNER_IMPORT_TOKEN, 'unit' => $myUnit]);
      echo json_encode($unitRes);
      exit;
    }

    // Edit a resident row (restricted fields only)
    case 'editDatabaseRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      // Resident cannot change Unit #, First Name, Last Name, Owner, Resident
      // Strip those fields to prevent tampering
      unset($body['Unit #'], $body['First Name'], $body['Last Name'],
            $body['Owner'], $body['Resident'], $body['Board']);
      // Restore from original (require match fields to be present)
      if (empty($body['matchUnit']) || empty($body['matchFirst']) || empty($body['matchLast'])) {
        echo json_encode(['error' => 'Missing required fields']); exit;
      }
      echo json_encode($useLocalDB
        ? dbEditResident($building, $body)
        : gasPost($webAppURL, array_merge($body, ['action' => 'editDatabaseRow', 'token' => OWNER_IMPORT_TOKEN]))
      );
      exit;
    }

    // Edit unit info row (UnitDB tab)
    case 'editUnitRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      echo json_encode($useLocalDB
        ? dbEditUnitInfo($building, $body)
        : gasPost($webAppURL, array_merge($body, ['action' => 'editUnitRow', 'token' => OWNER_IMPORT_TOKEN]))
      );
      exit;
    }

    // Edit car row
    case 'editCarRow': {
      $body = json_decode(file_get_contents('php://input'), true) ?? [];
      echo json_encode($useLocalDB
        ? dbEditCar($building, $body)
        : gasPost($webAppURL, array_merge($body, ['action' => 'editCarRow', 'token' => OWNER_IMPORT_TOKEN]))
      );
      exit;
    }

    // Send change request email
    case 'sendChangeRequest': {
      $body         = json_decode(file_get_contents('php://input'), true) ?? [];
      $cfg    = loadBuildingConfig($building);
      $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

      // Build admin notification list: all admin emails → contactEmail → President
      $adminEmails = array_values(array_filter(
        array_column(loadAdminCreds(CREDENTIALS_DIR . $building . '_admin.json'), 'email')
      ));
      $notifyTo    = $adminEmails ? implode(', ', $adminEmails) : trim($cfg['contactEmail'] ?? '');
      $dir          = rtrim(dirname($_SERVER['PHP_SELF']), '/');
      $adminUrl     = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir
                    . '/database-admin.php?building=' . urlencode($building);

      if ($useLocalDB) {
        $unit         = trim($body['unit']         ?? '');
        $residentName = trim($body['residentName'] ?? '');
        $reqType      = trim($body['reqType']      ?? '');
        $firstName    = trim($body['firstName']    ?? '');
        $lastName     = trim($body['lastName']     ?? '');
        $email        = trim($body['email']        ?? '');
        $phone1       = trim($body['phone1']       ?? '');
        $phone2       = trim($body['phone2']       ?? '');
        $notes        = trim($body['notes']        ?? '');

        if ($reqType === 'Add resident') {
          // Store in pending queue; admin approves/rejects in database-admin.php
          dbAddRequest($building, [
            'unit'         => $unit,
            'submitted_by' => $residentName,
            'req_type'     => $reqType,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $email,
            'phone1'       => $phone1,
            'phone2'       => $phone2,
            'full_time'    => !empty($body['fullTime']),
            'is_resident'  => !empty($body['resident']),
            'is_owner'     => !empty($body['owner']),
            'notes'        => $notes,
          ]);

          // Notify admin — shorter email since full detail is in the approval queue
          $toEmail = $notifyTo;
          if (!$toEmail) {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
              "SELECT email FROM residents WHERE building = ? AND board_role = 'President' AND email != '' LIMIT 1"
            );
            $stmt->execute([$building]);
            $toEmail = $stmt->fetchColumn() ?: '';
          }
          if ($toEmail) {
            $subject = "[$buildLabel] Pending: Add resident request from Unit $unit";
            $body2   = "A request to add a resident was submitted by $residentName (Unit $unit) "
                     . "and is waiting for your approval.\n\n"
                     . "Name: $firstName $lastName\n"
                     . ($email  ? "Email:    $email\n"  : '')
                     . ($phone1 ? "Phone #1: $phone1\n" : '')
                     . ($notes  ? "Notes:    $notes\n"  : '')
                     . "\nPlease log in to approve or reject:\n$adminUrl";
            $headers = implode("\r\n", [
              'From: SheepSite.com <noreply@sheepsite.com>',
              'Reply-To: noreply@sheepsite.com',
              'Content-Type: text/plain; charset=UTF-8',
            ]);
            mail($toEmail, $subject, $body2, $headers);
          }
          echo json_encode(['ok' => true]);

        } else {
          // Remove / Name correction — email-only, no queue
          $toEmail = $notifyTo;
          if (!$toEmail) {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
              "SELECT email FROM residents WHERE building = ? AND board_role = 'President' AND email != '' LIMIT 1"
            );
            $stmt->execute([$building]);
            $toEmail = $stmt->fetchColumn() ?: '';
          }
          if (!$toEmail) {
            echo json_encode(['error' => 'No admin email found. Add an email to an admin account in Admin → Admin Accounts.']);
            exit;
          }
          $fullTime = !empty($body['fullTime']) ? 'Yes' : 'No';
          $resident = !empty($body['resident']) ? 'Yes' : 'No';
          $owner    = !empty($body['owner'])    ? 'Yes' : 'No';
          $subject  = "[$buildLabel] Resident change request from Unit $unit";
          $lines    = [
            'A resident change request was submitted via the building website.',
            '',
            "Building:     $buildLabel",
            "Unit:         $unit",
            "Submitted by: $residentName",
            '',
            "Request type: $reqType",
            "Name:         $firstName $lastName",
          ];
          if ($email)  $lines[] = "Email:        $email";
          if ($phone1) $lines[] = "Phone #1:     $phone1";
          if ($phone2) $lines[] = "Phone #2:     $phone2";
          $lines[] = "Full Time:    $fullTime";
          $lines[] = "Resident:     $resident";
          $lines[] = "Owner:        $owner";
          if ($notes) $lines[] = "Notes:        $notes";
          $lines[] = '';
          $lines[] = "Please log in to the admin panel and open Manage Residents/Owners → Unit $unit to make the change.";
          $lines[] = '';
          $lines[] = $adminUrl;
          $headers = implode("\r\n", [
            'From: SheepSite.com <noreply@sheepsite.com>',
            'Reply-To: noreply@sheepsite.com',
            'Content-Type: text/plain; charset=UTF-8',
          ]);
          $sent = mail($toEmail, $subject, implode("\n", $lines), $headers);
          echo json_encode($sent ? ['ok' => true] : ['error' => 'Failed to send email.']);
        }
      } else {
        echo json_encode(gasPost($webAppURL, array_merge($body, [
          'action'       => 'sendChangeRequest',
          'token'        => OWNER_IMPORT_TOKEN,
          'buildingName' => $buildLabel,
          'contactEmail' => $contactEmail,
          'adminUrl'     => $adminUrl,
        ])));
      }
      exit;
    }
  }

  echo json_encode(['error' => 'Unknown action']);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($buildLabel) ?> – My Unit</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body        { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 1.5rem 1rem; }
    .top-bar    { display: flex; align-items: baseline; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    h1          { margin: 0; font-size: 1.4rem; flex: 1; }
    .top-links  { display: flex; gap: 1rem; font-size: 0.85rem; }
    .top-links a { color: #0070f3; text-decoration: none; }
    .top-links a:hover { text-decoration: underline; }

    .section-header { font-size: 1rem; font-weight: 700; margin: 1.5rem 0 0.75rem;
                      padding-bottom: 0.4rem; border-bottom: 2px solid #eee; }

    .person-card { border: 1px solid #e0e0e0; border-radius: 6px; padding: 1rem;
                   margin-bottom: 0.75rem; background: #fff; }
    .person-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.6rem; }
    .badges      { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.6rem; }
    .badge       { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 99px;
                   background: #e8f4ea; color: #1a7f37; font-weight: 600; }
    .badge.readonly { background: #f0f0f0; color: #888; }

    .field-grid  { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.6rem; }
    .field-pair  { display: flex; flex-direction: column; gap: 0.15rem; }
    .field-label { font-size: 0.75rem; color: #888; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    .field-val   { font-size: 0.875rem; color: #333; }
    .field-val.empty { color: #bbb; font-style: italic; }

    .person-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }

    .edit-form   { border: 1px solid #b3d1ff; border-radius: 6px; padding: 1rem;
                   margin-top: 0.75rem; background: #f0f7ff; display: none; }
    .edit-form label { font-size: 0.78rem; color: #555; font-weight: 600; display: block; margin-bottom: 0.15rem; }
    .edit-form input { width: 100%; padding: 0.35rem 0.5rem; border: 1px solid #ccc;
                       border-radius: 4px; font-size: 0.875rem; }
    .edit-form .field-grid { margin-bottom: 0.5rem; }

    .btn        { padding: 0.45rem 1rem; border: none; border-radius: 4px; font-size: 0.875rem;
                  cursor: pointer; white-space: nowrap; }
    .btn-primary  { background: #0070f3; color: #fff; }
    .btn-primary:hover { background: #005bb5; }
    .btn-secondary { background: #f0f0f0; color: #333; border: 1px solid #ccc; }
    .btn-secondary:hover { background: #e0e0e0; }
    .btn-sm     { padding: 0.3rem 0.7rem; font-size: 0.8rem; }
    .form-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }

    .msg        { padding: 0.5rem 0.75rem; border-radius: 4px; font-size: 0.875rem; margin: 0.5rem 0; }
    .msg.ok     { background: #e6f4ea; color: #1a7f37; }
    .msg.error  { background: #ffeef0; color: #c00; }
    .msg.info   { background: #eff6ff; color: #1d4ed8; }

    /* Toast */
    #toast          { position: fixed; top: 1.25rem; left: 50%; transform: translateX(-50%);
                      background: #1a7f37; color: #fff; padding: 0.6rem 1.25rem;
                      border-radius: 6px; font-size: 0.9rem; opacity: 0;
                      transition: opacity 0.2s; pointer-events: none; z-index: 200;
                      white-space: nowrap; max-width: 90vw; }
    #toast.error    { background: #c00; }
    #toast.visible  { opacity: 1; }

    /* Request popup */
    .overlay    { position: fixed; inset: 0; background: rgba(0,0,0,0.4);
                  display: flex; align-items: center; justify-content: center; z-index: 100; }
    .popup      { background: #fff; border-radius: 8px; padding: 1.5rem; max-width: 460px; width: 90%;
                  box-shadow: 0 8px 32px rgba(0,0,0,0.18); }
    .popup h3   { margin: 0 0 0.75rem; font-size: 1.05rem; }
    .popup label { font-size: 0.85rem; font-weight: 600; display: block; margin: 0.75rem 0 0.2rem; }
    .popup select, .popup input, .popup textarea {
                  width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #ccc;
                  border-radius: 4px; font-size: 0.9rem; }
    .popup textarea { resize: vertical; min-height: 60px; }
    .popup .form-actions { margin-top: 1rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= htmlspecialchars($buildLabel) ?> – My Unit</h1>
  <div class="top-links">
    <a href="my-account.php?building=<?= urlencode($building) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>">← My Account</a>
    <a href="display-private-dir.php?building=<?= urlencode($building) ?>&logout=1">Log out</a>
  </div>
</div>

<div id="toast"></div>
<div id="main-content"><p style="color:#888;">Loading your unit…</p></div>

<!-- Change request popup -->
<div class="overlay" id="request-overlay" style="display:none;">
  <div class="popup">
    <h3>Request Resident Change</h3>
    <p style="font-size:0.875rem;color:#666;margin:0 0 0.25rem;">
      To add or remove a resident, or to correct a name, submit a request to the board.
    </p>
    <label>Request type</label>
    <select id="req-type">
      <option value="Add resident">Add resident</option>
      <option value="Remove resident">Remove resident</option>
      <option value="Name correction">Name correction</option>
    </select>
    <label>First name</label>
    <input type="text" id="req-first" placeholder="Person being added / removed / corrected">
    <label>Last name</label>
    <input type="text" id="req-last">
    <label>Email</label>
    <input type="text" id="req-email" placeholder="optional">
    <label>Phone #1</label>
    <input type="text" id="req-ph1" placeholder="optional">
    <label>Phone #2</label>
    <input type="text" id="req-ph2" placeholder="optional">
    <div style="display:flex;gap:1.25rem;margin-top:0.75rem;">
      <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:600;font-size:0.85rem;margin:0;">
        <input type="checkbox" id="req-ft"> Full Time
      </label>
      <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:600;font-size:0.85rem;margin:0;">
        <input type="checkbox" id="req-res" checked> Resident
      </label>
      <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:600;font-size:0.85rem;margin:0;">
        <input type="checkbox" id="req-own" checked> Owner
      </label>
    </div>
    <label>Notes / reason (optional)</label>
    <textarea id="req-notes" placeholder="e.g. new owner, moved out, etc."></textarea>
    <div class="form-actions">
      <button class="btn btn-primary" onclick="submitChangeRequest()">Send Request</button>
      <button class="btn btn-secondary" onclick="closeRequestPopup()">Cancel</button>
    </div>
    <div id="req-msg" style="margin-top:0.5rem;"></div>
  </div>
</div>

<script>
const BUILDING    = <?= json_encode($building) ?>;
const BUILD_LABEL = <?= json_encode($buildLabel) ?>;
const USERNAME    = <?= json_encode($username) ?>;
const SCRIPT_BASE = 'my-unit.php?building=' + encodeURIComponent(BUILDING);

let myUnit      = null;
let myResidents = [];

async function init() {
  const res = await apiFetch('getMyUnit');
  if (res.error) {
    document.getElementById('main-content').innerHTML =
      `<div class="msg error">${esc(res.error)}</div>`;
    return;
  }

  myUnit      = res.unit;
  myResidents = res.residents || [];

  renderPage(myUnit, myResidents, res.car || {}, res.unitInfo || {});
}

// -------------------------------------------------------
// Render
// -------------------------------------------------------
function renderPage(unit, residents, car, unitInfo) {
  const main = document.getElementById('main-content');
  main.innerHTML = `
    <div style="font-size:0.9rem;color:#888;margin-bottom:1rem;">Unit ${esc(unit)}</div>

    <div class="section-header">
      Residents
      <button class="btn btn-primary btn-sm" style="margin-left:1rem;font-size:0.8rem;"
        onclick="openRequestPopup('${esc(unit)}')">Add/Remove Resident</button>
    </div>
    ${residents.map(r => residentCardHtml(unit, r)).join('') || '<p style="color:#888;font-size:0.9rem;">No residents on file.</p>'}

    <div class="section-header">Unit Information</div>
    ${unitInfoSectionHtml(unit, unitInfo)}

    <div class="section-header">Vehicle &amp; Parking</div>
    ${carSectionHtml(unit, car)}
  `;
}

// -------------------------------------------------------
// Resident cards (read-only name/status, editable contact info)
// -------------------------------------------------------
function residentCardHtml(unit, r) {
  const name    = `${r['First Name']} ${r['Last Name']}`.trim();
  const badges  = [
    r['Full Time'] ? 'Full Time' : null,
    r['Resident']  ? 'Resident'  : null,
    r['Owner']     ? 'Owner'     : null,
  ].filter(Boolean);
  const id      = `res-${esc(unit)}-${esc(r['First Name'])}-${esc(r['Last Name'])}`.replace(/[^a-z0-9-]/gi, '_');

  return `<div class="person-card" id="${id}">
    <div class="person-name">${esc(name)}</div>
    <div class="badges">
      ${badges.map(b => `<span class="badge readonly">${esc(b)}</span>`).join('')}
    </div>
    <div class="field-grid">
      ${fieldPair('Email',   r['eMail'])}
      ${fieldPair('Phone 1', r['Phone #1'])}
      ${fieldPair('Phone 2', r['Phone #2'])}
    </div>
    <div class="person-actions">
      <button class="btn btn-primary btn-sm"
        onclick="showEditResident('${id}')">Edit my info</button>
    </div>
    <div class="edit-form" id="ef-${id}">
      <div class="field-grid">
        <div><label>Email</label><input type="text" id="ef-email-${id}" value="${esc(r['eMail'])}"></div>
        <div><label>Phone #1</label><input type="text" id="ef-ph1-${id}" value="${esc(r['Phone #1'])}"></div>
        <div><label>Phone #2</label><input type="text" id="ef-ph2-${id}" value="${esc(r['Phone #2'])}"></div>
      </div>
      <div style="margin-top:0.5rem;">
        <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:600;font-size:0.85rem;">
          <input type="checkbox" id="ef-ft-${id}"${r['Full Time'] ? ' checked' : ''}> Full Time
        </label>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary btn-sm"
          onclick="saveResident('${esc(unit)}', '${esc(r['First Name'])}', '${esc(r['Last Name'])}', '${id}')">Save</button>
        <button class="btn btn-secondary btn-sm"
          onclick="document.getElementById('ef-${id}').style.display='none'">Cancel</button>
      </div>
      <div id="ef-msg-${id}"></div>
    </div>
  </div>`;
}

function fieldPair(label, val) {
  const empty = !val;
  return `<div class="field-pair">
    <div class="field-label">${label}</div>
    <div class="field-val${empty ? ' empty' : ''}">${esc(val || '—')}</div>
  </div>`;
}

function showEditResident(id) {
  document.getElementById('ef-' + id).style.display = 'block';
}

async function saveResident(unit, first, last, id) {
  const msgEl = document.getElementById('ef-msg-' + id);
  showMsg(msgEl, 'Saving…', 'info');
  const res = await apiPost('editDatabaseRow', {
    matchUnit:     unit,
    matchFirst:    first,
    matchLast:     last,
    'eMail':       document.getElementById('ef-email-' + id).value.trim(),
    'Phone #1':    document.getElementById('ef-ph1-'   + id).value.trim(),
    'Phone #2':    document.getElementById('ef-ph2-'   + id).value.trim(),
    'Full Time':   document.getElementById('ef-ft-'    + id).checked,
  });
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  document.getElementById('ef-' + id).style.display = 'none';
  const refresh = await apiFetch('getMyUnit');
  if (!refresh.error) {
    myResidents = refresh.residents || [];
    renderPage(myUnit, myResidents, refresh.car || {}, refresh.unitInfo || {});
  }
  toast('✓ Your info has been saved.');
}

// -------------------------------------------------------
// Unit Information
// -------------------------------------------------------
function unitInfoSectionHtml(unit, u) {
  u = u || {};
  return `<div class="person-card">
    <div class="field-grid">
      ${fieldPair('Insurance',   u['Insurance'])}
      ${fieldPair('Policy #',    u['Policy #'])}
      ${fieldPair('A/C Replaced',  u['AC Replaced'])}
      ${fieldPair('Water Heater',  u['Water Tank'])}
    </div>
    <div class="person-actions">
      <button class="btn btn-primary btn-sm" onclick="showEditUnitInfo()">Edit</button>
    </div>
    <div class="edit-form" id="unit-info-edit-form">
      <div class="field-grid">
        <div><label>Insurance</label><input type="text" id="ui-ins" value="${esc(u['Insurance'])}"></div>
        <div><label>Policy #</label><input type="text" id="ui-pol" value="${esc(u['Policy #'])}"></div>
        <div><label>A/C Replaced</label><input type="date" id="ui-ac" value="${esc(u['AC Replaced'])}"></div>
        <div><label>Water Heater</label><input type="date" id="ui-wt" value="${esc(u['Water Tank'])}"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary btn-sm" onclick="saveUnitInfo('${esc(unit)}')">Save</button>
        <button class="btn btn-secondary btn-sm"
          onclick="document.getElementById('unit-info-edit-form').style.display='none'">Cancel</button>
      </div>
      <div id="ui-msg"></div>
    </div>
  </div>`;
}

function showEditUnitInfo() {
  document.getElementById('unit-info-edit-form').style.display = 'block';
}

async function saveUnitInfo(unit) {
  const msgEl = document.getElementById('ui-msg');
  showMsg(msgEl, 'Saving…', 'info');
  const res = await apiPost('editUnitRow', {
    'Unit #':       unit,
    'Insurance':    document.getElementById('ui-ins').value.trim(),
    'Policy #':     document.getElementById('ui-pol').value.trim(),
    'AC Replaced':  document.getElementById('ui-ac').value,
    'Water Tank':   document.getElementById('ui-wt').value,
  });
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  document.getElementById('unit-info-edit-form').style.display = 'none';
  const refresh = await apiFetch('getMyUnit');
  if (!refresh.error) renderPage(myUnit, refresh.residents || [], refresh.car || {}, refresh.unitInfo || {});
  toast('✓ Unit information has been saved.');
}

// -------------------------------------------------------
// Vehicle & Parking
// -------------------------------------------------------
function carSectionHtml(unit, c) {
  return `<div class="person-card">
    <div class="field-grid">
      ${fieldPair('Parking Spot', c['Parking Spot'])}
      ${fieldPair('Make',  c['Car Make'])}
      ${fieldPair('Model', c['Car Model'])}
      ${fieldPair('Color', c['Car Color'])}
      ${fieldPair('Plate', c['Lic #'])}
      ${fieldPair('Notes', c['Notes'])}
    </div>
    <div class="person-actions">
      <button class="btn btn-primary btn-sm" onclick="showEditCar()">Edit</button>
    </div>
    <div class="edit-form" id="car-edit-form">
      <div class="field-grid">
        <div><label>Parking Spot</label><input type="text" id="cf-spot" value="${esc(c['Parking Spot'])}"></div>
        <div><label>Car Make</label><input type="text" id="cf-make" value="${esc(c['Car Make'])}"></div>
        <div><label>Car Model</label><input type="text" id="cf-model" value="${esc(c['Car Model'])}"></div>
        <div><label>Car Color</label><input type="text" id="cf-color" value="${esc(c['Car Color'])}"></div>
        <div><label>Plate</label><input type="text" id="cf-lic" value="${esc(c['Lic #'])}"></div>
        <div><label>Notes</label><input type="text" id="cf-notes" value="${esc(c['Notes'])}"></div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary btn-sm" onclick="saveCar('${esc(unit)}')">Save</button>
        <button class="btn btn-secondary btn-sm"
          onclick="document.getElementById('car-edit-form').style.display='none'">Cancel</button>
      </div>
      <div id="cf-msg"></div>
    </div>
  </div>`;
}

function showEditCar() {
  document.getElementById('car-edit-form').style.display = 'block';
}

async function saveCar(unit) {
  const msgEl = document.getElementById('cf-msg');
  showMsg(msgEl, 'Saving…', 'info');
  const res = await apiPost('editCarRow', {
    'Unit #':       unit,
    'Parking Spot': document.getElementById('cf-spot').value.trim(),
    'Car Make':     document.getElementById('cf-make').value.trim(),
    'Car Model':    document.getElementById('cf-model').value.trim(),
    'Car Color':    document.getElementById('cf-color').value.trim(),
    'Lic #':        document.getElementById('cf-lic').value.trim(),
    'Notes':        document.getElementById('cf-notes').value.trim(),
  });
  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  document.getElementById('car-edit-form').style.display = 'none';
  const refresh = await apiFetch('getMyUnit');
  if (!refresh.error) renderPage(myUnit, refresh.residents || [], refresh.car || {}, refresh.unitInfo || {});
  toast('✓ Vehicle info has been saved.');
}

// -------------------------------------------------------
// Change request popup
// -------------------------------------------------------
function openRequestPopup(unit) {
  document.getElementById('request-overlay').style.display = 'flex';
  document.getElementById('req-msg').innerHTML = '';
}

function closeRequestPopup() {
  document.getElementById('request-overlay').style.display = 'none';
}

async function submitChangeRequest() {
  const msgEl = document.getElementById('req-msg');
  const first = document.getElementById('req-first').value.trim();
  const last  = document.getElementById('req-last').value.trim();
  if (!first || !last) { showMsg(msgEl, 'Please fill in the name fields.', 'error'); return; }

  showMsg(msgEl, 'Sending…', 'info');
  const res = await apiPost('sendChangeRequest', {
    unit:          myUnit,
    residentName:  USERNAME,
    reqType:       document.getElementById('req-type').value,
    firstName:     first,
    lastName:      last,
    email:         document.getElementById('req-email').value.trim(),
    phone1:        document.getElementById('req-ph1').value.trim(),
    phone2:        document.getElementById('req-ph2').value.trim(),
    fullTime:      document.getElementById('req-ft').checked,
    resident:      document.getElementById('req-res').checked,
    owner:         document.getElementById('req-own').checked,
    notes:         document.getElementById('req-notes').value.trim(),
  });

  if (res.error) { showMsg(msgEl, res.error, 'error'); return; }
  closeRequestPopup();
  toast('✓ Your request has been sent to the board.', 'ok', 7000);
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

function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function showMsg(el, text, type) {
  el.className = 'msg ' + type;
  el.textContent = text;
}

let toastTimer = null;
function toast(text, type = 'ok', durationMs = 5000) {
  const el = document.getElementById('toast');
  el.textContent = text;
  el.className = type === 'error' ? 'error visible' : 'visible';
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.classList.remove('visible'); }, durationMs);
}

init();
</script>
</body>
</html>
