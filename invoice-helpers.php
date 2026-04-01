<?php
// -------------------------------------------------------
// invoice-helpers.php
// Shared invoice generation, storage, and email helpers.
//
// Used by building-detail.php (manual) and
// storage-cron.php (auto 30-day trigger).
// -------------------------------------------------------

defined('INVOICES_DIR') || define('INVOICES_DIR', __DIR__ . '/invoices/');
defined('CONFIG_DIR')   || define('CONFIG_DIR',   __DIR__ . '/config/');

// -------------------------------------------------------
// Invoice numbering
// -------------------------------------------------------
function nextInvoiceSeq(string $building): int {
  $dir = INVOICES_DIR . $building . '/';
  if (!is_dir($dir)) return 1;
  $max = 0;
  foreach (glob($dir . '*.json') as $f) {
    $inv = json_decode(file_get_contents($f), true) ?? [];
    $max = max($max, (int)($inv['seq'] ?? 0));
  }
  return $max + 1;
}

function makeInvoiceId(string $building, int $seq): string {
  return $building . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// -------------------------------------------------------
// Check whether an unpaid invoice already exists for
// the current renewal period (prevents duplicates from cron)
// -------------------------------------------------------
function unpaidInvoiceExists(string $building, string $renewalDate): bool {
  $dir = INVOICES_DIR . $building . '/';
  if (!is_dir($dir)) return false;
  foreach (glob($dir . '*.json') as $f) {
    $inv = json_decode(file_get_contents($f), true) ?? [];
    if (($inv['status'] ?? '') === 'unpaid' && ($inv['renewalDate'] ?? '') === $renewalDate) {
      return true;
    }
  }
  return false;
}

// -------------------------------------------------------
// Build line items from building config + pricing
// -------------------------------------------------------
function buildLineItems(array $bldCfg, array $pricing): array {
  $siteFee     = (float)($pricing['siteMonthlyPrice'] ?? 0) * 12;
  $discountPct = (float)($bldCfg['discountPct'] ?? 0);
  $items       = [];

  if ($siteFee > 0) {
    $items[] = ['description' => 'Annual site fee (monthly × 12)', 'amount' => round($siteFee, 2)];
  }

  if ($discountPct > 0 && $siteFee > 0) {
    $discountAmt = round($siteFee * $discountPct / 100, 2);
    $items[]     = ['description' => 'Discount (' . $discountPct . '%)', 'amount' => -$discountAmt];
  }

  if (!empty($bldCfg['hasDomain'])) {
    $domainFee = (float)($pricing['domainAnnualPrice'] ?? 0);
    if ($domainFee > 0) {
      $items[] = ['description' => 'Domain renewal', 'amount' => round($domainFee, 2)];
    }
  }

  // Storage upgrade — only if above default limit and matches a priced tier
  $storageLimit = (int)($bldCfg['storageLimit'] ?? 0);
  $defaultLimit = (int)($pricing['storageDefaultLimit'] ?? 524288000);
  if ($storageLimit > $defaultLimit) {
    foreach ($pricing['storageOptions'] ?? [] as $tier) {
      if ((int)$tier['bytes'] === $storageLimit && (float)($tier['pricePerMonth'] ?? 0) > 0) {
        $items[] = [
          'description' => 'Storage upgrade (' . $tier['label'] . ')',
          'amount'      => round((float)$tier['pricePerMonth'] * 12, 2),
        ];
        break;
      }
    }
  }

  return $items;
}

// -------------------------------------------------------
// Generate and save an invoice, then email it
// Returns the invoice array, or throws on failure.
// -------------------------------------------------------
function generateInvoice(string $building, array $bldCfg, array $pricing): array {
  $dir = INVOICES_DIR . $building . '/';
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    // Block direct web access to invoice files
    file_put_contents($dir . '.htaccess', "Deny from all\n");
  }

  $seq   = nextInvoiceSeq($building);
  $id    = makeInvoiceId($building, $seq);
  $items = buildLineItems($bldCfg, $pricing);
  $total = array_sum(array_column($items, 'amount'));

  $invoice = [
    'id'          => $id,
    'seq'         => $seq,
    'building'    => $building,
    'date'        => date('Y-m-d'),
    'dueDate'     => date('Y-m-d', strtotime('+30 days')),
    'renewalDate' => $bldCfg['renewalDate'] ?? null,
    'status'      => 'unpaid',
    'lineItems'   => $items,
    'total'       => round($total, 2),
    'paidDate'    => null,
    'generatedBy' => 'manual', // overwritten to 'cron' when triggered by cron
  ];

  file_put_contents($dir . $id . '.json', json_encode($invoice, JSON_PRETTY_PRINT));
  sendInvoiceEmail($invoice, $bldCfg);

  return $invoice;
}

// -------------------------------------------------------
// Mark an invoice as paid, advance renewalDate, send receipt
// -------------------------------------------------------
function markInvoicePaid(string $building, string $invoiceId): bool {
  // Validate invoice ID to prevent path traversal
  if (!preg_match('/^[a-zA-Z0-9_-]+-\d{4}$/', $invoiceId)) return false;

  $file = INVOICES_DIR . $building . '/' . $invoiceId . '.json';
  if (!file_exists($file)) return false;

  $invoice             = json_decode(file_get_contents($file), true);
  $invoice['status']   = 'paid';
  $invoice['paidDate'] = date('Y-m-d');
  file_put_contents($file, json_encode($invoice, JSON_PRETTY_PRINT));

  // Advance renewalDate by exactly 1 year from the current renewal date
  $cfgFile = CONFIG_DIR . $building . '.json';
  $cfg     = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) ?? [] : [];
  $current = $cfg['renewalDate'] ?? date('Y-m-d');
  $cfg['renewalDate'] = date('Y-m-d', strtotime($current . ' +1 year'));
  unset($cfg['suspended']); // clear any suspension
  file_put_contents($cfgFile, json_encode($cfg, JSON_PRETTY_PRINT));

  sendReceiptEmail($invoice, $cfg);
  return true;
}

// -------------------------------------------------------
// Load all invoices for a building, newest first
// -------------------------------------------------------
function loadInvoices(string $building): array {
  $dir = INVOICES_DIR . $building . '/';
  if (!is_dir($dir)) return [];
  $invoices = [];
  foreach (glob($dir . '*.json') as $f) {
    $inv = json_decode(file_get_contents($f), true);
    if ($inv) $invoices[] = $inv;
  }
  usort($invoices, fn($a, $b) => strcmp($b['date'], $a['date']));
  return $invoices;
}

// -------------------------------------------------------
// Shared HTML email wrapper
// -------------------------------------------------------
function invoiceEmailHtml(string $bodyContent): string {
  $logo = 'https://sheepsite.com/Scripts/assets/sheepsite-mascot.jpg';
  return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:6px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.1);">

  <!-- Header -->
  <tr>
    <td style="padding:28px 32px 20px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td width="90" valign="top">
            <img src="' . $logo . '" alt="SheepSite" width="80" style="border-radius:4px;">
          </td>
          <td align="right" valign="top">
            <div style="font-size:26px;font-weight:bold;color:#3a7a3a;line-height:1.1;">SheepSite</div>
            <div style="font-size:13px;color:#777;font-style:italic;">Powered by Sheep</div>
            <div style="font-size:12px;color:#999;margin-top:2px;">noreply@sheepsite.com</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Divider -->
  <tr><td style="padding:0 32px;"><hr style="border:none;border-top:1px solid #e0e0e0;margin:0;"></td></tr>

  <!-- Body -->
  <tr><td style="padding:24px 32px 32px;">' . $bodyContent . '</td></tr>

  <!-- Footer -->
  <tr>
    <td style="padding:16px 32px 24px;text-align:center;">
      <div style="font-size:14px;color:#3a7a3a;font-style:italic;">Thank you for your business!</div>
    </td>
  </tr>

</table>
</td></tr></table>
</body></html>';
}

// -------------------------------------------------------
// Invoice email
// -------------------------------------------------------
function sendInvoiceEmail(array $invoice, array $bldCfg): void {
  $to = $bldCfg['contactEmail'] ?? '';
  if (!$to) return;

  $building = $invoice['building'];
  $label    = htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $building)));
  $subject  = 'Invoice ' . $invoice['id'] . ' — ' . strip_tags($label) . ' Annual Renewal';

  // Line items rows
  $itemRows = '';
  foreach ($invoice['lineItems'] as $item) {
    $amt = $item['amount'] >= 0
      ? '$' . number_format($item['amount'], 2)
      : '&minus;$' . number_format(abs($item['amount']), 2);
    $color = $item['amount'] < 0 ? '#3a7a3a' : '#333';
    $itemRows .= '<tr>
      <td style="padding:8px 10px;border-bottom:1px solid #eee;font-size:14px;color:#333;">' . htmlspecialchars($item['description']) . '</td>
      <td style="padding:8px 10px;border-bottom:1px solid #eee;font-size:14px;text-align:right;color:' . $color . ';">' . $amt . '</td>
    </tr>';
  }

  $body = '
  <!-- Invoice heading + meta -->
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
    <tr>
      <td valign="top">
        <div style="font-size:22px;font-weight:bold;color:#222;letter-spacing:0.03em;">INVOICE</div>
      </td>
      <td align="right" valign="top" style="font-size:13px;color:#555;line-height:1.8;">
        <strong>Invoice #:</strong> ' . htmlspecialchars($invoice['id']) . '<br>
        <strong>Date:</strong> ' . htmlspecialchars($invoice['date']) . '<br>
        <strong>Due Date:</strong> ' . htmlspecialchars($invoice['dueDate'] ?? '') . '
      </td>
    </tr>
  </table>

  <!-- Bill To -->
  <div style="margin-bottom:24px;">
    <div style="font-size:12px;font-weight:bold;color:#888;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Bill To</div>
    <div style="font-size:14px;color:#333;font-weight:bold;">' . $label . '</div>
    <div style="font-size:13px;color:#555;">' . htmlspecialchars($to) . '</div>
  </div>

  <!-- Line items table -->
  <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ddd;border-radius:4px;overflow:hidden;margin-bottom:16px;">
    <thead>
      <tr style="background:#f0f7f0;">
        <th style="padding:10px;text-align:left;font-size:13px;color:#555;font-weight:bold;border-bottom:1px solid #ddd;">Description</th>
        <th style="padding:10px;text-align:right;font-size:13px;color:#555;font-weight:bold;border-bottom:1px solid #ddd;">Amount</th>
      </tr>
    </thead>
    <tbody>' . $itemRows . '</tbody>
  </table>

  <!-- Total -->
  <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
    <tr>
      <td></td>
      <td width="220" style="border-top:2px solid #333;padding-top:8px;">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="font-size:15px;font-weight:bold;color:#222;padding:4px 10px;">Total Due</td>
            <td style="font-size:15px;font-weight:bold;color:#222;text-align:right;padding:4px 10px;">$' . number_format($invoice['total'], 2) . '</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Payment terms -->
  <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:14px 16px;font-size:13px;color:#555;">
    <strong style="color:#333;">Payment Options</strong><br><br>
    <strong>By check:</strong> Make payable to <strong>SheepSite LLC</strong> and mail to [YOUR MAILING ADDRESS]<br><br>
    <strong>Online:</strong> Stripe payment link coming soon.<br><br>
    Questions? Reply to this email.
  </div>';

  $html    = invoiceEmailHtml($body);
  $headers = implode("\r\n", [
    'From: SheepSite.com <noreply@sheepsite.com>',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: SheepSite/1.0',
  ]);

  mail($to, $subject, $html, $headers);
}

// -------------------------------------------------------
// Receipt email
// -------------------------------------------------------
function sendReceiptEmail(array $invoice, array $cfg): void {
  $to = $cfg['contactEmail'] ?? '';
  if (!$to) return;

  $building = $invoice['building'];
  $label    = htmlspecialchars(ucwords(str_replace(['_', '-'], ' ', $building)));
  $subject  = 'Payment Received — ' . strip_tags($label) . ' Invoice ' . $invoice['id'];

  $body = '
  <div style="font-size:22px;font-weight:bold;color:#222;margin-bottom:20px;">Payment Received</div>

  <div style="background:#f0f7f0;border:1px solid #c3e0c3;border-radius:4px;padding:16px 20px;margin-bottom:24px;">
    <div style="font-size:15px;color:#2e6b2e;font-weight:bold;margin-bottom:12px;">&#10003; Payment confirmed</div>
    <table cellpadding="0" cellspacing="0" style="font-size:14px;color:#333;">
      <tr><td style="padding:3px 16px 3px 0;color:#777;">Invoice #</td><td style="font-weight:bold;">' . htmlspecialchars($invoice['id']) . '</td></tr>
      <tr><td style="padding:3px 16px 3px 0;color:#777;">Amount paid</td><td style="font-weight:bold;">$' . number_format($invoice['total'], 2) . '</td></tr>
      <tr><td style="padding:3px 16px 3px 0;color:#777;">Payment date</td><td>' . htmlspecialchars($invoice['paidDate'] ?? '') . '</td></tr>
      <tr><td style="padding:3px 16px 3px 0;color:#777;">Next renewal</td><td>' . htmlspecialchars($cfg['renewalDate'] ?? '—') . '</td></tr>
    </table>
  </div>

  <div style="font-size:13px;color:#777;">
    This receipt confirms your SheepSite annual renewal. Please keep it for your records.<br><br>
    Questions? Reply to this email.
  </div>';

  $html    = invoiceEmailHtml($body);
  $headers = implode("\r\n", [
    'From: SheepSite.com <noreply@sheepsite.com>',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'X-Mailer: SheepSite/1.0',
  ]);

  mail($to, $subject, $html, $headers);
}
