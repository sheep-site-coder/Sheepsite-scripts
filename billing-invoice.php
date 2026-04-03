<?php
// -------------------------------------------------------
// billing-invoice.php
// Customer-facing invoice payment page.
//
//   billing-invoice.php?building=LyndhurstH&invoice=LyndhurstH-0001&token=XYZ
//
// Auth: token validated against invoices/{building}/{id}.json paymentToken
// No session required — the token IS the auth.
//
// Flow (Stripe configured):
//   GET  → show invoice + "Pay with Card →"
//   POST → create Stripe Checkout session → redirect to Stripe
//   Stripe webhook (billing-webhook.php, type=invoice) → markInvoicePaid()
//
// Flow (Stripe NOT configured — test/fake mode):
//   GET  → show invoice + "Fake Pay" button
//   POST → markInvoicePaid() directly → redirect to billing-success.php
// -------------------------------------------------------

define('CONFIG_DIR',   __DIR__ . '/config/');
define('INVOICES_DIR', __DIR__ . '/invoices/');
define('SCRIPTS_URL',  'https://sheepsite.com/Scripts/');

require_once __DIR__ . '/invoice-helpers.php';

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function loadBldConfig(string $building): array {
  $file = CONFIG_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function loadStripeConfig(): array {
  $file = CONFIG_DIR . 'stripe.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function loadInvoiceFile(string $building, string $invoiceId): ?array {
  if (!preg_match('/^[a-zA-Z0-9_-]+-\d{4}$/', $invoiceId)) return null;
  $file = INVOICES_DIR . $building . '/' . $invoiceId . '.json';
  if (!file_exists($file)) return null;
  return json_decode(file_get_contents($file), true) ?? null;
}

function createStripeInvoiceSession(string $secretKey, array $params): array {
  $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($params),
    CURLOPT_USERPWD        => $secretKey . ':',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($err) return ['error' => 'Connection error: ' . $err];
  $data = json_decode($resp ?: '', true);
  if (!$data)             return ['error' => 'Invalid response from Stripe'];
  if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Stripe error'];
  if (empty($data['url']))   return ['error' => 'No checkout URL returned'];
  return ['url' => $data['url']];
}

// -------------------------------------------------------
// Validate params
// -------------------------------------------------------
$building  = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['building']  ?? '');
$invoiceId = trim($_GET['invoice'] ?? '');
$token     = trim($_GET['token']   ?? '');

if (!$building || !$invoiceId || !$token) {
  http_response_code(400);
  die('<p style="color:red;font-family:sans-serif;">Invalid link. Please use the link from your billing email.</p>');
}

// -------------------------------------------------------
// Load and validate invoice
// -------------------------------------------------------
$invoice = loadInvoiceFile($building, $invoiceId);

if (!$invoice) {
  http_response_code(404);
  die('<p style="color:red;font-family:sans-serif;">Invoice not found.</p>');
}

$storedToken = $invoice['paymentToken'] ?? '';
$tokenValid  = $storedToken && hash_equals($storedToken, $token);

if (!$tokenValid) {
  http_response_code(403);
?><!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Link Invalid</title>
<style>body{font-family:sans-serif;max-width:500px;margin:4rem auto;padding:0 1rem;text-align:center;}
h1{font-size:1.4rem;}p{color:#555;}</style></head>
<body>
<h1>Link Invalid</h1>
<p>This payment link is invalid or has expired.</p>
<p>If you need to make a payment, please contact <a href="mailto:admin@sheepsite.com">admin@sheepsite.com</a>.</p>
</body></html>
<?php
  exit;
}

// -------------------------------------------------------
// Load supporting data
// -------------------------------------------------------
$cfg         = loadBldConfig($building);
$stripe      = loadStripeConfig();
$stripeKey   = $stripe['secretKey'] ?? '';
$stripeReady = (bool)$stripeKey;
$label       = ucwords(str_replace(['_', '-'], ' ', $building));
$alreadyPaid = ($invoice['status'] ?? '') === 'paid';

// -------------------------------------------------------
// POST — process payment
// -------------------------------------------------------
$postError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyPaid) {

  // ---- Fake pay (test mode — Stripe not configured) ----
  if (!$stripeReady || isset($_POST['fake_pay'])) {
    if (markInvoicePaid($building, $invoiceId)) {
      header('Location: ' . SCRIPTS_URL . 'billing-success.php?' . http_build_query([
        'building' => $building,
        'type'     => $invoice['invoiceType'] ?? 'other',
        'invoice'  => $invoiceId,
      ]));
      exit;
    }
    $postError = 'Payment processing failed. Please try again or contact SheepSite.';

  // ---- Real Stripe checkout ----
  } else {
    $totalCents = (int)round($invoice['total'] * 100);
    if ($totalCents <= 0) {
      $postError = 'Invoice total is $0 — nothing to charge.';
    } else {
      $successUrl = SCRIPTS_URL . 'billing-success.php?' . http_build_query([
        'building' => $building,
        'type'     => $invoice['invoiceType'] ?? 'other',
        'invoice'  => $invoiceId,
      ]);
      $cancelUrl = SCRIPTS_URL . 'billing-invoice.php?' . http_build_query([
        'building' => $building,
        'invoice'  => $invoiceId,
        'token'    => $token,
      ]);
      $params = [
        'mode'                                                  => 'payment',
        'success_url'                                           => $successUrl . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'                                            => $cancelUrl,
        'line_items[0][price_data][currency]'                   => 'usd',
        'line_items[0][price_data][unit_amount]'                => $totalCents,
        'line_items[0][price_data][product_data][name]'         => 'Annual Renewal — ' . $label,
        'line_items[0][price_data][product_data][description]'  => 'Invoice ' . $invoiceId . ' — due ' . ($invoice['dueDate'] ?? $invoice['date']),
        'line_items[0][quantity]'                               => 1,
        'metadata[building]'                                    => $building,
        'metadata[type]'                                        => 'invoice',
        'metadata[invoice_id]'                                  => $invoiceId,
        'customer_email'                                        => $cfg['contactEmail'] ?? '',
      ];
      $result = createStripeInvoiceSession($stripeKey, $params);
      if (isset($result['url'])) {
        header('Location: ' . $result['url']);
        exit;
      }
      $postError = $result['error'] ?? 'Unknown error';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice <?= htmlspecialchars($invoiceId) ?> — SheepSite</title>
  <style>
    * { box-sizing: border-box; }
    body        { font-family: sans-serif; max-width: 580px; margin: 3rem auto; padding: 0 1.25rem; }
    h1          { font-size: 1.5rem; margin-bottom: 0.25rem; }
    .subtitle   { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    .meta-row   { display: flex; gap: 2.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .meta-item  { font-size: 0.875rem; }
    .meta-item span { display: block; color: #888; font-size: 0.78rem; margin-bottom: 0.15rem; }
    table       { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
    th          { text-align: left; padding: 8px 10px; font-size: 0.8rem; color: #777;
                  text-transform: uppercase; letter-spacing: 0.04em;
                  border-bottom: 2px solid #ddd; background: #f8f8f8; }
    th:last-child { text-align: right; }
    td          { padding: 10px; font-size: 0.9rem; color: #333; border-bottom: 1px solid #eee; }
    td:last-child { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #333; border-bottom: none;
                    font-size: 1rem; color: #111; }
    .discount   { color: #3a7a3a; }
    .paid-badge { display: inline-block; background: #d4edda; color: #155724;
                  border: 1px solid #c3e6cb; border-radius: 4px;
                  padding: 0.3rem 0.8rem; font-size: 0.85rem; font-weight: bold; margin-bottom: 1.5rem; }
    .btn        { display: block; width: 100%; padding: 0.75rem; font-size: 1rem;
                  border: none; border-radius: 5px; cursor: pointer; margin-top: 1rem; }
    .btn-pay    { background: #0070f3; color: #fff; }
    .btn-pay:hover { background: #005bb5; }
    .btn-fake   { background: #6c757d; color: #fff; }
    .btn-fake:hover { background: #545b62; }
    .error      { background: #ffeef0; color: #c00; padding: 0.65rem 0.85rem;
                  border-radius: 4px; font-size: 0.9rem; margin-bottom: 1rem; }
    .test-banner { background: #fff3cd; color: #856404; border: 1px solid #ffc107;
                   border-radius: 5px; padding: 0.6rem 0.9rem; font-size: 0.82rem;
                   margin-bottom: 1.25rem; }
    .stripe-note { font-size: 0.78rem; color: #888; text-align: center; margin-top: 0.75rem; }
  </style>
</head>
<body>

<div style="text-align:center;margin-bottom:1.5rem;">
  <img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" alt="SheepSite" height="70">
</div>

<h1>Invoice <?= htmlspecialchars($invoiceId) ?></h1>
<div class="subtitle">
  <?= htmlspecialchars($label) ?> &mdash;
  Due <?= htmlspecialchars($invoice['dueDate'] ?? $invoice['date']) ?>
</div>

<?php if ($alreadyPaid): ?>
  <div class="paid-badge">&#10003; Paid on <?= htmlspecialchars($invoice['paidDate'] ?? '—') ?></div>
  <p style="color:#555;font-size:0.9rem;">This invoice has already been paid. Thank you!</p>
<?php else: ?>

  <?php if ($postError): ?>
    <div class="error"><?= htmlspecialchars($postError) ?></div>
  <?php endif; ?>

  <?php if (!$stripeReady): ?>
    <div class="test-banner">
      <strong>Test mode</strong> — Stripe is not configured. Use "Fake Pay" to simulate a completed payment and test the full end-to-end flow including receipt email and renewal date advance.
    </div>
  <?php endif; ?>

  <!-- Invoice line items -->
  <table>
    <thead>
      <tr><th>Description</th><th>Amount</th></tr>
    </thead>
    <tbody>
      <?php foreach ($invoice['lineItems'] as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['description']) ?></td>
          <td <?= $item['amount'] < 0 ? 'class="discount"' : '' ?>>
            <?= $item['amount'] < 0
              ? '&minus;$' . number_format(abs($item['amount']), 2)
              : '$' . number_format($item['amount'], 2) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td>Total Due</td>
        <td>$<?= number_format($invoice['total'], 2) ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Payment form -->
  <form method="post">
    <?php if ($stripeReady): ?>
      <button type="submit" class="btn btn-pay">Pay $<?= number_format($invoice['total'], 2) ?> with Card &rarr;</button>
      <p class="stripe-note">Powered by Stripe. You will be redirected to a secure checkout page.</p>
    <?php else: ?>
      <button type="submit" name="fake_pay" value="1" class="btn btn-fake">
        Fake Pay $<?= number_format($invoice['total'], 2) ?> &rarr;
      </button>
      <p class="stripe-note">Test mode — no real payment is processed.</p>
    <?php endif; ?>
  </form>

<?php endif; ?>

</body>
</html>
