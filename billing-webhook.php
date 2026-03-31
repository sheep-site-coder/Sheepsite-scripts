<?php
// -------------------------------------------------------
// billing-webhook.php
// Stripe webhook handler — updates allocations after payment.
//
//   POST https://sheepsite.com/Scripts/billing-webhook.php
//
// Configure in Stripe Dashboard:
//   Endpoint URL: above
//   Events to listen for: checkout.session.completed
//
// Webhook signing secret stored in config/stripe.json:
//   { "secretKey": "sk_...", "webhookSecret": "whsec_..." }
//
// Metadata expected on the Checkout Session:
//   building       — building key (e.g. "LyndhurstH")
//   type           — "woolsy" or "storage"
//   credits_to_add — integer (woolsy only)
//   new_bytes      — integer bytes of new storage limit (storage only)
// -------------------------------------------------------

define('CONFIG_DIR', __DIR__ . '/config/');

// -------------------------------------------------------
// Only accept POST
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit;
}

// -------------------------------------------------------
// Load Stripe config
// -------------------------------------------------------
$stripeFile = CONFIG_DIR . 'stripe.json';
if (!file_exists($stripeFile)) {
  http_response_code(500);
  error_log('billing-webhook.php: config/stripe.json not found');
  exit;
}
$stripe        = json_decode(file_get_contents($stripeFile), true) ?? [];
$secretKey     = $stripe['secretKey']     ?? '';
$webhookSecret = $stripe['webhookSecret'] ?? '';

if (!$secretKey || !$webhookSecret) {
  http_response_code(500);
  error_log('billing-webhook.php: Stripe keys not configured');
  exit;
}

// -------------------------------------------------------
// Read raw body + verify Stripe signature
// -------------------------------------------------------
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!verifyStripeSignature($payload, $sigHeader, $webhookSecret)) {
  http_response_code(400);
  error_log('billing-webhook.php: signature verification failed');
  exit;
}

// -------------------------------------------------------
// Parse event
// -------------------------------------------------------
$event = json_decode($payload, true);
if (!$event || ($event['type'] ?? '') !== 'checkout.session.completed') {
  // Not our event type — acknowledge and ignore
  http_response_code(200);
  echo json_encode(['ignored' => true]);
  exit;
}

$session  = $event['data']['object'] ?? [];
$meta     = $session['metadata']     ?? [];
$building = $meta['building']        ?? '';
$type     = $meta['type']            ?? '';

if (!$building || !preg_match('/^[a-zA-Z0-9_-]+$/', $building)
  || !in_array($type, ['woolsy', 'storage'], true)) {
  http_response_code(200); // Don't retry — bad metadata
  error_log("billing-webhook.php: invalid building=$building type=$type");
  exit;
}

// -------------------------------------------------------
// Idempotency: check payment_intent already processed
// -------------------------------------------------------
$paymentIntentId = $session['payment_intent'] ?? ($session['id'] ?? '');
$processedFile   = CONFIG_DIR . 'processed_payments.json';
$processed       = file_exists($processedFile)
                 ? json_decode(file_get_contents($processedFile), true) ?? []
                 : [];

if ($paymentIntentId && isset($processed[$paymentIntentId])) {
  http_response_code(200);
  echo json_encode(['already_processed' => true]);
  exit;
}

// -------------------------------------------------------
// Apply the allocation update
// -------------------------------------------------------
$ok      = false;
$logNote = '';

if ($type === 'woolsy') {
  $creditsToAdd = (int)($meta['credits_to_add'] ?? 0);
  if ($creditsToAdd > 0) {
    $credFile    = __DIR__ . '/faqs/woolsy_credits.json';
    $allCredits  = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) ?? [] : [];
    if (!isset($allCredits[$building])) {
      $allCredits[$building] = ['allocated' => 1.0, 'used' => 0.0];
    }
    $allCredits[$building]['allocated'] = round($allCredits[$building]['allocated'] + $creditsToAdd, 4);
    file_put_contents($credFile, json_encode($allCredits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Clear the email-sent flag so the 90% trigger can fire again next time
    $bCfg = loadBuildingConfig($building);
    unset($bCfg['woolsyBillingEmailSent']);
    // Also clear billing token (one-time use)
    unset($bCfg['billingToken']);
    saveBuildingConfig($building, $bCfg);

    $ok      = true;
    $logNote = "woolsy +$creditsToAdd credits for $building";
  }

} else { // storage
  $newBytes = (int)($meta['new_bytes'] ?? 0);
  if ($newBytes > 0) {
    $bCfg                    = loadBuildingConfig($building);
    $bCfg['storageLimit']    = $newBytes;
    // Clear the email-sent flag and billing token
    unset($bCfg['storageLimitEmailSent']);
    unset($bCfg['billingToken']);
    saveBuildingConfig($building, $bCfg);

    $ok      = true;
    $logNote = "storage limit set to $newBytes bytes for $building";
  }
}

// -------------------------------------------------------
// Mark as processed (idempotency)
// -------------------------------------------------------
if ($ok && $paymentIntentId) {
  $processed[$paymentIntentId] = [
    'building'  => $building,
    'type'      => $type,
    'processed' => date('c'),
  ];
  file_put_contents($processedFile, json_encode($processed, JSON_PRETTY_PRINT));
}

if ($ok) {
  error_log("billing-webhook.php: success — $logNote");
  http_response_code(200);
  echo json_encode(['ok' => true]);
} else {
  http_response_code(200); // Don't retry — update failed due to bad data
  error_log("billing-webhook.php: update failed — building=$building type=$type meta=" . json_encode($meta));
  echo json_encode(['ok' => false]);
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function loadBuildingConfig(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveBuildingConfig(string $building, array $cfg): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . $building . '.json', json_encode($cfg, JSON_PRETTY_PRINT));
}

// Verify Stripe webhook signature (Stripe-Signature header).
// See: https://stripe.com/docs/webhooks/signatures
function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool {
  if (!$sigHeader || !$secret) return false;

  // Parse t= and v1= from header
  $parts    = explode(',', $sigHeader);
  $ts       = '';
  $sigFound = '';
  foreach ($parts as $part) {
    $part = trim($part);
    if (str_starts_with($part, 't='))  $ts       = substr($part, 2);
    if (str_starts_with($part, 'v1=')) $sigFound = substr($part, 3);
  }

  if (!$ts || !$sigFound) return false;

  // Replay attack: reject if timestamp is more than 5 minutes old
  if (abs(time() - (int)$ts) > 300) return false;

  $signed   = $ts . '.' . $payload;
  $expected = hash_hmac('sha256', $signed, $secret);

  return hash_equals($expected, $sigFound);
}
