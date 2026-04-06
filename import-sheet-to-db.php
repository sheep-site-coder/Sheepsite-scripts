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
set_time_limit(0);
ini_set('output_buffering', 'off');

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

function out(string $msg): void {
  echo $msg . "<br>\n";
  if (ob_get_level()) ob_flush();
  flush();
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Import to DB</title>
  <style>body { font-family: monospace; max-width: 700px; margin: 2rem auto; padding: 0 1rem; font-size: 0.95rem; }</style>
</head>
<body>
<h2>Importing <?= htmlspecialchars($building) ?> → MySQL</h2>
<?php

$errors = [];

// -------------------------------------------------------
// Step 1: Import residents
// -------------------------------------------------------
out('<strong>Step 1: Residents</strong>');
$dbRes = gasGet($webAppURL, ['page' => 'listDatabase', 'token' => OWNER_IMPORT_TOKEN]);
if (!empty($dbRes['error'])) {
  die('<p style="color:red;">Failed to fetch residents: ' . htmlspecialchars($dbRes['error']) . '</p>');
}

$rows      = $dbRes['rows'] ?? [];
$units     = [];
$unitInfoFromDb = []; // unit info fields collected from old Database rows (pre-UnitDB schema)
$resAdded  = 0; $resSkipped = 0;

foreach ($rows as $r) {
  $unit  = trim($r['Unit #']     ?? '');
  $first = trim($r['First Name'] ?? '');
  $last  = trim($r['Last Name']  ?? '');
  if (!$unit && !$first && !$last) continue;
  if ($unit) $units[$unit] = true;

  // Collect unit-level fields from Database rows (old schema has them per resident)
  // Take the first non-empty value found for each field per unit
  foreach (['Insurance', 'Policy #', 'AC Replaced', 'Water Tank'] as $field) {
    $val = trim($r[$field] ?? '');
    if ($val && empty($unitInfoFromDb[$unit][$field])) {
      $unitInfoFromDb[$unit][$field] = $val;
    }
  }

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
out("Added $resAdded residents, skipped $resSkipped duplicates.");

// -------------------------------------------------------
// Step 2: Cars, Unit Info, Emergency — one getUnit per unit
// -------------------------------------------------------
out('<strong>Step 2: Cars, Unit Info &amp; Emergency contacts</strong>');
$carAdded  = 0; $unitAdded = 0; $emAdded = 0;

foreach (array_keys($units) as $unit) {
  out("  Unit $unit...");
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

  // Unit info — prefer UnitDB tab; fall back to fields collected from Database rows
  $unitInfoData = $res['unitInfo'] ?? null;
  if (!$unitInfoData && !empty($unitInfoFromDb[$unit])) {
    $unitInfoData = array_merge(['Unit #' => $unit], $unitInfoFromDb[$unit]);
  }
  if (!empty($unitInfoData)) {
    try {
      $result = dbEditUnitInfo($building, $unitInfoData);
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

out("Cars: $carAdded upserted. Unit info: $unitAdded upserted. Emergency contacts: $emAdded added.");

if ($errors) {
  out('<strong style="color:red">' . count($errors) . ' error(s):</strong>');
  foreach ($errors as $e) out('&nbsp;&nbsp;⚠ ' . htmlspecialchars($e));
}

out('<br><strong style="color:green">✓ Import complete.</strong>');
out('<br><span style="color:#92400e">Remember to delete <code>import-sheet-to-db.php</code> from the server.</span>');
?>
</body>
</html>
