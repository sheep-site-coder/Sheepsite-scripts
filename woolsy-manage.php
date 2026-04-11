<?php
// -------------------------------------------------------
// woolsy-manage.php
// Woolsy management dashboard for building admins.
//
//   https://sheepsite.com/Scripts/woolsy-manage.php?building=X
//
// Auth: reuses manage_auth_{building} session from admin.php.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('FAQS_DIR',        __DIR__ . '/faqs/');
define('CREDITS_FILE',    FAQS_DIR . 'woolsy_credits.json');
define('USAGE_FILE',      FAQS_DIR . 'woolsy_usage.json');
define('CREDITS_DEFAULT_ALLOCATED', 1.0);
define('PROMPT_VERSION', 4);
require_once __DIR__ . '/storage/r2-storage.php';

// -------------------------------------------------------
// Validate building + auth
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)) {
    die('<p style="color:red;">Invalid or missing building name.</p>');
}

$adminCredFile = CREDENTIALS_DIR . $building . '_admin.json';
if (!file_exists($adminCredFile)) {
    header('Location: admin.php?building=' . urlencode($building));
    exit;
}

$sessionKey = 'manage_auth_' . $building;
if (empty($_SESSION[$sessionKey])) {
    header('Location: admin.php?building=' . urlencode($building));
    exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function getRulesVersion(string $file): int {
    if (!file_exists($file)) return 0;
    $fh   = fopen($file, 'r');
    $line = fgets($fh);
    fclose($fh);
    if (preg_match('/woolsy_prompt_version:\s*(\d+)/', $line, $m)) return (int)$m[1];
    return 1;
}

// List all PDFs in the building's public R2 tree.
// Returns [{id, name, folder, size}] where id is the full R2 key.
function _wm_listPdfs(string $building): array {
    $cfg    = _r2Cfg();
    $prefix = $building . '/public/';
    $files  = [];
    $token  = null;
    do {
        $q = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
        if ($token) $q['continuation-token'] = $token;
        [$status, $body] = _r2Request('GET', '/' . $cfg['bucket'], $q);
        if ($status !== 200) break;
        $sx = @simplexml_load_string($body);
        if (!$sx) break;
        foreach ($sx->Contents as $obj) {
            $key  = (string)$obj->Key;
            $size = (int)(string)$obj->Size;
            $name = basename($key);
            if ($name === '.keep' || str_ends_with($key, '/')) continue;
            if (!str_ends_with(strtolower($name), '.pdf')) continue;
            $rel    = substr($key, strlen($prefix));
            $parts  = explode('/', $rel);
            $folder = count($parts) > 1 ? implode('/', array_slice($parts, 0, -1)) : '';
            $files[] = ['id' => $key, 'name' => $name, 'folder' => $folder, 'size' => $size];
        }
        $token = (string)($sx->NextContinuationToken ?? '');
    } while ($token !== '');
    return $files;
}

// Compare current R2 listing against saved baseline. Returns status array.
function _wm_checkChanges(string $building): array {
    $baselineFile = FAQS_DIR . $building . '_baseline.json';
    if (!file_exists($baselineFile)) return ['notCheckedYet' => true];
    $baseline  = json_decode(file_get_contents($baselineFile), true) ?? [];
    $savedAt   = $baseline['savedAt']  ?? null;
    $baseFiles = $baseline['files']    ?? [];
    $current   = _wm_listPdfs($building);
    $curIndex  = [];
    foreach ($current as $f) { $curIndex[$f['id']] = $f['size']; }
    $changes = [];
    foreach ($curIndex as $key => $size) {
        if (!isset($baseFiles[$key]))        $changes[] = ['name' => basename($key), 'action' => 'added'];
        elseif ($baseFiles[$key] !== $size)  $changes[] = ['name' => basename($key), 'action' => 'modified'];
    }
    foreach ($baseFiles as $key => $size) {
        if (!isset($curIndex[$key]))         $changes[] = ['name' => basename($key), 'action' => 'removed'];
    }
    return ['status' => empty($changes) ? 'ok' : 'changes', 'changes' => $changes, 'checkedAt' => $savedAt];
}

function getCredits(string $building): array {
    if (!file_exists(CREDITS_FILE)) return ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
    $all = json_decode(file_get_contents(CREDITS_FILE), true) ?? [];
    return $all[$building] ?? ['allocated' => CREDITS_DEFAULT_ALLOCATED, 'used' => 0];
}

function getUsage(string $building): array {
    if (!file_exists(USAGE_FILE)) return [];
    $all = json_decode(file_get_contents(USAGE_FILE), true) ?? [];
    return $all[$building] ?? [];
}

// -------------------------------------------------------
// AJAX: loadFaq / saveFaq / docStatus / docCheck / buildDocIndex
// -------------------------------------------------------
$action = $_GET['action'] ?? '';

if ($action === 'loadFaq') {
    header('Content-Type: application/json');
    $file = FAQS_DIR . $building . '.txt';
    echo json_encode(['content' => file_exists($file) ? file_get_contents($file) : '']);
    exit;
}

if ($action === 'saveFaq' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $content = $body['content'] ?? '';
    $file    = FAQS_DIR . $building . '.txt';
    echo file_put_contents($file, $content) !== false
        ? json_encode(['ok' => true])
        : json_encode(['error' => 'Could not save file. Check faqs/ folder is writable.']);
    exit;
}

if (in_array($action, ['docStatus', 'docCheck'], true)) {
    header('Content-Type: application/json');
    $rulesFile    = FAQS_DIR . $building . '_rules.md';
    if (!file_exists($rulesFile)) {
        echo json_encode(['notInitialized' => true]);
        exit;
    }
    $result = _wm_checkChanges($building);
    echo json_encode($result);
    exit;
}

if ($action === 'buildDocIndex') {
    header('Content-Type: application/json');
    $files = _wm_listPdfs($building);
    if (empty($files)) {
        echo json_encode(['error' => 'No PDF files found in the public folder.']);
        exit;
    }
    $byFolder = [];
    foreach ($files as $f) {
        $folder = $f['folder'] ?: '(root)';
        $byFolder[$folder][] = $f['name'];
    }
    ksort($byFolder);
    $lines = ["DOCUMENT INDEX — {$building}", "Generated: " . date('F j, Y'), "", "PUBLIC DOCUMENTS", "================", ""];
    foreach ($byFolder as $folder => $names) {
        $lines[] = $folder . '/';
        foreach ($names as $n) { $lines[] = "  \u{2022} " . $n; }
        $lines[] = '';
    }
    file_put_contents(FAQS_DIR . $building . '_docindex.txt', implode("\n", $lines));
    echo json_encode(['ok' => true, 'generated' => date('F j, Y'), 'sectionCount' => count($byFolder)]);
    exit;
}

// -------------------------------------------------------
// Page data
// -------------------------------------------------------
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));
$rulesFile      = FAQS_DIR . $building . '_rules.md';
$rulesVersion   = getRulesVersion($rulesFile);
$promptOutdated = ($rulesVersion > 0 && $rulesVersion < PROMPT_VERSION);

$wc      = getCredits($building);
$wAlloc  = (float)($wc['allocated'] ?? CREDITS_DEFAULT_ALLOCATED);
$wUsed   = (float)($wc['used']      ?? 0);
$wRemain = max(0, $wAlloc - $wUsed);
$wPct    = $wAlloc > 0 ? min(100, round($wUsed / $wAlloc * 100)) : 100;

$docIndexFile   = FAQS_DIR . $building . '_docindex.txt';
$docIndexExists = file_exists($docIndexFile);
$docIndexDate   = $docIndexExists ? date('F j, Y', filemtime($docIndexFile)) : '';

// Usage stats: last 12 months + rolling year total
$usageData  = getUsage($building);
$thisMonth  = date('Y-m');
$thisYear   = date('Y');
$months     = [];
for ($i = 11; $i >= 0; $i--) {
    $key      = date('Y-m', strtotime("-{$i} months"));
    $months[] = ['key' => $key, 'label' => date('M Y', strtotime($key . '-01')), 'count' => $usageData[$key] ?? 0];
}
$yearTotal   = array_sum(array_filter($usageData, fn($k) => str_starts_with($k, $thisYear), ARRAY_FILTER_USE_KEY));
$monthTotal  = $usageData[$thisMonth] ?? 0;
$allTimeTotal = array_sum($usageData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Woolsy Management</title>
  <style>
    body             { font-family: sans-serif; max-width: 760px; margin: 3rem auto; padding: 0 1rem; color: #222; }
    .top-bar         { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.5rem; }
    h1               { margin: 0; font-size: 1.4rem; }
    h2               { font-size: 1.1rem; color: #333; margin: 1.75rem 0 0.5rem; border-bottom: 1px solid #eee; padding-bottom: 0.3rem; }
    .back-link       { font-size: 0.9rem; color: #0070f3; text-decoration: none; white-space: nowrap; }
    .back-link:hover { text-decoration: underline; }
    .section         { margin-bottom: 1.5rem; }
    .status-row      { font-size: 0.9rem; color: #444; margin-bottom: 0.4rem; line-height: 1.6; }
    .ok   { color: #1a7f37; font-weight: bold; }
    .warn { color: #b45309; font-weight: bold; }
    .muted { color: #888; }
    .action-btn      { display: inline-block; padding: 0.4rem 1rem; background: #0070f3; color: #fff;
                       border: none; border-radius: 4px; font-size: 0.875rem; cursor: pointer; text-decoration: none; }
    .action-btn:hover { background: #005bb5; }
    .small-btn       { padding: 0.25rem 0.65rem; font-size: 0.8rem; background: #f3f4f6; color: #333;
                       border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }
    .small-btn:hover { background: #e5e7eb; }
    .warn-banner     { padding: 0.65rem 0.9rem; background: #fffbeb; border: 1px solid #f59e0b;
                       border-radius: 4px; font-size: 0.875rem; color: #92400e; margin-bottom: 1rem; }
    .credit-bar-wrap { background: #eee; border-radius: 4px; height: 8px; margin: 0.4rem 0; max-width: 360px; }
    .credit-fill     { height: 8px; border-radius: 4px; background: #22c55e; }
    .credit-fill.warn    { background: #f59e0b; }
    .credit-fill.danger  { background: #ef4444; }
    /* Usage table */
    .usage-summary   { display: flex; gap: 2rem; margin-bottom: 1rem; }
    .usage-stat      { text-align: center; }
    .usage-stat .num { font-size: 1.8rem; font-weight: bold; color: #3D0066; line-height: 1; }
    .usage-stat .lbl { font-size: 0.78rem; color: #888; margin-top: 0.2rem; }
    .usage-table     { width: 100%; border-collapse: collapse; font-size: 0.875rem; max-width: 480px; }
    .usage-table th  { text-align: left; border-bottom: 2px solid #ddd; padding: 0.3rem 0.5rem; color: #555; }
    .usage-table td  { padding: 0.3rem 0.5rem; border-bottom: 1px solid #f0f0f0; }
    .usage-bar-cell  { width: 140px; }
    .usage-bar       { height: 10px; border-radius: 3px; background: #c4b5fd; display: inline-block; min-width: 2px; }
    /* FAQ editor */
    .faq-editor      { margin-top: 0.5rem; }
    .faq-editor textarea { width: 100%; box-sizing: border-box; font-family: monospace; font-size: 0.82rem;
                           line-height: 1.5; border: 1px solid #ccc; border-radius: 4px; padding: 0.5rem;
                           resize: vertical; min-height: 160px; }
    .faq-save-row    { display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; }
    .save-btn        { padding: 0.4rem 1rem; background: #0070f3; color: #fff; border: none;
                       border-radius: 4px; font-size: 0.875rem; cursor: pointer; }
    .save-btn:hover  { background: #005bb5; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1><img src="https://sheepsite.com/Scripts/assets/Woolsy-standing-transparent.png" height="44" alt="Woolsy" style="vertical-align:middle;margin-right:0.4rem;"> <?= htmlspecialchars($buildLabel) ?> – Woolsy Management</h1>
  <a href="admin.php?building=<?= urlencode($building) ?>" class="back-link">← Admin</a>
</div>

<?php if ($promptOutdated): ?>
<div class="warn-banner">
  ⚠️ Woolsy prompt updated (v<?= PROMPT_VERSION ?>) — a rebuild is recommended to cover new topic categories.
  <a href="woolsy-update.php?building=<?= urlencode($building) ?>" style="color:#92400e;font-weight:600;">Rebuild now →</a>
</div>
<?php endif; ?>

<!-- ===== Knowledge Base ===== -->
<h2>Knowledge Base</h2>
<div class="section">
  <div class="status-row" id="kb-status"><span class="muted">Checking…</span></div>
  <div class="status-row">
    📄 <strong>Document Index:</strong>
    <?php if ($docIndexExists): ?>
      Built <?= htmlspecialchars($docIndexDate) ?>
      &nbsp;<button class="small-btn" id="build-index-btn" onclick="buildDocIndex()">Rebuild</button>
    <?php else: ?>
      <span class="warn">Not built</span>
      &nbsp;<button class="small-btn" id="build-index-btn" onclick="buildDocIndex()">Build Index</button>
    <?php endif; ?>
    <span id="index-status-msg"></span>
  </div>
</div>

<!-- ===== Usage Statistics ===== -->
<h2>Usage Statistics</h2>
<div class="section">
  <div class="usage-summary">
    <div class="usage-stat">
      <div class="num"><?= number_format($monthTotal) ?></div>
      <div class="lbl">This month</div>
    </div>
    <div class="usage-stat">
      <div class="num"><?= number_format($yearTotal) ?></div>
      <div class="lbl"><?= $thisYear ?> total</div>
    </div>
    <div class="usage-stat">
      <div class="num"><?= number_format($allTimeTotal) ?></div>
      <div class="lbl">All time</div>
    </div>
  </div>
  <?php
  $maxCount = max(1, max(array_column($months, 'count')));
  ?>
  <table class="usage-table">
    <thead><tr><th>Month</th><th>Questions</th><th class="usage-bar-cell"></th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($months) as $m): ?>
      <tr>
        <td><?= htmlspecialchars($m['label']) ?><?= $m['key'] === $thisMonth ? ' <span class="muted">(current)</span>' : '' ?></td>
        <td><?= $m['count'] ?></td>
        <td><span class="usage-bar" style="width:<?= round($m['count'] / $maxCount * 130) ?>px"></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ===== Credit Usage ===== -->
<h2>Credit Usage</h2>
<div class="section">
  <div class="status-row">
    Used <strong><?= number_format($wUsed, 4) ?></strong> of <strong><?= number_format($wAlloc, 2) ?></strong> credits
    &nbsp;(<?= number_format($wRemain, 4) ?> remaining)
  </div>
  <div class="credit-bar-wrap">
    <div class="credit-fill <?= $wPct >= 100 ? 'danger' : ($wPct >= 80 ? 'warn' : '') ?>" style="width:<?= $wPct ?>%"></div>
  </div>
  <?php if ($wPct >= 100): ?>
    <div class="warn-banner" style="margin-top:0.5rem;">⛔ Credits exhausted — Woolsy is unavailable to residents. Contact SheepSite to top up.</div>
  <?php elseif ($wPct >= 80): ?>
    <div class="warn-banner" style="margin-top:0.5rem;">⚠️ Running low on credits. Contact SheepSite to add more.</div>
  <?php endif; ?>
</div>

<!-- ===== Building FAQ ===== -->
<h2>Building FAQ</h2>
<div class="section">
  <p style="font-size:0.875rem;color:#555;margin:0 0 0.6rem;">
    Add building-specific facts, correct Woolsy answers, or exact URLs here.
    This text is injected into Woolsy's context on every request — changes take effect immediately, no rebuild needed.
  </p>
  <div class="faq-editor">
    <textarea id="faq-textarea" placeholder="e.g.&#10;Board of Directors&#10;The current Board of Directors list is at: https://sheepsite.com/Scripts/public-report.php?building=<?= urlencode($building) ?>&page=board&#10;&#10;Property Manager&#10;For maintenance requests contact the property manager at ..."></textarea>
    <div class="faq-save-row">
      <button class="save-btn" onclick="saveFaq()">Save</button>
      <span id="faq-save-msg" style="font-size:0.85rem;"></span>
    </div>
  </div>
</div>

<script>
const BUILDING = <?= json_encode($building) ?>;
const BASE_URL = 'woolsy-manage.php?building=' + encodeURIComponent(BUILDING);

document.addEventListener('DOMContentLoaded', function() {
  loadKbStatus();
  loadFaqContent();
});

// ---- Knowledge Base status ----
function loadKbStatus() {
  fetch(BASE_URL + '&action=docStatus')
    .then(r => r.json()).then(renderKbStatus)
    .catch(() => { document.getElementById('kb-status').innerHTML = '<span class="muted">Status unavailable.</span>'; });
}

function renderKbStatus(data) {
  const el        = document.getElementById('kb-status');
  const updateUrl = 'woolsy-update.php?building=' + encodeURIComponent(BUILDING);

  if (data.error || data.notInitialized) {
    el.innerHTML = '📋 <strong>Knowledge Base:</strong> Not set up — <a href="' + updateUrl + '" style="color:#0070f3">Set Up Woolsy →</a>';
    return;
  }
  if (data.notCheckedYet) {
    el.innerHTML = '📋 <strong>Knowledge Base:</strong> Initialized — <a href="' + updateUrl + '" style="color:#0070f3">Manage →</a>';
    return;
  }
  const checked = data.checkedAt ? 'Checked ' + fmtDate(data.checkedAt) : '';
  const total   = data.fileCounts ? (data.fileCounts.IncorporationDocs + data.fileCounts.RulesDocs) + ' files' : '';
  if (data.status === 'changes') {
    const n = (data.changes || []).length;
    el.innerHTML = '📋 <strong>Knowledge Base:</strong> <span class="warn">⚠️ ' + n + ' file' + (n !== 1 ? 's' : '') + ' changed</span>' +
      (checked ? ' · ' + checked : '') + ' — <a href="' + updateUrl + '" style="color:#0070f3">Review &amp; Update →</a>';
  } else {
    el.innerHTML = '📋 <strong>Knowledge Base:</strong> <span class="ok">✅ Up to date</span>' +
      (checked ? ' · ' + checked : '') + (total ? ' · ' + total : '') +
      ' <button class="small-btn" onclick="checkDocNow()">Check now</button>';
  }
}

function checkDocNow() {
  document.getElementById('kb-status').innerHTML = '<span class="muted">Checking…</span>';
  fetch(BASE_URL + '&action=docCheck')
    .then(r => r.json()).then(renderKbStatus)
    .catch(() => { document.getElementById('kb-status').innerHTML = '<span class="muted">Check failed.</span>'; });
}

// ---- Document index ----
function buildDocIndex() {
  const btn = document.getElementById('build-index-btn');
  const msg = document.getElementById('index-status-msg');
  if (btn) btn.disabled = true;
  if (msg) { msg.style.color = '#888'; msg.textContent = ' Building…'; }
  fetch(BASE_URL + '&action=buildDocIndex')
    .then(r => r.json())
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

// ---- Building FAQ ----
function loadFaqContent() {
  fetch(BASE_URL + '&action=loadFaq')
    .then(r => r.json())
    .then(data => { document.getElementById('faq-textarea').value = data.content || ''; })
    .catch(() => {});
}

function saveFaq() {
  const content = document.getElementById('faq-textarea').value;
  const msg     = document.getElementById('faq-save-msg');
  msg.style.color = '#888'; msg.textContent = 'Saving…';
  fetch(BASE_URL + '&action=saveFaq', {
    method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ content: content })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) { msg.style.color = '#1a7f37'; msg.textContent = '✅ Saved'; }
    else         { msg.style.color = '#c00';     msg.textContent = '⚠️ ' + (data.error || 'Error'); }
  })
  .catch(() => { msg.style.color = '#c00'; msg.textContent = '⚠️ Request failed'; });
}

function fmtDate(iso) {
  try { return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
  catch(e) { return iso; }
}
</script>
</body>
</html>
