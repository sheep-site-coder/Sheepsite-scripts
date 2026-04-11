<?php
// -------------------------------------------------------
// pricing-admin.php
// Master admin tool for configuring service pricing.
// Reuses master_admin_auth session from master-admin.php.
//
//   https://sheepsite.com/Scripts/pricing-admin.php
// -------------------------------------------------------
session_start();

define('CONFIG_DIR', __DIR__ . '/config/');

if (empty($_SESSION['master_admin_auth'])) {
  header('Location: master-admin.php');
  exit;
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function loadPricing(): array {
  $file = CONFIG_DIR . 'pricing.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function savePricing(array $p): void {
  if (!is_dir(CONFIG_DIR)) mkdir(CONFIG_DIR, 0755, true);
  file_put_contents(CONFIG_DIR . 'pricing.json', json_encode($p, JSON_PRETTY_PRINT));
}

function fmtBytes(int $bytes): string {
  if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
  return round($bytes / 1024) . ' KB';
}

// -------------------------------------------------------
// POST handler
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $p = loadPricing();

  // Site & domain pricing
  $p['siteMonthlyPrice']  = round((float)($_POST['site_monthly_price']  ?? 0), 2);
  $p['domainAnnualPrice'] = round((float)($_POST['domain_annual_price'] ?? 0), 2);

  // Credit price
  $p['creditPrice'] = round((float)($_POST['credit_price'] ?? 0), 2);

  // Default storage limit
  $limitMB = (int)($_POST['default_limit_mb'] ?? 0);
  if ($limitMB > 0) {
    $p['storageDefaultLimit'] = $limitMB * 1048576;
  }

  // Storage tiers — rebuild from posted arrays
  $labels = $_POST['tier_label']         ?? [];
  $mbs    = $_POST['tier_mb']            ?? [];
  $prices = $_POST['tier_price_monthly'] ?? [];
  $tiers  = [];
  foreach ($labels as $i => $label) {
    $label = trim($label);
    $mb    = (int)($mbs[$i]    ?? 0);
    $price = (float)($prices[$i] ?? 0);
    if ($label && $mb > 0) {
      $tiers[] = [
        'label'        => $label,
        'bytes'        => $mb * 1048576,
        'pricePerMonth'=> round($price, 2),
      ];
    }
  }
  // Sort tiers by size ascending
  usort($tiers, fn($a, $b) => $a['bytes'] <=> $b['bytes']);
  $p['storageOptions'] = $tiers;

  savePricing($p);
  $message = 'Pricing saved.';

  header('Location: pricing-admin.php?' . http_build_query(['msg' => $message, 'type' => $messageType]));
  exit;
}

if (empty($message) && isset($_GET['msg'])) {
  $message     = $_GET['msg'];
  $messageType = $_GET['type'] ?? 'ok';
}

$p                = loadPricing();
$siteMonthly      = (float)($p['siteMonthlyPrice']  ?? 0);
$domainAnnual     = (float)($p['domainAnnualPrice'] ?? 0);
$creditPrice      = (float)($p['creditPrice']       ?? 0);
$defaultLimit     = (int)($p['storageDefaultLimit'] ?? 10737418240);
$tiers            = $p['storageOptions'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pricing Admin</title>
  <style>
    * { box-sizing: border-box; }
    body       { font-family: sans-serif; max-width: 700px; margin: 3rem auto; padding: 0 1rem; }
    .top-bar   { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; }
    h1         { margin: 0; font-size: 1.5rem; }
    .back      { font-size: 0.85rem; color: #0070f3; text-decoration: none; }
    .back:hover { text-decoration: underline; }
    .section   { border: 1px solid #ddd; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; }
    .section h2 { margin: 0 0 0.25rem; font-size: 1rem; }
    .hint      { font-size: 0.8rem; color: #888; margin: 0 0 1rem; }
    .message   { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.25rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    label      { display: block; font-size: 0.875rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text], input[type=number] {
      padding: 0.4rem 0.6rem; border: 1px solid #ccc; border-radius: 4px;
      font-size: 0.9rem; width: 120px; }
    .price-row { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
    .price-row > div { display: flex; flex-direction: column; gap: 0.2rem; }
    .unit      { font-size: 0.85rem; color: #555; align-self: flex-end; padding-bottom: 0.5rem; }
    .btn       { padding: 0.45rem 1.1rem; background: #0070f3; color: #fff; border: none;
                 border-radius: 4px; font-size: 0.875rem; cursor: pointer; }
    .btn:hover { background: #005bb5; }
    .btn-red   { background: #c00; color: #fff; border: none; border-radius: 4px;
                 padding: 0.3rem 0.7rem; font-size: 0.8rem; cursor: pointer; }
    .btn-red:hover { background: #900; }
    .btn-gray  { background: #fff; color: #333; border: 1px solid #ccc; border-radius: 4px;
                 padding: 0.4rem 0.9rem; font-size: 0.875rem; cursor: pointer; }
    .btn-gray:hover { background: #f5f5f5; }
    .tier-row  { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.6rem; }
    .tiers-head { display: flex; gap: 0.75rem; margin-bottom: 0.4rem; }
    .tiers-head span { font-size: 0.78rem; font-weight: bold; color: #555;
                       text-transform: uppercase; letter-spacing: 0.04em; width: 120px; }
  </style>
</head>
<body>

<div class="top-bar">
  <h1>Pricing Configuration</h1>
  <a href="master-admin.php" class="back">← Master Admin</a>
</div>

<?php if ($message): ?>
  <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<form method="post">

  <!-- Site subscription -->
  <div class="section">
    <h2>Site Subscription</h2>
    <p class="hint">Billed annually via Stripe. Annual amount = monthly × 12.</p>
    <div class="price-row">
      <div>
        <label>Site fee</label>
        <input type="number" name="site_monthly_price" min="0" step="0.01"
               value="<?= number_format($siteMonthly, 2) ?>">
      </div>
      <span class="unit">$ / month</span>
      <div>
        <label>Domain (own domain only)</label>
        <input type="number" name="domain_annual_price" min="0" step="0.01"
               value="<?= number_format($domainAnnual, 2) ?>">
      </div>
      <span class="unit">$ / year</span>
    </div>
    <p style="font-size:0.82rem;color:#888;margin:0.5rem 0 0;">
      Annual site invoice = (site monthly × 12)<?= $domainAnnual > 0 ? ' + domain annual' : '' ?> + storage for year.
      Buildings without their own domain are hosted on a sheepsite.com subdomain.
    </p>
  </div>

  <!-- Woolsy credits -->
  <div class="section">
    <h2>Woolsy Credits</h2>
    <p class="hint">Buildings buy any quantity. Billing triggered at 90% usage.</p>
    <div class="price-row">
      <div>
        <label>Price per credit</label>
        <input type="number" name="credit_price" min="0" step="0.01"
               value="<?= number_format($creditPrice, 2) ?>">
      </div>
      <span class="unit">$ / credit</span>
    </div>
  </div>

  <!-- Storage -->
  <div class="section">
    <h2>Storage</h2>
    <p class="hint">Default limit applies to all buildings. Upgrades billed pro-rated to renewal date, then annually.</p>
    <div class="price-row" style="margin-bottom:1.25rem;">
      <div>
        <label>Default limit</label>
        <input type="number" name="default_limit_mb" min="1"
               value="<?= round($defaultLimit / 1048576) ?>" style="width:100px;">
      </div>
      <span class="unit">MB (<?= fmtBytes($defaultLimit) ?>)</span>
    </div>

    <label>Upgrade tiers</label>
    <p class="hint" style="margin-top:0;">Sorted by size automatically on save.</p>

    <div class="tiers-head">
      <span>Label</span>
      <span>Size (MB)</span>
      <span>$/month</span>
      <span style="width:60px;"></span>
    </div>
    <div id="tiers-container">
      <?php foreach ($tiers as $i => $tier): ?>
      <div class="tier-row">
        <input type="text"   name="tier_label[]"         value="<?= htmlspecialchars($tier['label']) ?>" placeholder="e.g. 100 MB" style="width:120px;">
        <input type="number" name="tier_mb[]"            value="<?= round($tier['bytes'] / 1048576) ?>" min="1" style="width:120px;">
        <input type="number" name="tier_price_monthly[]" value="<?= number_format($tier['pricePerMonth'] ?? 0, 2) ?>" min="0" step="0.01" style="width:120px;">
        <button type="button" class="btn-red" onclick="removeTier(this)">Remove</button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="btn-gray" style="margin-top:0.5rem;" onclick="addTier()">+ Add Tier</button>
  </div>

  <button type="submit" class="btn">Save Pricing</button>

</form>

<script>
function addTier() {
  var container = document.getElementById('tiers-container');
  var div = document.createElement('div');
  div.className = 'tier-row';
  div.innerHTML =
    '<input type="text"   name="tier_label[]"         placeholder="e.g. 2 GB"  style="width:120px;">' +
    '<input type="number" name="tier_mb[]"            min="1"                   style="width:120px;" placeholder="MB">' +
    '<input type="number" name="tier_price_monthly[]" min="0" step="0.01"       style="width:120px;" placeholder="0.00">' +
    '<button type="button" class="btn-red" onclick="removeTier(this)">Remove</button>';
  container.appendChild(div);
}

function removeTier(btn) {
  btn.closest('.tier-row').remove();
}
</script>

</body>
</html>
