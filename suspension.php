<?php
// -------------------------------------------------------
// suspension.php — shared suspension check
//
// Include AFTER $building has been validated.
// $buildLabel is optional — falls back to $building.
//
// Checks renewalDate; auto-sets suspended:true if overdue.
// If suspended:
//   - JSON requests (chatbot.php, ?json=) → JSON error + exit
//   - All other requests → full "Service Unavailable" page + exit
// -------------------------------------------------------

$_suspCfgFile = __DIR__ . '/config/' . $building . '.json';
$_suspCfg     = file_exists($_suspCfgFile)
    ? (json_decode(file_get_contents($_suspCfgFile), true) ?? [])
    : [];

// Auto-suspend if renewal date has passed
if (!empty($_suspCfg['renewalDate']) && empty($_suspCfg['suspended'])) {
    if (strtotime($_suspCfg['renewalDate']) < time()) {
        $_suspCfg['suspended'] = true;
        file_put_contents($_suspCfgFile, json_encode($_suspCfg, JSON_PRETTY_PRINT));
    }
}

if (!empty($_suspCfg['suspended'])) {
    $_suspLabel = isset($buildLabel) ? htmlspecialchars($buildLabel) : htmlspecialchars($building);

    // JSON request? Return JSON error.
    $_isJson = isset($_GET['json'])
        || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        || (isset($_SERVER['HTTP_ACCEPT'])  && strpos($_SERVER['HTTP_ACCEPT'],  'application/json') !== false);

    if ($_isJson) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['error' => 'Service suspended']);
        exit;
    }

    // HTML page
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $_suspLabel ?> &mdash; Service Unavailable</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: sans-serif;
      background: #f5f5f5;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .box {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 12px;
      padding: 2.5rem 2rem;
      max-width: 500px;
      width: 100%;
      text-align: center;
    }
    .dodo {
      width: 220px;
      max-width: 80%;
      margin-bottom: 1.75rem;
    }
    h1 {
      font-size: 1.2rem;
      color: #333;
      margin-bottom: 1rem;
      line-height: 1.5;
    }
    p {
      color: #555;
      font-size: 0.95rem;
      line-height: 1.65;
      margin-bottom: 2rem;
    }
    .admin-link {
      font-size: 0.85rem;
      color: #888;
      border-top: 1px solid #eee;
      padding-top: 1.25rem;
    }
    .admin-link a {
      color: #2c5f8a;
      font-weight: bold;
      text-decoration: none;
    }
    .admin-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="box">
  <img class="dodo" src="https://sheepsite.com/Scripts/assets/Woolsy-dodo-transparent.png" alt="Woolsy the Dodo">
  <h1>The <?= $_suspLabel ?> website is unavailable at the moment because it needs to be renewed.</h1>
  <p>Please contact a board member to resolve the issue.</p>
  <div class="admin-link">
    If you are the administrator of this site, you can log in
    <a href="https://sheepsite.com/Scripts/admin.php?building=<?= urlencode($building) ?>">Here</a>.
  </div>
</div>
</body>
</html><?php
    exit;
}

unset($_suspCfgFile, $_suspCfg, $_suspLabel, $_isJson);
