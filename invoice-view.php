<?php
// -------------------------------------------------------
// invoice-view.php
// Renders an invoice as a clean HTML page for in-app viewing.
//
//   invoice-view.php?building=LyndhurstH&invoice=LyndhurstH-0001
//
// Auth: requires manage_auth_{building} OR master_admin_auth session.
// -------------------------------------------------------
session_start();

define('CONFIG_DIR',   __DIR__ . '/config/');
define('INVOICES_DIR', __DIR__ . '/invoices/');

$building  = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['building']  ?? '');
$invoiceId = trim($_GET['invoice'] ?? '');

if (!$building || !$invoiceId
    || !preg_match('/^[a-zA-Z0-9_-]+-\d{4}$/', $invoiceId)) {
  http_response_code(400); die('Invalid request.');
}

// Auth: building admin session OR master admin session
$authed = !empty($_SESSION['manage_auth_' . $building])
       || !empty($_SESSION['master_admin_auth']);
if (!$authed) {
  http_response_code(403); die('Not authorised.');
}

// Load invoice
$file = INVOICES_DIR . $building . '/' . $invoiceId . '.json';
if (!file_exists($file)) {
  http_response_code(404); die('Invoice not found.');
}
$inv   = json_decode(file_get_contents($file), true);
$label = ucwords(str_replace(['_', '-'], ' ', $building));

// Load contact email from config
$cfgFile      = CONFIG_DIR . $building . '.json';
$cfg          = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) ?? [] : [];
$contactEmail = $cfg['contactEmail'] ?? '';

$isPaid = ($inv['status'] ?? '') === 'paid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice <?= htmlspecialchars($invoiceId) ?></title>
  <style>
    * { box-sizing: border-box; }
    body      { font-family: Arial, sans-serif; max-width: 620px; margin: 1.5rem auto;
                padding: 0 1rem; font-size: 14px; color: #333; }
    .header   { display: flex; justify-content: space-between; align-items: flex-start;
                margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e0e0e0; }
    .brand    { font-size: 22px; font-weight: bold; color: #3a7a3a; line-height: 1.1; }
    .brand-sub{ font-size: 12px; color: #999; font-style: italic; }
    .inv-meta { text-align: right; font-size: 13px; color: #555; line-height: 1.9; }
    .inv-meta strong { color: #222; }
    .inv-title{ font-size: 22px; font-weight: bold; color: #222;
                letter-spacing: 0.03em; margin-bottom: 1.25rem; }
    .bill-to  { margin-bottom: 1.25rem; }
    .bill-to .lbl { font-size: 11px; font-weight: bold; color: #888;
                    text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
    table     { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
    th        { text-align: left; padding: 8px 10px; font-size: 12px; color: #555;
                font-weight: bold; border-bottom: 1px solid #ddd; background: #f0f7f0; }
    th:last-child { text-align: right; }
    td        { padding: 8px 10px; border-bottom: 1px solid #eee; }
    td:last-child { text-align: right; }
    .discount { color: #3a7a3a; }
    .total-wrap{ display: flex; justify-content: flex-end; margin-bottom: 1.25rem; }
    .total-box { width: 220px; border-top: 2px solid #333; padding-top: 6px; }
    .total-row { display: flex; justify-content: space-between;
                 font-weight: bold; font-size: 15px; color: #222; padding: 4px 10px; }
    .status-badge { display: inline-block; padding: 0.3rem 0.8rem; border-radius: 4px;
                    font-weight: bold; font-size: 13px; margin-bottom: 1.25rem; }
    .status-paid { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-open { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
    .footer   { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;
                font-size: 12px; color: #888; text-align: center; font-style: italic; }
  </style>
</head>
<body>

<div class="header">
  <div>
    <div class="brand">SheepSite</div>
    <div class="brand-sub">Powered by Sheep</div>
  </div>
  <div class="inv-meta">
    <strong>Invoice #:</strong> <?= htmlspecialchars($inv['id']) ?><br>
    <strong>Date:</strong> <?= htmlspecialchars($inv['date']) ?><br>
    <strong>Due:</strong> <?= htmlspecialchars($inv['dueDate'] ?? $inv['date']) ?>
  </div>
</div>

<div class="inv-title">INVOICE</div>

<div class="bill-to">
  <div class="lbl">Bill To</div>
  <div style="font-weight:bold;"><?= htmlspecialchars($label) ?></div>
  <?php if ($contactEmail): ?>
    <div style="color:#555;"><?= htmlspecialchars($contactEmail) ?></div>
  <?php endif; ?>
</div>

<?php if ($isPaid): ?>
  <span class="status-badge status-paid">&#10003; Paid <?= htmlspecialchars($inv['paidDate'] ?? '') ?>
    <?php if (!empty($inv['paymentMethod'])): ?>
      <span style="font-size:0.78rem;font-weight:normal;opacity:0.8;">
        — <?= htmlspecialchars(ucfirst($inv['paymentMethod'])) ?>
      </span>
    <?php endif; ?>
  </span>
<?php else: ?>
  <span class="status-badge status-open">&#9679; Unpaid</span>
<?php endif; ?>

<table>
  <thead>
    <tr><th>Description</th><th>Amount</th></tr>
  </thead>
  <tbody>
    <?php foreach ($inv['lineItems'] ?? [] as $item): ?>
    <tr>
      <td><?= htmlspecialchars($item['description']) ?></td>
      <td <?= $item['amount'] < 0 ? 'class="discount"' : '' ?>>
        <?= $item['amount'] < 0
          ? '&minus;$' . number_format(abs($item['amount']), 2)
          : '$' . number_format($item['amount'], 2) ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="total-wrap">
  <div class="total-box">
    <div class="total-row">
      <span>Total Due</span>
      <span>$<?= number_format($inv['total'], 2) ?></span>
    </div>
  </div>
</div>

<div class="footer">Thank you for your business!</div>

</body>
</html>
