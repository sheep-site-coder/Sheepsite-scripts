<?php
// -------------------------------------------------------
// r2-copy-template.php
// One-time tool: copies SampleSite/ → _template/ in R2.
// Master admin only. Delete from server after use.
//
//   https://sheepsite.com/Scripts/r2-copy-template.php
// -------------------------------------------------------
session_start();
define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

if (empty($_SESSION['master_admin_auth'])) {
  die('<p style="color:red;">Not authenticated. Log into master-admin.php first.</p>');
}

require_once __DIR__ . '/storage/r2-storage.php';

$result  = null;
$ran     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
  $result = _r2CopyTree('QGscratch/', '_template/');
  $ran    = true;
}

// Also do a quick list of what's currently under _template/
$cfg = _r2Cfg();
[$listStatus, $listBody] = _r2Request('GET', '/' . $cfg['bucket'], [
  'list-type' => '2',
  'prefix'    => '_template/',
  'max-keys'  => '200',
]);
$currentKeys = [];
if ($listStatus === 200) {
  $sx = @simplexml_load_string($listBody);
  if ($sx && isset($sx->Contents)) {
    foreach ($sx->Contents as $obj) $currentKeys[] = (string)$obj->Key;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>R2 Copy Template</title>
  <style>
    body { font-family: sans-serif; max-width: 640px; margin: 3rem auto; padding: 0 1rem; font-size: 0.95rem; }
    h1   { font-size: 1.3rem; margin-bottom: 0.25rem; }
    .sub { color: #888; font-size: 0.85rem; margin-bottom: 2rem; }
    .ok  { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; }
    .err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; }
    .warn { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.5rem; color: #78350f; }
    .btn { background: #1a5a2a; color: #fff; border: none; padding: 0.55rem 1.4rem; border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
    .btn:hover { background: #134520; }
    pre  { background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 0.75rem; font-size: 0.8rem; overflow-x: auto; }
    ul   { margin: 0.5rem 0; padding-left: 1.5rem; }
    li   { margin: 0.2rem 0; font-size: 0.85rem; }
  </style>
</head>
<body>

<h1>R2 Copy Template</h1>
<div class="sub">Copies <code>QGscratch/</code> → <code>_template/</code> in R2. Delete this file after use.</div>

<?php if ($ran): ?>

  <?php if (empty($result['errors'])): ?>
    <div class="ok">
      <strong>&#10003; Done.</strong> <?= $result['copied'] ?> file(s) copied to <code>_template/</code>.
    </div>
  <?php else: ?>
    <div class="err">
      <strong><?= $result['copied'] ?> file(s) copied.</strong>
      <?= count($result['errors']) ?> error(s):<br>
      <ul><?php foreach ($result['errors'] as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

<?php endif; ?>

<!-- Current _template/ contents -->
<h2 style="font-size:1rem;margin:0 0 0.5rem;">Current <code>_template/</code> contents (<?= count($currentKeys) ?> object<?= count($currentKeys) !== 1 ? 's' : '' ?>)</h2>
<?php if ($currentKeys): ?>
  <ul>
    <?php foreach ($currentKeys as $k): ?>
      <li><?= htmlspecialchars(substr($k, strlen('_template/'))) ?></li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <p style="color:#888;font-size:0.88rem;">Empty — nothing under <code>_template/</code> yet.</p>
<?php endif; ?>

<?php if (!$ran): ?>
<br>
<div class="warn">
  <strong>This will overwrite any existing objects under <code>_template/</code> that share the same key.</strong><br>
  It copies every file under <code>QGscratch/</code> to <code>_template/</code>, preserving the subfolder structure.
</div>
<form method="post">
  <input type="hidden" name="confirm" value="yes">
  <button type="submit" class="btn">Copy QGscratch → _template</button>
</form>
<?php endif; ?>

</body>
</html>
