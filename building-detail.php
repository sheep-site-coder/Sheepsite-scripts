<?php
// -------------------------------------------------------
// building-detail.php
// Per-building management page — operator view.
// Also handles "Add New Association" (?building=new).
//
//   https://sheepsite.com/Scripts/building-detail.php?building=LyndhurstH
//   https://sheepsite.com/Scripts/building-detail.php?building=new
//
// Reuses master_admin_auth session from master-admin.php.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');

if (empty($_SESSION['master_admin_auth'])) {
  header('Location: master-admin.php');
  exit;
}

$buildings   = require __DIR__ . '/buildings.php';
$buildingKey = trim($_GET['building'] ?? '');
$isNew       = $buildingKey === 'new';

if (!$isNew && (!$buildingKey || !preg_match('/^[a-zA-Z0-9_-]+$/', $buildingKey))) {
  header('Location: master-admin.php');
  exit;
}

if (!$isNew && !isset($buildings[$buildingKey])) {
  die('<p style="color:red;">Building not found in buildings.php.</p>');
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function loadBuildingConfig(string $b): array {
  $file = CONFIG_DIR . $b . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveBuildingConfig(string $b, array $cfg): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . $b . '.json', json_encode($cfg, JSON_PRETTY_PRINT));
}

function loadCredits(): array {
  $file = __DIR__ . '/faqs/woolsy_credits.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveCredits(array $all): void {
  file_put_contents(__DIR__ . '/faqs/woolsy_credits.json', json_encode($all, JSON_PRETTY_PRINT));
}

function loadPricing(): array {
  $file = CONFIG_DIR . 'pricing.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function fmtBytes(int $bytes): string {
  if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
  return round($bytes / 1024) . ' KB';
}

function pct(float $used, float $total): int {
  if ($total <= 0) return 0;
  return (int)min(100, round($used / $total * 100));
}

function barColor(int $pct): string {
  if ($pct >= 90) return '#dc2626';
  if ($pct >= 70) return '#f59e0b';
  return '#22c55e';
}

function fmtDate(string $iso): string {
  try { return (new DateTime($iso))->format('M j, Y'); } catch (Exception $e) { return $iso; }
}

// -------------------------------------------------------
// Master config (template/association folder IDs)
// -------------------------------------------------------
function loadMasterConfig(): array {
  $file = CONFIG_DIR . '_master_config.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}
function saveMasterConfig(array $cfg): void {
  file_put_contents(CONFIG_DIR . '_master_config.json', json_encode($cfg, JSON_PRETTY_PRINT));
}

// -------------------------------------------------------
// POST handlers
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

// ---- AJAX: create Drive folders for new association ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isNew) {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';

  if ($action === 'create_folders') {
    $buildingName      = trim($_POST['buildingName']      ?? '');
    $templateFolderId  = trim($_POST['templateFolderId']  ?? '');
    $parentFolderId    = trim($_POST['parentFolderId']     ?? '');
    $templateSheetId   = trim($_POST['templateSheetId']   ?? '');
    $saveDefaults      = !empty($_POST['saveDefaults']);

    if (!$buildingName || !$templateFolderId || !$parentFolderId) {
      echo json_encode(['ok' => false, 'error' => 'Building name, template folder ID, and association folder ID are all required.']);
      exit;
    }

    // Optionally persist IDs as defaults
    if ($saveDefaults) {
      $mc = loadMasterConfig();
      $mc['templateFolderId']    = $templateFolderId;
      $mc['associationFolderId'] = $parentFolderId;
      if ($templateSheetId) $mc['templateSheetId'] = $templateSheetId;
      saveMasterConfig($mc);
    }

    $appsScriptURL = defined('APPS_SCRIPT_URL') ? APPS_SCRIPT_URL : '';
    $appsScriptToken = defined('APPS_SCRIPT_TOKEN') ? APPS_SCRIPT_TOKEN : '';
    // Load from file-manager constants if not defined here
    if (!$appsScriptURL) {
      // Read from file-manager.php is not feasible; use the same constants file
      // Constants are defined in display-private-dir.php — load them
      $constFile = __DIR__ . '/display-private-dir.php';
      if (file_exists($constFile)) {
        $src = file_get_contents($constFile);
        if (preg_match("/define\('APPS_SCRIPT_URL',\s*'([^']+)'\)/", $src, $m)) $appsScriptURL   = $m[1];
        if (preg_match("/define\('APPS_SCRIPT_TOKEN',\s*'([^']+)'\)/", $src, $m)) $appsScriptToken = $m[1];
      }
    }

    $url = $appsScriptURL
         . '?action=setupBuildingFolders'
         . '&token='            . urlencode($appsScriptToken)
         . '&buildingName='     . urlencode($buildingName)
         . '&templateFolderId=' . urlencode($templateFolderId)
         . '&parentFolderId='   . urlencode($parentFolderId)
         . ($templateSheetId ? '&templateSheetId=' . urlencode($templateSheetId) : '');

    $raw  = @file_get_contents($url);
    $data = $raw ? json_decode($raw, true) : null;

    if (!$data || !empty($data['error'])) {
      echo json_encode(['ok' => false, 'error' => $data['error'] ?? 'No response from Apps Script. Check the URL and token.']);
    } else {
      echo json_encode($data);
    }
    exit;
  }

  echo json_encode(['ok' => false, 'error' => 'Unknown action']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isNew) {

  $action = $_POST['action'] ?? '';

  // ---- Save configuration ----
  if ($action === 'save_config') {
    $cfg = loadBuildingConfig($buildingKey);
    $cfg['siteURL']      = rtrim(trim($_POST['site_url']      ?? ''), '/');
    $cfg['contactEmail'] = trim($_POST['contact_email'] ?? '');
    $cfg['hasDomain']    = isset($_POST['has_domain']);
    $cfg['testSite']     = isset($_POST['test_site']);
    saveBuildingConfig($buildingKey, $cfg);
    $message = 'Configuration saved.';
  }

  // ---- Save website/site config ----
  if ($action === 'save_site_config') {
    $cfg = loadBuildingConfig($buildingKey);
    $cfg['displayName']    = trim($_POST['display_name']    ?? '');
    $cfg['headerImageUrl'] = trim($_POST['header_image_url'] ?? '');
    $cfg['calendarUrl']    = trim($_POST['calendar_url']    ?? '');
    $cfg['facebookUrl']    = trim($_POST['facebook_url']    ?? '');
    $cfg['propertyMgmt']   = [
      'name'        => trim($_POST['pm_name']         ?? ''),
      'url'         => trim($_POST['pm_url']          ?? ''),
      'phone'       => trim($_POST['pm_phone']        ?? ''),
      'buttonLabel' => trim($_POST['pm_button_label'] ?? ''),
    ];
    saveBuildingConfig($buildingKey, $cfg);
    $message = 'Website settings saved.';
  }

  // ---- Save billing config (renewalDate + discountPct) ----
  if ($action === 'save_billing_config') {
    $cfg = loadBuildingConfig($buildingKey);
    $discountRaw = (float)($_POST['discount_pct'] ?? 0);
    if ($discountRaw > 0) $cfg['discountPct'] = round(min(100, $discountRaw), 2);
    else unset($cfg['discountPct']);
    $renewalRaw = trim($_POST['renewal_date'] ?? '');
    if ($renewalRaw) {
      $cfg['renewalDate'] = $renewalRaw;
      if (strtotime($renewalRaw) > time()) unset($cfg['suspended']);
    } elseif (isset($cfg['renewalDate'])) {
      unset($cfg['renewalDate']);
    }
    saveBuildingConfig($buildingKey, $cfg);
    $message = 'Billing settings saved.';
  }

  // ---- Manual Woolsy top-up ----
  if ($action === 'topup_woolsy') {
    $add = (float)($_POST['credits_to_add'] ?? 0);
    if ($add > 0) {
      $all = loadCredits();
      $all[$buildingKey]['allocated'] = round(($all[$buildingKey]['allocated'] ?? 1.0) + $add, 4);
      saveCredits($all);
      $message = number_format($add, 2) . ' credits added to ' . $buildingKey . '.';
    } else {
      $message     = 'Enter a positive credit amount.';
      $messageType = 'error';
    }
  }

  // ---- Reset Woolsy usage ----
  if ($action === 'reset_woolsy_used') {
    $all = loadCredits();
    $all[$buildingKey]['used'] = 0;
    saveCredits($all);
    $message = 'Woolsy usage counter reset to 0.';
  }

  // ---- Generate invoice ----
  if ($action === 'generate_invoice') {
    require_once __DIR__ . '/invoice-helpers.php';
    $cfg     = loadBuildingConfig($buildingKey);
    $pricing = loadPricing();
    try {
      $inv     = generateInvoice($buildingKey, $cfg, $pricing);
      $message = 'Invoice ' . $inv['id'] . ' generated and emailed ($' . number_format($inv['total'], 2) . ').';
    } catch (Exception $e) {
      $message     = 'Invoice generation failed: ' . $e->getMessage();
      $messageType = 'error';
    }
  }

  // ---- Generate one-off "other" invoice ----
  if ($action === 'generate_other_invoice') {
    require_once __DIR__ . '/invoice-helpers.php';
    $cfg         = loadBuildingConfig($buildingKey);
    $description = trim($_POST['other_description'] ?? '');
    $amount      = round((float)($_POST['other_amount'] ?? 0), 2);
    if (!$description || $amount <= 0) {
      $message     = 'Description and a positive amount are required.';
      $messageType = 'error';
    } elseif (empty($cfg['contactEmail'])) {
      $message     = 'No contact email set — cannot send invoice.';
      $messageType = 'error';
    } else {
      $lineItems = [['description' => $description, 'amount' => $amount]];
      $inv = createOpenInvoice($buildingKey, $lineItems, $amount, 'manual', ['invoiceType' => 'other', 'paymentToken' => generateInvoiceToken()]);
      sendInvoiceEmail($inv, $cfg);
      $message = 'Invoice ' . $inv['id'] . ' created and emailed ($' . number_format($amount, 2) . ').';
    }
  }

  // ---- Mark invoice paid ----
  if ($action === 'mark_paid') {
    require_once __DIR__ . '/invoice-helpers.php';
    $invoiceId     = trim($_POST['invoice_id'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'check');
    if (!in_array($paymentMethod, ['check', 'stripe', 'cash', 'transfer'], true)) $paymentMethod = 'check';
    if (markInvoicePaid($buildingKey, $invoiceId, $paymentMethod)) {
      $message = 'Invoice ' . $invoiceId . ' marked as paid (' . $paymentMethod . '). Receipt sent.';
    } else {
      $message     = 'Could not mark invoice as paid — invoice not found.';
      $messageType = 'error';
    }
  }

  // ---- Reset billing email flags (test helper) ----
  if ($action === 'reset_billing_flags') {
    $cfg = loadBuildingConfig($buildingKey);
    unset($cfg['storageLimitEmailSent'], $cfg['woolsyBillingEmailSent'], $cfg['billingToken']);
    saveBuildingConfig($buildingKey, $cfg);
    $message = 'Billing email flags cleared — threshold emails will fire again on next trigger.';
  }

  // ---- Set storage limit ----
  if ($action === 'save_storage') {
    $mb = (int)($_POST['storage_limit_mb'] ?? 0);
    if ($mb > 0) {
      $cfg = loadBuildingConfig($buildingKey);
      $cfg['storageLimit'] = $mb * 1048576;
      // Clear the email-sent flag so a new warning can fire if needed
      unset($cfg['storageLimitEmailSent']);
      saveBuildingConfig($buildingKey, $cfg);
      $message = 'Storage limit updated to ' . $mb . ' MB.';
    } else {
      $message     = 'Enter a valid MB value.';
      $messageType = 'error';
    }
  }

  header('Location: building-detail.php?building=' . urlencode($buildingKey)
       . '&msg=' . urlencode($message) . '&type=' . urlencode($messageType));
  exit;
}

if (empty($message) && isset($_GET['msg'])) {
  $message     = $_GET['msg'];
  $messageType = $_GET['type'] ?? 'ok';
}

// -------------------------------------------------------
// Load data
// -------------------------------------------------------
$masterConfig = loadMasterConfig();

if (!$isNew) {
  $bldCfg      = loadBuildingConfig($buildingKey);
  $bldBuilding = $buildings[$buildingKey];
  $label       = ucwords(str_replace(['_', '-'], ' ', $buildingKey));
  $pricing     = loadPricing();
  $defaultLimit= (int)($pricing['storageDefaultLimit'] ?? 524288000);

  // Woolsy
  $allCredits  = loadCredits();
  $wc          = $allCredits[$buildingKey] ?? ['allocated' => 1.0, 'used' => 0];
  $wAlloc      = (float)($wc['allocated'] ?? 1.0);
  $wUsed       = (float)($wc['used']      ?? 0);
  $wPct        = pct($wUsed, $wAlloc);

  // Monthly usage stats
  $usageFile   = __DIR__ . '/faqs/woolsy_usage.json';
  $usageAll    = file_exists($usageFile) ? json_decode(file_get_contents($usageFile), true) ?? [] : [];
  $usageBld    = $usageAll[$buildingKey] ?? [];
  arsort($usageBld); // most recent month first

  // Storage
  $storageUsed    = (int)($bldCfg['storageUsed']    ?? 0);
  $storageLimit   = (int)($bldCfg['storageLimit']   ?? $defaultLimit);
  $storageUpdated = $bldCfg['storageUpdated'] ?? null;
  $sPct           = pct($storageUsed, $storageLimit);

  // ToS
  $tosFile     = CONFIG_DIR . 'tos.json';
  $tos         = file_exists($tosFile) ? json_decode(file_get_contents($tosFile), true) ?? [] : [];
  $tosVersion  = (int)($tos['version'] ?? 0);
  $tosScope    = $tos['scope'] ?? [];
  $inScope     = $tosVersion > 0 && ($tosScope === 'all' || (is_array($tosScope) && in_array($buildingKey, $tosScope)));
  $tosAccepted = $bldCfg['tosAccepted'] ?? null;
  $tosCurrent  = $tosAccepted && ((int)$tosAccepted['version'] === $tosVersion);

  // Signature history for this building
  $sigFile     = CONFIG_DIR . 'tos_signatures.json';
  $allSigs     = file_exists($sigFile) ? json_decode(file_get_contents($sigFile), true) ?? [] : [];
  $bldSigs     = array_values(array_filter($allSigs, fn($s) => ($s['building'] ?? '') === $buildingKey));

  // Invoices
  require_once __DIR__ . '/invoice-helpers.php';
  $invoices = loadInvoices($buildingKey);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $isNew ? 'Add New Association' : htmlspecialchars($label) . ' — Detail' ?></title>
  <style>
    * { box-sizing: border-box; }
    body        { font-family: sans-serif; max-width: 780px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar    { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1          { margin: 0; font-size: 1.5rem; }
    .back       { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover { text-decoration: underline; }
    .message    { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.25rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    .section    { border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; }
    .section h2 { margin: 0 0 1rem; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    label       { display: block; font-size: 0.875rem; font-weight: bold; margin-bottom: 0.25rem; }
    .hint       { font-size: 0.8rem; color: #888; margin: 0 0 1rem; }
    input[type=text], input[type=email], input[type=number], input[type=date] {
      padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px;
      font-size: 0.9rem; }
    .form-row   { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.75rem; align-items: flex-end; }
    .form-row > div { display: flex; flex-direction: column; gap: 0.2rem; }
    .btn        { padding: 0.45rem 1.1rem; background: #0070f3; color: #fff; border: none;
                  border-radius: 4px; font-size: 0.875rem; cursor: pointer; white-space: nowrap; }
    .btn:hover  { background: #005bb5; }
    .btn-gray   { background: #fff; color: #333; border: 1px solid #ccc; }
    .btn-gray:hover { background: #f5f5f5; }
    .btn-red    { background: #c00; color: #fff; border: none; }
    .btn-red:hover { background: #900; }
    .stat-row   { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .stat-item  { min-width: 160px; }
    .stat-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin-bottom: 0.25rem; }
    .stat-val   { font-size: 1rem; font-weight: bold; color: #111; }
    .stat-sub   { font-size: 0.78rem; color: #888; }
    .bar-wrap   { height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin: 0.35rem 0; max-width: 280px; }
    .bar-fill   { height: 100%; border-radius: 4px; }
    .badge      { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px;
                  font-size: 0.8rem; font-weight: 600; }
    .badge.ok   { background: #dcfce7; color: #15803d; }
    .badge.warn { background: #fef3c7; color: #92400e; }
    .badge.none { background: #f3f4f6; color: #9ca3af; }
    hr          { border: none; border-top: 1px solid #eee; margin: 1rem 0; }
    table       { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    th          { text-align: left; padding: 0.45rem 0.75rem; background: #f5f5f5;
                  border-bottom: 2px solid #ddd; color: #555; font-size: 0.78rem;
                  text-transform: uppercase; letter-spacing: 0.04em; }
    td          { padding: 0.5rem 0.75rem; border-bottom: 1px solid #eee; }
    tr:last-child td { border-bottom: none; }
    details     { margin-top: 0.75rem; }
    summary     { cursor: pointer; font-size: 0.875rem; color: #0070f3; }
    .code-block { background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px;
                  padding: 1rem; font-family: monospace; font-size: 0.82rem;
                  white-space: pre; overflow-x: auto; margin: 0.75rem 0; }
    .checkbox-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; margin-bottom: 0.75rem; }
    .future-tag { font-size: 0.72rem; background: #e0e7ff; color: #4338ca;
                  padding: 0.1rem 0.4rem; border-radius: 4px; font-weight: 600; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><?= $isNew ? 'Add New Association' : htmlspecialchars($label) ?></h1>
  <a href="master-admin.php" class="back">← Master Admin</a>
</div>

<?php if ($message): ?>
  <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($isNew):
  $savedTemplate    = $masterConfig['templateFolderId']    ?? '';
  $savedAssociation = $masterConfig['associationFolderId'] ?? '';
  $savedSheet       = $masterConfig['templateSheetId']     ?? '';
?>
<!-- ===================== NEW BUILDING SETUP ===================== -->

<style>
  .nb-phase       { background:#fff; border:1px solid #ddd; border-radius:6px; padding:1.5rem; margin-bottom:1.5rem; }
  .nb-phase h3    { margin:0 0 1rem; font-size:1.05rem; color:#1a3a5c; }
  .nb-row         { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.9rem; align-items:flex-end; }
  .nb-field       { display:flex; flex-direction:column; gap:0.25rem; }
  .nb-field label { font-size:0.82rem; font-weight:600; color:#555; }
  .nb-field input { padding:0.4rem 0.6rem; border:1px solid #ccc; border-radius:4px; font-size:0.9rem; }
  .nb-hint        { font-size:0.8rem; color:#888; margin-top:0.15rem; }
  .nb-create-btn  { background:#1a5a2a; color:#fff; border:none; padding:0.55rem 1.4rem;
                    border-radius:4px; font-size:0.95rem; cursor:pointer; margin-top:0.5rem; }
  .nb-create-btn:hover { background:#134520; }
  .nb-create-btn:disabled { background:#999; cursor:default; }
  .nb-success     { background:#f0faf0; border:1px solid #a0d0a0; border-radius:4px;
                    padding:0.6rem 1rem; font-size:0.88rem; color:#1a5a1a; margin-top:0.75rem; }
  .nb-error       { background:#fff0f0; border:1px solid #f0a0a0; border-radius:4px;
                    padding:0.6rem 1rem; font-size:0.88rem; color:#a00; margin-top:0.75rem; }
  /* Checklist */
  .checklist      { list-style:none; padding:0; margin:0; }
  .checklist li   { display:flex; gap:0.75rem; align-items:flex-start; margin-bottom:1.4rem;
                    padding-bottom:1.4rem; border-bottom:1px solid #eee; }
  .checklist li:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
  .cl-num         { flex-shrink:0; width:28px; height:28px; border-radius:50%;
                    background:#1a3a5c; color:#fff; font-weight:700; font-size:0.85rem;
                    display:flex; align-items:center; justify-content:center; margin-top:2px; }
  .cl-num.done    { background:#22c55e; }
  .cl-body        { flex:1; }
  .cl-title       { font-weight:700; font-size:0.95rem; margin-bottom:0.4rem; color:#222; }
  .cl-steps       { font-size:0.88rem; color:#444; line-height:1.6; }
  .cl-steps ol    { margin:0.4rem 0 0.4rem 1.2rem; padding:0; }
  .cl-steps li    { margin:0.2rem 0; }
  .cl-code        { background:#f4f4f4; border:1px solid #ddd; border-radius:4px;
                    padding:0.5rem 0.75rem; font-family:monospace; font-size:0.82rem;
                    white-space:pre-wrap; word-break:break-all; margin:0.5rem 0; position:relative; }
  .cl-copy        { position:absolute; top:0.35rem; right:0.5rem; font-size:0.75rem;
                    background:#fff; border:1px solid #ccc; border-radius:3px;
                    padding:0.15rem 0.5rem; cursor:pointer; color:#555; }
  .cl-copy:hover  { background:#f0f0f0; }
  .cl-note        { background:#fff8e8; border-left:3px solid #f0a800; padding:0.4rem 0.7rem;
                    font-size:0.83rem; color:#555; margin-top:0.5rem; border-radius:0 3px 3px 0; }
  .cl-check       { margin-top:0.6rem; display:flex; align-items:center; gap:0.4rem;
                    font-size:0.82rem; color:#888; }
  .cl-check input { cursor:pointer; width:15px; height:15px; }
</style>

<div class="nb-phase">
  <h3>Step 1 &mdash; Building Identity &amp; Drive Folders</h3>
  <div class="nb-row">
    <div class="nb-field">
      <label>Building key <span style="color:red">*</span></label>
      <input type="text" id="nb_key" placeholder="e.g. LyndhurstJ" style="width:160px;" oninput="nbUpdate()">
      <span class="nb-hint">No spaces. Used in URLs and config.</span>
    </div>
    <div class="nb-field">
      <label>Display name <span style="color:red">*</span></label>
      <input type="text" id="nb_displayname" placeholder="e.g. Lyndhurst J" style="width:180px;" oninput="nbUpdate()">
    </div>
    <div class="nb-field">
      <label>State</label>
      <input type="text" id="nb_state" value="FL" style="width:50px;" oninput="nbUpdate()">
    </div>
    <div class="nb-field">
      <label>Community</label>
      <input type="text" id="nb_community" placeholder="e.g. CVE (optional)" style="width:150px;" oninput="nbUpdate()">
    </div>
  </div>
  <div class="nb-row">
    <div class="nb-field" style="flex:1;min-width:280px;">
      <label>Template Folder ID <span style="color:red">*</span></label>
      <input type="text" id="nb_template" value="<?= htmlspecialchars($savedTemplate) ?>" placeholder="ID of Master Files/Template Folder in Drive" style="width:100%;" oninput="nbUpdate()">
      <span class="nb-hint">From Drive URL of the template folder inside Master Files.</span>
    </div>
  </div>
  <div class="nb-row">
    <div class="nb-field" style="flex:1;min-width:280px;">
      <label>Association Folders ID <span style="color:red">*</span></label>
      <input type="text" id="nb_assoc" value="<?= htmlspecialchars($savedAssociation) ?>" placeholder="ID of Association Folders in Drive" style="width:100%;" oninput="nbUpdate()">
      <span class="nb-hint">New building folder is created inside this folder.</span>
    </div>
  </div>
  <div class="nb-row">
    <div class="nb-field" style="flex:1;min-width:280px;">
      <label>Template Owner DB Sheet ID</label>
      <input type="text" id="nb_sheet" value="<?= htmlspecialchars($savedSheet) ?>" placeholder="ID of template Google Sheet (optional)" style="width:100%;">
      <span class="nb-hint">If provided, a copy named &ldquo;BuildingName Owner DB&rdquo; is created automatically.</span>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
    <button class="nb-create-btn" id="nb_create_btn" onclick="createFolders()" disabled>Create Drive Folders</button>
    <label style="font-size:0.82rem;color:#666;display:flex;align-items:center;gap:0.4rem;">
      <input type="checkbox" id="nb_savedefaults" checked>
      Save folder IDs as defaults
    </label>
  </div>
  <div id="nb_result"></div>
</div>

<div class="nb-phase" id="nb_checklist" style="display:none;">
  <h3>Setup Checklist</h3>
  <p style="font-size:0.88rem;color:#555;margin:0 0 1.25rem;">Work through these steps in order. Check each one off as you complete it.</p>
  <ul class="checklist">

    <!-- Step 1: Drive folders (auto-done) -->
    <li>
      <div class="cl-num done" id="cl_num_1">&#10003;</div>
      <div class="cl-body">
        <div class="cl-title">Drive Folders Created</div>
        <div class="cl-steps">
          Folder structure and template files (including system documents) cloned from template. Public folder set to &ldquo;Anyone with the link&rdquo;.<br>
          <strong>Public folder ID:</strong> <code id="cl_public_id">&mdash;</code><br>
          <strong>Private folder ID:</strong> <code id="cl_private_id">&mdash;</code>
        </div>
      </div>
    </li>

    <!-- Step 2: buildings.php + credentials -->
    <li>
      <div class="cl-num">2</div>
      <div class="cl-body">
        <div class="cl-title">Add Building to Server Config</div>
        <div class="cl-steps">
          <strong>a)</strong> Open <code>buildings.php</code> on the server and add this entry:
          <div class="cl-code" id="cl_snippet">Fill in building details above to generate snippet.<button class="cl-copy" onclick="copyCode('cl_snippet')">Copy</button></div>
          Upload the updated <code>buildings.php</code> to the server.<br><br>
          <strong>b)</strong> Create a new empty credentials file on the server at:
          <div class="cl-code" id="cl_creds_path">credentials/BuildingKey.json<button class="cl-copy" onclick="copyCode('cl_creds_path')">Copy</button></div>
          Contents must be exactly: <code>[]</code>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,2)"> Done</label>
      </div>
    </li>

    <!-- Step 3: MySQL database -->
    <li>
      <div class="cl-num">3</div>
      <div class="cl-body">
        <div class="cl-title">Import Resident Data into MySQL</div>
        <div class="cl-steps">
          Resident data lives in the MySQL database, not in a Google Sheet. Use the one-time import tool:<br><br>
          <strong>Option A — Import from existing Google Sheet (existing associations):</strong>
          <ol>
            <li>Upload <code>import-sheet-to-db.php</code> to the server if not already there</li>
            <li>Visit: <code>https://sheepsite.com/Scripts/import-sheet-to-db.php?building=<span id="cl_key_db"></span></code></li>
            <li>This reads the sheet&rsquo;s Database and CarDB tabs and inserts all rows into MySQL</li>
            <li>It is idempotent &mdash; safe to run multiple times; duplicate names are skipped</li>
          </ol>
          <strong>Option B — Enter data directly (new associations):</strong>
          <ol>
            <li>Set up the admin account first (Step 4), then log in</li>
            <li>Use <strong>Manage Residents</strong> &rarr; <strong>Add Resident</strong> to enter each resident manually</li>
          </ol>
          <div class="cl-note"><strong>Note:</strong> The President&rsquo;s record (with <em>board_role = President</em> and a valid email) must exist in the database before the admin password reset will work. Enter the President first if using Option B.</div>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,3)"> Done</label>
      </div>
    </li>

    <!-- Step 4: Admin account -->
    <li>
      <div class="cl-num">4</div>
      <div class="cl-body">
        <div class="cl-title">Set Up the Admin Account</div>
        <div class="cl-steps">
          Visit the admin page for the new association &mdash; it will redirect automatically to the password reset flow:
          <div class="cl-code" id="cl_admin_url">https://sheepsite.com/Scripts/admin.php?building=BuildingKey<button class="cl-copy" onclick="copyCode('cl_admin_url')">Copy</button></div>
          <ol>
            <li>Enter the <strong>President&rsquo;s unit number</strong> as the secret verification</li>
            <li>A temporary password is emailed to the President (looked up from the MySQL database by <em>board_role = President</em>)</li>
            <li>Log in with the temporary password &mdash; you will be prompted to set a permanent one immediately</li>
          </ol>
          <div class="cl-note"><strong>Note:</strong> If the President&rsquo;s record is not yet in MySQL, use the master password as a fallback to log in and set the admin password manually.</div>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,4)"> Done</label>
      </div>
    </li>

    <!-- Step 5: Create web accounts -->
    <li>
      <div class="cl-num">5</div>
      <div class="cl-body">
        <div class="cl-title">Create Resident Web Accounts</div>
        <div class="cl-steps">
          <ol>
            <li>Log in to the Admin Dashboard for the new association</li>
            <li>Click <strong>Manage Users</strong> &rarr; <strong>Sync Now</strong></li>
            <li>Sync compares the MySQL database against existing web accounts and lists everyone missing an account</li>
            <li>Check all residents &rarr; click <strong>Recreate Checked</strong> &mdash; accounts are created with a temporary password and a welcome email is sent automatically to each resident&rsquo;s email address on file</li>
            <li>For any resident without an email on file, use <strong>Add/Reset User</strong> to set a password manually and distribute it yourself</li>
          </ol>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,5)"> Done</label>
      </div>
    </li>

    <!-- Step 6: Website -->
    <li>
      <div class="cl-num">6</div>
      <div class="cl-body">
        <div class="cl-title">Configure the Building Website</div>
        <div class="cl-steps">
          The building website is a PHP site hosted on the association&rsquo;s own server (not Website Builder).
          See <code>NEW-SITE-GUIDE.md</code> in the repo for the full setup walkthrough. Key steps:<br><br>
          <ol>
            <li>Upload all site PHP files to the association&rsquo;s server</li>
            <li>Add the footer script to every page. The only value that changes per building is <code>BUILDING_NAME</code>:
              <div class="cl-code" id="cl_footer_script">Fill in building key above to generate script.<button class="cl-copy" onclick="copyCode('cl_footer_script')">Copy</button></div>
            </li>
            <li>Verify public and private folder links open the correct Drive folders</li>
            <li>Verify resident login, password reset, and the reports (Elevator List, Parking List, Resident List) all work</li>
          </ol>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,6)"> Done</label>
      </div>
    </li>

  </ul>
</div>

<script>
var nbPublicId  = '';
var nbPrivateId = '';
var nbSheetUrl  = '';

function nbUpdate() {
  var key = document.getElementById('nb_key').value.trim();
  var tpl = document.getElementById('nb_template').value.trim();
  var asc = document.getElementById('nb_assoc').value.trim();
  document.getElementById('nb_create_btn').disabled = !(key && tpl && asc);
  if (nbPublicId) updateChecklist();
}

function createFolders() {
  var key      = document.getElementById('nb_key').value.trim();
  var dispname = document.getElementById('nb_displayname').value.trim();
  var tpl      = document.getElementById('nb_template').value.trim();
  var asc      = document.getElementById('nb_assoc').value.trim();
  var sheet    = document.getElementById('nb_sheet').value.trim();
  var savedef  = document.getElementById('nb_savedefaults').checked;

  if (!key || !tpl || !asc) return;

  var btn = document.getElementById('nb_create_btn');
  btn.disabled    = true;
  btn.textContent = 'Creating\u2026';

  var fd = new FormData();
  fd.append('action',           'create_folders');
  fd.append('buildingName',     key);
  fd.append('templateFolderId', tpl);
  fd.append('parentFolderId',   asc);
  if (sheet)  fd.append('templateSheetId', sheet);
  if (savedef) fd.append('saveDefaults', '1');

  fetch('building-detail.php?building=new', { method: 'POST', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      btn.textContent = 'Create Drive Folders';
      var res = document.getElementById('nb_result');
      if (!d.ok) {
        res.innerHTML = '<div class="nb-error">&#10007; ' + esc(d.error || 'Unknown error') + '</div>';
        btn.disabled = false;
        return;
      }
      nbPublicId  = d.publicFolderId  || '';
      nbPrivateId = d.privateFolderId || '';
      nbSheetUrl  = d.sheetUrl        || '';
      var sheetNote = nbSheetUrl ? ' Owner DB sheet copied.' : '';
      res.innerHTML = '<div class="nb-success">&#10003; Drive folders created.' + sheetNote + '</div>';
      // Pre-warm the resident listing cache in the background
      if (nbPublicId && nbPrivateId) {
        var warmFd = new FormData();
        warmFd.append('building',        key);
        warmFd.append('publicFolderId',  nbPublicId);
        warmFd.append('privateFolderId', nbPrivateId);
        fetch('warm-cache.php', { method: 'POST', body: warmFd }).catch(function () {});
      }
      updateChecklist();
      document.getElementById('nb_checklist').style.display = '';
      document.getElementById('nb_checklist').scrollIntoView({ behavior: 'smooth', block: 'start' });
    })
    .catch(function (err) {
      document.getElementById('nb_result').innerHTML = '<div class="nb-error">&#10007; Network error: ' + esc(String(err)) + '</div>';
      btn.disabled    = false;
      btn.textContent = 'Create Drive Folders';
    });
}

function updateChecklist() {
  var key      = document.getElementById('nb_key').value.trim();
  var dispname = document.getElementById('nb_displayname').value.trim() || key;
  var state    = document.getElementById('nb_state').value.trim() || 'FL';
  var community= document.getElementById('nb_community').value.trim();

  // Step 1 IDs
  document.getElementById('cl_public_id').textContent  = nbPublicId  || '\u2014';
  document.getElementById('cl_private_id').textContent = nbPrivateId || '\u2014';

  // Step 2 sheet
  var sheetDisplayName = (dispname || key || 'Building') + ' Owner DB';
  ['cl_sheet_name','cl_sheet_name2'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.textContent = sheetDisplayName;
  });
  var bkEl = document.getElementById('cl_building_key_sheet');
  if (bkEl) bkEl.textContent = "'" + (key || 'BuildingKey') + "'";
  if (nbSheetUrl) {
    document.getElementById('cl_sheet_copied').style.display = '';
    document.getElementById('cl_sheet_manual').style.display = 'none';
    var linkWrap = document.getElementById('cl_sheet_link_wrap');
    if (linkWrap) linkWrap.innerHTML = '<a href="' + nbSheetUrl + '" target="_blank" rel="noopener" style="margin-left:0.5rem;font-weight:600;">Open Sheet &rarr;</a>';
  } else {
    document.getElementById('cl_sheet_copied').style.display = 'none';
    document.getElementById('cl_sheet_manual').style.display = '';
  }

  // Step 3 snippet
  if (key) {
    var lines = ["  '" + key + "' => [",
                 "    'state'           => '" + state + "',"];
    if (community) lines.push("    'community'       => '" + community + "',");
    lines.push("    'publicFolderId'  => '" + (nbPublicId  || 'PASTE_PUBLIC_FOLDER_ID')  + "',");
    lines.push("    'privateFolderId' => '" + (nbPrivateId || 'PASTE_PRIVATE_FOLDER_ID') + "',");
    lines.push("    'webAppURL'       => '',   // not used — reports served from MySQL");
    lines.push("  ],");
    var snip = document.getElementById('cl_snippet');
    var btn  = snip.querySelector('.cl-copy');
    snip.textContent = lines.join('\n');
    snip.appendChild(btn);
  }

  // Step 3 credentials path
  var cp = document.getElementById('cl_creds_path');
  var cpBtn = cp.querySelector('.cl-copy');
  cp.textContent = key ? 'credentials/' + key + '.json' : 'credentials/BuildingKey.json';
  cp.appendChild(cpBtn);

  // Step 3 DB import URL
  var dbKeyEl = document.getElementById('cl_key_db');
  if (dbKeyEl) dbKeyEl.textContent = key || 'BuildingKey';

  // Step 4 admin URL
  var au = document.getElementById('cl_admin_url');
  var auBtn = au.querySelector('.cl-copy');
  au.textContent = key ? 'https://sheepsite.com/Scripts/admin.php?building=' + encodeURIComponent(key) : 'https://sheepsite.com/Scripts/admin.php?building=BuildingKey';
  au.appendChild(auBtn);

  // Step 6 footer script
  var fs = document.getElementById('cl_footer_script');
  var fsBtn = fs.querySelector('.cl-copy');
  fs.textContent = key ? generateFooterScript(key) : 'Fill in building key above to generate script.';
  fs.appendChild(fsBtn);
}

function generateFooterScript(key) {
  return "<script>\nconst BUILDING_NAME = '" + key + "';\n\ndocument.addEventListener('DOMContentLoaded', function () {\n  const PUBLIC_URL  = 'https://sheepsite.com/Scripts/display-public-dir.php';\n  const PRIVATE_URL = 'https://sheepsite.com/Scripts/display-private-dir.php';\n\n  document.querySelectorAll('.gdrive-link').forEach(function (btn) {\n    var url = PUBLIC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);\n    var subdir = btn.getAttribute('data-subdir');\n    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);\n    url += '&return=' + encodeURIComponent(window.location.href);\n    btn.href = url;\n  });\n\n  document.querySelectorAll('iframe[data-script=\"protected-report\"]').forEach(function (iframe) {\n    var url = 'https://sheepsite.com/Scripts/protected-report.php?building=' + encodeURIComponent(BUILDING_NAME);\n    var page = iframe.getAttribute('data-page');\n    if (page) url += '&page=' + encodeURIComponent(page);\n    iframe.onload = function () { var l = document.getElementById('doc-loader'); if (l) l.style.display='none'; };\n    iframe.src = url;\n  });\n\n  document.querySelectorAll('iframe[data-script=\"public-report\"]').forEach(function (iframe) {\n    var url = 'https://sheepsite.com/Scripts/public-report.php?building=' + encodeURIComponent(BUILDING_NAME);\n    var page = iframe.getAttribute('data-page');\n    if (page) url += '&page=' + encodeURIComponent(page);\n    url += '&nav=0';\n    iframe.onload = function () { var l = document.getElementById('doc-loader'); if (l) l.style.display='none'; };\n    iframe.src = url;\n  });\n\n  document.querySelectorAll('iframe[data-script=\"get-doc-byname\"]').forEach(function (iframe) {\n    var url = 'https://sheepsite.com/Scripts/get-doc-byname.php?building=' + encodeURIComponent(BUILDING_NAME);\n    var subdir = iframe.getAttribute('data-subdir');\n    var filename = iframe.getAttribute('data-filename');\n    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);\n    if (filename) url += '&filename=' + encodeURIComponent(filename);\n    iframe.style.display = 'none';\n    iframe.onload = function () { iframe.style.display='block'; var l=document.getElementById('doc-loader'); if(l) l.style.display='none'; };\n    iframe.src = url;\n  });\n\n  document.querySelectorAll('a[href*=\"admin.php\"]').forEach(function (link) {\n    link.href = 'https://sheepsite.com/Scripts/admin.php?building=' + encodeURIComponent(BUILDING_NAME);\n  });\n});\n\nfunction openFolder(subdir) {\n  var url = 'https://sheepsite.com/Scripts/display-public-dir.php?building=' + encodeURIComponent(BUILDING_NAME) + '&return=' + encodeURIComponent(window.location.href);\n  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);\n  window.location.href = url;\n}\nfunction openPrivateFolder(subdir) {\n  var url = 'https://sheepsite.com/Scripts/display-private-dir.php?building=' + encodeURIComponent(BUILDING_NAME) + '&return=' + encodeURIComponent(window.location.href);\n  if (subdir) url += '&path=' + encodeURIComponent(subdir);\n  window.location.href = url;\n}\nfunction openReport(page) {\n  window.location.href = 'https://sheepsite.com/Scripts/protected-report.php?building=' + encodeURIComponent(BUILDING_NAME) + '&page=' + encodeURIComponent(page) + '&return=' + encodeURIComponent(window.location.href);\n}\nfunction openPublicReport(page) {\n  window.location.href = 'https://sheepsite.com/Scripts/public-report.php?building=' + encodeURIComponent(BUILDING_NAME) + '&page=' + encodeURIComponent(page);\n}\nfunction openAdmin() {\n  window.location.href = 'https://sheepsite.com/Scripts/admin.php?building=' + encodeURIComponent(BUILDING_NAME);\n}\nfunction openDoc(subdir, filename) {\n  var url = 'https://sheepsite.com/Scripts/get-doc-byname.php?building=' + encodeURIComponent(BUILDING_NAME) + '&filename=' + encodeURIComponent(filename);\n  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);\n  window.open(url, '_blank');\n}\n<\/script>";
}

function clCheck(cb, num) {
  var el = document.querySelector('.checklist li:nth-child(' + num + ') .cl-num');
  if (el) {
    el.classList.toggle('done', cb.checked);
    el.textContent = cb.checked ? '\u2713' : num;
  }
}

function copyCode(id) {
  var el  = document.getElementById(id);
  var btn = el.querySelector('.cl-copy');
  var txt = el.textContent.replace(btn ? btn.textContent : '', '').trim();
  navigator.clipboard.writeText(txt).then(function () {
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(function () { btn.textContent = orig; }, 1500);
  });
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Trigger checklist updates on input changes (before folders are created)
['nb_key','nb_displayname','nb_state','nb_community'].forEach(function(id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('input', function() { if (nbPublicId) updateChecklist(); });
});
</script>

<?php else: ?>
<!-- ===================== EXISTING BUILDING ===================== -->

<!-- Overview stats -->
<div class="section">
  <h2>📊 Overview</h2>
  <div class="stat-row">
    <div class="stat-item">
      <div class="stat-label">Woolsy Credits</div>
      <div class="stat-val"><?= number_format($wUsed, 2) ?> / <?= number_format($wAlloc, 2) ?></div>
      <div class="bar-wrap"><div class="bar-fill" style="width:<?= $wPct ?>%;background:<?= barColor($wPct) ?>;"></div></div>
      <div class="stat-sub"><?= $wPct ?>% used</div>
    </div>
    <div class="stat-item">
      <div class="stat-label">Storage</div>
      <?php if (isset($bldCfg['storageUsed'])): ?>
        <div class="stat-val"><?= fmtBytes($storageUsed) ?> / <?= fmtBytes($storageLimit) ?></div>
        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $sPct ?>%;background:<?= barColor($sPct) ?>;"></div></div>
        <div class="stat-sub"><?= $sPct ?>% used<?= $storageUpdated ? ' · updated ' . fmtDate($storageUpdated) : '' ?></div>
      <?php else: ?>
        <div class="stat-val" style="color:#aaa;">Not yet measured</div>
        <div class="stat-sub">Admin must visit Storage Report to populate</div>
      <?php endif; ?>
    </div>
    <div class="stat-item">
      <div class="stat-label">Terms of Service</div>
      <?php if (!$inScope): ?>
        <span class="badge none">Not enrolled</span>
      <?php elseif ($tosCurrent): ?>
        <span class="badge ok">✓ Accepted v<?= (int)$tosAccepted['version'] ?></span>
        <div class="stat-sub"><?= fmtDate($tosAccepted['date']) ?></div>
      <?php else: ?>
        <span class="badge warn">⚠ Pending v<?= $tosVersion ?></span>
      <?php endif; ?>
    </div>
    <div class="stat-item">
      <div class="stat-label">Renewal Date</div>
      <?php $rd = $bldCfg['renewalDate'] ?? null; ?>
      <?php if ($rd): ?>
        <?php $days = (int)((strtotime($rd) - time()) / 86400); ?>
        <div class="stat-val <?= $days <= 30 ? 'style="color:#dc2626"' : '' ?>"><?= fmtDate($rd) ?></div>
        <div class="stat-sub"><?= $days > 0 ? $days . ' days away' : 'Overdue' ?></div>
      <?php else: ?>
        <div class="stat-val" style="color:#aaa;">—</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Configuration -->
<div class="section">
  <h2>⚙️ Configuration</h2>
  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>">
    <input type="hidden" name="action" value="save_config">
    <div class="form-row">
      <div style="flex:1;min-width:220px;">
        <label>Site URL</label>
        <input type="text" name="site_url" value="<?= htmlspecialchars($bldCfg['siteURL'] ?? '') ?>"
               placeholder="https://lyndhurstH.com" style="width:100%;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label>Billing contact email</label>
        <input type="email" name="contact_email" value="<?= htmlspecialchars($bldCfg['contactEmail'] ?? '') ?>"
               placeholder="board@example.com" style="width:100%;">
      </div>
    </div>
    <div class="checkbox-row">
      <input type="checkbox" id="has_domain" name="has_domain" <?= !empty($bldCfg['hasDomain']) ? 'checked' : '' ?>>
      <label for="has_domain" style="margin:0;font-weight:normal;">This building has its own domain (billed for domain renewal)</label>
    </div>
    <div class="checkbox-row">
      <input type="checkbox" id="test_site" name="test_site" <?= !empty($bldCfg['testSite']) ? 'checked' : '' ?>>
      <label for="test_site" style="margin:0;font-weight:normal;">Test Site mode — disables admin password change, contact email, and Woolsy KB rebuild</label>
    </div>
    <button type="submit" class="btn">Save Configuration</button>
  </form>

  <hr>
  <details>
    <summary>Drive folder IDs &amp; Apps Script URL</summary>
    <table style="margin-top:0.75rem;">
      <tr><th>Field</th><th>Value</th></tr>
      <tr><td>Public Folder ID</td><td><code><?= htmlspecialchars($bldBuilding['publicFolderId'] ?? '—') ?></code></td></tr>
      <tr><td>Private Folder ID</td><td><code><?= htmlspecialchars($bldBuilding['privateFolderId'] ?? '—') ?></code></td></tr>
      <tr><td>Apps Script URL</td><td style="word-break:break-all;"><code><?= htmlspecialchars($bldBuilding['webAppURL'] ?? '—') ?></code></td></tr>
      <tr><td>State</td><td><?= htmlspecialchars($bldBuilding['state'] ?? '—') ?></td></tr>
      <tr><td>Community</td><td><?= htmlspecialchars($bldBuilding['community'] ?? '—') ?></td></tr>
    </table>
    <p style="font-size:0.82rem;color:#888;margin-top:0.5rem;">These fields are set in <code>buildings.php</code> on the server.</p>
  </details>
</div>

<!-- Website -->
<div class="section">
  <h2>🌐 Website</h2>
  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>">
    <input type="hidden" name="action" value="save_site_config">

    <div class="form-row">
      <div style="flex:1;min-width:220px;">
        <label>Association display name</label>
        <input type="text" name="display_name" value="<?= htmlspecialchars($bldCfg['displayName'] ?? '') ?>"
               placeholder="Lyndhurst H Condo" style="width:100%;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label>Header image filename <small style="color:#888;font-weight:normal;">(stored in Scripts/assets/)</small></label>
        <input type="text" name="header_image_url" value="<?= htmlspecialchars($bldCfg['headerImageUrl'] ?? '') ?>"
               placeholder="SampleSite-header.jpg" style="width:100%;">
      </div>
    </div>

    <div class="form-row">
      <div style="flex:1;min-width:220px;">
        <label>Google Calendar URL</label>
        <input type="text" name="calendar_url" value="<?= htmlspecialchars($bldCfg['calendarUrl'] ?? '') ?>"
               placeholder="https://calendar.google.com/..." style="width:100%;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label>Facebook URL</label>
        <input type="text" name="facebook_url" value="<?= htmlspecialchars($bldCfg['facebookUrl'] ?? '') ?>"
               placeholder="https://facebook.com/groups/..." style="width:100%;">
      </div>
    </div>

    <h3 style="margin:1.25rem 0 0.75rem;font-size:0.95rem;color:#555;">Property Management</h3>
    <div class="form-row">
      <div style="flex:1;min-width:160px;">
        <label>Company name</label>
        <input type="text" name="pm_name" value="<?= htmlspecialchars($bldCfg['propertyMgmt']['name'] ?? '') ?>"
               placeholder="Seacrest" style="width:100%;">
      </div>
      <div style="flex:1;min-width:160px;">
        <label>Phone</label>
        <input type="text" name="pm_phone" value="<?= htmlspecialchars($bldCfg['propertyMgmt']['phone'] ?? '') ?>"
               placeholder="1-888-828-6464" style="width:100%;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label>Portal URL</label>
        <input type="text" name="pm_url" value="<?= htmlspecialchars($bldCfg['propertyMgmt']['url'] ?? '') ?>"
               placeholder="https://home.seacrestservices.com/login" style="width:100%;">
      </div>
      <div style="flex:1;min-width:120px;">
        <label>Button label</label>
        <input type="text" name="pm_button_label" value="<?= htmlspecialchars($bldCfg['propertyMgmt']['buttonLabel'] ?? '') ?>"
               placeholder="Vantaca" style="width:100%;">
      </div>
    </div>

    <button type="submit" class="btn">Save Website Settings</button>
  </form>
</div>

<!-- Woolsy Credits -->
<div class="section">
  <h2><img src="https://sheepsite.com/Scripts/assets/Woolsy-standing-transparent.png" height="28" alt="Woolsy" style="vertical-align:middle;margin-right:0.4rem;"> Woolsy Credits</h2>
  <div class="stat-row" style="margin-bottom:0.5rem;">
    <div>
      <div class="stat-label">Allocated</div>
      <div class="stat-val"><?= number_format($wAlloc, 2) ?></div>
    </div>
    <div>
      <div class="stat-label">Used</div>
      <div class="stat-val"><?= number_format($wUsed, 2) ?></div>
    </div>
    <div>
      <div class="stat-label">Remaining</div>
      <div class="stat-val"><?= number_format(max(0, $wAlloc - $wUsed), 2) ?></div>
    </div>
    <div>
      <div class="stat-label">Usage</div>
      <div class="stat-val"><?= $wPct ?>%</div>
    </div>
  </div>

  <hr>
  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="action" value="topup_woolsy">
    <div>
      <label>Add credits (manual top-up)</label>
      <input type="number" name="credits_to_add" min="0.01" step="0.01" placeholder="e.g. 5.00" style="width:120px;">
    </div>
    <button type="submit" class="btn">Add Credits</button>
  </form>

  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>"
        style="margin-top:0.75rem;"
        onsubmit="return confirm('Reset usage counter to 0 for <?= htmlspecialchars($buildingKey) ?>?');">
    <input type="hidden" name="action" value="reset_woolsy_used">
    <button type="submit" class="btn btn-gray" style="font-size:0.82rem;">Reset usage counter to 0</button>
  </form>

  <?php if ($usageBld): ?>
  <hr>
  <details>
    <summary>Monthly question history</summary>
    <table style="margin-top:0.75rem;max-width:320px;">
      <tr><th>Month</th><th style="text-align:right;">Questions</th></tr>
      <?php foreach (array_slice($usageBld, 0, 12, true) as $month => $count): ?>
      <tr>
        <td><?= htmlspecialchars($month) ?></td>
        <td style="text-align:right;"><?= (int)$count ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </details>
  <?php endif; ?>
</div>

<!-- Storage -->
<div class="section">
  <h2>📦 Storage</h2>
  <?php if (isset($bldCfg['storageUsed'])): ?>
  <div class="stat-row" style="margin-bottom:0.5rem;">
    <div>
      <div class="stat-label">Used</div>
      <div class="stat-val"><?= fmtBytes($storageUsed) ?></div>
    </div>
    <div>
      <div class="stat-label">Limit</div>
      <div class="stat-val"><?= fmtBytes($storageLimit) ?></div>
    </div>
    <div>
      <div class="stat-label">Available</div>
      <div class="stat-val"><?= fmtBytes(max(0, $storageLimit - $storageUsed)) ?></div>
    </div>
    <div>
      <div class="stat-label">Usage</div>
      <div class="stat-val"><?= $sPct ?>%</div>
    </div>
  </div>
  <div class="bar-wrap" style="max-width:400px;height:10px;">
    <div class="bar-fill" style="width:<?= $sPct ?>%;background:<?= barColor($sPct) ?>;"></div>
  </div>
  <?php if ($storageUpdated): ?>
    <p class="hint" style="margin-top:0.4rem;">Last measured: <?= htmlspecialchars(fmtDate($storageUpdated)) ?></p>
  <?php endif; ?>
  <?php else: ?>
    <p class="hint">Storage not yet measured. Admin must visit the Storage Report page to populate the cache.</p>
  <?php endif; ?>

  <hr>
  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="action" value="save_storage">
    <div>
      <label>Set storage limit (MB)</label>
      <input type="number" name="storage_limit_mb" min="1"
             value="<?= round($storageLimit / 1048576) ?>" style="width:120px;">
    </div>
    <button type="submit" class="btn">Update Limit</button>
  </form>
</div>

<!-- License Agreement -->
<div class="section">
  <h2>📜 License Agreement</h2>
  <?php if (!$inScope): ?>
    <p style="font-size:0.9rem;color:#888;">This building is not enrolled in ToS enforcement.
       <a href="tos-admin.php" style="color:#0070f3;">Manage enrollment →</a></p>
  <?php elseif ($tosCurrent): ?>
    <p style="font-size:0.9rem;">
      <span class="badge ok">✓ Current — v<?= (int)$tosAccepted['version'] ?></span>
      &nbsp; Accepted <?= htmlspecialchars(fmtDate($tosAccepted['date'])) ?>
      by <?= htmlspecialchars($tosAccepted['who'] ?? 'admin') ?>
    </p>
  <?php else: ?>
    <p style="font-size:0.9rem;">
      <span class="badge warn">⚠ Pending v<?= $tosVersion ?></span>
      &nbsp; Building admin must log in and accept before accessing admin features.
    </p>
  <?php endif; ?>

  <?php if ($bldSigs): ?>
  <details style="margin-top:0.5rem;">
    <summary><?= count($bldSigs) ?> archived signature(s)</summary>
    <table style="margin-top:0.5rem;">
      <tr><th>Version</th><th>Date</th><th>Signed By</th></tr>
      <?php foreach (array_reverse($bldSigs) as $sig): ?>
      <tr>
        <td>v<?= (int)($sig['version'] ?? 0) ?></td>
        <td><?= htmlspecialchars(fmtDate($sig['date'] ?? '')) ?></td>
        <td><?= htmlspecialchars($sig['who'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </details>
  <?php endif; ?>
</div>

<!-- Billing -->
<div class="section">
  <h2>💳 Billing</h2>

  <?php
  // Calculate what the next invoice would look like
  $pricing      = $pricing ?? loadPricing();
  $previewItems = buildLineItems($bldCfg, $pricing);
  $previewTotal = array_sum(array_column($previewItems, 'amount'));
  ?>

  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>" style="margin-bottom:1.25rem;">
    <input type="hidden" name="action" value="save_billing_config">
    <div class="form-row">
      <div>
        <label>Renewal date</label>
        <input type="date" name="renewal_date"
               value="<?= htmlspecialchars($bldCfg['renewalDate'] ?? '') ?>">
      </div>
      <div>
        <label>Discount %</label>
        <input type="number" name="discount_pct" min="0" max="100" step="0.01"
               value="<?= htmlspecialchars((string)($bldCfg['discountPct'] ?? '')) ?>"
               placeholder="0" style="width:90px;">
      </div>
    </div>
    <button type="submit" class="btn">Save Billing Settings</button>
  </form>

  <div style="margin-bottom:1rem;">
    <div class="stat-label" style="margin-bottom:0.4rem;">Next invoice preview</div>
    <table style="max-width:380px;margin-bottom:0.5rem;">
      <?php foreach ($previewItems as $item): ?>
      <tr>
        <td style="padding:0.25rem 0.5rem 0.25rem 0;font-size:0.875rem;color:#555;"><?= htmlspecialchars($item['description']) ?></td>
        <td style="padding:0.25rem 0;font-size:0.875rem;text-align:right;white-space:nowrap;">
          <?= $item['amount'] >= 0 ? '$' . number_format($item['amount'], 2) : '-$' . number_format(abs($item['amount']), 2) ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr style="border-top:1px solid #ddd;">
        <td style="padding:0.35rem 0.5rem 0 0;font-weight:bold;font-size:0.875rem;">Total</td>
        <td style="padding:0.35rem 0 0;font-weight:bold;font-size:0.875rem;text-align:right;">$<?= number_format($previewTotal, 2) ?></td>
      </tr>
    </table>
    <?php if (!$previewItems): ?>
      <p class="hint">No pricing configured. Set site fee in <a href="pricing-admin.php" style="color:#0070f3;">Pricing</a>.</p>
    <?php endif; ?>
  </div>

  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>"
        onsubmit="return confirm('Generate and email this invoice to <?= htmlspecialchars($bldCfg['contactEmail'] ?? 'the contact email') ?>?');">
    <input type="hidden" name="action" value="generate_invoice">
    <button type="submit" class="btn" <?= empty($bldCfg['contactEmail']) ? 'disabled title="Set a contact email first"' : '' ?>>
      Generate &amp; Email Invoice
    </button>
    <?php if (empty($bldCfg['contactEmail'])): ?>
      <span style="font-size:0.8rem;color:#c00;margin-left:0.5rem;">No contact email set</span>
    <?php endif; ?>
  </form>

  <hr>
  <div style="font-size:0.875rem;font-weight:bold;margin-bottom:0.5rem;">One-off invoice</div>
  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>"
        onsubmit="return confirm('Create and email a one-off invoice to <?= htmlspecialchars($bldCfg['contactEmail'] ?? 'the contact email') ?>?');"
        style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="action" value="generate_other_invoice">
    <div>
      <label style="display:block;font-size:0.82rem;font-weight:bold;margin-bottom:0.25rem;">Description</label>
      <input type="text" name="other_description" placeholder="e.g. Setup fee" style="width:240px;">
    </div>
    <div>
      <label style="display:block;font-size:0.82rem;font-weight:bold;margin-bottom:0.25rem;">Amount ($)</label>
      <input type="number" name="other_amount" min="0.01" step="0.01" placeholder="0.00" style="width:100px;">
    </div>
    <button type="submit" class="btn" <?= empty($bldCfg['contactEmail']) ? 'disabled title="Set a contact email first"' : '' ?>>
      Create &amp; Send
    </button>
  </form>

  <?php if ($invoices): ?>
  <hr>
  <div style="font-size:0.875rem;font-weight:bold;margin-bottom:0.5rem;">Invoice history</div>
  <table>
    <thead>
      <tr>
        <th>Invoice</th>
        <th>Date</th>
        <th style="text-align:right;">Amount</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($invoices as $inv): ?>
      <tr>
        <td>
          <a href="invoice-view.php?<?= htmlspecialchars(http_build_query(['building' => $buildingKey, 'invoice' => $inv['id']])) ?>"
             target="_blank" style="font-size:0.82rem;font-family:monospace;color:#0070f3;text-decoration:none;">
            <?= htmlspecialchars($inv['id']) ?>
          </a>
        </td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($inv['date']) ?></td>
        <td style="text-align:right;">$<?= number_format($inv['total'], 2) ?></td>
        <td>
          <?php if ($inv['status'] === 'paid'): ?>
            <span class="badge ok">✓ Paid <?= htmlspecialchars($inv['paidDate'] ?? '') ?></span>
          <?php else: ?>
            <span class="badge warn">Unpaid</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($inv['status'] !== 'paid'): ?>
            <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>"
                  onsubmit="return confirm('Mark <?= htmlspecialchars($inv['id']) ?> as paid by check? This will apply the appropriate service change and send a receipt.');"
                  style="margin:0;">
              <input type="hidden" name="action" value="mark_paid">
              <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($inv['id']) ?>">
              <input type="hidden" name="payment_method" value="check">
              <button type="submit" class="btn btn-gray" style="font-size:0.8rem;padding:0.2rem 0.6rem;">Mark Paid</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!empty($bldCfg['stripeCustomerId'])): ?>
    <p style="font-size:0.875rem;margin-top:0.75rem;">Stripe customer ID: <code><?= htmlspecialchars($bldCfg['stripeCustomerId']) ?></code></p>
  <?php endif; ?>

  <hr style="margin:1.25rem 0;">
  <div style="font-size:0.8rem;color:#888;margin-bottom:0.5rem;">Testing</div>
  <form method="post" action="building-detail.php?building=<?= urlencode($buildingKey) ?>"
        onsubmit="return confirm('Clear storageLimitEmailSent and woolsyBillingEmailSent flags for <?= htmlspecialchars($buildingKey) ?>?');">
    <input type="hidden" name="action" value="reset_billing_flags">
    <button type="submit" class="btn btn-gray" style="font-size:0.82rem;">Reset billing email flags</button>
  </form>
</div>

<?php endif; // isNew ?>


</body>
</html>
