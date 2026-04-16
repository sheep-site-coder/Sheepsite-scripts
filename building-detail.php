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

// -------------------------------------------------------
// POST — provision a new building
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isNew) {
  $newKey      = trim($_POST['building_key'] ?? '');
  $displayName = trim($_POST['display_name'] ?? '');
  $state       = strtoupper(trim($_POST['state'] ?? 'FL'));
  $community   = trim($_POST['community'] ?? '');

  if (!$newKey || !preg_match('/^[a-zA-Z0-9_-]+$/', $newKey)) {
    $message     = 'Invalid building key — use letters, numbers, hyphens and underscores only.';
    $messageType = 'error';
  } elseif (isset($buildings[$newKey])) {
    $message     = 'Key "' . htmlspecialchars($newKey) . '" already exists in buildings.php.';
    $messageType = 'error';
  } else {
    $provisionErrors = [];

    // 1 — Resident credentials file (empty accounts array)
    $resFile = CREDENTIALS_DIR . $newKey . '.json';
    if (!file_exists($resFile)) {
      if (file_put_contents($resFile, '[]') === false) $provisionErrors[] = 'Could not create credentials/' . $newKey . '.json';
    }

    // 2 — Admin credentials file (default admin/admin, mustChange)
    $admFile = CREDENTIALS_DIR . $newKey . '_admin.json';
    if (!file_exists($admFile)) {
      $defaultAdmin = [[
        'user'       => 'admin',
        'pass'       => password_hash('admin', PASSWORD_BCRYPT),
        'email'      => '',
        'mustChange' => true,
      ]];
      if (file_put_contents($admFile, json_encode($defaultAdmin, JSON_PRETTY_PRINT)) === false) {
        $provisionErrors[] = 'Could not create credentials/' . $newKey . '_admin.json';
      }
    }

    // 3 — Building config file
    $cfgFile = CONFIG_DIR . $newKey . '.json';
    if (!file_exists($cfgFile)) {
      $pricing      = loadPricing();
      $defaultLimit = (int)($pricing['storageDefaultLimit'] ?? 10737418240);
      $newCfg = [
        'displayName'  => $displayName ?: $newKey,
        'siteURL'      => '',
        'contactEmail' => '',
        'storageLimit' => $defaultLimit,
        'storageUsed'  => 0,
      ];
      if (file_put_contents($cfgFile, json_encode($newCfg, JSON_PRETTY_PRINT)) === false) {
        $provisionErrors[] = 'Could not create config/' . $newKey . '.json';
      }
    }

    // 4 — Woolsy credits (1 credit teaser)
    $allCreds = loadCredits();
    if (!isset($allCreds[$newKey])) {
      $allCreds[$newKey] = ['allocated' => 1.0, 'used' => 0.0];
      saveCredits($allCreds);
    }

    // 5 — Copy R2 template tree
    require_once __DIR__ . '/storage/r2-storage.php';
    $r2result = _r2CopyTree('_template/', $newKey . '/');

    // Redirect to post-provision checklist
    $params = http_build_query([
      'building'    => 'new',
      'provisioned' => $newKey,
      'displayName' => $displayName,
      'state'       => $state,
      'community'   => $community,
      'r2copied'    => $r2result['copied'],
      'r2errors'    => count($r2result['errors']),
      'errors'      => implode('|', $provisionErrors),
    ]);
    header('Location: building-detail.php?' . $params);
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isNew) {

  $action = $_POST['action'] ?? '';

  // ---- Save configuration ----
  if ($action === 'save_config') {
    $cfg = loadBuildingConfig($buildingKey);
    $cfg['siteURL']   = rtrim(trim($_POST['site_url'] ?? ''), '/');
    $cfg['hasDomain'] = isset($_POST['has_domain']);
    $cfg['testSite']  = isset($_POST['test_site']);
    saveBuildingConfig($buildingKey, $cfg);
    $message = 'Configuration saved.';
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
  $defaultLimit= (int)($pricing['storageDefaultLimit'] ?? 10737418240);

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

<?php
$provisioned    = trim($_GET['provisioned']    ?? '');
$provKey        = $provisioned ?: '';
$provDisplay    = trim($_GET['displayName']    ?? $provKey);
$provState      = trim($_GET['state']          ?? 'FL');
$provCommunity  = trim($_GET['community']      ?? '');
$provR2Copied   = (int)($_GET['r2copied']      ?? -1);
$provR2Errors   = (int)($_GET['r2errors']      ?? 0);
$provFileErrors = array_filter(explode('|', $_GET['errors'] ?? ''));

if ($isNew):
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
  .nb-create-btn:hover  { background:#134520; }
  .nb-create-btn:disabled { background:#999; cursor:default; }
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
  .cl-done-row    { background:#f0faf0; border:1px solid #a0d0a0; border-radius:6px;
                    padding:0.75rem 1rem; font-size:0.88rem; color:#1a5a1a; margin-bottom:1.25rem; }
  .cl-done-row ul { margin:0.4rem 0 0 1rem; padding:0; }
  .cl-done-row li { margin:0.15rem 0; }
  .cl-warn-row    { background:#fff8e8; border:1px solid #f0c060; border-radius:6px;
                    padding:0.75rem 1rem; font-size:0.88rem; color:#7a4a00; margin-bottom:1.25rem; }
  .cl-check       { margin-top:0.6rem; display:flex; align-items:center; gap:0.4rem;
                    font-size:0.82rem; color:#888; }
  .cl-check input { cursor:pointer; width:15px; height:15px; }
</style>

<?php if (!$provisioned): ?>
<!-- ── Phase 1: Identity form ── -->
<div class="nb-phase">
  <h3>New Association Setup</h3>
  <form method="post" id="provision-form">
    <input type="hidden" name="action" value="provision">
    <div class="nb-row">
      <div class="nb-field">
        <label>Building key <span style="color:red">*</span></label>
        <input type="text" name="building_key" id="nb_key" placeholder="e.g. LyndhurstJ" style="width:160px;"
               value="<?= htmlspecialchars($_POST['building_key'] ?? '') ?>" oninput="nbValidate()">
        <span class="nb-hint">Letters, numbers, hyphens. Used in URLs and filenames.</span>
      </div>
      <div class="nb-field">
        <label>Display name</label>
        <input type="text" name="display_name" placeholder="e.g. Lyndhurst J" style="width:180px;"
               value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
      </div>
      <div class="nb-field">
        <label>State</label>
        <input type="text" name="state" value="<?= htmlspecialchars($_POST['state'] ?? 'FL') ?>" style="width:50px;">
      </div>
      <div class="nb-field">
        <label>Community</label>
        <input type="text" name="community" placeholder="e.g. CVE (optional)" style="width:150px;"
               value="<?= htmlspecialchars($_POST['community'] ?? '') ?>">
      </div>
    </div>
    <button type="submit" class="nb-create-btn" id="nb_create_btn"
            <?= empty($_POST['building_key']) ? 'disabled' : '' ?>>Create Association</button>
  </form>
</div>

<script>
function nbValidate() {
  var key = document.getElementById('nb_key').value.trim();
  document.getElementById('nb_create_btn').disabled = !key;
}
</script>

<?php else:
  // Phase 2 — post-provision checklist
  $bldSnippet  = "  '" . $provKey . "' => [\n    'state'     => '" . $provState . "',";
  if ($provCommunity) $bldSnippet .= "\n    'community' => '" . $provCommunity . "',";
  $bldSnippet .= "\n  ],";

  $indexPhp = "<?php\ndefine('BUILDING', '" . $provKey . "');\nrequire '../Scripts/building-site.php';";

  $adminUrl = 'https://sheepsite.com/Scripts/admin.php?building=' . urlencode($provKey);
?>

<!-- ── Phase 2: Post-provision checklist ── -->
<div class="cl-done-row">
  <strong>&#10003; Association provisioned: <?= htmlspecialchars($provKey) ?></strong>
  <ul>
    <li>credentials/<?= htmlspecialchars($provKey) ?>.json — created (empty resident accounts)</li>
    <li>credentials/<?= htmlspecialchars($provKey) ?>_admin.json — created (admin / admin, mustChange)</li>
    <li>config/<?= htmlspecialchars($provKey) ?>.json — created (defaults: 10 GB storage, empty siteURL/contactEmail)</li>
    <li>Woolsy credits — initialized (1 credit)</li>
    <?php if ($provR2Copied >= 0): ?>
      <li>R2 template tree — <?= $provR2Copied ?> file(s) copied to <?= htmlspecialchars($provKey) ?>/<?= $provR2Errors > 0 ? ' <strong style="color:#c00;">(' . $provR2Errors . ' error(s) — check R2 dashboard)</strong>' : '' ?></li>
    <?php endif; ?>
  </ul>
</div>

<?php if ($provFileErrors): ?>
<div class="cl-warn-row">
  <strong>Warning — some files could not be created:</strong>
  <ul><?php foreach ($provFileErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  Check that credentials/ and config/ are writable by PHP on the server.
</div>
<?php endif; ?>

<div class="nb-phase">
  <h3>Remaining Setup Steps</h3>
  <p style="font-size:0.88rem;color:#555;margin:0 0 1.25rem;">Complete these in order, then hand off the admin URL to the new association admin.</p>
  <ul class="checklist">

    <!-- Step 1: buildings.php -->
    <li>
      <div class="cl-num">1</div>
      <div class="cl-body">
        <div class="cl-title">Add to buildings.php</div>
        <div class="cl-steps">
          Edit <code>buildings.php</code> in the repo and add this entry, then upload to the server:
          <div class="cl-code" id="cl_snippet"><?= htmlspecialchars($bldSnippet) ?><button class="cl-copy" onclick="copyCode('cl_snippet')">Copy</button></div>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,1)"> Done</label>
      </div>
    </li>

    <!-- Step 2: cPanel subdomain -->
    <li>
      <div class="cl-num">2</div>
      <div class="cl-body">
        <div class="cl-title">Create the Subdomain in cPanel</div>
        <div class="cl-steps">
          In cPanel &rarr; Domains &rarr; Create a subdomain:
          <ol>
            <li>Subdomain: <strong><?= htmlspecialchars($provKey) ?></strong>, Domain: sheepsite.com</li>
            <li>Document root: leave as default (e.g. <code>/home/account/<?= htmlspecialchars(strtolower($provKey)) ?>.sheepsite.com</code>)</li>
            <li>For a custom domain: point its DNS A record to the server IP, then set the document root accordingly</li>
          </ol>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,2)"> Done</label>
      </div>
    </li>

    <!-- Step 3: index.php -->
    <li>
      <div class="cl-num">3</div>
      <div class="cl-body">
        <div class="cl-title">Upload index.php to the Subdomain Root</div>
        <div class="cl-steps">
          Create a file named <code>index.php</code> in the subdomain document root with this content:
          <div class="cl-code" id="cl_index"><?= htmlspecialchars($indexPhp) ?><button class="cl-copy" onclick="copyCode('cl_index')">Copy</button></div>
          <div class="cl-note">Adjust the <code>require</code> path if your Scripts folder is at a different location.</div>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,3)"> Done</label>
      </div>
    </li>

    <!-- Step 4: Hand off to admin -->
    <li>
      <div class="cl-num">4</div>
      <div class="cl-body">
        <div class="cl-title">Hand Off to the Association Admin</div>
        <div class="cl-steps">
          Send the admin the following URL and credentials. Their first login will force a password change and prompt them to set their email address.
          <div class="cl-code" id="cl_admin_url"><?= htmlspecialchars($adminUrl) ?><button class="cl-copy" onclick="copyCode('cl_admin_url')">Copy</button></div>
          <strong>Default credentials:</strong> username <code>admin</code> / password <code>admin</code><br>
          The admin should immediately:
          <ol>
            <li>Set a strong password (required on first login)</li>
            <li>Set their email address in <strong>Settings &rarr; Admin Accounts</strong></li>
            <li>Fill in Building Settings (site URL, contact email, renewal date)</li>
            <li>Upload documents to the public/private folders via <strong>File Management</strong></li>
            <li>Run Woolsy setup once documents are in place</li>
            <li>Add residents via <strong>Manage Residents</strong> and create their web accounts via <strong>Manage Users</strong></li>
          </ol>
        </div>
        <label class="cl-check"><input type="checkbox" onchange="clCheck(this,4)"> Done</label>
      </div>
    </li>

  </ul>
</div>

<script>
function clCheck(cb, num) {
  var el = document.querySelector('.checklist li:nth-child(' + num + ') .cl-num');
  if (el) { el.classList.toggle('done', cb.checked); el.textContent = cb.checked ? '\u2713' : num; }
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

</script>

<?php endif; // !provisioned ?>

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
    <div class="form-row" style="margin-bottom:1rem;">
      <div style="flex:1;min-width:220px;">
        <label>Site URL</label>
        <input type="text" name="site_url" value="<?= htmlspecialchars($bldCfg['siteURL'] ?? '') ?>"
               placeholder="https://sheepsite.com/LyndhurstH" style="width:100%;">
      </div>
      <div style="flex:1;min-width:220px;">
        <label>Billing contact email <small style="font-weight:normal;color:#888;">(set in Association Settings)</small></label>
        <input type="text" value="<?= htmlspecialchars($bldCfg['contactEmail'] ?? '') ?>"
               readonly style="width:100%;background:#f5f5f5;color:#666;cursor:default;">
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
    <summary>buildings.php values</summary>
    <table style="margin-top:0.75rem;">
      <tr><th>Field</th><th>Value</th></tr>
      <tr><td>State</td><td><?= htmlspecialchars($bldBuilding['state'] ?? '—') ?></td></tr>
      <tr><td>Community</td><td><?= htmlspecialchars($bldBuilding['community'] ?? '—') ?></td></tr>
    </table>
    <p style="font-size:0.82rem;color:#888;margin-top:0.5rem;">These fields are set in <code>buildings.php</code> on the server.</p>
  </details>
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
