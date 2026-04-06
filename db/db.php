<?php
// -------------------------------------------------------
// db/db.php — PDO connection helper
//
// Credentials are stored in credentials/db.json (gitignored):
//   {
//     "host":   "localhost",
//     "dbname": "qgscrmoq_unitsdb",
//     "user":   "qgscrmoq_unitsdb",
//     "pass":   "..."
//   }
// -------------------------------------------------------

function getDB(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $credFile = __DIR__ . '/../credentials/db.json';
  if (!file_exists($credFile)) {
    throw new RuntimeException('Database credentials file not found: credentials/db.json');
  }
  $cfg = json_decode(file_get_contents($credFile), true);
  if (!$cfg) {
    throw new RuntimeException('Invalid database credentials file.');
  }

  $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4';
  $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);
  return $pdo;
}
