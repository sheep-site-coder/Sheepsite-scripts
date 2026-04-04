<?php
// Handle contact form submission
$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name     = trim(strip_tags($_POST['name'] ?? ''));
    $assoc    = trim(strip_tags($_POST['association'] ?? ''));
    $message  = trim(strip_tags($_POST['message'] ?? ''));
    if ($name && $assoc && $message) {
        $to      = 'SheepSite@sheepsite.com';
        $subject = "SheepSite Inquiry from $name – $assoc";
        $email   = trim(strip_tags($_POST['email'] ?? ''));
        $phone   = trim(strip_tags($_POST['phone'] ?? ''));
        $body    = "Name: $name\nAssociation / Building: $assoc\nEmail: $email\nPhone: $phone\n\n$message";
        $headers = "From: noreply@sheepsite.com\r\nReply-To: $name <noreply@sheepsite.com>";
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent) $error = 'There was a problem sending your message. Please email us directly at SheepSite@sheepsite.com.';
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Get Started — SheepSite</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue:    #2a5a8a;
      --blue-dk: #1a3a5c;
      --blue-lt: #e8f0f8;
      --amber:   #e8940a;
      --dark:    #1c2630;
      --mid:     #4a5568;
      --light:   #f7f9fc;
      --white:   #ffffff;
      --radius:  10px;
    }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--dark); background: var(--white); line-height: 1.65; font-size: 16px; }

    /* NAV */
    nav { position: sticky; top: 0; z-index: 100; background: var(--white); border-bottom: 1px solid #e2e8f0; padding: 0 2rem; display: flex; align-items: center; justify-content: space-between; height: 64px; }
    .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 1.3rem; font-weight: 700; color: var(--blue); }
    .nav-brand img { height: 38px; }
    .nav-links { display: flex; gap: 2rem; list-style: none; }
    .nav-links a { text-decoration: none; color: var(--mid); font-size: 0.95rem; font-weight: 500; transition: color 0.15s; }
    .nav-links a:hover { color: var(--blue); }
    .nav-cta { background: var(--blue); color: var(--white) !important; padding: 8px 20px; border-radius: 6px; }
    .nav-cta:hover { background: var(--blue-dk) !important; }

    /* HERO */
    .page-hero { background: linear-gradient(135deg, var(--blue-dk) 0%, var(--blue) 100%); color: var(--white); padding: 4rem 2rem; text-align: center; }
    .page-hero h1 { font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 800; margin-bottom: 0.75rem; }
    .page-hero p { font-size: 1.1rem; color: rgba(255,255,255,0.82); max-width: 600px; margin: 0 auto; }

    /* SECTIONS */
    section { padding: 4.5rem 2rem; }
    .section-inner { max-width: 1000px; margin: 0 auto; }
    .section-label { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--blue); margin-bottom: 0.6rem; }
    .section-title { font-size: clamp(1.5rem, 3vw, 2rem); font-weight: 800; color: var(--dark); margin-bottom: 0.8rem; line-height: 1.25; }
    .section-intro { font-size: 1rem; color: var(--mid); max-width: 700px; margin-bottom: 2rem; }

    /* TEST DRIVE */
    .testdrive-bg { background: var(--light); }
    .testdrive-box {
      background: var(--white);
      border: 2px solid var(--blue);
      border-radius: var(--radius);
      padding: 2.5rem;
      display: flex;
      gap: 3rem;
      align-items: flex-start;
      flex-wrap: wrap;
    }
    .testdrive-main { flex: 1; min-width: 260px; }
    .testdrive-main h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--blue); }
    .testdrive-main p { color: var(--mid); font-size: 0.95rem; margin-bottom: 1.25rem; }
    .btn-blue { background: var(--blue); color: var(--white); font-weight: 700; font-size: 1rem; padding: 12px 28px; border-radius: 8px; text-decoration: none; display: inline-block; transition: background 0.15s; }
    .btn-blue:hover { background: var(--blue-dk); }
    .creds-grid { display: flex; gap: 2rem; flex-wrap: wrap; }
    .cred-box { background: var(--blue-lt); border-radius: 8px; padding: 1.25rem 1.5rem; min-width: 180px; }
    .cred-box h4 { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--blue); margin-bottom: 0.6rem; }
    .cred-row { font-size: 0.88rem; color: var(--mid); margin-bottom: 0.3rem; }
    .cred-row span { font-weight: 700; color: var(--dark); font-family: monospace; font-size: 0.95rem; }

    /* TWO-COLUMN */
    .experience-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; }
    .exp-card { background: var(--white); border: 1px solid #e2e8f0; border-radius: var(--radius); padding: 2rem; }
    .exp-card.resident { border-top: 4px solid #38a169; }
    .exp-card.admin    { border-top: 4px solid var(--blue); }
    .exp-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.3rem; }
    .exp-card h3.resident-color { color: #38a169; }
    .exp-card h3.admin-color    { color: var(--blue); }
    .exp-card .role-desc { font-size: 0.88rem; color: var(--mid); margin-bottom: 1.25rem; font-style: italic; }
    .exp-list { list-style: none; margin-bottom: 1.5rem; }
    .exp-list li { padding: 0.45rem 0; font-size: 0.9rem; color: var(--mid); border-bottom: 1px solid #f0f4f8; display: flex; gap: 0.6rem; align-items: flex-start; }
    .exp-list li:last-child { border-bottom: none; }
    .exp-list li::before { content: "•"; font-weight: 700; flex-shrink: 0; margin-top: 1px; }
    .exp-card.resident .exp-list li::before { color: #38a169; }
    .exp-card.admin    .exp-list li::before { color: var(--blue); }
    .manual-link { display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem; font-weight: 600; text-decoration: none; padding: 9px 18px; border-radius: 6px; transition: background 0.15s; }
    .manual-link.resident { background: #f0faf4; color: #276749; border: 1px solid #c6f0d8; }
    .manual-link.resident:hover { background: #c6f0d8; }
    .manual-link.admin { background: var(--blue-lt); color: var(--blue-dk); border: 1px solid #bcd4ec; }
    .manual-link.admin:hover { background: #bcd4ec; }

    /* CONTACT */
    .contact-bg { background: linear-gradient(135deg, var(--blue-dk) 0%, var(--blue) 100%); }
    .contact-bg .section-title { color: var(--white); }
    .contact-bg .section-intro { color: rgba(255,255,255,0.8); }
    .contact-bg .section-label { color: #fbbf24; }
    .contact-form { background: var(--white); border-radius: var(--radius); padding: 2.5rem; max-width: 600px; }
    .form-row { margin-bottom: 1.25rem; }
    .form-row label { display: block; font-size: 0.88rem; font-weight: 600; color: var(--dark); margin-bottom: 0.4rem; }
    .form-row input, .form-row textarea {
      width: 100%; padding: 10px 14px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 0.95rem; font-family: inherit; color: var(--dark); transition: border-color 0.15s;
    }
    .form-row input:focus, .form-row textarea:focus { outline: none; border-color: var(--blue); }
    .form-row textarea { min-height: 120px; resize: vertical; }
    .btn-submit { background: var(--blue); color: var(--white); font-weight: 700; font-size: 1rem; padding: 12px 28px; border: none; border-radius: 8px; cursor: pointer; transition: background 0.15s; }
    .btn-submit:hover { background: var(--blue-dk); }
    .alert-success { background: #f0faf4; border: 1px solid #9ae6b4; color: #276749; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.95rem; margin-bottom: 1.5rem; }
    .alert-error   { background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.95rem; margin-bottom: 1.5rem; }

    /* FOOTER */
    footer { background: var(--dark); color: rgba(255,255,255,0.6); padding: 2.5rem 2rem; text-align: center; font-size: 0.875rem; }
    footer .footer-brand { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 0.75rem; }
    footer .footer-brand img { height: 28px; opacity: 0.85; }
    footer .footer-brand span { color: rgba(255,255,255,0.85); font-weight: 600; font-size: 1rem; }
    footer a { color: rgba(255,255,255,0.5); text-decoration: none; }
    footer a:hover { color: rgba(255,255,255,0.85); }
    footer nav { margin-top: 0.75rem; display: flex; gap: 1.5rem; justify-content: center; }

    @media (max-width: 768px) {
      .nav-links { display: none; }
      .experience-grid { grid-template-columns: 1fr; }
      .testdrive-box { flex-direction: column; gap: 1.5rem; }
    }
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-brand">
    <img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" alt="SheepSite">
    SheepSite
  </a>
  <ul class="nav-links">
    <li><a href="index.php#features">Features</a></li>
    <li><a href="index.php#woolsy">Woolsy AI</a></li>
    <li><a href="#contact">Contact Us</a></li>
    <li><a href="index.php" class="nav-cta">← Back to Home</a></li>
  </ul>
</nav>

<div class="page-hero">
  <h1>See It. Learn It. Get Started.</h1>
  <p>Take the site for a test drive, explore what your residents will experience, and see exactly what you'll be managing as the administrator.</p>
</div>

<!-- TEST DRIVE -->
<section class="testdrive-bg">
  <div class="section-inner">
    <div class="section-label">Live Demo</div>
    <h2 class="section-title">Take it for a test drive</h2>
    <p class="section-intro">The best way to understand what SheepSite delivers is to use it. The demo site below is a fully working SheepSite installation — explore it as a resident, then log in as the admin to see what you'd be managing.</p>

    <div class="testdrive-box">
      <div class="testdrive-main">
        <h3>SampleSite Demo</h3>
        <p>A complete SheepSite building website — same features, same interface, same experience your residents and board will have. Browse the public area, log in as a resident, and try asking Woolsy a condo question.</p>
        <a href="https://samplesite.sheepsite.com/" target="_blank" class="btn-blue">Open Demo Site &rarr;</a>
      </div>
      <div class="creds-grid">
        <div class="cred-box">
          <h4>Resident Login</h4>
          <div class="cred-row">Username: <span>SampleSite</span></div>
          <div class="cred-row">Password: <span>Testdrive</span></div>
        </div>
        <div class="cred-box">
          <h4>Admin Login</h4>
          <div class="cred-row">Username: <span>admin</span></div>
          <div class="cred-row">Password: <span>admintest</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TWO EXPERIENCES -->
<section>
  <div class="section-inner">
    <div class="section-label">Two Perspectives</div>
    <h2 class="section-title">One site. Two very different experiences.</h2>
    <p class="section-intro">As the board administrator, you are setting up a site that serves two audiences — residents who use it to find information and manage their unit, and yourself as the admin who keeps its content up to date. Here is what each side looks like.</p>

    <div class="experience-grid">

      <div class="exp-card resident">
        <h3 class="resident-color">The Resident Experience</h3>
        <p class="role-desc">What your unit owners and residents will see and use every day.</p>
        <ul class="exp-list">
          <li>Browse public documents — rules, forms, incorporation documents, board info — with no login required</li>
          <li>Log in with a personal account to access private documents: financials, board minutes, budgets, contracts</li>
          <li>Search across every document on the site with a single keyword — no need to know which folder it's in</li>
          <li>Chat with Woolsy, the AI assistant, directly in the browser — ask anything about building rules in plain language</li>
          <li>View their unit's information — residents, vehicles, emergency contacts — and request corrections</li>
          <li>Change their own password at any time without contacting the board</li>
          <li>Reset a forgotten password instantly via email self-serve — the board is never involved</li>
        </ul>
        <a href="https://sheepsite.com/Scripts/docs/Sheepsite-Resident-Manual.html" target="_blank" class="manual-link resident">
          📖 Read the Resident Manual
        </a>
      </div>

      <div class="exp-card admin">
        <h3 class="admin-color">The Admin Experience</h3>
        <p class="role-desc">What you — the board administrator — will manage from your private dashboard.</p>
        <ul class="exp-list">
          <li>Upload documents with drag and drop — files appear on the site instantly, no extra steps</li>
          <li>Add, edit, and remove residents from the built-in database — resident and parking reports update themselves automatically</li>
          <li>Manage individual owner login accounts, or run a one-click Sync to create accounts for your entire database at once</li>
          <li>Tag documents with keywords to make them easier for residents to find through search</li>
          <li>Monitor storage usage with a built-in storage report — upgrade your plan in seconds if needed</li>
          <li>Set up and update Woolsy's knowledge base — point it at your governing documents and let the AI do the work</li>
          <li>View and pay invoices directly from your admin dashboard — billing is automated and transparent</li>
        </ul>
        <a href="https://sheepsite.com/Scripts/docs/Sheepsite-Admin-Manual.html" target="_blank" class="manual-link admin">
          📋 Read the Admin Manual
        </a>
      </div>

    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="contact-bg" id="contact">
  <div class="section-inner">
    <div class="section-label">Get Started</div>
    <h2 class="section-title">Ready to get your community online?</h2>
    <p class="section-intro">Tell us about your association and we'll take it from there. Setup is handled by SheepSite — your site will be ready to use before you know it.</p>

    <div class="contact-form">
      <?php if ($sent): ?>
        <div class="alert-success">
          <strong>Message sent!</strong> Thank you — we'll be in touch shortly.
        </div>
      <?php else: ?>
        <?php if ($error): ?>
          <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <div class="form-row">
            <label for="name">Your Name</label>
            <input type="text" id="name" name="name" placeholder="Jane Smith" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-row">
            <label for="association">Association / Building Name</label>
            <input type="text" id="association" name="association" placeholder="e.g. Sunny Beach Condo Association" required value="<?= htmlspecialchars($_POST['association'] ?? '') ?>">
          </div>
          <div class="form-row">
            <label for="email">Your Email Address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-row">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="(555) 123-4567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
          <div class="form-row">
            <label for="message">Tell us about your community</label>
            <textarea id="message" name="message" placeholder="How many units? Any questions? Anything you'd like us to know…" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="contact_submit" class="btn-submit">Send Message</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<footer>
  <div class="footer-brand">
    <img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" alt="SheepSite">
    <span>SheepSite</span>
  </div>
  <p>Building websites for Florida condominium associations.</p>
  <nav>
    <a href="index.php">Home</a>
    <a href="index.php#features">Features</a>
    <a href="index.php#woolsy">Woolsy AI</a>
    <a href="#contact">Contact</a>
  </nav>
  <p style="margin-top:1.5rem; font-size:0.8rem;">&copy; <?= date('Y') ?> SheepSite.com &mdash; Powered by Sheep</p>
</footer>

</body>
</html>
