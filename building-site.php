<?php
// -------------------------------------------------------
// building-site.php — SheepSite self-hosted building website
//
// Test mode:  sheepsite.com/Scripts/building-site.php?building=SampleSite
// Production: per-domain index.php defines BUILDING constant and requires this file
// -------------------------------------------------------

$buildings = require __DIR__ . '/buildings.php';

// In production, the domain's index.php sets: define('BUILDING', 'SampleSite');
// In test mode, building comes from the query string.
if (defined('BUILDING')) {
  $building = BUILDING;
} else {
  $building = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['building'] ?? '');
}

if (!$building || !isset($buildings[$building])) {
  http_response_code(404);
  exit('<h2 style="font-family:sans-serif;padding:2rem;">Building not found.</h2>');
}

$buildLabel = ucwords(str_replace(['_', '-'], ' ', $building));
require __DIR__ . '/suspension.php';

$page = preg_replace('/[^a-z-]/', '', $_GET['page'] ?? 'home');
if (!in_array($page, ['home', 'about', 'resources-public', 'resources-private', 'cenclub', 'social'])) {
  $page = 'home';
}

// Compute the base URL for the Scripts directory.
// In test mode (direct URL access) this auto-derives from the request host.
// In production (via index.php on a standalone domain), index.php should
// define SCRIPTS_BASE before requiring this file, e.g.:
//   define('SCRIPTS_BASE', 'https://sheepsite.com/Scripts/');
if (defined('SCRIPTS_BASE')) {
  $scriptsBase = SCRIPTS_BASE;
} else {
  $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host        = $_SERVER['HTTP_HOST'] ?? 'sheepsite.com';
  $scriptsBase = $scheme . '://' . $host . '/Scripts/';
}

// Site config comes from config/{building}.json (managed via building-detail.php)
$cfgFile = __DIR__ . '/config/' . $building . '.json';
$cfg     = file_exists($cfgFile) ? json_decode(file_get_contents($cfgFile), true) ?? [] : [];

$displayName    = $cfg['displayName']                  ?? $building;
$headerImageFile = $cfg['headerImageUrl'] ?? '';
$headerImageUrl  = $headerImageFile ? $scriptsBase . 'assets/' . $headerImageFile : '';
$calendarUrl    = $cfg['calendarUrl']                  ?? '';
$facebookUrl    = $cfg['facebookUrl']                  ?? '';
$pmName         = $cfg['propertyMgmt']['name']         ?? 'Property Management';
$pmUrl          = $cfg['propertyMgmt']['url']          ?? '#';
$pmPhone        = $cfg['propertyMgmt']['phone']        ?? '';
$pmButtonLabel  = $cfg['propertyMgmt']['buttonLabel']  ?? 'Portal';

$heroTitles = [
  'home'              => 'Welcome to ' . $displayName,
  'about'             => 'About Us',
  'resources-public'  => 'Resource Center',
  'resources-private' => 'Private Resources',
  'cenclub'           => 'CenClub',
  'social'            => 'Social Links',
];
$heroTitle = $heroTitles[$page] ?? 'Welcome';

// Nav URL helper: includes ?building= in test mode, omits it in production
function navUrl($p) {
  global $building;
  $qs = '?page=' . $p;
  if (!defined('BUILDING')) $qs .= '&building=' . urlencode($building);
  return $qs;
}
function isActive($p) {
  global $page;
  return $page === $p ? ' class="active"' : '';
}
function isResourcesActive() {
  global $page;
  return in_array($page, ['resources-public', 'resources-private', 'cenclub']) ? ' class="active"' : '';
}
function isDropdownActive($p) {
  global $page;
  return $page === $p ? ' class="active"' : '';
}

$bldJs = json_encode($building);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($displayName); ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; color: #222; background: #fff; }

  /* ---- NAV ---- */
  nav {
    background: #1a1a2e;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 2rem;
    height: 62px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
  }
  .nav-brand {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #fff;
    font-size: 1.1rem;
    font-weight: bold;
    font-family: Georgia, serif;
    font-style: italic;
    text-decoration: none;
  }
  .nav-brand .palm { font-size: 1.6rem; line-height: 1; }
  .nav-links { display: flex; gap: 0.1rem; align-items: center; }
  .nav-links > a, .nav-links > .dropdown > a {
    color: #ccc;
    text-decoration: none;
    padding: 0.45rem 0.9rem;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: color 0.15s;
    border-bottom: 2px solid transparent;
    display: block;
    cursor: pointer;
  }
  .nav-links > a:hover, .nav-links > .dropdown > a:hover { color: #fff; }
  .nav-links > a.active, .nav-links > .dropdown > a.active { color: #4dd0e1; border-bottom-color: #4dd0e1; }

  /* Dropdown */
  .dropdown { position: relative; }
  .dropdown-menu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #2a2a3e;
    border-radius: 0 0 6px 6px;
    min-width: 160px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    z-index: 200;
  }
  .dropdown:hover .dropdown-menu { display: block; }
  .dropdown-menu a {
    display: block;
    padding: 0.65rem 1.1rem;
    color: #ccc;
    font-size: 0.88rem;
    text-decoration: none;
    border-bottom: 1px solid rgba(255,255,255,0.05);
  }
  .dropdown-menu a:last-child { border-bottom: none; }
  .dropdown-menu a:hover { color: #fff; background: #3a3a50; }
  .dropdown-menu a.active { color: #4dd0e1; }

  /* Hamburger */
  .hamburger {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    color: #fff;
    font-size: 1.7rem;
    padding: 0.25rem;
    line-height: 1;
  }

  /* Mobile nav */
  @media (max-width: 768px) {
    nav { padding: 0 1.25rem; }
    .hamburger { display: block; }
    .nav-links {
      display: none;
      position: absolute;
      top: 62px;
      left: 0;
      right: 0;
      background: #1a1a2e;
      flex-direction: column;
      align-items: stretch;
      padding: 0.5rem 0 1rem;
      box-shadow: 0 6px 16px rgba(0,0,0,0.5);
      z-index: 99;
    }
    .nav-links.open { display: flex; }
    .nav-links > a, .nav-links > .dropdown > a {
      padding: 0.75rem 1.5rem;
      border-bottom: none;
      border-radius: 0;
    }
    .dropdown { position: static; }
    .dropdown:hover .dropdown-menu { display: none; }
    .dropdown.open .dropdown-menu { display: block; }
    .dropdown-menu {
      position: static;
      box-shadow: none;
      background: #111120;
      border-radius: 0;
      min-width: 0;
    }
    .dropdown-menu a { padding: 0.65rem 2.5rem; }
    .hero-title { font-size: 1.8rem; }
  }

  /* ---- HERO ---- */
  .hero {
    width: 100%;
    height: 252px;
    background-size: cover;
    background-position: center;
    background-color: #2d0050;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }
  .hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.18);
  }
  .hero-title {
    position: relative;
    z-index: 1;
    color: #fff;
    font-size: 2.8rem;
    font-weight: bold;
    font-style: italic;
    text-align: center;
    text-shadow: 2px 3px 10px rgba(0,0,0,0.75);
    padding: 0 2rem;
  }

  /* ---- CONTENT WRAPPER ---- */
  .content { max-width: 860px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
  .section  { margin-bottom: 2rem; }

  /* ---- BUTTONS ---- */
  .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 10px 26px;
    border-radius: 5px;
    border: none;
    font-size: 15px;
    font-family: inherit;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
  }
  .btn-primary  { background: linear-gradient(to right, #3D0066, #BB0099); color: #fff; }
  .btn-primary:hover  { opacity: 0.88; }
  .btn-teal     { background: #00796b; color: #fff; }
  .btn-teal:hover     { background: #00695c; }
  .btn-report   { background: #0077b6; color: #fff; }
  .btn-report:hover   { background: #005f8e; }
  .btn-calendar { background: #f59e0b; color: #fff; }
  .btn-calendar:hover { background: #d97706; }
  .btn-facebook { background: #1877f2; color: #fff; }
  .btn-facebook:hover { background: #145dbf; }
  .btn-disabled { background: #ccc; color: #888; cursor: not-allowed; }
  .btn-search {
    display: flex;
    width: 100%;
    justify-content: center;
    background: transparent;
    color: #BB0099;
    border: 2px solid #BB0099;
    font-size: 1rem;
    padding: 12px;
    margin-bottom: 2rem;
  }
  .btn-search:hover { background: #fff0fb; }

  /* ---- ANNOUNCEMENT ---- */
  .announce-head {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
  }
  .announce-head .bar { flex: 1; height: 4px; background: #222; border-radius: 2px; }
  .announce-head h2 {
    font-size: 1.45rem;
    font-weight: 900;
    font-style: italic;
    text-align: center;
    line-height: 1.2;
    white-space: nowrap;
  }

  /* ---- IFRAME CONTAINER ---- */
  .iframe-box {
    position: relative;
    width: 100%;
    height: 340px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    background: #f8f8f8;
  }
  .iframe-box iframe { width: 100%; height: 100%; border: none; display: none; }
  .iframe-spinner {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .spinner-ring {
    width: 40px; height: 40px;
    border: 4px solid #e0c0f0;
    border-top-color: #7A0099;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  .iframe-spinner p { margin-top: 10px; font-size: 13px; color: #888; font-family: inherit; }

  /* ---- RESOURCE ROWS ---- */
  .resource-row {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid #f0f0f0;
  }
  .resource-row:last-child { border-bottom: none; }
  .resource-row .label { flex: 1; font-weight: 600; font-size: 0.98rem; }
  .resource-row .sublabel { color: #666; font-size: 0.88rem; font-weight: 400; }

  /* ---- ABOUT BOX ---- */
  .about-box {
    border: 2px solid #BB0099;
    border-radius: 6px;
    padding: 2rem;
    text-align: center;
    margin-top: 2rem;
  }
  .about-box h2 { font-size: 1.4rem; font-weight: 900; font-style: italic; margin-bottom: 0.8rem; }
  .about-box p  { color: #444; font-size: 0.95rem; line-height: 1.75; max-width: 560px; margin: 0 auto; }

  /* ---- FOOTER ---- */
  footer {
    background: #e8e8e8;
    padding: 1.1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .footer-left .copy    { font-size: 0.85rem; color: #444; }
  .footer-left .copy a  { color: #BB0099; text-decoration: none; }
  .footer-left .powered { font-style: italic; color: #BB0099; font-size: 0.88rem; margin-top: 2px; }
  footer img { height: 72px; }

  /* ---- SECTION HEADING (Resources pages) ---- */
  .page-intro { color: #444; font-size: 0.95rem; line-height: 1.7; margin-bottom: 1.5rem; padding: 1rem 1.25rem; background: #faf5ff; border-left: 4px solid #BB0099; border-radius: 0 4px 4px 0; }
  .group-heading { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.1em; color: #999; text-transform: uppercase; padding: 1.25rem 0 0.5rem; border-top: 1px solid #eee; margin-top: 0.5rem; }
  .group-heading:first-child { border-top: none; margin-top: 0; padding-top: 0; }
  .cenclub-block { margin-bottom: 2rem; }
  .cenclub-block p { font-size: 0.97rem; margin-bottom: 0.6rem; color: #333; }
  .cenclub-block ul { padding-left: 1.5rem; }
  .cenclub-block ul li { margin-bottom: 0.3rem; font-size: 0.95rem; color: #444; }
  .cenclub-red { color: #cc0000; font-weight: 600; }
</style>
</head>
<body>

<!-- ===== NAV ===== -->
<nav>
  <a class="nav-brand" href="<?php echo navUrl('home'); ?>">
    <span class="palm">🌴</span>
    <?php echo htmlspecialchars($displayName); ?>
  </a>
  <button class="hamburger" id="hamburger" aria-label="Menu">&#9776;</button>
  <div class="nav-links" id="nav-links">
    <a href="<?php echo navUrl('home'); ?>"<?php echo isActive('home'); ?>>Home</a>
    <a href="<?php echo navUrl('about'); ?>"<?php echo isActive('about'); ?>>About Us</a>
    <div class="dropdown">
      <a href="#"<?php echo isResourcesActive(); ?>>Resources &#9662;</a>
      <div class="dropdown-menu">
        <a href="<?php echo navUrl('resources-public'); ?>"<?php echo isDropdownActive('resources-public'); ?>>Public</a>
        <a href="<?php echo navUrl('resources-private'); ?>"<?php echo isDropdownActive('resources-private'); ?>>Private</a>
        <a href="<?php echo navUrl('cenclub'); ?>"<?php echo isDropdownActive('cenclub'); ?>>CenClub</a>
        <a href="<?php echo navUrl('social'); ?>"<?php echo isDropdownActive('social'); ?>>Social</a>
      </div>
    </div>
    <div class="dropdown">
      <a href="#">Admin &#9662;</a>
      <div class="dropdown-menu">
        <a href="#" onclick="openMyAccount(); return false;">My Account</a>
        <a href="#" onclick="openAdmin(); return false;">Site Admin</a>
      </div>
    </div>
  </div>
</nav>

<!-- ===== HERO ===== -->
<div class="hero"<?php if ($headerImageUrl) echo ' style="background-image:url(' . htmlspecialchars($headerImageUrl, ENT_QUOTES) . ');"'; ?>>
  <div class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></div>
</div>

<!-- ===== PAGE CONTENT ===== -->
<div class="content">

<?php if ($page === 'home'): ?>

  <button class="btn btn-search" onclick="openSearch()">&#128269; Search ALL Documents</button>

  <!-- Latest News / Announcement -->
  <div class="section">
    <div class="announce-head">
      <div class="bar"></div>
      <h2>LATEST NEWS /<br>ANNOUNCEMENT</h2>
      <div class="bar"></div>
    </div>
    <div class="iframe-box">
      <div class="iframe-spinner" id="doc-loader">
        <div class="spinner-ring"></div>
        <p>Loading...</p>
      </div>
      <iframe
        data-script="get-doc-byname"
        data-subdir="Page1Docs"
        data-filename="Announcement Page1"
        title="Latest Announcement">
      </iframe>
    </div>
  </div>

  <!-- Mid/End Year Report -->
  <div class="section">
    <div class="resource-row">
      <span class="label">Mid/End Year Report</span>
      <button class="btn btn-primary" onclick="openDoc('Page1Docs', 'Mid-End Year Report')">&#128196; Click to open</button>
    </div>

    <!-- Calendar -->
    <div class="resource-row">
      <span class="label">Calendar</span>
      <?php if ($calendarUrl): ?>
        <a class="btn btn-calendar" href="<?php echo htmlspecialchars($calendarUrl); ?>" target="_blank">&#128197; Building's Calendar</a>
      <?php else: ?>
        <span style="color:#aaa;font-size:0.9rem;">No calendar configured</span>
      <?php endif; ?>
    </div>

    <!-- Work Order / Property Management -->
    <div class="resource-row">
      <div class="label">
        Work Order
        <?php if ($pmPhone): ?>
          <br><span class="sublabel"><?php echo htmlspecialchars($pmPhone); ?></span>
        <?php endif; ?>
      </div>
      <a class="btn btn-teal" href="<?php echo htmlspecialchars($pmUrl); ?>" target="_blank">&#9962; <?php echo htmlspecialchars($pmButtonLabel); ?></a>
    </div>
  </div>

  <!-- About Us -->
  <div class="about-box">
    <h2>ABOUT US</h2>
    <p>This is our little slice of paradise here in sunny Florida. This is a PRIVATE site
       intended solely for the owners and residents of this building, to serve as a central
       location for the sharing of information related to our community.</p>
  </div>

<?php elseif ($page === 'about'): ?>

  <iframe
    data-script="public-report"
    data-page="board"
    style="width:100%;height:600px;border:1px solid #ddd;border-radius:4px;display:block;"
    title="Board of Directors">
  </iframe>

<?php elseif ($page === 'resources-public'): ?>

  <div class="page-intro">
    Use the links below to browse public documents and resources. No login is required.
  </div>

  <button class="btn btn-search" onclick="openSearch()">&#128269; Search ALL Documents</button>

  <div class="section">
    <div class="resource-row">
      <span class="label">Root Folder &mdash; All Public Docs</span>
      <button class="btn btn-primary" onclick="openFolder()">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Building Guides &amp; Rules</span>
      <button class="btn btn-primary" onclick="openFolder('RulesDocs')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Forms / Applications</span>
      <button class="btn btn-primary" onclick="openFolder('Forms')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Other Documents</span>
      <button class="btn btn-primary" onclick="openFolder('OtherDocs')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Incorporation Documents</span>
      <button class="btn btn-primary" onclick="openFolder('IncorporationDocs')">&#128196; Click to open</button>
    </div>

  </div>

<?php elseif ($page === 'resources-private'): ?>

  <div class="page-intro">
    This page contains links to the Association&rsquo;s private documents, made available only
    to owners. Click on the document type of interest and the system will ask you to log in with
    a username and password. If you do not have log-in credentials, please contact a member of
    the Board.
  </div>

  <button class="btn btn-search" onclick="openSearch()">&#128269; Search ALL Documents</button>

  <div class="section">
    <div class="resource-row">
      <span class="label">Root Folder &mdash; All Private Docs</span>
      <button class="btn btn-primary" onclick="openPrivateFolder()">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Board Minutes</span>
      <button class="btn btn-primary" onclick="openPrivateFolder('BoardMinutes')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Financial Statements</span>
      <button class="btn btn-primary" onclick="openPrivateFolder('FinancialDocs/FinancialStatements')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Budgets</span>
      <button class="btn btn-primary" onclick="openPrivateFolder('FinancialDocs/BudgetDocs')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">SIRs Documents</span>
      <button class="btn btn-primary" onclick="openPrivateFolder('FinancialDocs/SIRSDocs')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Contracts</span>
      <button class="btn btn-primary" onclick="openPrivateFolder('Contracts')">&#128196; Click to open</button>
    </div>

    <div class="group-heading">Reports</div>

    <div class="resource-row">
      <span class="label">Owner Directory</span>
      <button class="btn btn-report" onclick="openReport('resident')">&#128196; Click to open</button>
    </div>
    <div class="resource-row">
      <span class="label">Parking Spots</span>
      <button class="btn btn-report" onclick="openReport('parking')">&#128196; Click to open</button>
    </div>
  </div>


<?php elseif ($page === 'cenclub'): ?>

  <div style="text-align:center;margin-bottom:2.5rem;">
    <a class="btn btn-primary" href="https://cenclub.com/services" target="_blank">&#127760; CenClub Resource Page</a>
    <p style="margin-top:0.75rem;font-size:0.9rem;color:#666;">This Link will bring you to the CenClub web site.</p>
  </div>

  <div class="cenclub-block">
    <p>Scroll down the page to <span class="cenclub-red">&ldquo;Staff Office Resources&rdquo;</span> for information on the following</p>
    <ul>
      <li>Important Phone Numbers</li>
      <li>Seacrest Services</li>
      <li>Comcast &amp; Xfinity</li>
      <li>Homestead information &mdash; Broward County</li>
    </ul>
  </div>

  <div class="cenclub-block">
    <p>Scroll down the page to <span class="cenclub-red">&ldquo;I.D. Office Resources&rdquo;</span> for information on the following</p>
    <ul>
      <li>Property Transfer Application</li>
      <li>Service/Support Animal Application</li>
      <li>Companion Pass Request Form</li>
      <li>Gate Pass Request</li>
      <li>Guest Pass Request</li>
      <li>Contractor Entry Request</li>
      <li>Resident Emergency Information Form</li>
    </ul>
  </div>

  <div class="cenclub-block">
    <p>Scroll down the page to <span class="cenclub-red">&ldquo;Administration Office Resources&rdquo;</span> for information on the following</p>
    <ul>
      <li>Automatic Payment Form</li>
      <li>CenClub &mdash; Payment Options Information</li>
      <li>Cancellation of Direct Debit</li>
    </ul>
  </div>

<?php elseif ($page === 'social'): ?>

  <div style="max-width:480px;margin:0 auto;">

    <div class="resource-row">
      <span class="label">Facebook Group</span>
      <?php if ($facebookUrl): ?>
        <a class="btn btn-facebook" href="<?php echo htmlspecialchars($facebookUrl); ?>" target="_blank">&#128081; Facebook</a>
      <?php else: ?>
        <span class="btn btn-facebook" style="opacity:0.5;cursor:default;">&#128081; Facebook</span>
      <?php endif; ?>
    </div>

    <div class="resource-row">
      <div class="label">
        Forum
        <br><span class="sublabel">Coming soon</span>
      </div>
      <span class="btn btn-disabled">&#128172; Forum</span>
    </div>

  </div>

<?php endif; ?>

</div><!-- /content -->

<!-- ===== FOOTER ===== -->
<footer>
  <div style="display:flex;align-items:center;gap:1.2rem;">
    <img src="<?php echo htmlspecialchars($scriptsBase); ?>assets/Woolsy-original-transparent.png" alt="SheepSite">
    <div class="footer-left">
      <div class="copy">&copy; 2025 <a href="https://sheepsite.com">SheepSite.com</a></div>
      <div class="powered">Powered by Sheep</div>
    </div>
  </div>
</footer>

<!-- ===== SCRIPTS ===== -->
<script>
const BUILDING_NAME = <?php echo $bldJs; ?>;
const SCRIPTS_BASE  = <?php echo json_encode($scriptsBase); ?>;
window.BUILDING_NAME = BUILDING_NAME;

document.addEventListener('DOMContentLoaded', function () {
  var SCRIPTS = SCRIPTS_BASE;

  // Admin links
  document.querySelectorAll('a[href*="admin.php"]').forEach(function (link) {
    link.href = SCRIPTS + 'admin.php?building=' + encodeURIComponent(BUILDING_NAME);
  });

  // get-doc-byname iframes (announcement, documents)
  document.querySelectorAll('iframe[data-script="get-doc-byname"]').forEach(function (iframe) {
    var url = SCRIPTS + 'get-doc-byname.php?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir   = iframe.getAttribute('data-subdir');
    var filename = iframe.getAttribute('data-filename');
    if (subdir)   url += '&subdir='   + encodeURIComponent(subdir);
    if (filename) url += '&filename=' + encodeURIComponent(filename);
    iframe.onload = function () {
      iframe.style.display = 'block';
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });

  // Public report iframes (board of directors)
  document.querySelectorAll('iframe[data-script="public-report"]').forEach(function (iframe) {
    var url = SCRIPTS + 'public-report.php?building=' + encodeURIComponent(BUILDING_NAME);
    var pg = iframe.getAttribute('data-page');
    if (pg) url += '&page=' + encodeURIComponent(pg);
    url += '&nav=0';
    iframe.onload = function () {
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });
});

function openFolder(subdir) {
  var url = SCRIPTS_BASE + 'display-public-dir.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return='   + encodeURIComponent(window.location.href);
  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
  window.location.href = url;
}
function openPrivateFolder(subdir) {
  var url = SCRIPTS_BASE + 'display-private-dir.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return='   + encodeURIComponent(window.location.href);
  if (subdir) url += '&path=' + encodeURIComponent(subdir);
  window.location.href = url;
}
function openReport(reportPage) {
  window.location.href = SCRIPTS_BASE + 'protected-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page='     + encodeURIComponent(reportPage)
    + '&return='   + encodeURIComponent(window.location.href);
}
function openPublicReport(reportPage) {
  window.location.href = SCRIPTS_BASE + 'public-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page='     + encodeURIComponent(reportPage)
    + '&return='   + encodeURIComponent(window.location.href);
}
function openAdmin() {
  window.location.href = SCRIPTS_BASE + 'admin.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME);
}
function openMyAccount() {
  window.location.href = SCRIPTS_BASE + 'my-account.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME);
}
function openSearch() {
  window.location.href = SCRIPTS_BASE + 'search.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return='   + encodeURIComponent(window.location.href);
}
function openDoc(subdir, filename) {
  var url = SCRIPTS_BASE + 'get-doc-byname.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&filename=' + encodeURIComponent(filename);
  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
  window.open(url, '_blank');
}

// Hamburger toggle
var hamburger = document.getElementById('hamburger');
var navLinks  = document.getElementById('nav-links');
if (hamburger) {
  hamburger.addEventListener('click', function () {
    navLinks.classList.toggle('open');
  });
}

// Mobile dropdown: click to open/close (hover disabled on mobile via CSS)
document.querySelectorAll('.dropdown > a').forEach(function (toggle) {
  toggle.addEventListener('click', function (e) {
    if (window.innerWidth <= 768) {
      e.preventDefault();
      this.closest('.dropdown').classList.toggle('open');
    }
  });
});
</script>

<!-- Woolsy floating chatbot -->
<script src="<?php echo htmlspecialchars($scriptsBase); ?>chatbot-widget.js"></script>

</body>
</html>
