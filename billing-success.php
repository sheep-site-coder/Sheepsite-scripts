<?php
// -------------------------------------------------------
// billing-success.php
// Stripe post-payment landing page.
// The actual allocation update happens in billing-webhook.php.
// This page just shows a confirmation message.
// -------------------------------------------------------

$building = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['building'] ?? '');
$type     = in_array($_GET['type'] ?? '', ['woolsy', 'storage', 'invoice']) ? $_GET['type'] : '';
$label    = $building ? ucwords(str_replace(['_', '-'], ' ', $building)) : 'Your building';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Received — SheepSite</title>
  <style>
    * { box-sizing: border-box; }
    body  { font-family: sans-serif; max-width: 500px; margin: 5rem auto; padding: 0 1.25rem; text-align: center; }
    .icon { font-size: 3rem; margin-bottom: 1rem; }
    h1    { font-size: 1.5rem; margin-bottom: 0.5rem; }
    p     { color: #555; line-height: 1.5; }
    .note { font-size: 0.85rem; color: #888; margin-top: 2rem; }
  </style>
</head>
<body>
  <div class="icon">✓</div>
  <h1>Payment received — thank you!</h1>

  <?php if ($type === 'invoice'): ?>
    <p>Your annual renewal payment for <strong><?= htmlspecialchars($label) ?></strong> has been received.
       Your receipt has been emailed and your renewal date has been updated.</p>
  <?php elseif ($type === 'woolsy'): ?>
    <p>Your Woolsy credits for <strong><?= htmlspecialchars($label) ?></strong> are being applied now
       and will be active within a few minutes.</p>
  <?php elseif ($type === 'storage'): ?>
    <p>Your storage upgrade for <strong><?= htmlspecialchars($label) ?></strong> is being applied now
       and will be active within a few minutes.</p>
  <?php else: ?>
    <p>Your payment for <strong><?= htmlspecialchars($label) ?></strong> has been received
       and will be applied shortly.</p>
  <?php endif; ?>

  <p class="note">You can close this tab. If you have any questions, contact your SheepSite administrator.</p>
</body>
</html>
