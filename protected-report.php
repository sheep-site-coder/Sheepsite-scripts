<?php
// -------------------------------------------------------
// protected-report.php  (feature/unitdb — MySQL version)
// Generates resident reports directly from MySQL.
//
// Usage:
//   ?building=BUILDING_NAME&page=parking
//   ?building=BUILDING_NAME&page=elevator
//   ?building=BUILDING_NAME&page=resident
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

$buildings = require __DIR__ . '/buildings.php';
require_once __DIR__ . '/db/db.php';

$pages = [
  'elevator' => 'Elevator List',
  'parking'  => 'Parking List',
  'resident' => 'Resident List',
];

// -------------------------------------------------------
// Get and validate parameters
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
$page     = $_GET['page'] ?? 'parking';

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

$sessionKey = 'private_auth_' . $building;
$baseURL    = '?building=' . urlencode($building) . '&page=' . urlencode($page)
            . ($returnURL ? '&return=' . urlencode($returnURL) : '');

// -------------------------------------------------------
// Logout
// -------------------------------------------------------
if (isset($_GET['logout'])) {
  unset($_SESSION[$sessionKey]);
  header('Location: ' . $baseURL);
  exit;
}

// -------------------------------------------------------
// Login — handle POST
// -------------------------------------------------------
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  $credFile = CREDENTIALS_DIR . $building . '.json';
  $users    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];

  $authenticated = false;
  foreach ($users as $u) {
    if ($u['user'] === $username && password_verify($password, $u['pass'])) {
      $authenticated = true;
      break;
    }
  }

  if ($authenticated) {
    $_SESSION[$sessionKey] = $username;
    header('Location: ' . $baseURL);
    exit;
  } else {
    $loginError = 'Invalid username or password.';
  }
}

// -------------------------------------------------------
// Admin bypass — only when ?adminview=1 is explicitly set
// -------------------------------------------------------
$adminSessionKey = 'manage_auth_' . $building;
$isAdminViewing  = !empty($_SESSION[$adminSessionKey]) && !empty($_GET['adminview']);

// -------------------------------------------------------
// Login — show form if not authenticated
// -------------------------------------------------------
if (!$isAdminViewing && empty($_SESSION[$sessionKey])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Login</title>
  <style>
    body       { font-family: sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
    h1         { margin-bottom: 0.25rem; font-size: 1.4rem; }
    .subtitle  { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label      { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text], input[type=password] {
                 width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                 border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;
                 margin-bottom: 1rem; }
    .login-btn { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                 border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .login-btn:hover { background: #005bb5; }
    .error     { color: red; font-size: 0.9rem; margin-bottom: 1rem; }
    .back-btn  { display: inline-block; margin-bottom: 1.5rem; font-size: 0.9rem;
                 color: #0070f3; text-decoration: none; }
    .back-btn:hover { text-decoration: underline; }
    .admin-bypass { background:#f0f7ff; border:1px solid #b3d4f5; border-radius:6px;
                    padding:0.75rem 1rem; margin-bottom:1.5rem; font-size:0.9rem; }
    .admin-bypass a { color:#0070f3; font-weight:bold; text-decoration:none; }
    .admin-bypass a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <?php if ($returnURL): ?>
    <a href="<?= htmlspecialchars($returnURL) ?>" class="back-btn">← Back to site</a>
  <?php endif; ?>
  <h1><?= htmlspecialchars($buildLabel) ?></h1>
  <div class="subtitle"><?= htmlspecialchars($pageTitle) ?> — login required</div>

  <?php if (!empty($_SESSION[$adminSessionKey])): ?>
    <div class="admin-bypass">
      Logged in as admin &mdash;
      <a href="<?= htmlspecialchars($baseURL . '&adminview=1') ?>">Continue as Admin →</a>
    </div>
  <?php endif; ?>

  <?php if ($loginError): ?>
    <p class="error"><?= htmlspecialchars($loginError) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= htmlspecialchars($baseURL) ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" autocomplete="username" autocapitalize="none" autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password">

    <button type="submit" class="login-btn">Log in</button>
  </form>
  <p style="text-align:center;margin-top:1rem;font-size:0.85rem;">
    <a href="forgot-password.php?building=<?= urlencode($building) ?>" style="color:#0070f3;text-decoration:none;">Forgot password?</a>
  </p>
</body>
</html>
<?php
  exit;
}

// -------------------------------------------------------
// mustChange check
// -------------------------------------------------------
if (!$isAdminViewing) {
  $credFile = CREDENTIALS_DIR . $building . '.json';
  $allUsers = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];
  foreach ($allUsers as $u) {
    if ($u['user'] === $_SESSION[$sessionKey] && !empty($u['mustChange'])) {
      $reportRedirect = 'protected-report.php?building=' . urlencode($building)
                      . '&page=' . urlencode($page)
                      . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      header('Location: change-password.php?building=' . urlencode($building)
           . '&mustchange=1'
           . '&redirect=' . urlencode($reportRedirect)
           . ($returnURL ? '&return=' . urlencode($returnURL) : ''));
      exit;
    }
  }
}

// -------------------------------------------------------
// Authenticated — fetch data from MySQL
// -------------------------------------------------------
$currentUser = $_SESSION[$sessionKey];
$today       = date('F j, Y');

try {
  $pdo = getDB();

  if ($page === 'resident') {
    $stmt = $pdo->prepare(
      'SELECT unit, last_name, first_name, phone1
       FROM residents WHERE building = ?
       ORDER BY last_name, first_name'
    );
    $stmt->execute([$building]);
    $residents = $stmt->fetchAll();
    // natural-sort by unit as secondary option (JS handles re-sort)
    $jsonData = json_encode(array_values($residents));

  } elseif ($page === 'elevator') {
    $stmt = $pdo->prepare(
      'SELECT unit, last_name, first_name
       FROM residents WHERE building = ? AND last_name != ""
       ORDER BY last_name, first_name'
    );
    $stmt->execute([$building]);
    $rows = $stmt->fetchAll();

    // Group by unit+last_name, combine first names
    $groups = [];
    foreach ($rows as $r) {
      $key = $r['unit'] . '_' . $r['last_name'];
      if (!isset($groups[$key])) {
        $groups[$key] = ['unit' => $r['unit'], 'last' => $r['last_name'], 'firsts' => []];
      }
      if ($r['first_name'] !== '' && !in_array($r['first_name'], $groups[$key]['firsts'])) {
        $groups[$key]['firsts'][] = $r['first_name'];
      }
    }

    $list = array_values($groups);
    usort($list, fn($a, $b) =>
      strcasecmp($a['last'], $b['last']) ?: strnatcasecmp($a['unit'], $b['unit'])
    );

    // Two-column layout
    $half    = (int)ceil(count($list) / 2);
    $elevRows = [];
    for ($i = 0; $i < $half; $i++) {
      $elevRows[] = [
        'left'  => $list[$i],
        'right' => $list[$i + $half] ?? null,
      ];
    }

  } elseif ($page === 'parking') {
    $stmt = $pdo->prepare(
      'SELECT unit, parking_spot, make, model, color
       FROM car_db WHERE building = ? AND (unit != "" OR parking_spot != "")
       ORDER BY unit, parking_spot'
    );
    $stmt->execute([$building]);
    $cars     = $stmt->fetchAll();
    $jsonData = json_encode(array_values($cars));
  }

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
    body        { font-family: sans-serif; display: flex; flex-direction: column; height: 100vh; }
    .top-bar    { display: flex; justify-content: space-between; align-items: center;
                  padding: 0.5rem 1rem; background: #f5f5f5; border-bottom: 1px solid #ddd;
                  font-size: 0.85rem; flex-shrink: 0; }
    .top-bar a  { color: #0070f3; text-decoration: none; margin-left: 0.75rem; }
    .top-bar a:hover { text-decoration: underline; }
    .nav-links a { font-weight: bold; }
    .nav-links a.active { color: #333; pointer-events: none; text-decoration: none; }
    .report-wrap { flex: 1; overflow-y: auto; padding: 1.5rem 2rem; background: #fff; }

    /* Resident + Parking shared table styles */
    .sort-bar   { display: flex; gap: 0.5rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .sort-bar button {
                  padding: 0.4rem 1rem; font-size: 0.9rem;
                  border: 1px solid #aaa; border-radius: 4px;
                  background: #f0f0f0; cursor: pointer; }
    .sort-bar button.active { background: #1a5276; color: #fff; border-color: #1a5276; }
    table       { width: 100%; border-collapse: collapse;
                  font-size: clamp(0.8rem, 2.5vw, 0.95rem); }
    thead th    { text-align: left; padding: 0.5rem 0.6rem;
                  border-bottom: 2px solid #555; font-weight: bold; white-space: nowrap; }
    tbody td    { padding: 0.4rem 0.6rem; border-bottom: 1px solid #e0e0e0; }
    tbody tr:last-child td { border-bottom: none; }

    /* Elevator two-column */
    .elev-title { font-size: clamp(1.3rem, 4vw, 1.6rem); font-weight: bold; }
    .elev-date  { font-size: 0.85rem; color: #666; margin-bottom: 1.2rem; }
    .print-btn  { display: inline-block; margin-bottom: 1rem;
                  padding: 0.4rem 1rem; background: #0070f3; color: #fff;
                  border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer; }
    .print-btn:hover { background: #005bb5; }
    .elev-table { width: 100%; border-collapse: collapse;
                  font-size: clamp(0.8rem, 2.5vw, 0.95rem); }
    .elev-table td { padding: 0.3rem 0.4rem; vertical-align: top; }
    .elev-table td.last  { font-weight: bold; width: 22%; }
    .elev-table td.first { width: 22%; }
    .elev-table td.unit  { color: #555; width: 8%; }
    .elev-table td.gutter { width: 8%; }
    @media print {
      .print-btn  { display: none; }
      .top-bar    { display: none; }
      @page       { size: letter portrait; margin: 0; }
      .report-wrap { padding: 0.5in; }
    }
    @media (max-width: 480px) {
      .report-wrap { padding: 1rem 0.6rem; }
    }
  </style>
</head>
<body>
  <div class="top-bar">
    <div>
      <?php if ($returnURL): ?>
        <a href="<?= htmlspecialchars($returnURL) ?>">← Back to site</a>
      <?php endif; ?>
    </div>
    <div class="nav-links">
      <?php foreach (['elevator' => 'Elevator List', 'parking' => 'Parking List', 'resident' => 'Resident List'] as $p => $label):
        $navURL = '?building=' . urlencode($building) . '&page=' . urlencode($p)
                . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      ?>
        <a href="<?= htmlspecialchars($navURL) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <span style="color:#666;"><?= htmlspecialchars($currentUser) ?></span>
      <?php
        $reportRedirect = 'protected-report.php?building=' . urlencode($building)
                        . '&page=' . urlencode($page)
                        . ($returnURL ? '&return=' . urlencode($returnURL) : '');
      ?>
      <a href="change-password.php?building=<?= urlencode($building) ?>&redirect=<?= urlencode($reportRedirect) ?><?= $returnURL ? '&return=' . urlencode($returnURL) : '' ?>">Change password</a>
      <a href="<?= htmlspecialchars($baseURL) ?>&logout=1">Log out</a>
    </div>
  </div>

  <div class="report-wrap">

<?php if ($page === 'resident'): ?>
    <div class="sort-bar">
      <button id="btn-unit" onclick="sortBy('unit')">Sort by Unit #</button>
      <button id="btn-last" class="active" onclick="sortBy('last')">Sort by Last Name</button>
    </div>
    <table>
      <thead>
        <tr>
          <th>Unit #</th>
          <th>Last Name</th>
          <th>First Name</th>
          <th>Phone #1</th>
        </tr>
      </thead>
      <tbody id="table-body"></tbody>
    </table>
    <script>
      const data = <?= $jsonData ?>;
      let currentSort = 'last';
      function naturalCompare(a, b) {
        return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
      }
      function sortBy(key) {
        currentSort = key;
        document.getElementById('btn-unit').classList.toggle('active', key === 'unit');
        document.getElementById('btn-last').classList.toggle('active', key === 'last');
        const sorted = [...data].sort((a, b) =>
          key === 'unit'
            ? naturalCompare(a.unit, b.unit) || naturalCompare(a.last_name, b.last_name)
            : naturalCompare(a.last_name, b.last_name) || naturalCompare(a.unit, b.unit)
        );
        document.getElementById('table-body').innerHTML = sorted.map(r =>
          `<tr>
            <td>${r.unit}</td>
            <td>${r.last_name}</td>
            <td>${r.first_name}</td>
            <td>${r.phone1}</td>
          </tr>`
        ).join('');
      }
      sortBy('last');
    </script>

<?php elseif ($page === 'elevator'): ?>
    <div class="elev-title"><?= htmlspecialchars($buildLabel) ?> Residents</div>
    <div class="elev-date">(<?= htmlspecialchars($today) ?>)</div>
    <button class="print-btn" onclick="printFit()">Print</button>
    <script>
      function printFit() {
        var pageH = 1056;
        var contentH = document.body.scrollHeight;
        if (contentH > pageH) document.documentElement.style.zoom = pageH / contentH;
        window.print();
        document.documentElement.style.zoom = '';
      }
    </script>
    <table class="elev-table"><tbody>
      <?php foreach ($elevRows as $row):
        $l = $row['left'];
        $r = $row['right'];
        $lName = implode(' & ', $l['firsts']);
        $rName = $r ? implode(' & ', $r['firsts']) : '';
      ?>
      <tr>
        <td class="last"><?= htmlspecialchars($l['last']) ?></td>
        <td class="first"><?= htmlspecialchars($lName) ?></td>
        <td class="unit"><?= htmlspecialchars($l['unit']) ?></td>
        <td class="gutter"></td>
        <td class="last"><?= $r ? htmlspecialchars($r['last']) : '' ?></td>
        <td class="first"><?= $r ? htmlspecialchars($rName) : '' ?></td>
        <td class="unit"><?= $r ? htmlspecialchars($r['unit']) : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table>

<?php elseif ($page === 'parking'): ?>
    <div class="sort-bar">
      <button id="btn-unit" class="active" onclick="sortBy('unit')">Sort by Unit #</button>
      <button id="btn-spot" onclick="sortBy('spot')">Sort by Parking Spot</button>
    </div>
    <table>
      <thead>
        <tr>
          <th>Unit #</th>
          <th>Parking Spot</th>
          <th>Car Make</th>
          <th>Car Model</th>
          <th>Car Color</th>
        </tr>
      </thead>
      <tbody id="table-body"></tbody>
    </table>
    <script>
      const data = <?= $jsonData ?>;
      let currentSort = 'unit';
      function naturalCompare(a, b) {
        return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
      }
      function sortBy(key) {
        currentSort = key;
        document.getElementById('btn-unit').classList.toggle('active', key === 'unit');
        document.getElementById('btn-spot').classList.toggle('active', key === 'spot');
        const sorted = [...data].sort((a, b) =>
          key === 'spot'
            ? naturalCompare(a.parking_spot, b.parking_spot) || naturalCompare(a.unit, b.unit)
            : naturalCompare(a.unit, b.unit) || naturalCompare(a.parking_spot, b.parking_spot)
        );
        document.getElementById('table-body').innerHTML = sorted.map(r =>
          `<tr>
            <td>${r.unit}</td>
            <td>${r.parking_spot}</td>
            <td>${r.make}</td>
            <td>${r.model}</td>
            <td>${r.color}</td>
          </tr>`
        ).join('');
      }
      sortBy('unit');
    </script>

<?php endif; ?>

  </div><!-- .report-wrap -->
</body>
</html>
