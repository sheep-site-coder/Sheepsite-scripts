<?php
// -------------------------------------------------------
// billing-success.php
// Stripe post-payment landing page.
// The actual allocation update happens in billing-webhook.php.
// This page just shows a confirmation message.
// -------------------------------------------------------

$building = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['building'] ?? '');
$type     = preg_replace('/[^a-z_]/', '', $_GET['type'] ?? '');
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
    h1    { font-size: 1.5rem; margin-bottom: 0.5rem; }
    p     { color: #555; line-height: 1.5; }
    .note { font-size: 0.85rem; color: #888; margin-top: 2rem; }
    @keyframes dance {
      0%   { transform: translateY(0)    rotate(0deg);  }
      20%  { transform: translateY(-18px) rotate(-8deg); }
      40%  { transform: translateY(-10px) rotate(6deg);  }
      60%  { transform: translateY(-18px) rotate(-5deg); }
      80%  { transform: translateY(-6px)  rotate(4deg);  }
      100% { transform: translateY(0)    rotate(0deg);  }
    }
    .woolsy { animation: dance 1s ease-in-out 3; margin-bottom: 1.25rem; display: inline-block; }
  </style>
</head>
<body>
  <div>
    <img src="https://sheepsite.com/Scripts/assets/Woolsy-danse-transparent.png"
         alt="Woolsy" height="110" class="woolsy">
  </div>
  <h1>Payment received — thank you!</h1>

  <?php if ($type === 'renewal'): ?>
    <p>Your annual renewal payment for <strong><?= htmlspecialchars($label) ?></strong> has been received.
       Your receipt has been emailed and your renewal date has been updated.</p>
  <?php elseif ($type === 'woolsy'): ?>
    <p>Your Woolsy credits for <strong><?= htmlspecialchars($label) ?></strong> are being applied now
       and will be active within a few minutes.</p>
  <?php elseif ($type === 'storage'): ?>
    <p>Your storage upgrade for <strong><?= htmlspecialchars($label) ?></strong> is being applied now
       and will be active within a few minutes.</p>
  <?php else: ?>
    <p>Your payment for <strong><?= htmlspecialchars($label) ?></strong> has been received.
       A receipt has been emailed to you.</p>
  <?php endif; ?>

  <p class="note">You can close this tab. If you have any questions, contact your SheepSite administrator.</p>

<script>
  if (window.opener && !window.opener.closed) {
    window.opener.location.reload();
  }
</script>
</body>
</html>
