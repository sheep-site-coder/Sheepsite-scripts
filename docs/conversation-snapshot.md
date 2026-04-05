# Conversation Snapshot - Sheepsite-scripts
**Date:** March 28, 2026 (updated)

---

## Project Overview

**Sheepsite-scripts** is the active system powering SheepSite.com — a service for Florida condo associations that need to comply with Florida Statute 718.111(12) (25+ unit buildings must maintain websites with specific documents).

This replaced the earlier `sheep_ftp` approach (GCP Cloud Functions + FTP sync), which was more complex than needed.

---

## How It Works

```
Building Website (e.g. LyndhurstH.com)
│
│  Footer script identifies the building
│  User clicks Public or Private button
│
▼
sheepsite.com/Scripts/
│  display-public-dir.php  — no login required
│  display-private-dir.php — per-owner login (bcrypt)
│
▼
Google Apps Script (dir-display-bridge.gs)
│  Runs as SheepSite Google account
│  Reads Google Drive folders
│  Returns file/folder listings as JSON
│
▼
PHP renders file browser in iframe on building website
```

### Key Design Points
- **Single Google Drive account** (SheepSite's) holds all buildings' files
- **Public folders** — anyone can browse, direct Drive download links
- **Private folders** — per-owner login, downloads proxied through PHP (Drive URLs hidden)
- **One Apps Script** shared by all buildings (dir-display-bridge.gs)
- **Per-building Apps Script** in each Google Sheet for reports and owner management
- **`buildings.php` is the only file to edit** when adding a new building

---

## Architecture

### PHP Scripts (sheepsite.com/Scripts/)
| File | Purpose |
|------|---------|
| `buildings.php` | Central config — maps building names to Drive folder IDs and Sheet URLs |
| `display-public-dir.php` | Public folder browser (no auth) |
| `display-private-dir.php` | Private folder browser (owner login required) |
| `manage-users.php` | Add/remove/reset resident passwords per building; import/sync from Sheet |
| `change-password.php` | Self-service password change for residents |
| `forgot-password.php` | Password reset via email (uses Apps Script) |
| `admin.php` | Admin landing page |
| `storage-report.php` | Drive storage breakdown by building |
| `protected-report.php` | Login-protected iframe for Sheets reports |
| `public-report.php` | Public iframe for Board of Directors report |
| `get-doc-byname.php` | Looks up a file by name in a building's Public folder |
| `setup-admin.php` | One-time admin setup (delete after use) |
| `search.php` | Owner-facing file search — login required, searches filenames + tag index |
| `tag-admin.php` | Admin UI to assign free-form tags to files; tags stored in `tags/{building}.json` |
| `file-manager.php` | Admin UI to upload, delete, rename files and create subfolders in Public/Private folders |

### Apps Scripts
| File | Deployment | Purpose |
|------|-----------|---------|
| `dir-display-bridge.gs` | Single shared URL (all buildings) | list, listPrivate, listAdmin, download, storageReport, search, deleteFile, renameFile, createFolder, uploadFile (doPost) |
| `sheets/building-script.gs` | Per building Google Sheet | Routes report/owner requests |
| `sheets/board-list.gs` | Master library | Board of Directors list |
| `sheets/elevator-list.gs` | Master library | Elevator list |
| `sheets/parking-list.gs` | Master library | Parking list |
| `sheets/resident-list.gs` | Master library | Resident list |
| `sheets/owner-import.gs` | Master library | Import owners from Sheet |
| `sheets/reset-password.gs` | Master library | Email temporary password |

### Credentials (not committed to git)
- `credentials/{building}.json` — resident accounts (bcrypt hashed passwords)
- `credentials/{building}_admin.json` — per-building admin
- `credentials/_master.json` — master override admin (all buildings)

---

## Buildings Configured

| Building | Status |
|----------|--------|
| QGscratch | Configured (test/scratch site) |
| SampleSite | Configured (demo/template) |
| LyndhurstH | Configured (live community) |
| LyndhurstI | Configured (live community) |

---

## Current State

**Completed:**
- [x] Session 18 — Resident-facing UX polish + database-admin performance:
      - `database-admin.php`: local `unitCache` added — after first open of a unit, all
        subsequent writes (add/edit/delete resident, vehicle, emergency) update the cache and
        re-render instantly without a second Apps Script round trip (`refreshUnitDetail`)
      - `database-admin.php`: delete resident now shows "Deleting…" toast + success toast;
        uses `await` so panel refreshes before toast appears
      - `database-admin.php`: missing `&building=` param added to welcome email URL — was
        causing `doResetPassword` to bail before sending the email
      - `database-admin.php`: `siteURL` from `config/{building}.json` baked into login URL
        in welcome emails so "← Back to site" works throughout the first-login flow
      - `admin.php`: Building Settings card gains "Building website URL" field (saves
        `siteURL` to `config/{building}.json`); card description updated
      - `display-private-dir.php`: mustChange redirect after fresh login (no returnURL) now
        goes to `my-account.php` instead of private files root
      - `my-account.php`: falls back to `siteURL` from config for "← Back to site" when no
        `return=` param is present; "Private files" link always shown in top bar; Change
        Password card always passes `redirect=my-account.php` so back link returns to hub
      - `my-unit.php`: action buttons (Edit my info, Add/Remove Resident, Edit vehicle) changed
        to `btn-primary` (blue) to look active rather than disabled; toast notification system
        added (5s saves, 7s change request); change request popup gains email, phone 1,
        phone 2, Full Time/Resident/Owner fields; PHP passes `adminUrl` to Apps Script so
        admin receives a direct link to `database-admin.php` in the change request email
      - `sheets/reset-password.gs`: welcome email body restructured — shows
        `username --> xxx  (note: all lower case)` and `password --> xxx` on indented lines
      - `sheets/database-admin.gs`: `doSendChangeRequest` includes email, phones, status
        flags, and direct admin URL in the change request email body
      - `admin.php`: "Manage Residents/Owners" card description updated — removed "Copy All
        Emails", now reads "Includes Email List capture for easy community wide emails"
      - `database-admin.php`: delete confirm dialog updated to say "database" not "Google Sheet"
      - `database-admin.php`: Get Email List toast added (8s) explaining copy + BCC usage
- [x] Session 17 — Resident Database Admin fully implemented:
      - `database-admin.php` (NEW): admin CRUD for Database, CarDB, and Emergency tabs;
        unit-centric layout with inline panel expansion; floor grouping with 3-choice prompt
        (first digit / first 2 digits / flat list) always shown on first visit regardless of
        database state; preference saved to `config/{building}.json`
      - "Get Email List" button (renamed from "Copy All Emails") with hover tooltip explaining
        paste into BCC; calls Apps Script getAllEmails endpoint
      - Account creation is email-gated: if no email provided when adding a person, only the
        DB row is written — no web account created; if email is added later, account auto-created
        at that point with welcome email sent
      - "Create Person" button (was "Add & Create Login") — reflects actual behavior
      - `directemail` parameter added to `doResetPassword` in reset-password.gs: bypasses
        Database tab lookup to avoid timing race when email is sent right after row insert
      - In-place panel refresh: `reloadUnitDetail()` re-renders only the open unit panel without
        collapsing it; active tab preserved; no full DOM re-render after save
      - Toast notification system: fixed-position, DOM-independent, 5-second auto-dismiss;
        survives panel re-renders that would otherwise swallow inline messages
      - `sheets/database-admin.gs` (NEW): master library file with 11 CRUD functions for all
        three tabs; all require OWNER_IMPORT_TOKEN auth
      - `sheets/building-script.gs` (UPDATED): 3 new GET routes (listDatabase, getUnit,
        getAllEmails) + new doPost() routing 8 write actions to DatabaseSheetMaster
      - `sheets/reset-password.gs` (UPDATED): directemail param bypasses DB lookup; welcome
        email body differs from reset email ("A login account has been created for you")
      - `my-account.php` (NEW): resident hub page — My Unit Info, Change Password, Ask Woolsy cards
      - `my-unit.php` (NEW): resident unit editor with restricted field permissions; read-only
        for First/Last Name, Owner/Resident checkboxes, Board role hidden; "Add/Remove Resident"
        button opens change request popup → email sent to building contact or President
      - `admin.php` (UPDATED): new "Manage Residents/Owners" card (first position); "Manage Users"
        renamed to "Manage User Accounts" with updated description; inline Building Settings card
        (contact email field saved to config/{building}.json)
      - `footer-for-sites.js` (UPDATED): openMyAccount() helper added
      - `config/` folder on server: per-building config JSON (floorGrouping + contactEmail)
- [x] Session 16 — FL.txt Woolsy training update + Resident Database Admin design:
      - faqs/states/FL.txt: updated with HB 913 (2025) virtual meeting recording requirements;
        rewrote "Is the association required to record board meetings?" (was wrong — now correctly
        distinguishes in-person (no) vs. video conference (mandatory); added 4 new director-context
        Q&As: official record status of recordings, director obligation, attorney-client privilege gap,
        prohibition on fully virtual meetings; updated official records + website requirements lists
      - Design doc written (memory): Resident Database Admin — full in-site management of
        Database, CarDB, and Emergency & Condo Sitter tabs; eliminates last Google Sheets dependency
        for building admins; See design_database_admin.md in memory for full spec
- [x] All PHP scripts for public/private browsing
- [x] Resident login, password change, forgot password flow
- [x] Admin user management per building
- [x] Apps Script dir-display-bridge (shared across all buildings)
- [x] Per-building Google Sheets scripts (reports + owner management)
- [x] Storage report
- [x] README.md and NEW-SITE-GUIDE.md
- [x] QGscratch, SampleSite, LyndhurstH, LyndhurstI configured
- [x] /start-sheeping and /done-sheeping commands set up
- [x] manage-users.php: Add/Reset Resident — creates or resets account, emails temp
      password via Apps Script if resident found in database, sets mustChange accordingly
- [x] manage-users.php: Import/Sync — after import, detects web accounts with no
      matching database record; shows sync panel for admin to remove or keep each one
- [x] manage-users.php: PRG pattern — all POST actions redirect to GET via session
      flash to prevent browser re-submission on refresh
- [x] manage-users.php: language standardized to "resident" throughout (was "user/owner")
- [x] manage-users.php: JS alert popup for Add/Reset action results
- [x] File search feature (branch: `feature/search-and-tagging`):
      - search.php: owner login → search by filename AND/OR tags; AND word logic;
        results show Public/Private badge, tags, download link
      - tag-admin.php: admin folder browser to assign free-form tags to files;
        tags stored as {tags, name, tree} in tags/{building}.json; autocomplete
        from existing tags; accessible from admin.php as "Manage Tags"
      - dir-display-bridge.gs: new `search` action using Folder.searchFiles()
        for recursive filename matching across both Public and Private trees
      - footer-for-sites.js: openSearch() helper added
- [x] File manager feature (branch: `feature/search-and-tagging`):
      - file-manager.php: admin UI to upload (drag & drop, multi-file), delete, rename
        files and create subfolders in Public and Private Drive folders
      - Duplicate detection: prompts to replace; on multi-file drop, skips duplicates
        and uploads the rest if admin declines replacement
      - Upload progress shown inline ("2 of 5 — Uploading filename… 34%")
      - dir-display-bridge.gs: added listAdmin (returns folder IDs + currentFolderId),
        deleteFile, renameFile, createFolder (doGet) and uploadFile (doPost)
      - admin.php: reordered cards (Manage Users, Manage Files, Manage Tags, Storage
        Report, User Manual) and renamed File Manager → "Manage Files", File Tags → "Manage Tags"
      - File size display: ≥ 1 MB shown as MB, smaller shown as KB
- [x] Tag preservation on file replace (file-manager.php):
      - When a file is replaced via the file manager, tags are automatically migrated
        from the old Drive file ID to the new one — no re-tagging needed
      - New `migrateTags` PHP endpoint reads/writes tags/{building}.json server-side
      - Rename already preserved tags (same file ID); now replace does too
- [x] Documentation suite (docs/ folder):
      - compliance-overview.html — FL statute 718.111(12) compliance overview for prospective clients
      - NEW-SITE-GUIDE.html — converted from NEW-SITE-GUIDE.md with step badges and code blocks
      - Manual-admin.html — full admin manual (generated by build-manual.py with embedded images)
      - Manual-resident.html — resident-facing guide (login, search, password reset)
      - build-manual.py — Python script to regenerate Manual-admin.html from source + images
- [x] FAQ chatbot — **deployed and working on SampleSite**:
      - Bot named "Woolsy" — sheep theme, 🐑 floating FAB with "Ask Woolsy!" speech bubble
      - chatbot-widget.js: public-facing cheeky deflector bot; zero API cost for anonymous users;
        keyword-aware deflections; "Open Resident Chat →" opens chatbot-page.php as popup window
        (changed from iframe overlay to fix third-party cookie issue in cross-domain iframes)
      - chatbot-page.php: resident-facing full chatbot; reuses private_auth_{building} session;
        logout handler added; greets resident by name; calls chatbot.php for answers
      - chatbot.php: API endpoint; model: claude-haiku-4-5-20251001; assembles context from FAQ layers
      - ANTHROPIC_API_KEY stored in Scripts/.htaccess on server (SetEnv)
      - footer-for-sites.js: added `window.BUILDING_NAME = BUILDING_NAME` — required for external
        scripts to access building name (const doesn't go on window, var does)
      - faqs/ folder structure: _global.txt (written), states/FL.txt (written),
        {building}.txt templates, {building}_rules.md (gitignored, generated during setup phase)
      - FAQ context layers: _global.txt → states/{STATE}.txt → communities/{COMMUNITY}.txt → {building}.txt → {building}_rules.md
- [x] CVE community FAQ layer — `faqs/communities/CVE.txt` (new):
      - Covers all three governing entities at CVE (building association, CVE Master Management, CenClub)
      - CVE Master Management rules: city water prohibition, mobile car wash ban, SFWMD/Broward County
        watering schedule (hours, banned days, address-based schedule, hand-watering exemption)
      - CenClub Recreation Management rules: full facility list + hours, ID card requirement, general
        conduct, pet policy (NO animals incl. ESAs — service animals only per FL §413.08), children/minors
        restrictions, dress code, guest rules (2 max, no guests in classes, fitness after 1pm, 30-day pass),
        fitness center rules, sauna rules, full contact directory
      - chatbot.php updated: loads community layer between state and building layers
      - buildings.php updated: added `community => 'CVE'` to SampleSite, LyndhurstH, LyndhurstI
      - Source attribution added: specific URLs and refresh instructions for future updates
- [x] LyndhurstH governing docs extracted → faqs/LyndhurstH_rules.md:
      - Source documents: Articles of Incorporation, Bylaws, Declaration of Condominium (all 2021
        Amended and Restated versions); text-based PDFs stored in Sharefolder/
      - Full extraction covering: age/ownership rules, pets, guests, rentals, parking, smoking,
        alterations, floor coverings, maintenance split, assessments, insurance, fines, board structure,
        common element rules, washing machines, EV charging, and more
      - Key nuances captured: ownership vs. residency age rules are separate; 55+ rule is per unit
        not per person; spouse of 55+ owner may reside regardless of own age; adult child of
        part-year owner faces 14-day guest cap when owner absent + unresolved compliance question
        if seeking approved resident status; both scenarios include full supporting reasoning
      - File is gitignored (faqs/*_rules.md) — must be uploaded to server manually
- [x] LyndhurstH_rules.md expanded with 3 additional Board documents (March 2026):
      - "Welcome to Lyndhurst H" (Dec 2025), "Condo Life at Lyndhurst H" (Apr 2025),
        Bulk & Debris Rules — all merged into LyndhurstH_rules.md
      - New sections: Contacts & Resources, Bulk Trash & Debris, Major Deliveries & Moving,
        Electric Bikes/Scooters, Security Gate & ID Cards, Communications & Board Contact
      - Updated sections: Smoking (25-ft rule), Renovation Rules (hours, permits, approval
        package, contractor requirements), Maintenance (A/C fluid, water heater, pest control),
        Common Elements (garbage disposal, laundry hours/no-bleach, catwalks fire rule, no
        feeding animals), Parking (15 guest spots, forward-in rule, car washing clarified),
        Guest Rules (must be present; guest pass process), Rental (55+ occupant required)
      - Source PDFs stored in Sharefolder/lyndhurst-rules-new/
- [x] SampleSite.txt and SampleSite_rules.md created (mirrors LyndhurstH — same community)
- [x] Condo-Life-at-Lyndhurst-H.html — styled HTML version of the Rules Summary PDF,
      matching the PDF's Garamond font, blue/black label colors, red highlights, and layout;
      saved to Sharefolder/lyndhurst-rules-new/
- [x] Woolsy credit system — tracking, gating, and admin display:
      - chatbot.php: credit check before each API call (hard stop at limit); token-cost
        deduction after successful call using Haiku pricing ($0.80/$4.00 per MTok)
      - faqs/woolsy_credits.json: seed file (gitignored, upload manually); all buildings
        start at 1 credit allocated, 0 used
      - admin.php: Woolsy Knowledge Base card always visible; shows used/allocated with
        progress bar, low-credit warning (≥80%), exhausted notice (100%)
      - master-admin.php: operator card hub (auth: _master.json); Woolsy Management card
        links to woolsy-admin.php
      - woolsy-admin.php: all-buildings credit table (allocated/used/remaining/status) +
        top-up form; "← Master Admin" back link
      - All files uploaded to server ✓
- [x] CVE.txt: added Building Types section (2-story Garden vs 4-story with internal
      elevators) and exterior elevator removal Q&A (FL 718.113 material alteration,
      Fair Housing Act exposure, permit requirements)
- [x] Woolsy doc indexing by admin — fully built (session 12):
      - woolsy-update.php: admin UI for setup (first time) and update (when changes detected)
        · Setup mode: lists all docs in IncorporationDocs/ + RulesDocs/
        · Update mode: same, with CHANGED/NEW/REMOVED badges on changed files
        · Progressive AJAX probe — extracts text from each PDF one by one, live progress counter
        · Handles scanned PDFs: Google Drive OCR; warns if text < 100 chars (likely unreadable)
        · Credit estimate shown before committing: ~X credits (charCount/4 tokens, Haiku pricing)
        · Calls Claude (Haiku) with full doc text; returns proposed rules.md; Accept / Cancel
        · On Accept: saves faqs/{building}_rules.md + stamps baseline in Apps Script
      - dir-display-bridge.gs: 8 new actions + checkAllBuildings() weekly trigger function:
        · checkAllBuildings() — time-driven trigger; scans Drive metadata only, no API calls
        · runDocCheck() — internal: compares file names + modifiedTime to stored baseline
        · docCheckResult — returns stored check result from Script Properties
        · docCheck — on-demand scan (called from admin "Check now" button)
        · listDocFiles — file listing for IncorporationDocs + RulesDocs with sizes
        · extractDocText — copies PDF as Google Doc (triggers OCR), exports plain text, deletes temp
        · stampBaseline — stamps current file state, marks building initialized, registers for weekly checks
      - admin.php: Woolsy card updated with async doc status section:
        · Loads docCheckResult via AJAX on page load; no extra latency on page render
        · Three states: Not set up → link to woolsy-update.php; ✅ Up to date + "Check now" button;
          ⚠️ N files changed → link to woolsy-update.php
        · "Check now" triggers on-demand scan and refreshes status inline
      - Weekly trigger: set up checkAllBuildings() in Apps Script with time-driven trigger (weekly)
        Skips buildings that haven't done initial setup (_initialized flag not set)
      - Scanned PDF handling: Drive's OCR converts scanned PDFs automatically on copy;
        files with < 100 extracted chars are flagged ⚠️ with user notice; skipped in processing
- [x] Session 15 — Woolsy management page, usage/login tracking, board member fix:
      - woolsy-manage.php: NEW — dedicated Woolsy management dashboard replacing the bloated
        admin.php Woolsy card; sections: KB status + Check now, Document Index build/rebuild,
        Usage Statistics (last 12 months bar chart + this month/year/all-time totals),
        Credit Usage bar, Building FAQ editor
      - admin.php: Woolsy card simplified to a clean link card like all others; shows
        "X questions this month" or credit warning inline; rebuild banner still shown if outdated;
        all Woolsy AJAX handlers + JS + CSS removed from admin.php (moved to woolsy-manage.php)
      - chatbot.php: logUsage() added — increments faqs/woolsy_usage.json by month per building
        after each successful API call; tracks cumulative question counts
      - display-private-dir.php + chatbot-page.php: logLogin() added — logs per-user daily
        login counts to credentials/login_stats.json; auto-prunes entries older than 12 months
      - manage-users.php: user table gains "30 days" and "12 months" login count columns;
        green when active, dash when zero; mustChange flag shown inline as "pw reset" badge
      - woolsy-update.php: PROMPT_VERSION bumped to v4; board member names/contacts excluded
        from extraction (dynamic data changes with elections); chatbot.php updated to redirect
        board composition questions to live Board of Directors report instead of stale rules.md
      - chatbot-page.php: session format bug fixed — was storing ['user'=>name] array but
        display-private-dir.php expects plain string; users logging in via Woolsy popup can
        now access private files without re-authenticating
      - docs/build-manual.py: new "Editing the Building FAQ" subsection documenting the inline
        FAQ editor, when to use it, board member caveat; admin manual rebuilt
- [x] Session 14 — Woolsy quality improvements, Architecture Manual, UX fixes:
      - woolsy-update.php: switched extraction model from Haiku → Sonnet 4.6 (better legal doc
        comprehension); CURL timeout 120→240s; PHP set_time_limit 180→300s; processing message
        updated to "2–3 minutes"; live elapsed-time counter added to processing step
      - woolsy-update.php: document exclusion feature — "Include" checkbox per readable file;
        admin can uncheck duplicates/scans before build; estimate updates live; excluded files
        omitted from Claude call
      - woolsy-update.php: credit estimate overhauled for Sonnet pricing ($3/$15 per MTok);
        now includes existing rules.md tokens in input estimate; output ratio raised to 55%;
        label changed to "up to ~X credits"
      - master-admin.php: Architecture Manual card added (links to docs/Sheepsite-Architecture.html)
      - docs/build-architecture.py + Sheepsite-Architecture.html: NEW — comprehensive architecture
        manual covering system overview, actors, deployment topology, GAS layer rationale, file
        inventory, auth architecture, Woolsy stack, data files, adding a building, and Operator
        Procedures (prompt versioning, credit top-up, server maintenance)
      - Woolsy question-lost bug RESOLVED: root cause was browser caching old chatbot-widget.js;
        fix: ?q= in popup URL + URLSearchParams read in JS + preserve ?q= through login redirect
      - LyndhurstH Woolsy widget activated: chatbot-widget.js script tag added to footer
      - PROMPT_VERSION bump to v3: admin card shows amber rebuild banner for outdated buildings
      - normalizeKey() + rewritten computeDelta(): fuzzy heading matching prevents false NEW+REMOVED
        pairs from Claude varying dash style or capitalization
      - Extraction prompt overhauled: removed predefined topic list; resident-focused "would a
        resident ask about this?" test; explicit callouts for unit boundary definitions and common
        element modification rules
- [x] Session 13 — Documentation, UX fixes, and Woolsy document awareness:
      - manage-users.php: added "← Admin" back link to top bar (matches other card pages)
      - Manual rename: Manual-admin.html → Sheepsite-Admin-Manual.html,
        Manual-resident.html → Sheepsite-Resident-Manual.html; docs/ served from Scripts/docs/
      - admin.php: "User Manual" card renamed "Admin User Manual"; URL updated to docs/Sheepsite-Admin-Manual.html
      - build-manual.py: added Section 9 (Woolsy Knowledge Base) with "Before You Begin" block,
        setup/update flow reflecting new checklist UI, scanned PDF conversion note, weekly check description;
        updated TOC and dashboard card table
      - build-resident.py: NEW — converts static Sheepsite-Resident-Manual.html to Python build
        script matching build-manual.py structure; adds Section 6 (Ask Woolsy), adds Section 9
        (Getting Help with Woolsy-first guidance); credential delivery wording corrected
      - woolsy-update.php: review step redesigned — instead of raw text dump, shows a section-level
        delta checklist (NEW/CHANGED/REMOVED per ## heading); all checked by default; admin unchecks
        to reject; save applies only accepted changes; setup mode shows all sections as NEW checkboxes
      - dir-display-bridge.gs: added buildDocIndex action (recursive public folder walk → {path, files[]});
        Forms folder added to runDocCheck + handleStampBaseline weekly scan
      - admin.php: added buildDocIndex AJAX action; Woolsy card shows Document Index row with
        Build/Rebuild button and inline status feedback
      - chatbot.php: loads faqs/{building}_docindex.txt; injected into system prompt with instruction
        to reference document locations and suggest Search when unsure
      - woolsy-update.php: auto-rebuilds document index after each knowledge base save
      - Known bug (saved to memory): question typed in public Woolsy widget is lost after resident
        logs in — two fixes attempted (URL param + session stash), root cause not yet resolved

---

## Next Steps

- Onboard additional communities as they sign up (follow NEW-SITE-GUIDE.md)
- **Extract governing docs for LyndhurstI** — same process as LyndhurstH when docs are available
- **Session 19 continuation — see below**

---

## Session 19 — Work Completed

- **docs/terms-of-service.html** (NEW) — click-through license agreement for new building admins.
  Covers: service description, data collected (association financial docs vs. no personal financial data),
  admin responsibilities, security measures, data breach limitation of liability, FL §718.111(12) compliance
  disclaimer, Woolsy not-legal-advice disclaimer, no warranty, liability cap, indemnification, governing law
  (Florida / Broward County). Includes explicit Administrator Risk Acknowledgment block (they affirm measures
  are reasonable and accept residual risk). Not yet wired into the UI — needs attorney review first.

- **manage-users.php** — Bulk Account Management section redesigned:
  - Split into three sub-sections inside a single visual block: Import from CSV, Import from Sheet, Sync
  - **Import from CSV**: drag-and-drop CSV upload; client-side parsing with flexible column detection
    (First Name, Last Name, Unit #, Email, Phone); preview table with username previews before submit;
    Import button disabled until valid file loaded
  - **Import from Sheet**: same as before, updated description clarifying it's a one-time catch-up tool
  - **Sync — Find Orphaned or Missing Accounts**: now does both directions in one pass:
    - Orphans (yellow panel) — web accounts with no database match → admin checks to remove
    - Missing accounts (blue panel) — database residents with no web account → admin checks to recreate;
      system auto-generates temp password and sends welcome email (same as database-admin.php flow)
  - Removed manual temp password entry from Sync entirely — consistent with auto-generate-and-email pattern
  - Added `generateTempPassword()` helper

---

## Session 19 — Architectural Decision: Import/Sync Separation

**Problem identified:** The CSV import in manage-users.php was doing the wrong thing — it was creating
web credentials from CSV data, but the CSV should be populating the *resident database*, not the credentials file.

**Agreed architecture going forward:**

| Card | Responsibility |
|------|---------------|
| **Manage Residents/Owners (database-admin.php)** | Resident *data* — add/edit/delete, and CSV bulk import INTO the database |
| **Manage User Accounts (manage-users.php)** | Web *access* — individual Add/Reset, and Sync (create accounts for DB residents who don't have one, remove orphans) |

**Import from Sheet in manage-users.php becomes redundant** under this model — Sync alone handles
"create accounts for everyone in the database who doesn't have one."

---

## Session 20 — Work Completed

- **manage-users.php** — Import cleanup:
  - Removed **Import from CSV** and **Import from Sheet** PHP handlers and HTML entirely
  - "Bulk Account Management" section replaced with a clean standalone **Sync** section (h2 + description + button)
  - Removed all `.bulk-section`, `.bulk-subsec`, `.drop-zone`, `.csv-*` CSS
  - Removed CSV import JS IIFE entirely

- **database-admin.php** — CSV import added:
  - New `importResidents` AJAX handler (PHP) → POSTs rows to Apps Script
  - "⤓ Import from CSV" button in toolbar toggles a collapsible import panel
  - Panel: drag-and-drop CSV, flexible column detection (First/Last/Unit/Email/Phone), preview table, Import button
  - On success: 10s toast with counts + prompt to use Sync for logins; unit list reloads in place

- **sheets/database-admin.gs** — New `doImportResidents(data, expectedToken)`:
  - Validates token; reads existing First+Last combos into a Set
  - Appends new rows (Unit #, First Name, Last Name, eMail, Phone #1) for each non-duplicate
  - Returns `{ok: true, added, skipped}`

- **sheets/building-script.gs** — Added `importResidents` case to `doPost()`

## Session 19 — Work To Do Next Session

### Task 3 — Wire up terms-of-service.html as a click-through on first admin login
- **Prerequisite: attorney review of terms-of-service.html before activating**
- Version-tracked: `config/tos.json` holds current version number + effective date
- Per-building acceptance stored in `config/{building}.json` as `tosAccepted: {version, date, who}`
- On admin.php login: compare accepted version to current → if mismatch, redirect to `tos-accept.php`
- Accept → writes to building config + appends to `config/tos_signatures.json` (audit archive)
- Decline → logs out

### Task 4 — Master admin ToS management card (tos-admin.php)
- New card in master-admin.php: "License Agreements" → tos-admin.php
- Signature status table: all buildings, current vs. pending, signed-by, date, version
- "Issue New Version" → bumps version in tos.json, archives all current signatures, forces re-acceptance
- Full signature history / audit trail (append-only, never deleted)

### Task 5 — Document ToS in user manual (Sheepsite-Admin-Manual.html)
- Explain ToS is shown on first login and must be accepted to proceed
- When SheepSite updates the terms, re-acceptance required on next login
- Previous acceptances are archived

---

## Session 20 (continued) — Master Admin + Billing System

- **master-admin.php** — full redesign as per-building association dashboard:
  - One card per building: name, site URL, Woolsy credit bar, Storage bar, ToS badge (if in scope),
    Renewal date (red if ≤30 days), "Manage →" link
  - Card border highlights: warn at 70%, alert at 90% or renewal ≤30 days
  - "+ Add New Building" → building-detail.php?building=new
  - System tools grid: Woolsy Overview, License Agreements, Pricing, Architecture Manual

- **building-detail.php** (NEW) — per-building management page:
  - `?building=new`: form that generates buildings.php snippet for copy-paste
  - Existing buildings: Overview stats row, Configuration (siteURL/contactEmail/renewalDate/hasDomain),
    Woolsy Credits (stats + manual top-up + reset), Storage (stats + bar + set limit),
    License Agreement (status + archived signatures), Billing placeholder (coming soon)

- **pricing-admin.php** (NEW) — master admin tool for pricing configuration:
  - Site subscription (siteMonthlyPrice + domainAnnualPrice)
  - Woolsy credits (creditPrice per credit)
  - Storage tiers (label + bytes + pricePerMonth) with add/remove tier UI
  - Saves to config/pricing.json (gitignored)

- **tos-admin.php** (NEW) — master admin ToS management:
  - Reuses master_admin_auth session
  - Current version, scope enrollment (per-building checkboxes or all), signature status table,
    Issue New Version (archives existing signatures), Signature History collapsible

- **tos-accept.php** (NEW) — building admin ToS click-through gate:
  - Requires manage_auth_{building} session
  - Renders ToS iframe with version/date; Accept writes to config/{building}.json + appends to
    config/tos_signatures.json; Decline unsets session

- **admin.php** — ToS gate added: after mustChange check, if building is in ToS scope and admin
  hasn't accepted current version → redirect to tos-accept.php

- **storage-report.php** — cacheTotal POST action: writes storageUsed + storageUpdated to
  config/{building}.json (used by master-admin.php dashboard + file upload limit enforcement)

- **file-manager.php** — folder delete + modal bug fix:
  - App-created folders tracked in config/{building}_folders.json; only those get Delete button
  - dir-display-bridge.gs: handleDeleteFolder — checks empty, calls setTrashed(true)
  - Modal button fix: window.closeFmConfirm and window.proceedFmConfirm assigned to window
    (not plain function declarations) so HTML onclick can find them inside the IIFE

- **config/pricing.json** (NEW, gitignored) — seed file with $0.00 prices for all fields

- **config/tos.json** (NEW, gitignored) — version 1, scoped to SampleSite for testing

- **billing-helpers.php** (NEW) — shared billing email trigger helpers:
  - checkWoolsyThreshold(building, used, allocated) — fires billing email at 90%; once only (flag)
  - checkStorageThreshold(building) — fires billing email when upload blocked; once only (flag)
  - generateBillingToken() — stores random 64-char token in config/{building}.json (7-day TTL)
  - sendBillingEmail() — mail() to contactEmail with tokenized billing.php link
  - Flags: woolsyBillingEmailSent / storageLimitEmailSent in config/{building}.json

- **chatbot.php** — calls checkWoolsyThreshold() after deductCost(); requires billing-helpers.php

- **file-manager.php** — storage limit check in upload handler: if storageUsed cached + upload
  would exceed limit → calls checkStorageThreshold() + returns error to UI

- **billing.php** (NEW) — customer-facing Stripe Checkout payment page:
  - Token-validated (no session needed — token IS the auth)
  - Woolsy: quantity input, live total, Stripe Checkout for credit purchase
  - Storage: tier selection with pro-rated pricing (to renewalDate if set, else 12 months)
  - Reads config/stripe.json for Stripe secret key; shows "not configured" notice if absent
  - Stripe Checkout session created via cURL (no library needed)
  - Metadata passed: building, type, credits_to_add OR new_bytes

- **billing-webhook.php** (NEW) — Stripe webhook (checkout.session.completed):
  - Verifies Stripe signature (HMAC SHA-256, 5-min replay window)
  - Woolsy: adds credits to woolsy_credits.json, clears woolsyBillingEmailSent flag
  - Storage: sets storageLimit in config/{building}.json, clears storageLimitEmailSent flag
  - Both: clears billingToken (one-time use)
  - Idempotency: processed payment_intent IDs stored in config/processed_payments.json

- **billing-success.php** (NEW) — Stripe post-payment landing page (cosmetic; update via webhook)

- **docs/build-manual.py** — Section 4 folder delete documented; ToS section added in Section 1;
  "How the System Works" heading + architecture diagram removed

---

**Config files (gitignored) that need to exist on server:**
- `config/stripe.json` — `{"secretKey":"sk_live_...","webhookSecret":"whsec_..."}`
- `config/pricing.json` — seed file committed; update via pricing-admin.php
- `config/tos.json` — `{"version":1,"effectiveDate":"YYYY-MM-DD","documentPath":"docs/terms-of-service.html","scope":["SampleSite"]}`
- `config/processed_payments.json` — created automatically by webhook on first payment

---

---

## Session 21 — Protected Files, PDF Embeds, Admin Manual Cleanup, Add New Building

### file-manager.php
- **Protected system files**: `Announcement Page 1` and `Mid-End Year Report` annotated as `protected: true` in `?json=list` PHP handler
- Protected files show a **Replace** button (amber) + greyed "system file" label; no Rename or Delete
- Replace flow: admin selects any file → uploads to folder → renames to exact protected name → migrates tags → deletes old file; website embed continues working by name
- Hidden `<input id="replace-input">` + `replaceProtected()` / `handleReplaceSelect()` JS functions added
- **Background storage refresh**: fire-and-forget `fetch()` on DOMContentLoaded calls `?json=storage_refresh` endpoint; updates `config/{building}.json` silently every time admin opens file manager

### get-doc-byname.php
- Now picks correct preview URL based on MIME type returned by GAS listing:
  - Google Doc → `docs.google.com/document/d/{id}/preview`
  - Google Sheet → `docs.google.com/spreadsheets/d/{id}/preview`
  - Google Slides → `docs.google.com/presentation/d/{id}/preview`
  - PDF / any other file → `drive.google.com/file/d/{id}/preview`
- Enables replacing Google Doc embeds with PDFs (admin uses Replace button, no name change needed)

### docs/build-manual.py (admin manual)
- Removed all "manage files via Google Drive" references:
  - Deleted "Access to Google Drive" from What You Need
  - Deleted entire "Google Drive (Alternative Method)" section + screenshots
  - Updated "How Publishing Works" — no Drive mention
  - Changed both folder table headers from "Google Drive Folder" to "Folder"
  - "Deleting a file is permanent" (was "removes it from Google Drive")
  - Woolsy section: "correct folders" / "Public section" (no Drive)
- Added **System files** blockquote explaining Replace button and why these files can't be renamed/deleted
- Rebuild required: `python3 docs/build-manual.py` → upload `docs/Sheepsite-Admin-Manual.html`

### building-detail.php — Add New Building (complete redesign of `?building=new`)
- **Phase 1 form**: building key, display name, state, community + 3 pre-fillable Drive IDs (Template Folder, Association Folders, template sheet) + "Save as defaults" checkbox
- **Create Drive Folders** button → AJAX POST → calls GAS `setupBuildingFolders` → returns public/private folder IDs + sheet URL
- **Phase 2 checklist**: 6 numbered steps rendered after folder creation, all values pre-filled:
  - Step 1: auto-done (green ✓), shows both folder IDs
  - Step 2: "Open Sheet →" link + 3 remaining manual steps (BUILDING_NAME, deploy, triggers)
  - Step 3: auto-generated `buildings.php` snippet (copy button) + credentials file path
  - Step 4: exact admin URL (copy button) with setup instructions
  - Step 5: owner import walkthrough
  - Step 6: full footer script with building key pre-filled (copy button)
- Master config IDs saved to `config/_master_config.json` (pre-filled on next new building)

### dir-display-bridge.gs — new `setupBuildingFolders` action
- Clones full folder structure AND files from template into `Association Folders/NewBuildingName/`
- Sets `Public/` sharing to "Anyone with the link" (Viewer) automatically
- Copies template Owner DB Google Sheet → names it `"BuildingName Owner DB"` → places in building folder → returns `sheetUrl`
- Returns: `buildingFolderId`, `publicFolderId`, `privateFolderId`, `sheetId`, `sheetUrl`
- **Requires GAS redeployment as new version**

### config/_master_config.json (NEW)
- Stores `templateFolderId`, `associationFolderId`, `templateSheetId` — persisted on first use via "Save as defaults"

### NEW-SITE-GUIDE.md
- Step 1 updated: describes automated folder + sheet creation via master admin dashboard
- Step 2 updated: only 3 manual steps remain (BUILDING_NAME, deploy, triggers)

---

**Files to push to server (session 21):**
- `file-manager.php`
- `get-doc-byname.php`
- `building-detail.php`
- `dir-display-bridge.gs` — paste into GAS + **deploy new version**
- `config/_master_config.json` — new file; fill in IDs via form + Save defaults
- Run `python3 docs/build-manual.py` → upload `docs/Sheepsite-Admin-Manual.html`

**One-time Drive prep before testing Add New Building:**
- Put system PDFs (`Announcement Page 1`, `Mid-End Year Report`) in correct subfolder of template folder
- Set up template Owner DB sheet (tabs + headers + script + library linked, but NOT deployed — deploy is per-building)
- Get IDs for template folder, Association Folders, template sheet → paste in form → Save defaults

---

---

## Session 26 — Billing UX polish, invoice email fixes, large file quarantine

### Large file upload — quarantine flow (`file-manager.php` + `dir-display-bridge.gs`)
- Files >30 MB now go through a quarantine flow instead of raw Drive link
- **Storage pre-check**: before showing the Drive modal, checks if big files + small files
  would exceed remaining storage → if yes, shows "Buy More Storage" modal instead
- **"Too large" modal**: calls `setup_big_upload` endpoint first to create
  `BigUploads/{tree}/{path}/` folder structure under building root in Drive;
  opens a blank window immediately (prevents popup blocker) then navigates to the Drive folder;
  explains the Quarantined tab next step
- **Quarantined tab** (3rd tab, amber): hidden until clicked; on open fires storage_refresh +
  listBigUploads in parallel; shows all pending files with checkboxes (all checked by default);
  live storage math — remaining space updates as files are checked/unchecked;
  over-limit: Publish disabled + warning + "Buy More Storage" button;
  Publish → confirm dialog listing what gets published vs deleted → moves checked files to
  target paths, deletes unchecked; tab reloads after
- **Buy More Storage**: opens blank window immediately, fetches billing token, navigates to
  `billing.php?type=storage` — same popup-blocker fix applied
- **Bug fix**: toolbar div was missing `id="toolbar"` causing `switchTree()` to crash silently
  (JS error aborted before `loadListing()` — tabs appeared to switch but content didn't change)

### New GAS actions (`dir-display-bridge.gs`) — deploy new version required
- `setupBigUploadFolder` — creates `BigUploads/{tree}/{path}/` under building root (idempotent)
- `listBigUploads` — recursive scan of BigUploads tree; returns `{id,name,bytes,size,targetPath}`
- `publishBigUpload` — moves file to target path in Public/Private tree; creates subfolders if needed
- `scanBigUploads` — internal recursive helper

### New PHP endpoints (`file-manager.php`)
- `json=setup_big_upload` — creates BigUploads subfolder, returns Drive folderId
- `json=list_big_uploads` — proxies GAS listBigUploads
- `json=publish_quarantine` — moves checked files, deletes unchecked, returns counts
- `json=billing_url` — generates fresh billing token, returns `billing.php` URL for storage upgrade

### Billing / invoice UX (`admin.php`, `invoice-helpers.php`, `billing-helpers.php`)
- Pay links open in new tab (`target="_blank"`)
- `window.opener.location.reload()` in `billing-success.php` — parent admin tab refreshes after payment
- Billing card shows renewal date: grey normally, red+bold if ≤30 days
- Invoice sort changed to seq descending (largest number first) in `loadInvoices()`
- All From/Reply-To email headers → `sheepsite@sheepsite.com`
- Invoice email: removed "pay by check" block; contact email shown as clickable link
- `billing-invoice.php`: passes `invoice['invoiceType']` to success page instead of hardcoded `invoice`
- `billing-success.php`: type-aware messages (`renewal`/`woolsy`/`storage`); generic fallback for
  one-off (`other`) and unknown types; dancing Woolsy (`Woolsy-danse-transparent.png`, 3× animation)
- Woolsy logo (`Woolsy-original-transparent.png`, 70px) added to `billing-invoice.php`
- Storage limit modal in file manager: replaced "Contact SheepSite" text with "Buy More Storage →" button

### `~/.claude/settings.json`
- Hook key corrected: `postToolUse` → `PostToolUse` (PascalCase required by current Claude Code)

---

## Session 25 — UI polish, one-off invoices, billing section improvements

### `admin.php`
- Pay links now open in new tab (`target="_blank"`)
- Billing card summary shows renewal date: grey normally, red+bold if ≤30 days away

### `billing-invoice.php`
- Woolsy logo (`Woolsy-original-transparent.png`, 70px) added at top of page
- Success URL now passes `invoice['invoiceType']` instead of hardcoded `type=invoice`
  so renewal invoices get the renewal message and one-off invoices get the generic message

### `billing-success.php`
- Dancing Woolsy animation: `Woolsy-danse-transparent.png`, bounces+wiggles 3× on load
- Type-aware messages restored: `renewal`, `woolsy`, `storage` get specific text; anything
  else (one-off `other` invoices, unknown types) falls back to generic "payment received" message
- `window.opener.location.reload()` — reloads parent admin tab after payment so Pay link
  updates to ✓ Paid without manual refresh

### `invoice-helpers.php`
- `loadInvoices()` sort changed from date to seq descending — largest invoice number first
  (affects both admin.php and master-admin.php invoice tables)
- Invoice email payment block: removed "pay by check" text; Pay online link kept;
  contact email changed to `SheepSite@sheepsite.com`
- Invoice numbering remains per-building (global numbering tried and reverted — obfuscates business size)

### `billing-helpers.php` + `invoice-helpers.php`
- All `From:` and `Reply-To:` email headers updated to `sheepsite@sheepsite.com`

### `~/.claude/settings.json`
- Hook key corrected from `postToolUse` → `PostToolUse` (PascalCase required by current Claude Code)

---

## Session 25 — UI polish, one-off invoices, billing section improvements

### Woolsy icon — replaced 🐑 emoji with image across all admin pages
- `admin.php`: Woolsy card icon → `Woolsy-standing-transparent.png` at 44px
- `master-admin.php`: Woolsy tool card icon → same image
- `woolsy-admin.php`: h1 title → image inline with text
- `woolsy-manage.php`: h1 title → image inline with text; top-bar fixed (`space-between` so "← Admin" stays right)
- `chatbot-widget.js`: FAB button → white circle (46px) inside blue ring (52px) with Woolsy at 38px

### Invoice improvements (`invoice-helpers.php`)
- `sendInvoiceEmail()` subject now type-aware:
  - `renewal` → "Invoice ID — Building Annual Renewal"
  - `storage` → "Invoice ID — Building Storage Upgrade"
  - `woolsy` → "Invoice ID — Building Woolsy Credits"
  - `other` → "Invoice ID — Building" (no suffix)

### `building-detail.php` — Billing section restructure
- **Renewal date + Discount %** moved from Configuration section to Billing section
  - New `save_billing_config` POST action (separate from `save_config` to avoid blanking other fields)
  - "Save Billing Settings" button
- **One-off invoice form** added below "Generate & Email Invoice":
  - Description (text) + Amount ($) fields + "Create & Send" button
  - Uses `generate_other_invoice` action → `createOpenInvoice()` with `invoiceType=other` + `paymentToken`
  - Sends invoice email immediately; "Pay →" link appears in admin.php billing card

### `billing-helpers.php`
- Storage invoice email subject changed to plain "Invoice" (no "Annual Renewal" suffix)

---

## Session 24 — Invoicing system hardening + Mark Paid (Check) flow

### Invoice creation for threshold emails (`billing-helpers.php`)
- `sendBillingEmail()` now calls `createOpenInvoice()` before sending the email — creates a real
  invoice file at `invoices/{building}/{id}.json` with correct amount, due date, and type metadata
- `invoiceExtra` array passed to `createOpenInvoice()` stores type-specific data:
  - Woolsy: `invoiceType=woolsy`, `creditsToAdd=10`
  - Storage: `invoiceType=storage`, `newBytes={tier bytes}`
- `invoiceId` stored in `config/{building}.json` under `billingToken.invoiceId`
- Root cause of "pending" display fixed: `billing-helpers.php` was never uploaded after the
  invoice creation logic was added last session

### `invoice-helpers.php` — type-aware `markInvoicePaid()`
- `createOpenInvoice()` gains optional `$extra` array param (merged into invoice JSON)
- `generateInvoice()` (renewal cron) now stores `invoiceType=renewal` and `paymentMethod=null`
- `markInvoicePaid()` signature changed: `$advanceRenewal` replaced by `$paymentMethod='check'`
  - Stores `paymentMethod` on the invoice JSON
  - Applies side effects based on `invoiceType`:
    - `renewal` → advance renewalDate 1 year, clear suspension (same as before)
    - `storage` → set `storageLimit` to `invoice.newBytes`, clear `storageLimitEmailSent` + `billingToken`
    - `woolsy` → add `invoice.creditsToAdd` to `woolsy_credits.json`, clear `woolsyBillingEmailSent` + `billingToken`

### `billing-webhook.php`
- `type=invoice` path now passes `'stripe'` as payment method to `markInvoicePaid()`

### `invoice-view.php`
- Paid invoices show payment method: "✓ Paid 2026-04-02 — Check" or "— Stripe"

### `building-detail.php` — invoice display cleanup
- Removed the `$hasPendingBilling` special row (was causing every invoice to appear twice)
- Invoice history now relies solely on `loadInvoices()` loop — single source of truth
- Mark Paid button label: "Mark Paid" (was "Mark Paid (Check)")
- Confirm dialog updated to reflect type-aware side effects
- `$hasPendingBilling`, `$pendingInvoice`, `$bldTok` variables removed (no longer needed)

### `admin.php` (association admin page) — same duplicate fix
- Removed `$hasPendingBilling` special row from invoice table
- Restored billing token Pay URL lookup (`$billingTokenPayUrl`) for threshold invoices that
  have no `paymentToken` — Pay link now correctly points to `billing.php` for those invoices
- `$hasPendingBilling` fallback in billing status header removed (open invoices cover it)

### `master-admin.php`
- Building cards unchanged (Billing ✓/✗ only) — Mark Paid lives in building-detail only

---

---

## Session 27 — Marketing Site, Manual Redesigns, Demo Site Restrictions

### Marketing Website (NEW — `marketing/` folder)
- **`marketing/index.php`** — SheepSite company home page; replaces Website Builder site at `sheepsite.com/index.php`:
  - Sticky nav, hero with Florida 718 compliance badge, amber compliance banner, 9 feature cards,
    Woolsy AI spotlight section, self-serve highlights with stat boxes, CTA section, contact modal
  - Contact modal: Name, Association, Email, Phone, Message → `mailto:SheepSite@sheepsite.com`
  - All CTA buttons link to `get-started.php`; no external dependencies (Woolsy images from Scripts/assets/)
- **`marketing/get-started.php`** — Get Started page:
  - Live Demo section: link to `samplesite.sheepsite.com` with resident (SampleSite/Testdrive) and
    admin (admin/admintest) demo credentials displayed in styled credential boxes
  - Two Experiences: side-by-side resident (green) vs admin (blue) feature comparison, each with
    link to respective user manual
  - Contact form: Name, Association, Email, Phone, Message → PHP `mail()` to `SheepSite@sheepsite.com`
  - Upload both files to sheepsite.com root folder

### Resident Manual (`docs/build-resident.py` + `Sheepsite-Resident-Manual.html`)
- **Woolsy-original-transparent.png** logo (90px) at top of page with "POWERED BY SHEEP" caption
- **Woolsy_Working on it.png** icon in white rounded box on every section header (blue bar)
- Section 3 (Logging In): updated to "Admin dropdown → Resources Private" for login nav
- Section 6 (Ask Woolsy): updated to describe inline floating panel (not popup); login form
  embedded in panel; Woolsy logo image in text (not emoji)
- Section 7 (My Account): updated to reference Admin nav dropdown (not just Resource Center)
- Section 9 (Getting Help): Woolsy logo image in text (not emoji)

### Admin Manual (`docs/build-manual.py` + `Sheepsite-Admin-Manual.html`)
- Same logo/header treatment as resident manual
- **Sections restructured:**
  - Section 3: Manage Residents/Owners (moved from old Section 7)
  - Section 4: Manage User Accounts (was 3); cross-ref updated to Section 3
  - Section 5: Managing Files (rewrite of old Section 4 — "Primary Method" concept removed,
    old resource center screenshots removed; new content added)
  - Sections 6–7: Tag Management and Storage Report renumbered
  - Section 9: Renamed to "Woolsy AI Assistant" (was "Woolsy Knowledge Base")
- **Section 5 new content:**
  - Storage exceeded: explains deselect-to-fit option OR click Add Storage to go to billing
  - Large files (>30 MB): new-tab BigUploads flow, Quarantine section must be used to publish
- Section 1: old placeholder image removed; admin access updated to "Admin dropdown → Site Admin"

### `my-account.php`
- "Ask Woolsy" card replaced with **Resident Manual** card (opens Sheepsite-Resident-Manual.html
  in new tab); icon changed from Woolsy-standing to Woolsy-original-transparent

### `building-site.php`
- About Us (Board of Directors) iframe height increased from 480px → 600px for up to 7 board members

### Demo Site Restrictions (`testSite` flag)
- **`change-password.php`**: loads building config; if `testSite` set — shows warning banner,
  hides form entirely; POST handler skipped
- **`forgot-password.php`**: if `testSite` set for owner flow — shows "resets disabled" message,
  hides form; admin reset (`?role=admin`) is unaffected

### Architecture Doc (`docs/build-architecture.py`)
- Added Marketing Website section (index.php, get-started.php) to File Inventory
- Added `my-account.php` and `my-unit.php` entries (were missing)
- Updated change-password.php and forgot-password.php descriptions with testSite info
- Added `testSite` field to `config/{building}.json` field list
- Expanded `docs/` path description to name all three manuals and build scripts

---

## Session 28 — Bug fixes, cron diagnosis, marketing copy

- **building-site.php** — Fixed private folder button paths:
  - `FinancialStatements` → `FinancialDocs/FinancialStatements`
  - `Budgets` → `FinancialDocs/BudgetDocs`
  - `SIRsDocs` → `FinancialDocs/SIRSDocs`
  - Board Minutes and Contracts were already correct

- **marketing/index.php** — Corrected "Section 718" → "Chapter 718" in all 3 occurrences:
  - Hero badge
  - Florida Law Reminder banner
  - Feature card description
  - Woolsy bullet point

- **storage-cron.php diagnosis** — Cron was not running because cPanel path was wrong:
  - Was: `/home/sheepsite/sheepsite.com/Scripts/storage-cron.php`
  - Correct: `/home/qgscrmoq/sheepsite/Scripts/storage-cron.php`
  - Invoice generation confirmed working via manual HTTP trigger — SampleSite-0009 generated for $476.29

---

*Snapshot updated: April 5, 2026 (session 28 — private folder paths, marketing copy, cron path fix)*
*Working directory: /Users/alain/github/Sheepsite-scripts*
