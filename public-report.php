<?php
// -------------------------------------------------------
// public-report.php  (feature/unitdb — MySQL version)
// Serves public (no login required) reports from MySQL.
//
// Usage:
//   ?building=BUILDING_NAME&page=board
// -------------------------------------------------------

$buildings = require __DIR__ . '/buildings.php';
require_once __DIR__ . '/db/db.php';

// Public pages — add more here as needed
$pages = [
  'board' => 'Board of Directors',
];

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$page     = $_GET['page'] ?? 'board';

if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

if (!array_key_exists($page, $pages)) {
  die('<p style="color:red;">Invalid page.</p>');
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
$pageTitle  = $pages[$page];
require __DIR__ . '/suspension.php';
$returnURL  = $_GET['return'] ?? '';
if ($returnURL && !preg_match('/^https?:\/\//', $returnURL)) $returnURL = '';
$showNav    = ($_GET['nav'] ?? '1') !== '0';

// -------------------------------------------------------
// Fetch data from MySQL
// -------------------------------------------------------
$roleOrder = ['President', 'Vice President', 'Treasurer', 'Secretary', 'Director'];

function roleIndex(string $role): int {
  global $roleOrder;
  $normalized = trim($role);
  $idx = array_search($normalized, $roleOrder);
  if ($idx !== false) return $idx;
  if (stripos($normalized, 'director') !== false) return count($roleOrder) - 1;
  return count($roleOrder);
}

try {
  $pdo  = getDB();
  $stmt = $pdo->prepare(
    'SELECT unit, first_name, last_name, email, phone1, board_role
     FROM residents
     WHERE building = ? AND board_role IS NOT NULL AND board_role != ""'
  );
  $stmt->execute([$building]);
  $members = $stmt->fetchAll();
  usort($members, fn($a, $b) => roleIndex($a['board_role']) - roleIndex($b['board_role']));
} catch (Exception $e) {
  die('<p style="color:red;">Database error: ' . htmlspecialchars($e->getMessage()) . '</p>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – <?= htmlspecialchars($pageTitle) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body        { font-family: Georgia, serif; background: #fff; color: #222;
                  display: flex; flex-direction: column; min-height: 100vh; }
    .top-bar    { padding: 0.5rem 1rem; background: #f5f5f5; border-bottom: 1px solid #ddd;
                  font-size: 0.85rem; flex-shrink: 0; }
    .top-bar a  { color: #0070f3; text-decoration: none; }
    .top-bar a:hover { text-decoration: underline; }
    .report-wrap { flex: 1; padding: 2rem 1.5rem; max-width: 680px; margin: 0 auto; width: 100%; }
    h1          { font-size: clamp(1.4rem, 5vw, 2rem); font-weight: bold;
                  margin-bottom: 1.8rem; border-bottom: 2px solid #ccc; padding-bottom: 0.5rem; }
    .person     { margin-bottom: 1.4rem; }
    .line1      { font-size: clamp(0.95rem, 3vw, 1.05rem); font-weight: bold; }
    .role       { color: #555; }
    .line2      { font-size: clamp(0.82rem, 2.5vw, 0.92rem); color: #444;
                  padding-left: 1.2rem; margin-top: 0.2rem; }
    .line2 a    { color: #1a5276; text-decoration: none; }
    .line2 a:hover { text-decoration: underline; }
    .sep        { color: #aaa; }
    @media (max-width: 400px) {
      .report-wrap { padding: 1.2rem 1rem; }
    }
  </style>
</head>
<body>
  <?php if ($showNav && $returnURL): ?>
  <div class="top-bar">
    <a href="<?= htmlspecialchars($returnURL) ?>">← Back to site</a>
  </div>
  <?php endif; ?>

  <div class="report-wrap">
    <h1>Board of Directors</h1>

    <?php foreach ($members as $m):
      $contact = [];
      if ($m['email']) $contact[] = '<a href="mailto:' . htmlspecialchars($m['email']) . '">' . htmlspecialchars($m['email']) . '</a>';
      if ($m['phone1']) $contact[] = htmlspecialchars($m['phone1']);
      if ($m['unit'])   $contact[] = 'Unit ' . htmlspecialchars($m['unit']);
    ?>
    <div class="person">
      <div class="line1">
        <span class="role"><?= htmlspecialchars($m['board_role']) ?></span>
        &nbsp;
        <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
      </div>
      <?php if ($contact): ?>
      <div class="line2">
        <?= implode('<span class="sep"> | </span>', $contact) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($members)): ?>
      <p style="color:#666;">No board members found.</p>
    <?php endif; ?>
  </div>
</body>
</html>
