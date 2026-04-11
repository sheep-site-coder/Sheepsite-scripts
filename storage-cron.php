<?php
// -------------------------------------------------------
// storage-cron.php
// Nightly cron job — refreshes storage usage for every
// building and saves results to config/{building}.json.
//
// Intended to be run via cPanel Cron Jobs:
//   php /path/to/Scripts/storage-cron.php
//
// Can also be triggered via HTTP with a secret token:
//   https://sheepsite.com/Scripts/storage-cron.php?token=CRON_TOKEN
//
// Schedule: once daily is sufficient (e.g. 3:00 AM)
// -------------------------------------------------------

define('CONFIG_DIR',  __DIR__ . '/config/');
define('CRON_TOKEN', 'sh33pCr0nN0jje59dd26');  // change this; used for HTTP-triggered runs only
require_once __DIR__ . '/storage/r2-storage.php';

// -------------------------------------------------------
// Auth: CLI always allowed; HTTP requires token
// -------------------------------------------------------
$isCli = (php_sapi_name() === 'cli');

set_time_limit(300);

if (!$isCli) {
  $tok = $_GET['token'] ?? '';
  if (!hash_equals(CRON_TOKEN, $tok)) {
    http_response_code(403);
    exit("Forbidden\n");
  }
  header('Content-Type: text/plain; charset=UTF-8');
  // Disable output buffering so lines stream to browser as they complete
  while (ob_get_level()) ob_end_clean();
  ob_implicit_flush(true);
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function log_line(string $msg): void {
  $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
  echo $line;
  // Also append to a log file
  $logFile = __DIR__ . '/storage-cron.log';
  file_put_contents($logFile, $line, FILE_APPEND);
}

require_once __DIR__ . '/invoice-helpers.php';

// -------------------------------------------------------
// Main loop
// -------------------------------------------------------
$buildings = require __DIR__ . '/buildings.php';
$pricingFile = CONFIG_DIR . 'pricing.json';
$pricing     = file_exists($pricingFile) ? json_decode(file_get_contents($pricingFile), true) ?? [] : [];

log_line('Storage cron started — ' . count($buildings) . ' building(s)');

$ok      = 0;
$failed  = 0;

$r2cfg = _r2Cfg();

foreach ($buildings as $key => $cfg) {
  $total = _r2SumBytes($key, $r2cfg);
  if ($total === null) {
    log_line("  $key — FAILED (R2 storage query)");
    $failed++;
    continue;
  }
  $cfgFile = CONFIG_DIR . $key . '.json';
  $saved   = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) ?? [] : [];
  $saved['storageUsed']    = $total;
  $saved['storageUpdated'] = date('c');
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents($cfgFile, json_encode($saved, JSON_PRETTY_PRINT));

  $mb = round($total / 1048576, 1);
  log_line("  $key — OK ({$mb} MB)");
  $ok++;

  // 30-day invoice trigger
  $renewalDate = $saved['renewalDate'] ?? null;
  if ($renewalDate) {
    $daysUntil = (int)((strtotime($renewalDate) - time()) / 86400);
    if ($daysUntil <= 30 && $daysUntil >= 0 && !unpaidInvoiceExists($key, $renewalDate)) {
      try {
        $inv = generateInvoice($key, $saved, $pricing);
        $inv['generatedBy'] = 'cron';
        // Re-save with generatedBy updated
        $invFile = INVOICES_DIR . $key . '/' . $inv['id'] . '.json';
        file_put_contents($invFile, json_encode($inv, JSON_PRETTY_PRINT));
        log_line("  $key — Invoice {$inv['id']} generated (\${$inv['total']})");
      } catch (Exception $e) {
        log_line("  $key — Invoice generation FAILED: " . $e->getMessage());
      }
    }

    // 10-day reminder — send once per unpaid renewal invoice
    if ($daysUntil <= 10 && $daysUntil >= 0) {
      $unpaidInv = getUnpaidRenewalInvoice($key, $renewalDate);
      if ($unpaidInv && empty($unpaidInv['reminderSent'])) {
        try {
          sendReminderEmail($unpaidInv, $saved);
          log_line("  $key — Reminder sent for {$unpaidInv['id']}");
        } catch (Exception $e) {
          log_line("  $key — Reminder FAILED: " . $e->getMessage());
        }
      }
    }
  }
}

log_line("Storage cron done — {$ok} ok, {$failed} failed");
