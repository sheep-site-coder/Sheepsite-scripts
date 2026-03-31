<?php
// -------------------------------------------------------
// building-detail.php
// Per-building management page — operator view.
// Also handles "Add New Building" when ?building=new.
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
// POST handlers
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isNew) {

  $action = $_POST['action'] ?? '';

  // ---- Save configuration ----
  if ($action === 'save_config') {
    $cfg = loadBuildingConfig($buildingKey);
    $cfg['siteURL']      = rtrim(trim($_POST['site_url']      ?? ''), '/');
    $cfg['contactEmail'] = trim($_POST['contact_email'] ?? '');
    $cfg['hasDomain']    = isset($_POST['has_domain']);
    $renewalRaw = trim($_POST['renewal_date'] ?? '');
    if ($renewalRaw) $cfg['renewalDate'] = $renewalRaw;
    elseif (isset($cfg['renewalDate'])) unset($cfg['renewalDate']);
    saveBuildingConfig($buildingKey, $cfg);
    $message = 'Configuration saved.';
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $isNew ? 'Add New Building' : htmlspecialchars($label) . ' — Detail' ?></title>
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
  <h1><?= $isNew ? 'Add New Building' : htmlspecialchars($label) ?></h1>
  <a href="master-admin.php" class="back">← Master Admin</a>
</div>

<?php if ($message): ?>
  <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($isNew): ?>
<!-- ===================== NEW BUILDING SETUP ===================== -->
<div class="section">
  <h2>New Building Setup</h2>
  <p class="hint">Fill in the fields below to generate the <code>buildings.php</code> entry and initial config. You will need to manually add the entry to <code>buildings.php</code> on the server.</p>

  <form id="new-building-form">
    <div class="form-row">
      <div>
        <label>Building key (no spaces)</label>
        <input type="text" id="nb_key" placeholder="e.g. LyndhurstJ" style="width:200px;" oninput="updateSnippet()">
      </div>
      <div>
        <label>State code</label>
        <input type="text" id="nb_state" value="FL" style="width:60px;" oninput="updateSnippet()">
      </div>
      <div>
        <label>Community</label>
        <input type="text" id="nb_community" placeholder="e.g. CVE (optional)" style="width:160px;" oninput="updateSnippet()">
      </div>
    </div>
    <div class="form-row">
      <div style="flex:1;min-width:260px;">
        <label>Public Folder ID</label>
        <input type="text" id="nb_public" placeholder="Google Drive folder ID" style="width:100%;" oninput="updateSnippet()">
      </div>
    </div>
    <div class="form-row">
      <div style="flex:1;min-width:260px;">
        <label>Private Folder ID</label>
        <input type="text" id="nb_private" placeholder="Google Drive folder ID" style="width:100%;" oninput="updateSnippet()">
      </div>
    </div>
    <div class="form-row">
      <div style="flex:1;min-width:260px;">
        <label>Apps Script Web App URL</label>
        <input type="text" id="nb_webapp" placeholder="https://script.google.com/macros/s/.../exec" style="width:100%;" oninput="updateSnippet()">
      </div>
    </div>
  </form>

  <label>buildings.php entry to add:</label>
  <div class="code-block" id="snippet">Fill in the fields above to generate the snippet.</div>

  <p style="font-size:0.85rem;color:#555;margin-top:0.5rem;">
    Copy and paste this entry into <code>buildings.php</code> on the server, then upload the file.
    Once added, the building will appear on the master admin dashboard.
  </p>
</div>

<script>
function updateSnippet() {
  var key       = document.getElementById('nb_key').value.trim();
  var state     = document.getElementById('nb_state').value.trim() || 'FL';
  var community = document.getElementById('nb_community').value.trim();
  var pub       = document.getElementById('nb_public').value.trim();
  var priv      = document.getElementById('nb_private').value.trim();
  var webapp    = document.getElementById('nb_webapp').value.trim();

  if (!key) { document.getElementById('snippet').textContent = 'Fill in the fields above to generate the snippet.'; return; }

  var lines = [
    "  '" + key + "' => [",
    "    'state'           => '" + state + "',",
  ];
  if (community) lines.push("    'community'       => '" + community + "',");
  lines.push("    'publicFolderId'  => '" + pub + "',");
  lines.push("    'privateFolderId' => '" + priv + "',");
  lines.push("    'webAppURL'       => '" + webapp + "',");
  lines.push("  ],");

  document.getElementById('snippet').textContent = lines.join('\n');
}
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
        <label>Contact email</label>
        <input type="email" name="contact_email" value="<?= htmlspecialchars($bldCfg['contactEmail'] ?? '') ?>"
               placeholder="board@example.com" style="width:100%;">
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>Renewal date</label>
        <input type="date" name="renewal_date"
               value="<?= htmlspecialchars($bldCfg['renewalDate'] ?? '') ?>">
      </div>
    </div>
    <div class="checkbox-row">
      <input type="checkbox" id="has_domain" name="has_domain" <?= !empty($bldCfg['hasDomain']) ? 'checked' : '' ?>>
      <label for="has_domain" style="margin:0;font-weight:normal;">This building has its own domain (billed for domain renewal)</label>
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

<!-- Woolsy Credits -->
<div class="section">
  <h2>🐑 Woolsy Credits</h2>
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

<!-- Billing — placeholder -->
<div class="section">
  <h2>💳 Billing &nbsp;<span class="future-tag">Coming soon</span></h2>
  <p class="hint">Stripe subscription management, payment history, and invoicing will appear here once billing is configured.</p>
  <?php if (!empty($bldCfg['stripeCustomerId'])): ?>
    <p style="font-size:0.875rem;">Stripe customer ID: <code><?= htmlspecialchars($bldCfg['stripeCustomerId']) ?></code></p>
  <?php endif; ?>
</div>

<?php endif; // isNew ?>

</body>
</html>
