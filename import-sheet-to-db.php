<?php
// -------------------------------------------------------
// import-sheet-to-db.php — ONE-TIME import tool
// Pulls all data from the building's Google Sheet and
// inserts it into MySQL. Safe to re-run — skips duplicates.
//
// Usage:
//   https://qgscratch.website/Scripts/import-sheet-to-db.php?building=QGscratch
//
// DELETE THIS FILE after import is confirmed.
// -------------------------------------------------------

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('OWNER_IMPORT_TOKEN', 'QRF*!v2r2KgJEesq&P');

$buildings = require __DIR__ . '/buildings.php';

$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

if (!file_exists(CREDENTIALS_DIR . 'db.json')) {
  die('<p style="color:red;">credentials/db.json not found — DB not configured.</p>');
}

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/db/residents.php';

$webAppURL = $buildings[$building]['webAppURL'];

function gasGet(string $url, array $params): array {
  $fullUrl = $url . '?' . http_build_query($params);
  $ctx     = stream_context_create(['http' => ['timeout' => 45]]);
  $body    = @file_get_contents($fullUrl, false, $ctx);
  if ($body === false) return ['error' => 'Could not reach Apps Script'];
  return json_decode($body, true) ?? ['error' => 'Invalid response'];
}

$log   = [];
$errors = [];

// -------------------------------------------------------
// Step 1: Import residents
// -------------------------------------------------------
$log[] = '<strong>Step 1: Residents</strong>';
$dbRes = gasGet($webAppURL, ['page' => 'listDatabase', 'token' => OWNER_IMPORT_TOKEN]);
if (!empty($dbRes['error'])) {
  die('<p style="color:red;">Failed to fetch residents: ' . htmlspecialchars($dbRes['error']) . '</p>');
}

$rows    = $dbRes['rows'] ?? [];
$units   = [];
$resAdded = 0; $resSkipped = 0;

foreach ($rows as $r) {
  $unit  = trim($r['Unit #']     ?? '');
  $first = trim($r['First Name'] ?? '');
  $last  = trim($r['Last Name']  ?? '');
  if (!$unit && !$first && !$last) continue;
  if ($unit) $units[$unit] = true;

  try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
      'SELECT id FROM residents WHERE building=? AND first_name=? AND last_name=?'
    );
    $stmt->execute([$building, $first, $last]);
    if ($stmt->fetch()) { $resSkipped++; continue; }

    $result = dbAddResident($building, array_merge($r, ['Unit #' => $unit]));
    if (!empty($result['error'])) { $errors[] = 'Resident ' . $first . ' ' . $last . ': ' . $result['error']; }
    else $resAdded++;
  } catch (Exception $e) {
    $errors[] = 'Resident ' . $first . ' ' . $last . ': ' . $e->getMessage();
  }
}
$log[] = "Added $resAdded residents, skipped $resSkipped duplicates.";

// -------------------------------------------------------
// Step 2: Cars, Unit Info, Emergency — one getUnit per unit
// -------------------------------------------------------
$log[]     = '<strong>Step 2: Cars, Unit Info &amp; Emergency contacts</strong>';
$carAdded  = 0; $unitAdded = 0; $emAdded = 0;

foreach (array_keys($units) as $unit) {
  $res = gasGet($webAppURL, ['page' => 'getUnit', 'token' => OWNER_IMPORT_TOKEN, 'unit' => $unit]);
  if (!empty($res['error'])) {
    $errors[] = "Unit $unit getUnit error: " . $res['error'];
    continue;
  }

  // Car
  if (!empty($res['car'])) {
    try {
      $result = dbEditCar($building, $res['car']);
      if (!empty($result['error'])) $errors[] = "Unit $unit car: " . $result['error'];
      else $carAdded++;
    } catch (Exception $e) { $errors[] = "Unit $unit car: " . $e->getMessage(); }
  }

  // Unit info
  if (!empty($res['unitInfo'])) {
    try {
      $result = dbEditUnitInfo($building, $res['unitInfo']);
      if (!empty($result['error'])) $errors[] = "Unit $unit unitInfo: " . $result['error'];
      else $unitAdded++;
    } catch (Exception $e) { $errors[] = "Unit $unit unitInfo: " . $e->getMessage(); }
  }

  // Emergency contacts
  foreach ($res['emergency'] ?? [] as $em) {
    try {
      $pdo  = getDB();
      $stmt = $pdo->prepare(
        'SELECT id FROM emergency WHERE building=? AND unit=? AND first_name=? AND last_name=?'
      );
      $stmt->execute([$building, $unit, trim($em['First Name'] ?? ''), trim($em['Last Name'] ?? '')]);
      if ($stmt->fetch()) continue; // already exists

      $result = dbAddEmergency($building, $em);
      if (!empty($result['error'])) $errors[] = "Unit $unit emergency: " . $result['error'];
      else $emAdded++;
    } catch (Exception $e) { $errors[] = "Unit $unit emergency: " . $e->getMessage(); }
  }

  usleep(200000); // 0.2s pause between units to avoid Apps Script rate limits
}

$log[] = "Cars: $carAdded upserted. Unit info: $unitAdded upserted. Emergency contacts: $emAdded added.";

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Import to DB — <?= htmlspecialchars($building) ?></title>
  <style>
    body { font-family: sans-serif; max-width: 700px; margin: 3rem auto; padding: 0 1rem; }
    h1   { font-size: 1.4rem; }
    .ok  { color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
    .err { color: #991b1b; background: #fee2e2; border: 1px solid #fecaca; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
    ul   { margin: 0.5rem 0 0; padding-left: 1.5rem; }
    p    { margin: 0.4rem 0; }
    .warn { color: #92400e; background: #fef3c7; border: 1px solid #fde68a; padding: 1rem; border-radius: 6px; margin-top: 1.5rem; }
  </style>
</head>
<body>
<h1>Import: <?= htmlspecialchars($building) ?> → MySQL</h1>

<div class="ok">
  <?php foreach ($log as $line): ?>
    <p><?= $line ?></p>
  <?php endforeach; ?>
</div>

<?php if ($errors): ?>
<div class="err">
  <strong><?= count($errors) ?> error(s):</strong>
  <ul>
    <?php foreach ($errors as $e): ?>
      <li><?= htmlspecialchars($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="warn">
  <strong>Remember:</strong> Delete <code>import-sheet-to-db.php</code> from the server once the import looks correct.
</div>
</body>
</html>
