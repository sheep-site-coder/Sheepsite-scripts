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
    * { box-sizing: border-box; }
    body { font-family: sans-serif; background: #f5f5f5; margin: 0;
           display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .box { background: #fff; border: 1px solid #ddd; border-radius: 10px;
           padding: 2.5rem 2rem; max-width: 480px; width: 100%; text-align: center; }
    h1   { font-size: 1.25rem; margin: 0 0 0.75rem; color: #333; }
    p    { color: #555; font-size: 0.95rem; line-height: 1.6; margin: 0; }
  </style>
</head>
<body>
<div class="box">
  <h1>Service Unavailable</h1>
  <p>Online services for <strong><?= $_suspLabel ?></strong> are currently unavailable.<br><br>
     Please contact your building&rsquo;s board of directors for assistance.</p>
</div>
</body>
</html><?php
    exit;
}

unset($_suspCfgFile, $_suspCfg, $_suspLabel, $_isJson);
