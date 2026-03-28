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

---

*Snapshot updated: March 28, 2026 (session 18 — resident UX polish, database-admin performance, first-login flow)*
*Working directory: /Users/alain/github/Sheepsite-scripts*
