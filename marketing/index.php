<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SheepSite — Building Websites for Florida Condo Associations</title>
  <meta name="description" content="SheepSite provides fully built, Florida 718-compliant condo association websites — complete with document storage, AI assistant, resident database, and self-serve owner accounts. No technical skills required.">
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

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--dark);
      background: var(--white);
      line-height: 1.65;
      font-size: 16px;
    }

    /* ── NAV ── */
    nav {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--white);
      border-bottom: 1px solid #e2e8f0;
      padding: 0 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 64px;
    }
    .nav-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--blue);
    }
    .nav-brand img { height: 38px; }
    .nav-links { display: flex; gap: 2rem; list-style: none; }
    .nav-links a {
      text-decoration: none;
      color: var(--mid);
      font-size: 0.95rem;
      font-weight: 500;
      transition: color 0.15s;
    }
    .nav-links a:hover { color: var(--blue); }
    .nav-cta {
      background: var(--blue);
      color: var(--white) !important;
      padding: 8px 20px;
      border-radius: 6px;
      transition: background 0.15s !important;
    }
    .nav-cta:hover { background: var(--blue-dk) !important; color: var(--white) !important; }

    /* ── HERO ── */
    .hero {
      background: linear-gradient(135deg, var(--blue-dk) 0%, var(--blue) 60%, #3a7ab5 100%);
      color: var(--white);
      padding: 5rem 2rem 4rem;
      text-align: center;
    }
    .hero-inner {
      max-width: 820px;
      margin: 0 auto;
    }
    .hero-badge {
      display: inline-block;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      color: #fff;
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      padding: 5px 14px;
      border-radius: 20px;
      margin-bottom: 1.5rem;
    }
    .hero h1 {
      font-size: clamp(2rem, 5vw, 3.2rem);
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: 1.2rem;
    }
    .hero h1 span { color: #fbbf24; }
    .hero p {
      font-size: clamp(1rem, 2.5vw, 1.2rem);
      color: rgba(255,255,255,0.85);
      max-width: 640px;
      margin: 0 auto 2.5rem;
    }
    .hero-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
    .btn-primary {
      background: #fbbf24;
      color: #1a2840;
      font-weight: 700;
      font-size: 1rem;
      padding: 14px 32px;
      border-radius: 8px;
      text-decoration: none;
      transition: background 0.15s, transform 0.1s;
      display: inline-block;
    }
    .btn-primary:hover { background: #f59e0b; transform: translateY(-1px); }
    .btn-outline {
      background: transparent;
      color: var(--white);
      border: 2px solid rgba(255,255,255,0.5);
      font-weight: 600;
      font-size: 1rem;
      padding: 12px 30px;
      border-radius: 8px;
      text-decoration: none;
      transition: border-color 0.15s, background 0.15s;
      display: inline-block;
    }
    .btn-outline:hover { border-color: #fff; background: rgba(255,255,255,0.1); }
    .hero-woolsy {
      margin-top: 3rem;
    }
    .hero-woolsy img { height: 130px; opacity: 0.92; }
    .hero-woolsy p {
      font-size: 0.8rem;
      color: rgba(255,255,255,0.5);
      margin-top: 0.5rem;
      margin-bottom: 0;
    }

    /* ── COMPLIANCE BANNER ── */
    .compliance-bar {
      background: var(--amber);
      color: var(--white);
      text-align: center;
      padding: 1rem 2rem;
      font-size: 0.95rem;
      font-weight: 500;
    }
    .compliance-bar strong { font-weight: 700; }

    /* ── SECTION COMMONS ── */
    section { padding: 5rem 2rem; }
    .section-inner { max-width: 1080px; margin: 0 auto; }
    .section-label {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--blue);
      margin-bottom: 0.6rem;
    }
    .section-title {
      font-size: clamp(1.6rem, 3vw, 2.2rem);
      font-weight: 800;
      color: var(--dark);
      margin-bottom: 1rem;
      line-height: 1.25;
    }
    .section-intro {
      font-size: 1.05rem;
      color: var(--mid);
      max-width: 680px;
      margin-bottom: 3rem;
    }

    /* ── FEATURE GRID ── */
    .features-bg { background: var(--light); }
    .feature-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }
    .feature-card {
      background: var(--white);
      border: 1px solid #e2e8f0;
      border-radius: var(--radius);
      padding: 1.75rem;
      transition: box-shadow 0.2s, transform 0.15s;
    }
    .feature-card:hover { box-shadow: 0 8px 24px rgba(42,90,138,0.12); transform: translateY(-2px); }
    .feature-icon {
      width: 48px;
      height: 48px;
      background: var(--blue-lt);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      font-size: 1.4rem;
    }
    .feature-card h3 {
      font-size: 1rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.5rem;
    }
    .feature-card p {
      font-size: 0.9rem;
      color: var(--mid);
      line-height: 1.6;
    }

    /* ── WOOLSY SPOTLIGHT ── */
    .woolsy-section {
      background: linear-gradient(135deg, var(--blue-dk) 0%, var(--blue) 100%);
      color: var(--white);
    }
    .woolsy-inner {
      max-width: 1080px;
      margin: 0 auto;
      display: flex;
      gap: 4rem;
      align-items: center;
    }
    .woolsy-text { flex: 1; }
    .woolsy-text .section-label { color: #fbbf24; }
    .woolsy-text .section-title { color: var(--white); }
    .woolsy-text .section-intro { color: rgba(255,255,255,0.8); margin-bottom: 2rem; }
    .woolsy-features { list-style: none; margin-bottom: 2rem; }
    .woolsy-features li {
      padding: 0.5rem 0;
      color: rgba(255,255,255,0.9);
      font-size: 0.95rem;
      display: flex;
      gap: 0.6rem;
      align-items: flex-start;
    }
    .woolsy-features li::before {
      content: "✓";
      color: #fbbf24;
      font-weight: 700;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .woolsy-image {
      flex: 0 0 200px;
      text-align: center;
    }
    .woolsy-image img { width: 180px; }

    /* ── SELF-SERVE ── */
    .selfserve-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3rem;
      align-items: center;
    }
    .selfserve-text .section-intro { margin-bottom: 1.5rem; }
    .check-list { list-style: none; }
    .check-list li {
      padding: 0.55rem 0;
      font-size: 0.95rem;
      color: var(--mid);
      display: flex;
      gap: 0.7rem;
      align-items: flex-start;
      border-bottom: 1px solid #edf2f7;
    }
    .check-list li:last-child { border-bottom: none; }
    .check-list li::before {
      content: "✓";
      color: var(--blue);
      font-weight: 700;
      flex-shrink: 0;
      margin-top: 1px;
    }
    .stat-box {
      background: var(--blue-lt);
      border-radius: var(--radius);
      padding: 2.5rem;
      text-align: center;
    }
    .stat-number {
      font-size: 3.5rem;
      font-weight: 900;
      color: var(--blue);
      line-height: 1;
      margin-bottom: 0.4rem;
    }
    .stat-label {
      font-size: 0.9rem;
      color: var(--mid);
      font-weight: 500;
    }
    .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

    /* ── CTA ── */
    .cta-section {
      background: var(--light);
      text-align: center;
    }
    .cta-section .section-title { margin: 0 auto 1rem; }
    .cta-section .section-intro { margin: 0 auto 2rem; }
    .cta-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
    .btn-blue {
      background: var(--blue);
      color: var(--white);
      font-weight: 700;
      font-size: 1rem;
      padding: 14px 32px;
      border-radius: 8px;
      text-decoration: none;
      transition: background 0.15s;
      display: inline-block;
    }
    .btn-blue:hover { background: var(--blue-dk); }

    /* ── FOOTER ── */
    footer {
      background: var(--dark);
      color: rgba(255,255,255,0.6);
      padding: 2.5rem 2rem;
      text-align: center;
      font-size: 0.875rem;
    }
    footer .footer-brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 0.75rem;
    }
    footer .footer-brand img { height: 28px; opacity: 0.85; }
    footer .footer-brand span { color: rgba(255,255,255,0.85); font-weight: 600; font-size: 1rem; }
    footer a { color: rgba(255,255,255,0.5); text-decoration: none; }
    footer a:hover { color: rgba(255,255,255,0.85); }
    footer nav { margin-top: 0.75rem; display: flex; gap: 1.5rem; justify-content: center; }

    /* ── RESPONSIVE ── */
    @media (max-width: 768px) {
      .nav-links { display: none; }
      .woolsy-inner { flex-direction: column; gap: 2rem; }
      .woolsy-image { order: -1; }
      .selfserve-grid { grid-template-columns: 1fr; }
      .stat-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ── NAV ── -->
<nav>
  <a href="#" class="nav-brand">
    <img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" alt="SheepSite">
    SheepSite
  </a>
  <ul class="nav-links">
    <li><a href="#features">Features</a></li>
    <li><a href="#woolsy">Woolsy AI</a></li>
    <li><a href="#self-serve">Self-Serve</a></li>
    <li><a href="get-started.php" class="nav-cta">Get Started</a></li>
  </ul>
</nav>

<!-- ── HERO ── -->
<div class="hero">
  <div class="hero-inner">
    <div class="hero-badge">Florida Section 718 Compliant</div>
    <h1>Your Association Website,<br><span>Built and Ready to Go</span></h1>
    <p>A complete, fully hosted condo association website — documents, owner accounts, AI assistant, and more — delivered ready to use. No technical skills required.</p>
    <div class="hero-actions">
      <a href="get-started.php" class="btn-primary">Get Started Today</a>
      <a href="#features" class="btn-outline">See What's Included</a>
    </div>
    <div class="hero-woolsy">
      <img src="https://sheepsite.com/Scripts/assets/Woolsy-standing-transparent.png" alt="Woolsy">
      <p>Powered by Sheep</p>
    </div>
  </div>
</div>

<!-- ── COMPLIANCE BANNER ── -->
<div class="compliance-bar">
  <strong>Florida Law Reminder:</strong> As of January 1, 2026, per Section 718 of the Florida Statutes, all condo associations with 25 or more units are required to maintain a website and publish governing documents online.
</div>

<!-- ── FEATURES ── -->
<section class="features-bg" id="features">
  <div class="section-inner">
    <div class="section-label">Everything Included</div>
    <h2 class="section-title">Built for your association.<br>Ready from day one.</h2>
    <p class="section-intro">SheepSite handles every aspect of your building website — we set it up, you manage the content. Zero technical skills required. Here is what comes with every site.</p>

    <div class="feature-grid">

      <div class="feature-card">
        <div class="feature-icon">🏗️</div>
        <h3>Custom Website, Pre-Built for You</h3>
        <p>Your association gets a fully configured website branded to your community and ready to go — no design work, no technical setup, no web developer needed. Your site can be <strong>yourbuilding.sheepsite.com</strong> or your own custom domain <strong>yourbuilding.com</strong>.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">📋</div>
        <h3>Florida 718 Compliance Built In</h3>
        <p>Your site is structured to meet all current Florida Statutes Section 718 requirements for condo association websites. Stay compliant without lifting a finger.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">📂</div>
        <h3>Public &amp; Private Document Storage</h3>
        <p>Upload documents with simple drag and drop. Public files are open to all visitors; private files are protected by individual owner accounts. No FTP, no cloud console.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🔍</div>
        <h3>Full-Site Document Search</h3>
        <p>Residents can search across every document on the site with a single keyword. No more "which folder is it in?" — everything is found instantly.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🏷️</div>
        <h3>Keyword Tagging for Documents</h3>
        <p>Tag any file with descriptive keywords so residents can find it even when the filename alone wouldn't match their search. Your tags stay with the file forever.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🎥</div>
        <h3>Large File &amp; Video Support</h3>
        <p>Store and share large video recordings — board meeting recordings, community events, and more — with dedicated high-capacity file handling built right in.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <h3>Owner &amp; Resident Database</h3>
        <p>A built-in database tracks every unit's residents, vehicles, emergency contacts, and more. Resident list, parking list, and a lobby-ready one-page resident list are all updated automatically with any change.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🔑</div>
        <h3>Individual Owner Accounts</h3>
        <p>Every owner gets their own private login. Accounts are created automatically from your database — no spreadsheet to maintain, no manual setup, no IT support.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">💳</div>
        <h3>Automated Billing &amp; Renewals</h3>
        <p>Invoice emails arrive well before your renewal date. Pay online by card in seconds, or by check if you prefer. No surprise lapses, no manual reminders to chase.</p>
      </div>

    </div>
  </div>
</section>

<!-- ── WOOLSY SPOTLIGHT ── -->
<section class="woolsy-section" id="woolsy">
  <div class="woolsy-inner">
    <div class="woolsy-text">
      <div class="section-label">Meet Woolsy</div>
      <h2 class="section-title">Your Building's AI Assistant</h2>
      <p class="section-intro">Woolsy is the AI chat assistant built into every SheepSite building website. Residents ask questions in plain language — Woolsy answers instantly, 24/7.</p>
      <ul class="woolsy-features">
        <li>Fully trained on Florida Statutes Section 718 — Woolsy knows condo law</li>
        <li>Trained on your community's own Declaration, Bylaws, and Rules &amp; Regulations</li>
        <li>Answers questions like: pet rules, renovation approvals, parking policy, guest rules, and more</li>
        <li>Residents log in directly inside the chat panel — no separate login page</li>
        <li>Logged-in owners get full conversation history and follow-up questions</li>
        <li>Knowledge base is updated by you whenever governing documents change — no SheepSite involvement needed</li>
      </ul>
      <p style="font-size:0.78rem; color:rgba(255,255,255,0.5); margin-top:1.5rem; line-height:1.5; max-width:520px;">
        <em>Woolsy is an AI assistant and is not a lawyer. Any answer of a legal nature provided by Woolsy must not be construed as valid legal advice. Always consult a qualified attorney for legal matters.</em>
      </p>
    </div>
    <div class="woolsy-image">
      <img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" alt="Woolsy AI Assistant">
    </div>
  </div>
</section>

<!-- ── SELF-SERVE ── -->
<section id="self-serve">
  <div class="section-inner">
    <div class="selfserve-grid">
      <div class="selfserve-text">
        <div class="section-label">Zero Tech Skills Needed</div>
        <h2 class="section-title">If you can drag a file into a window, you can run your site.</h2>
        <p class="section-intro">SheepSite was designed from the ground up for board members, not IT professionals. Every feature works the way you already work.</p>
        <ul class="check-list">
          <li>Drag and drop files to upload — no FTP, no third-party storage interfaces to learn</li>
          <li>Owners create accounts and reset their own passwords — you are never asked</li>
          <li>Owners update their own contact info, vehicles, and emergency contacts directly on the site</li>
          <li>Forgotten passwords are handled automatically by the system and via resident self-serve tools — zero board involvement. Account credentials follow strict industry standards: all passwords are stored as one-way cryptographic hashes, meaning no one — including SheepSite — can ever read a resident's password</li>
          <li>Resident list, parking list, and a lobby-ready one-page resident list are all updated automatically with any database change</li>
          <li>Woolsy's knowledge base is updated by clicking a button — the AI does the rest</li>
          <li>Billing is automated — invoices arrive by email, payment takes 30 seconds online</li>
        </ul>
      </div>
      <div>
        <div class="stat-grid">
          <div class="stat-box">
            <div class="stat-number">0</div>
            <div class="stat-label">Technical skills required to manage your site</div>
          </div>
          <div class="stat-box">
            <div class="stat-number">24/7</div>
            <div class="stat-label">Woolsy answers resident questions around the clock</div>
          </div>
          <div class="stat-box">
            <div class="stat-number">100%</div>
            <div class="stat-label">Self-serve owner accounts — no admin intervention</div>
          </div>
          <div class="stat-box">
            <div class="stat-number">718</div>
            <div class="stat-label">Florida statute compliance built into every site</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── CONTACT MODAL ── -->
<div id="contact-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:999; align-items:center; justify-content:center; padding:1rem;">
  <div style="background:#fff; border-radius:12px; padding:2.5rem; max-width:520px; width:100%; position:relative; max-height:90vh; overflow-y:auto;">
    <button onclick="document.getElementById('contact-modal').style.display='none'" style="position:absolute; top:1rem; right:1rem; background:none; border:none; font-size:1.4rem; cursor:pointer; color:#888; line-height:1;">&times;</button>
    <h2 style="font-size:1.3rem; font-weight:800; color:#1c2630; margin-bottom:0.4rem;">Get in Touch</h2>
    <p style="font-size:0.9rem; color:#4a5568; margin-bottom:1.75rem;">Tell us about your association — we'll get back to you promptly.</p>
    <form id="contact-form">
      <div style="margin-bottom:1.1rem;">
        <label style="display:block; font-size:0.88rem; font-weight:600; margin-bottom:0.35rem;">Your Name</label>
        <input type="text" id="cf-name" placeholder="Jane Smith" style="width:100%; padding:10px 14px; border:1px solid #cbd5e0; border-radius:6px; font-size:0.95rem; font-family:inherit;">
      </div>
      <div style="margin-bottom:1.1rem;">
        <label style="display:block; font-size:0.88rem; font-weight:600; margin-bottom:0.35rem;">Association / Building Name</label>
        <input type="text" id="cf-assoc" placeholder="e.g. Lyndhurst Condominiums" style="width:100%; padding:10px 14px; border:1px solid #cbd5e0; border-radius:6px; font-size:0.95rem; font-family:inherit;">
      </div>
      <div style="margin-bottom:1.5rem;">
        <label style="display:block; font-size:0.88rem; font-weight:600; margin-bottom:0.35rem;">Tell us about your community</label>
        <textarea id="cf-msg" rows="4" placeholder="How many units? Any questions? Anything you'd like us to know…" style="width:100%; padding:10px 14px; border:1px solid #cbd5e0; border-radius:6px; font-size:0.95rem; font-family:inherit; resize:vertical;"></textarea>
      </div>
      <div id="cf-status" style="margin-bottom:1rem; font-size:0.9rem;"></div>
      <button type="button" onclick="submitContact()" style="background:#2a5a8a; color:#fff; font-weight:700; font-size:1rem; padding:12px 28px; border:none; border-radius:8px; cursor:pointer; width:100%;">Send Message</button>
    </form>
  </div>
</div>

<!-- ── CTA ── -->
<section class="cta-section" id="contact">
  <div class="section-inner">
    <div class="section-label">Get Started</div>
    <h2 class="section-title">Ready to give your community the website it deserves?</h2>
    <p class="section-intro">Getting set up is simple. We do the heavy lifting — you get a fully working website tailored to your association.</p>
    <div class="cta-actions">
      <a href="get-started.php" class="btn-blue">See What's Included</a>
      <button onclick="document.getElementById('contact-modal').style.display='flex'" style="background:transparent; color:#2a5a8a; font-weight:700; font-size:1rem; padding:12px 28px; border:2px solid #2a5a8a; border-radius:8px; cursor:pointer;">Contact Us to Get Started</button>
    </div>
  </div>
</section>

<script>
function submitContact() {
  var name  = document.getElementById('cf-name').value.trim();
  var assoc = document.getElementById('cf-assoc').value.trim();
  var msg   = document.getElementById('cf-msg').value.trim();
  var status = document.getElementById('cf-status');
  if (!name || !assoc || !msg) {
    status.innerHTML = '<span style="color:#c53030;">Please fill in all fields.</span>';
    return;
  }
  var body = encodeURIComponent('Name: ' + name + '\nAssociation: ' + assoc + '\n\n' + msg);
  window.location.href = 'mailto:SheepSite@sheepsite.com?subject=' + encodeURIComponent('SheepSite Inquiry from ' + name + ' – ' + assoc) + '&body=' + body;
}
</script>

<!-- ── FOOTER ── -->
<footer>
  <div class="footer-brand">
    <img src="https://sheepsite.com/Scripts/assets/Woolsy-original-transparent.png" alt="SheepSite">
    <span>SheepSite</span>
  </div>
  <p>Building websites for Florida condominium associations.</p>
  <nav>
    <a href="#">Home</a>
    <a href="#features">Features</a>
    <a href="#woolsy">Woolsy AI</a>
    <a href="mailto:info@sheepsite.com">Contact</a>
  </nav>
  <p style="margin-top:1.5rem; font-size:0.8rem;">&copy; <?= date('Y') ?> SheepSite.com &mdash; Powered by Sheep</p>
</footer>

</body>
</html>
