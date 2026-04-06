<?php
// sync-test.php — minimal sync test, no session/auth required
// Upload to public_html/Scripts/ and visit:
//   https://qgscratch.website/Scripts/sync-test.php?building=QGscratch
// DELETE after testing.

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/db/residents.php';

$building = $_GET['building'] ?? 'QGscratch';

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Step 1: get residents from DB
  $dbResult  = dbListDatabase($building);
  $residents = $dbResult['rows'] ?? [];

  // Step 2: load web accounts
  $credFile = CREDENTIALS_DIR . $building . '.json';
  $users    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) ?? [] : [];

  // Step 3: build owner list (firstName/lastName)
  $owners = array_map(fn($r) => [
    'first' => $r['First Name'],
    'last'  => $r['Last Name'],
  ], $residents);

  $result = [
    'residents' => count($residents),
    'webUsers'  => count($users),
    'owners'    => array_map(fn($o) => $o['first'] . ' ' . $o['last'], $owners),
    'usernames' => array_column($users, 'user'),
  ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sync Test</title>
  <style>body { font-family: monospace; max-width: 700px; margin: 2rem auto; padding: 0 1rem; }</style>
</head>
<body>
<h2>Sync Test — <?= htmlspecialchars($building) ?></h2>

<?php if ($result): ?>
  <p>✓ POST received</p>
  <p>DB residents: <?= $result['residents'] ?></p>
  <p>Web accounts: <?= $result['webUsers'] ?></p>
  <p>Residents: <?= implode(', ', $result['owners']) ?></p>
  <p>Usernames: <?= implode(', ', $result['usernames']) ?></p>
<?php else: ?>
  <p>GET — form not yet submitted.</p>
<?php endif; ?>

<form method="post" action="sync-test.php?building=<?= urlencode($building) ?>">
  <button type="submit">Run Sync Test</button>
</form>
</body>
</html>
