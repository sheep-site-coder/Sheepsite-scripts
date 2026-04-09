<?php
// -------------------------------------------------------
// cron-test.php — One-time diagnostic to find server paths
// for cPanel cron job configuration.
//
// Visit in browser to see paths.
// Run via cron to write a log entry.
// Delete once cron path is confirmed working.
// -------------------------------------------------------

$logFile  = __DIR__ . '/cron-test.log';
$isCli    = (php_sapi_name() === 'cli');
$ts       = date('Y-m-d H:i:s');
$phpBin   = PHP_BINARY;
$dir      = __DIR__;
$cwd      = getcwd();
$docRoot  = $_SERVER['DOCUMENT_ROOT'] ?? '(not set)';

$entry = "[{$ts}] OK | __DIR__={$dir} | cwd={$cwd} | php=" . PHP_BINARY . "\n";

if ($isCli) {
  // Write to log file so we can check after cron fires
  file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
  echo $entry;
} else {
  // Browser: show all the useful paths
  header('Content-Type: text/plain');
  echo "=== Sheepsite Cron Path Diagnostic ===\n\n";
  echo "__DIR__          : {$dir}\n";
  echo "getcwd()         : {$cwd}\n";
  echo "DOCUMENT_ROOT    : {$docRoot}\n";
  echo "PHP_BINARY       : {$phpBin}\n";
  echo "PHP version      : " . PHP_VERSION . "\n";
  echo "\n";
  echo "--- Suggested cron command ---\n";
  echo "{$phpBin} {$dir}/storage-cron.php\n";
  echo "\n";
  echo "--- Log file will appear at ---\n";
  echo "{$logFile}\n";
  echo "\n";
  echo "Set cPanel cron to run every minute:\n";
  echo "* * * * * {$phpBin} {$dir}/cron-test.php\n";
  echo "\n";
  echo "After a minute, check the log file via File Manager or:\n";
  echo "  tail {$logFile}\n";
}
