<?php
// -------------------------------------------------------
// billing-helpers.php
// Shared helpers for billing email triggers.
// Included by chatbot.php, file-manager.php, etc.
//
// Provides:
//   checkWoolsyThreshold($building, $used, $allocated)
//   checkStorageThreshold($building)
//
// Both are no-ops if the email-sent flag is already set,
// or if no contactEmail is configured for the building.
// -------------------------------------------------------

define('BILLING_BASE_URL',   'https://sheepsite.com/Scripts/');
define('BILLING_TOKEN_TTL',  7 * 86400); // 7 days in seconds
define('BILLING_CONFIG_DIR', __DIR__ . '/config/');

// -------------------------------------------------------
// Config helpers
// -------------------------------------------------------
function loadBillingBuildingConfig(string $building): array {
  $file = BILLING_CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveBillingBuildingConfig(string $building, array $cfg): void {
  if (!is_dir(BILLING_CONFIG_DIR)) mkdir(BILLING_CONFIG_DIR, 0755, true);
  file_put_contents(BILLING_CONFIG_DIR . $building . '.json', json_encode($cfg, JSON_PRETTY_PRINT));
}

// -------------------------------------------------------
// Token generation
// Generates a random token, stores it in building config.
// Overwrites any previous token for the same building.
// -------------------------------------------------------
function generateBillingToken(string $building, string $type, array &$cfg): string {
  $token = bin2hex(random_bytes(32));
  $cfg['billingToken'] = [
    'token'   => $token,
    'type'    => $type,
    'expires' => date('c', time() + BILLING_TOKEN_TTL),
  ];
  return $token;
}

// -------------------------------------------------------
// Email send
// Composes and sends the billing email for $type (woolsy|storage).
// Modifies $cfg: sets email-sent flag and stores the token.
// Returns true if mail() accepted the message.
// -------------------------------------------------------
function sendBillingEmail(string $building, string $type, array &$cfg): bool {
  $contactEmail = $cfg['contactEmail'] ?? '';
  if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) return false;

  $token      = generateBillingToken($building, $type, $cfg);
  $billingUrl = BILLING_BASE_URL . 'billing.php?'
              . http_build_query(['building' => $building, 'type' => $type, 'token' => $token]);

  $label = ucwords(str_replace(['_', '-'], ' ', $building));

  if ($type === 'woolsy') {
    $subject = "[$label] Woolsy credit top-up needed";
    $body    = "Your building's Woolsy AI credits are at 90% usage.\n\n"
             . "To keep Woolsy available to your residents, please top up your credits:\n\n"
             . "  $billingUrl\n\n"
             . "This link expires in 7 days. Once payment is confirmed, credits are\n"
             . "applied automatically.\n\n"
             . "— SheepSite";
    $flag    = 'woolsyBillingEmailSent';
  } else {
    $subject = "[$label] Storage limit reached — uploads blocked";
    $body    = "Your building's file storage has reached its limit.\n\n"
             . "File uploads are currently blocked. To add more storage:\n\n"
             . "  $billingUrl\n\n"
             . "This link expires in 7 days. Storage is upgraded immediately on\n"
             . "payment confirmation.\n\n"
             . "— SheepSite";
    $flag    = 'storageLimitEmailSent';
  }

  $headers = implode("\r\n", [
    'From: SheepSite.com <noreply@sheepsite.com>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: SheepSite/1.0',
  ]);

  $sent = mail($contactEmail, $subject, $body, $headers);
  if ($sent) {
    $cfg[$flag] = true;
  }
  return $sent;
}

// -------------------------------------------------------
// Check Woolsy 90% threshold.
// Call after deducting credits. Reads fresh config from disk.
// -------------------------------------------------------
function checkWoolsyThreshold(string $building, float $used, float $allocated): void {
  if ($allocated <= 0 || $used <= 0) return;
  if (($used / $allocated) < 0.90) return;

  $cfg = loadBillingBuildingConfig($building);
  if (!empty($cfg['woolsyBillingEmailSent'])) return;

  sendBillingEmail($building, 'woolsy', $cfg);
  saveBillingBuildingConfig($building, $cfg);
}

// -------------------------------------------------------
// Check storage limit.
// Call when an upload is blocked by the limit check.
// -------------------------------------------------------
function checkStorageThreshold(string $building): void {
  $cfg = loadBillingBuildingConfig($building);
  if (!empty($cfg['storageLimitEmailSent'])) return;

  sendBillingEmail($building, 'storage', $cfg);
  saveBillingBuildingConfig($building, $cfg);
}
