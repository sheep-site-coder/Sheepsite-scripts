<?php
// -------------------------------------------------------
// tos-admin.php
// Master admin tool for managing the SheepSite Terms of Service.
//
//   https://sheepsite.com/Scripts/tos-admin.php
//
// Requires master credentials (_master.json). Provides:
//   - Scope control (which buildings must accept)
//   - Signature status table
//   - Issue new version (archives old signatures, clears acceptance)
//   - Signature history (append-only archive)
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');

// Reuses master-admin.php session — log in there first
if (empty($_SESSION['master_admin_auth'])) {
  header('Location: master-admin.php');
  exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
$buildings = require __DIR__ . '/buildings.php';

function loadTos(): array {
  $file = CONFIG_DIR . 'tos.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveTos(array $tos): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . 'tos.json', json_encode($tos, JSON_PRETTY_PRINT));
}

function loadBuildingConfig(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveBuildingConfig(string $building, array $config): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . $building . '.json', json_encode($config, JSON_PRETTY_PRINT));
}

function loadSignatures(): array {
  $file = CONFIG_DIR . 'tos_signatures.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function fmtDate(string $iso): string {
  if (!$iso) return '—';
  try {
    $dt = new DateTime($iso);
    return $dt->format('M j, Y g:i A');
  } catch (Exception $e) {
    return htmlspecialchars($iso);
  }
}

// -------------------------------------------------------
// POST handlers (authenticated)
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // ---- Save scope ----
  if (isset($_POST['save_scope'])) {
    $tos = loadTos();
    if (!$tos) {
      $message     = 'No ToS configuration found. Create an initial version first.';
      $messageType = 'error';
    } else {
      $checked = $_POST['scope_buildings'] ?? [];
      $checked = array_filter(array_map('trim', $checked));
      $tos['scope'] = array_values($checked);
      saveTos($tos);
      $message = 'Scope updated.';
    }
  }

  // ---- Enable for all buildings ----
  if (isset($_POST['enable_all'])) {
    $tos = loadTos();
    if (!$tos) {
      $message     = 'No ToS configuration found. Create an initial version first.';
      $messageType = 'error';
    } else {
      $tos['scope'] = 'all';
      saveTos($tos);
      $message = 'ToS is now required for all buildings.';
    }
  }

  // ---- Restrict to list ----
  if (isset($_POST['restrict_scope'])) {
    $tos = loadTos();
    if ($tos) {
      $tos['scope'] = array_keys($buildings); // start with all selected
      saveTos($tos);
      $message = 'Scope changed from "all" to a building list. Uncheck buildings you want to exclude, then save.';
    }
  }

  // ---- Create initial version ----
  if (isset($_POST['create_initial'])) {
    if (file_exists(CONFIG_DIR . 'tos.json')) {
      $message     = 'ToS configuration already exists.';
      $messageType = 'error';
    } else {
      $effDate = trim($_POST['eff_date'] ?? date('Y-m-d'));
      $tos = [
        'version'      => 1,
        'effectiveDate'=> $effDate,
        'documentPath' => 'docs/terms-of-service.html',
        'scope'        => [],
      ];
      saveTos($tos);
      $message = 'Initial ToS version created. Add buildings to scope to enable enforcement.';
    }
  }

  // ---- Issue new version ----
  if (isset($_POST['issue_version'])) {
    $tos = loadTos();
    if (!$tos) {
      $message     = 'No ToS configuration found.';
      $messageType = 'error';
    } else {
      $newVersion = (int)$tos['version'] + 1;
      $effDate    = trim($_POST['new_eff_date'] ?? date('Y-m-d'));

      // Archive current tosAccepted from all building configs
      $sigFile = CONFIG_DIR . 'tos_signatures.json';
      $sigs    = file_exists($sigFile) ? json_decode(file_get_contents($sigFile), true) ?? [] : [];
      $scope   = $tos['scope'];
      $inScope = $scope === 'all' ? array_keys($buildings) : (is_array($scope) ? $scope : []);

      foreach ($inScope as $b) {
        $cfg = loadBuildingConfig($b);
        if (!empty($cfg['tosAccepted'])) {
          $sigs[] = array_merge($cfg['tosAccepted'], ['building' => $b, 'archivedAt' => date('c')]);
          unset($cfg['tosAccepted']);
          saveBuildingConfig($b, $cfg);
        }
      }
      file_put_contents($sigFile, json_encode($sigs, JSON_PRETTY_PRINT));

      $tos['version']       = $newVersion;
      $tos['effectiveDate'] = $effDate;
      saveTos($tos);

      $message = "Version $newVersion issued. All in-scope buildings must re-accept before their next login.";
    }
  }

  header('Location: tos-admin.php?' . http_build_query(['msg' => $message, 'type' => $messageType]));
  exit;
}

// Carry flash message from redirect
if (empty($message) && isset($_GET['msg'])) {
  $message     = $_GET['msg'];
  $messageType = $_GET['type'] ?? 'ok';
}

// -------------------------------------------------------
// Render
// -------------------------------------------------------
$tos       = loadTos();
$sigs      = loadSignatures();
$tosExists = !empty($tos);
$tosVersion= (int)($tos['version'] ?? 0);
$scope     = $tos['scope'] ?? [];
$scopeAll  = $scope === 'all';
$scopeList = $scopeAll ? array_keys($buildings) : (is_array($scope) ? $scope : []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ToS Admin</title>
  <style>
    * { box-sizing: border-box; }
    body       { font-family: sans-serif; max-width: 820px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar   { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1         { margin: 0; font-size: 1.5rem; }
    .logout    { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .logout:hover { text-decoration: underline; }
    .section   { border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; }
    .section h2 { margin: 0 0 1rem; font-size: 1rem; }
    .message   { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.25rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    label      { font-size: 0.9rem; font-weight: bold; display: block; margin-bottom: 0.25rem; }
    input[type=text], input[type=date] {
      padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px;
      font-size: 0.9rem; width: 220px; }
    .btn       { padding: 0.45rem 1.1rem; background: #0070f3; color: #fff; border: none;
                 border-radius: 4px; font-size: 0.875rem; cursor: pointer; }
    .btn:hover { background: #005bb5; }
    .btn-red   { background: #c00; }
    .btn-red:hover { background: #900; }
    .btn-gray  { background: #fff; color: #333; border: 1px solid #ccc; }
    .btn-gray:hover { background: #f5f5f5; }
    table      { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    th         { text-align: left; padding: 0.5rem 0.75rem; background: #f5f5f5;
                 border-bottom: 2px solid #ddd; color: #555; font-size: 0.78rem;
                 text-transform: uppercase; letter-spacing: 0.04em; }
    td         { padding: 0.55rem 0.75rem; border-bottom: 1px solid #eee; vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    .badge     { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 10px;
                 font-size: 0.78rem; font-weight: 600; }
    .badge.ok  { background: #e6f4ea; color: #1a7f37; }
    .badge.pending { background: #fef3c7; color: #92400e; }
    .badge.none    { background: #f3f4f6; color: #888; }
    .scope-checks  { display: flex; flex-wrap: wrap; gap: 0.75rem 2rem; margin-bottom: 1rem; }
    .scope-checks label { font-weight: normal; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; }
    details    { margin-top: 0.5rem; }
    summary    { cursor: pointer; font-size: 0.9rem; color: #0070f3; margin-bottom: 0.75rem; }
    .meta-row  { display: flex; gap: 2rem; font-size: 0.875rem; margin-bottom: 0.75rem; }
    .meta-row span { color: #555; }
    .meta-row strong { color: #222; }
    .form-row  { display: flex; align-items: flex-end; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
    .warn-box  { background: #fffbeb; border: 1px solid #f59e0b; border-radius: 6px;
                 padding: 0.75rem 1rem; font-size: 0.875rem; color: #92400e; margin-bottom: 0.75rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1>License Agreement Admin</h1>
  <a href="master-admin.php" class="logout">← Master Admin</a>
</div>

<?php if ($message): ?>
  <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if (!$tosExists): ?>
<!-- ===================== INITIAL SETUP ===================== -->
<div class="section">
  <h2>Initial Setup</h2>
  <p style="font-size:0.9rem;color:#555;margin:0 0 1rem;">
    No Terms of Service configuration exists yet. Create the initial version to begin enforcement.
    The ToS document is already at <code>docs/terms-of-service.html</code>.
  </p>
  <form method="post">
    <div class="form-row">
      <div>
        <label>Effective Date</label>
        <input type="date" name="eff_date" value="<?= date('Y-m-d') ?>">
      </div>
      <button type="submit" name="create_initial" class="btn">Create Version 1</button>
    </div>
  </form>
</div>

<?php else: ?>

<!-- ===================== CURRENT VERSION ===================== -->
<div class="section">
  <h2>Current Version</h2>
  <div class="meta-row">
    <span><strong>Version <?= $tosVersion ?></strong></span>
    <span>Effective: <strong><?= htmlspecialchars($tos['effectiveDate'] ?? '—') ?></strong></span>
    <span>Document: <a href="<?= htmlspecialchars($tos['documentPath'] ?? '') ?>" target="_blank" style="color:#0070f3;">View ToS ↗</a></span>
  </div>
</div>

<!-- ===================== SCOPE ===================== -->
<div class="section">
  <h2>Enrollment (Scope)</h2>
  <?php if ($scopeAll): ?>
    <p style="font-size:0.9rem;margin:0 0 0.75rem;">
      <strong>All buildings</strong> are required to accept the Terms of Service.
    </p>
    <form method="post" style="display:inline;">
      <button type="submit" name="restrict_scope" class="btn btn-gray">Change to per-building list</button>
    </form>
  <?php else: ?>
    <p style="font-size:0.9rem;color:#555;margin:0 0 0.75rem;">
      Check the buildings that must accept the ToS. Unchecked buildings skip the acceptance gate.
    </p>
    <form method="post">
      <div class="scope-checks">
        <?php foreach ($buildings as $b => $cfg): ?>
          <label>
            <input type="checkbox" name="scope_buildings[]" value="<?= htmlspecialchars($b) ?>"
              <?= in_array($b, $scopeList) ? 'checked' : '' ?>>
            <?= htmlspecialchars(ucwords(str_replace(['_','-'], ' ', $b))) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:0.75rem;align-items:center;">
        <button type="submit" name="save_scope" class="btn">Save scope</button>
        <button type="submit" name="enable_all" class="btn" style="background:#1a7f37;">Enable for all buildings</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- ===================== SIGNATURE STATUS ===================== -->
<div class="section">
  <h2>Signature Status</h2>
  <?php if (empty($scopeList)): ?>
    <p style="font-size:0.9rem;color:#888;font-style:italic;">No buildings are currently enrolled.</p>
  <?php else: ?>
  <table>
    <tr>
      <th>Building</th>
      <th>Status</th>
      <th>Signed By</th>
      <th>Date Signed</th>
      <th>Version Signed</th>
    </tr>
    <?php foreach ($scopeList as $b):
      $cfg      = loadBuildingConfig($b);
      $accepted = $cfg['tosAccepted'] ?? null;
      $isCurrent= $accepted && ((int)$accepted['version'] === $tosVersion);
      $label    = ucwords(str_replace(['_','-'], ' ', $b));
    ?>
    <tr>
      <td><strong><?= htmlspecialchars($label) ?></strong></td>
      <td>
        <?php if ($isCurrent): ?>
          <span class="badge ok">✓ Current</span>
        <?php elseif ($accepted): ?>
          <span class="badge pending">⚠ Outdated (v<?= (int)$accepted['version'] ?>)</span>
        <?php else: ?>
          <span class="badge pending">Pending</span>
        <?php endif; ?>
      </td>
      <td><?= $accepted ? htmlspecialchars($accepted['who']) : '—' ?></td>
      <td><?= $accepted ? fmtDate($accepted['date']) : '—' ?></td>
      <td><?= $accepted ? 'v' . (int)$accepted['version'] : '—' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php
    // Show unenrolled buildings
    $unenrolled = array_diff(array_keys($buildings), $scopeList);
    if (!$scopeAll && $unenrolled):
  ?>
  <details style="margin-top:1rem;">
    <summary><?= count($unenrolled) ?> building(s) not enrolled</summary>
    <table style="margin-top:0.5rem;">
      <tr><th>Building</th><th>Status</th></tr>
      <?php foreach ($unenrolled as $b): ?>
      <tr>
        <td><?= htmlspecialchars(ucwords(str_replace(['_','-'], ' ', $b))) ?></td>
        <td><span class="badge none">Not enrolled</span></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </details>
  <?php endif; ?>
</div>

<!-- ===================== ISSUE NEW VERSION ===================== -->
<div class="section">
  <h2>Issue New Version</h2>
  <div class="warn-box">
    ⚠ Issuing a new version will archive all current signatures and require every enrolled building
    to re-accept before their next admin login. Update the ToS document before proceeding.
  </div>
  <form method="post">
    <div class="form-row">
      <div>
        <label>New Version</label>
        <input type="text" value="<?= $tosVersion + 1 ?>" disabled style="width:80px;background:#f5f5f5;color:#888;">
      </div>
      <div>
        <label>Effective Date</label>
        <input type="date" name="new_eff_date" value="<?= date('Y-m-d') ?>">
      </div>
      <button type="submit" name="issue_version" class="btn btn-red"
              onclick="return confirm('Issue version <?= $tosVersion + 1 ?> and require all enrolled buildings to re-accept?');">
        Issue Version <?= $tosVersion + 1 ?> &amp; Require Re-acceptance
      </button>
    </div>
  </form>
</div>

<!-- ===================== SIGNATURE HISTORY ===================== -->
<div class="section">
  <h2>Signature History</h2>
  <?php if (empty($sigs)): ?>
    <p style="font-size:0.9rem;color:#888;font-style:italic;">No archived signatures yet.</p>
  <?php else: ?>
  <details>
    <summary><?= count($sigs) ?> archived signature(s)</summary>
    <table style="margin-top:0.5rem;">
      <tr>
        <th>Building</th>
        <th>Version</th>
        <th>Signed By</th>
        <th>Date Signed</th>
      </tr>
      <?php foreach (array_reverse($sigs) as $sig): ?>
      <tr>
        <td><?= htmlspecialchars($sig['building'] ?? '—') ?></td>
        <td>v<?= (int)($sig['version'] ?? 0) ?></td>
        <td><?= htmlspecialchars($sig['who'] ?? '—') ?></td>
        <td><?= fmtDate($sig['date'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </details>
  <?php endif; ?>
</div>

<?php endif; // tosExists ?>

</body>
</html>
