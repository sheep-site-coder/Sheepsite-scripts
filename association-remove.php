<?php
// -------------------------------------------------------
// association-remove.php
// Master admin tool to remove a building association.
//
// Deletes server-side files only. Google Drive folders
// must be removed manually.
//
//   https://sheepsite.com/Scripts/association-remove.php
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CONFIG_DIR',      __DIR__ . '/config/');
define('TAGS_DIR',        __DIR__ . '/tags/');
define('FAQS_DIR',        __DIR__ . '/faqs/');

if (empty($_SESSION['master_admin_auth'])) {
  header('Location: master-admin.php');
  exit;
}

$buildings = require __DIR__ . '/buildings.php';
$message   = '';
$msgType   = 'ok';
$removed   = null; // building key that was just removed

// -------------------------------------------------------
// POST — perform removal
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $key     = trim($_POST['building_key'] ?? '');
  $confirm = trim($_POST['confirm_key']  ?? '');

  if (!$key || !array_key_exists($key, $buildings)) {
    $message = 'Invalid building key.';
    $msgType = 'error';
  } elseif ($confirm !== $key) {
    $message = 'Confirmation key does not match. No files were deleted.';
    $msgType = 'error';
  } else {
    // Delete server-side files
    $toDelete = [
      CREDENTIALS_DIR . $key . '.json',
      CREDENTIALS_DIR . $key . '_admin.json',
      CONFIG_DIR      . $key . '.json',
      CONFIG_DIR      . $key . '_folders.json',
      TAGS_DIR        . $key . '.json',
      FAQS_DIR        . $key . '.txt',
      FAQS_DIR        . $key . '_docindex.txt',
    ];
    $deleted  = [];
    $missing  = [];
    foreach ($toDelete as $f) {
      if (file_exists($f)) {
        unlink($f);
        $deleted[] = basename(dirname($f)) . '/' . basename($f);
      } else {
        $missing[] = basename(dirname($f)) . '/' . basename($f);
      }
    }

    // Remove from woolsy_credits.json
    $creditsFile = FAQS_DIR . 'woolsy_credits.json';
    if (file_exists($creditsFile)) {
      $credits = json_decode(file_get_contents($creditsFile), true) ?? [];
      if (isset($credits[$key])) {
        unset($credits[$key]);
        file_put_contents($creditsFile, json_encode($credits, JSON_PRETTY_PRINT));
        $deleted[] = 'faqs/woolsy_credits.json (entry removed)';
      }
    }

    // Remove from woolsy_usage.json
    $usageFile = FAQS_DIR . 'woolsy_usage.json';
    if (file_exists($usageFile)) {
      $usage = json_decode(file_get_contents($usageFile), true) ?? [];
      if (isset($usage[$key])) {
        unset($usage[$key]);
        file_put_contents($usageFile, json_encode($usage, JSON_PRETTY_PRINT));
        $deleted[] = 'faqs/woolsy_usage.json (entry removed)';
      }
    }

    // Remove from login_stats.json
    $statsFile = CREDENTIALS_DIR . 'login_stats.json';
    if (file_exists($statsFile)) {
      $stats = json_decode(file_get_contents($statsFile), true) ?? [];
      if (isset($stats[$key])) {
        unset($stats[$key]);
        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        $deleted[] = 'credentials/login_stats.json (entry removed)';
      }
    }

    $removed = $key;
    $message = count($deleted) . ' item(s) removed.';
    $msgType = 'ok';
  }
}

$buildingKeys = array_keys($buildings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Remove Association — Master Admin</title>
  <style>
    * { box-sizing: border-box; }
    body      { font-family: sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
    .wrap     { max-width: 680px; margin: 0 auto; padding: 2rem 1rem 4rem; }
    .top-bar  { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1        { margin: 0; font-size: 1.3rem; }
    .back     { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover { text-decoration: underline; }

    .msg      { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .msg.ok   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .msg.error{ background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

    .card     { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; }
    label     { display: block; font-size: 0.85rem; font-weight: bold; margin-bottom: 0.3rem; }
    select, input[type=text] {
      width: 100%; padding: 0.45rem 0.6rem; border: 1px solid #ccc;
      border-radius: 4px; font-size: 0.95rem; margin-bottom: 1rem;
    }
    .file-list { margin: 0.5rem 0 1.25rem; padding: 0; list-style: none; }
    .file-list li { font-size: 0.85rem; color: #444; padding: 0.2rem 0;
                    padding-left: 1.2rem; position: relative; }
    .file-list li::before { content: '✕'; color: #dc2626; position: absolute; left: 0; font-size: 0.75rem; top: 0.25rem; }
    .file-list li.manual::before { content: '⚠'; color: #d97706; }

    .confirm-box { background: #fff5f5; border: 1px solid #fca5a5; border-radius: 6px;
                   padding: 1rem; margin-bottom: 1rem; }
    .confirm-box p { margin: 0 0 0.75rem; font-size: 0.9rem; color: #7f1d1d; }

    .btn-remove { background: #dc2626; color: #fff; border: none; border-radius: 5px;
                  padding: 0.6rem 1.4rem; font-size: 0.95rem; cursor: pointer; }
    .btn-remove:hover { background: #b91c1c; }
    .btn-remove:disabled { opacity: 0.5; cursor: default; }

    .snippet  { background: #1e1e1e; color: #d4d4d4; border-radius: 6px;
                padding: 1rem; font-size: 0.82rem; font-family: monospace;
                white-space: pre; overflow-x: auto; margin: 0.5rem 0 1rem; }
    .copy-btn { font-size: 0.78rem; background: #fff; border: 1px solid #ccc;
                border-radius: 4px; padding: 0.2rem 0.5rem; cursor: pointer; margin-left: 0.5rem; }

    .notice   { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px;
                padding: 1rem 1.25rem; font-size: 0.88rem; color: #78350f; margin-bottom: 1.5rem; }
    .notice strong { display: block; margin-bottom: 0.25rem; }
  </style>
</head>
<body>
<div class="wrap">

<div class="top-bar">
  <h1>Remove Association</h1>
  <a href="master-admin.php" class="back">← Master Admin</a>
</div>

<?php if ($message): ?>
  <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($removed): ?>
<!-- ── Post-removal checklist ── -->
<div class="card">
  <h2 style="margin:0 0 1rem;font-size:1rem;">Removal complete for: <strong><?= htmlspecialchars($removed) ?></strong></h2>
  <p style="font-size:0.9rem;color:#555;margin:0 0 1.25rem;">Server files have been deleted. Complete the following manual steps:</p>

  <p style="font-size:0.85rem;font-weight:bold;margin:0 0 0.4rem;">1. Remove from buildings.php</p>
  <p style="font-size:0.85rem;color:#555;margin:0 0 0.4rem;">Delete the following block from <code>buildings.php</code> on the server:</p>
  <div class="snippet" id="snippet-buildings"><?= "  '" . htmlspecialchars($removed) . "' => [\n    // ... remove this entire entry\n  ]," ?></div>

  <p style="font-size:0.85rem;font-weight:bold;margin:1rem 0 0.4rem;">2. Remove Google Drive folders</p>
  <p style="font-size:0.85rem;color:#555;margin:0 0 0.75rem;">
    Log in to the SheepSite Google account and trash the association folder manually:<br>
    <strong>Association Folders / <?= htmlspecialchars($removed) ?>/</strong>
  </p>

  <p style="font-size:0.85rem;font-weight:bold;margin:1rem 0 0.4rem;">3. Remove the Google Sheet</p>
  <p style="font-size:0.85rem;color:#555;margin:0;">
    Trash the <strong><?= htmlspecialchars($removed) ?> Owner DB</strong> sheet from Google Drive.
  </p>
</div>

<?php else: ?>
<!-- ── Remove form ── -->

<div class="notice">
  <strong>What this tool does</strong>
  Deletes all server-side files for the selected association (credentials, config, tags, Woolsy data).
  Google Drive folders and the Owner DB sheet must be removed manually — this tool will remind you after deletion.
</div>

<div class="card">
  <form method="post" id="remove-form">

    <label for="building_key">Select Association</label>
    <select name="building_key" id="building_key" onchange="updateChecklist()">
      <option value="">— choose —</option>
      <?php foreach ($buildingKeys as $k): ?>
        <option value="<?= htmlspecialchars($k) ?>"
          <?= (($_POST['building_key'] ?? '') === $k) ? 'selected' : '' ?>>
          <?= htmlspecialchars($k) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <p style="font-size:0.85rem;font-weight:bold;margin:0 0 0.4rem;">Files that will be deleted:</p>
    <ul class="file-list" id="file-list">
      <li style="color:#bbb;font-style:italic;font-size:0.85rem;padding-left:0;">Select an association above to see the list.</li>
    </ul>

    <div class="confirm-box">
      <p><strong>This cannot be undone.</strong> Type the key shown below to confirm deletion.</p>
      <div id="key-display" style="display:none;margin-bottom:0.75rem;">
        <span style="font-size:0.82rem;color:#7f1d1d;">Association key:</span><br>
        <span id="confirm-hint" style="font-family:monospace;font-size:1.1rem;font-weight:bold;color:#991b1b;letter-spacing:0.03em;"></span>
      </div>
      <label for="confirm_key">Type the key above to confirm</label>
      <input type="text" name="confirm_key" id="confirm_key" placeholder="Type association key here" autocomplete="off">
    </div>

    <button type="submit" class="btn-remove" id="remove-btn" disabled>Remove Association</button>
  </form>
</div>

<?php endif; ?>

</div><!-- /wrap -->

<script>
var filesByBuilding = <?php
  $map = [];
  foreach ($buildingKeys as $k) {
    $files = [
      'credentials/' . $k . '.json',
      'credentials/' . $k . '_admin.json',
      'config/'      . $k . '.json',
      'config/'      . $k . '_folders.json',
      'tags/'        . $k . '.json',
      'faqs/'        . $k . '.txt',
      'faqs/'        . $k . '_docindex.txt',
      'faqs/woolsy_credits.json (entry)',
      'faqs/woolsy_usage.json (entry)',
      'credentials/login_stats.json (entry)',
    ];
    $map[$k] = $files;
  }
  echo json_encode($map);
?>;

function updateChecklist() {
  var key     = document.getElementById('building_key').value;
  var list    = document.getElementById('file-list');
  var hint    = document.getElementById('confirm-hint');
  var btn     = document.getElementById('remove-btn');
  var confirm = document.getElementById('confirm_key');

  var keyDisplay = document.getElementById('key-display');

  if (!key) {
    list.innerHTML = '<li style="color:#bbb;font-style:italic;font-size:0.85rem;padding-left:0;">Select an association above to see the list.</li>';
    hint.textContent = '';
    keyDisplay.style.display = 'none';
    btn.disabled = true;
    return;
  }

  var files = filesByBuilding[key] || [];
  var html  = '';
  files.forEach(function (f) {
    html += '<li>' + f + '</li>';
  });
  list.innerHTML = html;
  hint.textContent = key;
  keyDisplay.style.display = 'block';
  confirm.value = '';
  btn.disabled = true;

  confirm.oninput = function () {
    btn.disabled = (confirm.value.trim() !== key);
  };
}

// Re-attach on page load if a value was pre-selected (e.g. after error)
window.addEventListener('DOMContentLoaded', function () {
  if (document.getElementById('building_key').value) updateChecklist();
});
</script>
</body>
</html>
