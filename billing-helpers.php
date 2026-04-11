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

define('BILLING_BASE_URL',    'https://sheepsite.com/Scripts/');
define('BILLING_TOKEN_TTL',   7 * 86400); // 7 days in seconds
define('BILLING_CONFIG_DIR',  __DIR__ . '/config/');
define('BILLING_PRICING_FILE', __DIR__ . '/config/pricing.json');

require_once __DIR__ . '/invoice-helpers.php';

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

  // Build line items and create open invoice before sending email
  $pricing  = file_exists(BILLING_PRICING_FILE) ? json_decode(file_get_contents(BILLING_PRICING_FILE), true) ?? [] : [];
  $lineItems = [];
  $total     = 0.0;

  $invoiceExtra = [];

  if ($type === 'woolsy') {
    $creditPrice  = (float)($pricing['creditPrice'] ?? 0);
    $qty          = 10; // default top-up
    $total        = round($creditPrice * $qty, 2);
    $lineItems    = [['description' => 'Woolsy Credits (' . $qty . ' credits)', 'amount' => $total]];
    $invoiceExtra = ['invoiceType' => 'woolsy', 'creditsToAdd' => $qty];
  } else { // storage
    $defaultLimit = (int)($pricing['storageDefaultLimit'] ?? 10737418240);
    $currentLimit = (int)($cfg['storageLimit'] ?? $defaultLimit);
    $renewalDate  = $cfg['renewalDate'] ?? null;
    $tiers        = $pricing['storageOptions'] ?? [];
    usort($tiers, fn($a, $b) => (int)($a['bytes'] ?? 0) <=> (int)($b['bytes'] ?? 0));
    foreach ($tiers as $tier) {
      $monthly = (float)($tier['pricePerMonth'] ?? 0);
      if ((int)($tier['bytes'] ?? 0) > $currentLimit && $monthly > 0) {
        $months       = $renewalDate ? max(1, (int)ceil((strtotime($renewalDate) - time()) / (30.44 * 86400))) : 12;
        $total        = round($monthly * $months, 2);
        $tierLabel    = $tier['label'] ?? (round($tier['bytes'] / 1073741824, 2) . ' GB');
        $lineItems    = [['description' => 'Storage Upgrade — ' . $tierLabel, 'amount' => $total]];
        $invoiceExtra = ['invoiceType' => 'storage', 'newBytes' => (int)$tier['bytes']];
        break;
      }
    }
    if (!$lineItems) {
      $lineItems    = [['description' => 'Storage Upgrade', 'amount' => 0]];
      $invoiceExtra = ['invoiceType' => 'storage'];
    }
  }

  $openInvoice = createOpenInvoice($building, $lineItems, $total, 'auto', $invoiceExtra);

  $token      = generateBillingToken($building, $type, $cfg);
  $cfg['billingToken']['invoiceId'] = $openInvoice['id'];
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
    $subject = "[$label] Invoice";
    $body    = "Your building's file storage has reached its limit.\n\n"
             . "File uploads are currently blocked. To add more storage:\n\n"
             . "  $billingUrl\n\n"
             . "This link expires in 7 days. Storage is upgraded immediately on\n"
             . "payment confirmation.\n\n"
             . "— SheepSite";
    $flag    = 'storageLimitEmailSent';
  }

  $headers = implode("\r\n", [
    'From: SheepSite.com <sheepsite@sheepsite.com>',
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
