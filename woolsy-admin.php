<?php
// -------------------------------------------------------
// woolsy-admin.php
// Woolsy credit management — all buildings.
// Requires master admin session from master-admin.php.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('CREDITS_FILE',    __DIR__ . '/faqs/woolsy_credits.json');
define('CREDITS_DEFAULT', 1.0);
define('SESSION_KEY',     'master_admin_auth');

// Redirect to login if not authenticated
if (empty($_SESSION[SESSION_KEY])) {
    header('Location: master-admin.php');
    exit;
}

$buildings = require __DIR__ . '/buildings.php';

$allCredits = [];
if (file_exists(CREDITS_FILE)) {
    $allCredits = json_decode(file_get_contents(CREDITS_FILE), true) ?? [];
}

// -------------------------------------------------------
// Handle top-up POST
// -------------------------------------------------------
$topupMessage     = '';
$topupMessageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topup_building'])) {
    $topupBuilding = $_POST['topup_building'] ?? '';
    $topupAmount   = (float)($_POST['topup_amount'] ?? 0);

    if (!isset($buildings[$topupBuilding])) {
        $topupMessage     = 'Unknown building.';
        $topupMessageType = 'error';
    } elseif ($topupAmount <= 0) {
        $topupMessage     = 'Amount must be greater than zero.';
        $topupMessageType = 'error';
    } else {
        if (!isset($allCredits[$topupBuilding])) {
            $allCredits[$topupBuilding] = ['allocated' => CREDITS_DEFAULT, 'used' => 0];
        }
        $allCredits[$topupBuilding]['allocated'] = round(
            ($allCredits[$topupBuilding]['allocated'] ?? CREDITS_DEFAULT) + $topupAmount, 4
        );
        file_put_contents(CREDITS_FILE, json_encode($allCredits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $topupMessage = "Added {$topupAmount} credit(s) to {$topupBuilding}. "
                      . "New total: " . number_format($allCredits[$topupBuilding]['allocated'], 4) . " credits.";
    }
}

// -------------------------------------------------------
// Helper
// -------------------------------------------------------
function bCredits(array $allCredits, string $building): array {
    return $allCredits[$building] ?? ['allocated' => CREDITS_DEFAULT, 'used' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SheepSite — Woolsy Management</title>
  <style>
    body        { font-family: sans-serif; max-width: 860px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar    { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem; }
    h1          { margin: 0; font-size: 1.5rem; }
    .back       { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover { text-decoration: underline; }
    .subtitle   { color: #666; font-size: 0.95rem; margin-bottom: 2rem; }

    table       { width: 100%; border-collapse: collapse; font-size: 0.9rem; margin-bottom: 2rem; }
    th          { text-align: left; padding: 0.5rem 0.75rem; background: #f5f5f5;
                  border-bottom: 2px solid #ddd; font-size: 0.82rem; color: #444;
                  text-transform: uppercase; letter-spacing: 0.03em; }
    td          { padding: 0.5rem 0.75rem; border-bottom: 1px solid #eee; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }

    .badge        { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 3px; font-size: 0.78rem; font-weight: bold; }
    .badge.ok     { background: #e6f4ea; color: #1a7f37; }
    .badge.warn   { background: #fffbeb; color: #92400e; }
    .badge.danger { background: #fef2f2; color: #7f1d1d; }

    .credit-bar  { width: 80px; background: #eee; border-radius: 4px; height: 6px;
                   display: inline-block; vertical-align: middle; margin-right: 6px; }
    .credit-fill { height: 6px; border-radius: 4px; background: #0070f3; }
    .credit-fill.warn   { background: #f59e0b; }
    .credit-fill.danger { background: #dc2626; }

    h2          { font-size: 1rem; margin: 0 0 0.75rem; }
    .topup-form { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 0.5rem; }
    .topup-form label { font-size: 0.85rem; font-weight: bold; display: block; margin-bottom: 0.25rem; }
    .topup-form select,
    .topup-form input[type=number] {
                  padding: 0.45rem 0.6rem; border: 1px solid #ccc; border-radius: 4px; font-size: 0.95rem; }
    .topup-form input[type=number] { width: 100px; }
    .topup-btn  { padding: 0.45rem 1rem; background: #0070f3; color: #fff; border: none;
                  border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
    .topup-btn:hover { background: #005bb5; }
    .message    { padding: 0.55rem 0.85rem; border-radius: 4px; margin-bottom: 1.25rem; font-size: 0.88rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    .hint       { font-size: 0.8rem; color: #888; margin-top: 0.4rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1>🐑 Woolsy Management</h1>
  <a href="master-admin.php" class="back">← Master Admin</a>
</div>
<div class="subtitle">Credit usage across all buildings</div>

<?php if ($topupMessage): ?>
  <div class="message <?= $topupMessageType ?>"><?= htmlspecialchars($topupMessage) ?></div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>Building</th>
      <th>Allocated</th>
      <th>Used</th>
      <th>Remaining</th>
      <th>Usage</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($buildings as $bName => $_): ?>
    <?php
      $bc       = bCredits($allCredits, $bName);
      $alloc    = (float)($bc['allocated'] ?? CREDITS_DEFAULT);
      $used     = (float)($bc['used']      ?? 0);
      $remain   = max(0, $alloc - $used);
      $pct      = $alloc > 0 ? min(100, round($used / $alloc * 100)) : 100;
      $barCls   = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warn' : '');
      $badgeCls = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warn' : 'ok');
      $badgeTxt = $pct >= 100 ? 'Exhausted' : ($pct >= 80 ? 'Low' : 'OK');
    ?>
    <tr>
      <td><?= htmlspecialchars($bName) ?></td>
      <td><?= number_format($alloc,  4) ?></td>
      <td><?= number_format($used,   6) ?></td>
      <td><?= number_format($remain, 4) ?></td>
      <td>
        <span class="credit-bar">
          <span class="credit-fill <?= $barCls ?>" style="width:<?= $pct ?>%"></span>
        </span>
        <?= $pct ?>%
      </td>
      <td><span class="badge <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Add credits</h2>
<form method="post" class="topup-form">
  <div>
    <label for="topup_building">Building</label>
    <select id="topup_building" name="topup_building">
      <?php foreach ($buildings as $bName => $_): ?>
        <option value="<?= htmlspecialchars($bName) ?>"><?= htmlspecialchars($bName) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label for="topup_amount">Credits to add</label>
    <input type="number" id="topup_amount" name="topup_amount" min="0.5" step="0.5" value="5">
  </div>
  <button type="submit" class="topup-btn">Add credits</button>
</form>
<p class="hint">1 credit = $1 of API cost. Price credits to buildings at your rate.</p>

</body>
</html>
