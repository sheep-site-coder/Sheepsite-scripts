<?php
// -------------------------------------------------------
// setup-admin.php
// ONE-TIME SETUP TOOL — delete from server after use.
//
// Creates hashed admin credential files for manage-users.php.
// Place on sheepsite.com/Scripts/, run once, then DELETE.
//
// Usage:
//   Set passwords in the $config array below, upload,
//   visit the page once to create the files, then delete.
// -------------------------------------------------------

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

// -------------------------------------------------------
// SET YOUR PASSWORDS HERE before uploading
// -------------------------------------------------------
$config = [
  'master' => [
    'user' => 'sheepsite',
    'pass' => 'CHANGE_ME',       // master password
  ],
  'buildings' => [
    'QGscratch'  => ['user' => 'admin', 'pass' => 'CHANGE_ME'],
    'LyndhurstH' => ['user' => 'admin', 'pass' => 'CHANGE_ME'],
    'LyndhurstI' => ['user' => 'admin', 'pass' => 'CHANGE_ME'],
  ],
];
// -------------------------------------------------------

$errors  = [];
$created = [];

// Safety check — refuse to run if passwords haven't been changed
foreach ($config['buildings'] as $b => $cred) {
  if ($cred['pass'] === 'CHANGE_ME') {
    $errors[] = "Password not set for $b.";
  }
}
if ($config['master']['pass'] === 'CHANGE_ME') {
  $errors[] = "Master password not set.";
}

if (!$errors) {
  if (!is_dir(CREDENTIALS_DIR)) mkdir(CREDENTIALS_DIR, 0755, true);

  // Write master credentials
  $masterFile = CREDENTIALS_DIR . '_master.json';
  $masterData = ['user' => $config['master']['user'], 'pass' => password_hash($config['master']['pass'], PASSWORD_DEFAULT)];
  file_put_contents($masterFile, json_encode($masterData, JSON_PRETTY_PRINT));
  $created[] = '_master.json';

  // Write per-building admin credentials
  foreach ($config['buildings'] as $building => $cred) {
    $data = ['user' => $cred['user'], 'pass' => password_hash($cred['pass'], PASSWORD_DEFAULT)];
    file_put_contents(CREDENTIALS_DIR . $building . '_admin.json', json_encode($data, JSON_PRETTY_PRINT));
    $created[] = $building . '_admin.json';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Setup</title>
  <style>
    body { font-family: sans-serif; max-width: 500px; margin: 3rem auto; padding: 0 1rem; }
    .ok    { background: #e6f4ea; color: #1a7f37; padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 0.5rem; }
    .error { background: #ffeef0; color: #c00;    padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 0.5rem; }
    .warn  { background: #fff8e1; color: #7a5800; padding: 0.8rem 0.9rem; border-radius: 4px; margin-top: 1.5rem; font-weight: bold; }
  </style>
</head>
<body>
  <h1>Admin Setup</h1>

  <?php if ($errors): ?>
    <?php foreach ($errors as $e): ?>
      <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <p>Edit the <code>$config</code> array in this file and set all passwords before running.</p>
  <?php else: ?>
    <?php foreach ($created as $f): ?>
      <div class="ok">Created: credentials/<?= htmlspecialchars($f) ?></div>
    <?php endforeach; ?>
    <div class="warn">&#9888; Delete this file from the server now.</div>
  <?php endif; ?>
</body>
</html>
