# Sheepsite Scripts

Utilities for displaying Google Drive folder contents on building websites. Public folders are open access; Private folders require owners to log in with individual usernames and passwords.

---

## ⚡ New Building Setup (Start Here)

See **[NEW-SITE-GUIDE.md](NEW-SITE-GUIDE.md)** for the complete step-by-step guide to creating a new building site.

---

## Architecture Overview

### Public folder listing

```
Building Website (e.g. LyndhurstH.com)
│
│  Footer script sets BUILDING_NAME = 'LyndhurstH'
│  Buttons use class="gdrive-link" with optional data-subdir
│
│  [User clicks button]
│
▼
sheepsite.com/Scripts/display-public-dir.php
│  ?building=LyndhurstH&subdir=Forms
│
│  Looks up Google Drive Public folder ID for 'LyndhurstH'
│  Calls Apps Script (action=list) with folderId + subdir
│
▼
Google Apps Script (Dir Display Bridge)
│  Navigates Drive folder, returns JSON of folders + files with download URLs
│
▼
display-public-dir.php renders page:
  - Breadcrumb navigation
  - Clickable folder cards
  - File cards with Download buttons (direct Drive URLs)

No authentication required.
```

### Private folder listing

```
Building Website (e.g. LyndhurstH.com)
│
│  Footer script sets BUILDING_NAME = 'LyndhurstH'
│  Buttons use class="local-link" with optional data-path
│
│  [User clicks button]
│
▼
sheepsite.com/Scripts/display-private-dir.php
│  ?building=LyndhurstH&path=SubFolder
│
│  PHP session checked — if not logged in, HTML login form is shown
│  Owner enters individual username + password
│  Credentials validated against credentials/LyndhurstH.json (bcrypt hashes)
│  On success: session set, listing shown
│
│  Calls Apps Script (action=listPrivate) with folderId + subdir + token
│
▼
Google Apps Script (Dir Display Bridge)
│  Runs as SheepSite account — can access restricted Private folder
│  Returns JSON of folders + file IDs (no direct download URLs)
│
▼
display-private-dir.php renders page:
  - Breadcrumb navigation
  - Clickable folder cards
  - File cards with Download buttons (proxied — Drive URLs never exposed)

For downloads:
  display-private-dir.php → Apps Script (action=download) → base64 file bytes
  → decoded and streamed to browser by PHP

Security layers:
  - Private folder restricted in Google Drive → only SheepSite account can access
  - Session login → each owner has an individual username and password
  - Passwords stored as bcrypt hashes in credentials/*.json (never plaintext)
  - Secret token → Apps Script rejects calls without it
  - Download proxy → files stream through PHP, token and Drive URLs never exposed
```

---

## Components

### `dir-display-bridge.gs` — Google Apps Script

**Location:** Google Apps Script project called "Dir Display Bridge"
**Deployed at:** `https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec`

Handles all Drive access on behalf of the SheepSite account.

**doGet actions:**

| Action          | Token required | Description |
|-----------------|---------------|-------------|
| `list`          | No            | Lists a Public folder — returns folders + files with download URLs |
| `listPrivate`   | Yes           | Lists a Private folder — returns folders + file IDs (no URLs) |
| `listAdmin`     | Yes           | Lists a folder returning folder IDs, file IDs + sizes, and `currentFolderId` — used by file-manager.php |
| `download`      | Yes           | Fetches a file by ID — returns base64-encoded bytes for PHP to stream |
| `storageReport` | Yes           | Returns total size + per-subfolder breakdown for a folder (used by storage-report.php) |
| `search`        | Yes           | Searches filenames across both Public and Private trees (AND logic across words) |
| `deleteFile`    | Yes           | Moves a file to trash |
| `renameFile`    | Yes           | Renames a file |
| `createFolder`  | Yes           | Creates a subfolder inside a given parent folder |

**doPost actions:**

| Action        | Token required | Description |
|---------------|---------------|-------------|
| `uploadFile`  | Yes (in body) | Creates a file from base64-encoded bytes in a given folder |

**Parameters:**

| Parameter        | Action(s)                              | Description |
|------------------|----------------------------------------|-------------|
| `action`         | all (doGet)                            | Action name |
| `folderId`       | `list`, `listPrivate`, `listAdmin`, `storageReport` | Google Drive folder ID |
| `subdir`         | `list`, `listPrivate`, `listAdmin`     | Subfolder path (e.g. `Forms` or `Forms/2024`) |
| `fileId`         | `download`, `deleteFile`, `renameFile` | Google Drive file ID |
| `newName`        | `renameFile`                           | New filename |
| `parentFolderId` | `createFolder`                         | ID of the folder to create the subfolder in |
| `name`           | `createFolder`                         | Name of the new subfolder |
| `publicFolderId` | `search`                               | Root public folder ID |
| `privateFolderId`| `search`                               | Root private folder ID |
| `query`          | `search`                               | Search string (space-separated words, AND logic) |
| `token`          | all privileged actions                 | Must match `SECRET_TOKEN` in the script |

**How to redeploy after code changes:**
1. Open the Apps Script project
2. Click **Deploy → Manage deployments**
3. Click the pencil (edit) icon
4. Set Version to **New version**
5. Click **Deploy** — the URL stays the same

---

### `display-public-dir.php` — Public Directory File Listing Page

**Location:** `sheepsite.com/Scripts/display-public-dir.php`

Central file browser for the Public Google Drive folder. No login required. Called from any building site.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `subdir`   | No       | Subfolder path (e.g. `Forms` or `Forms/2024`) |
| `return`   | No       | URL to link back to (set automatically by footer script) |

**Example URLs:**
```
https://sheepsite.com/Scripts/display-public-dir.php?building=QGscratch
https://sheepsite.com/Scripts/display-public-dir.php?building=QGscratch&subdir=Forms
https://sheepsite.com/Scripts/display-public-dir.php?building=QGscratch&subdir=Forms/2024
```

---

### `display-private-dir.php` — Private Directory File Listing Page

**Location:** `sheepsite.com/Scripts/display-private-dir.php`

Central file browser for the Private Google Drive folder. Requires individual owner login before displaying anything. Downloads are proxied through this page so Drive URLs and the secret token are never exposed.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `path`     | No       | Subfolder path within Private (e.g. `Financials`) |
| `return`   | No       | URL to link back to (set automatically by footer script) |

**Example URLs:**
```
https://sheepsite.com/Scripts/display-private-dir.php?building=LyndhurstH
https://sheepsite.com/Scripts/display-private-dir.php?building=LyndhurstH&path=Financials
```

---

### `manage-users.php` — User Management

**Location:** `sheepsite.com/Scripts/manage-users.php`

Per-building admin page for managing owner accounts. Each building has its own URL, its own admin credentials, and can only manage its own users.

**Access:**
```
https://sheepsite.com/Scripts/manage-users.php?building=NewBuildingName
```

**Two access levels:**
- **Building admin** — credentials stored as bcrypt hash in `credentials/{building}_admin.json`
- **Master override** — credentials stored as bcrypt hash in `credentials/_master.json`, can access any building

Building admin credentials are created automatically on first use via `forgot-password.php`. Master credentials are set up using `setup-admin.php` (see below).

**Actions available:** Import from Sheet, add user, remove user, change password.

**Import from Sheet:** reads the `Database` tab from the building's Google Sheet via Apps Script (`?page=owners&token=OWNER_IMPORT_TOKEN`), creates accounts for all owners not already registered, sets `mustChange: true` so they must change the temporary password on first login.

---

### `credentials/` — Per-Building Credentials

**Location:** `sheepsite.com/Scripts/credentials/`

All credential files are excluded from git (`.gitignore`) and live only on the server.

**Owner credentials** — one file per building:
```json
[
  { "user": "jsmith",  "pass": "$2y$10$...", "mustChange": true },
  { "user": "jdoe",    "pass": "$2y$10$..." }
]
```
- `mustChange: true` is set on import — cleared after the owner changes their password
- Usernames are generated as first initial + last name (e.g. `jsmith`)

**Admin credentials** — one file per building:
```
credentials/{building}_admin.json   → { "user": "admin", "pass": "$2y$10$...", "mustChange": true }
credentials/_master.json            → { "user": "sheepsite", "pass": "$2y$10$..." }
```

- `{building}_admin.json` is created automatically the first time the admin password reset is used — no manual setup required
- `_master.json` must be created manually using `setup-admin.php` (see below)
- `mustChange: true` is set on the admin account after a password reset — cleared once a new password is chosen
- `credentials/.htaccess` blocks all direct web access to this folder
- All passwords stored as bcrypt hashes — never plaintext, never in source code

---

### `admin.php` — Admin Landing Page

**Location:** `sheepsite.com/Scripts/admin.php`

Central landing page for building administrators. Requires the same admin login as `manage-users.php` — the session is shared, so logging into either page grants access to both.

**Access URL:**
```
https://sheepsite.com/Scripts/admin.php?building=LyndhurstH
```

Or from the building site, set the menu link URL to:
```
https://sheepsite.com/Scripts/admin.php
```
The footer script automatically injects the building name into any link pointing to `admin.php`, so no `?building=` param is needed in the menu.

**Links available after login (in order):**
- **Manage Users** → `manage-users.php` — import owners, add/remove accounts, reset passwords
- **Manage Files** → `file-manager.php` — upload, delete, rename files and create subfolders
- **Manage Tags** → `tag-admin.php` — assign searchable tags to files for the owner search feature
- **Storage Report** → `storage-report.php` — Drive storage usage breakdown by folder
- **User Manual** → Google Doc with step-by-step admin instructions (opens in new tab)

No `$buildings` array needed — validates the building by checking that admin credentials exist on the server.

**First-time setup:** if `credentials/{building}_admin.json` does not exist yet, `admin.php` automatically redirects to `forgot-password.php` to bootstrap the admin account. No manual file creation or `setup-admin.php` needed for per-building admin accounts.

---

### `storage-report.php` — Drive Storage Report

**Location:** `sheepsite.com/Scripts/storage-report.php`

Admin-authenticated page showing storage usage for a building's Public and Private Google Drive folders. Reuses the `manage_auth_{building}` session — no separate login needed if already logged into the admin page.

Loads both folders in parallel via AJAX (spinners shown while calculating). Displays:
- **Grand total** (Public + Private combined) at the top
- Per-folder breakdown table sorted largest-first, with a Total row

Calls `dir-display-bridge.gs` with `action=storageReport` — NOT the building's Apps Script URL.

**Access:**
```
https://sheepsite.com/Scripts/storage-report.php?building=LyndhurstH
```

---

### `file-manager.php` — Admin File Manager

**Location:** `sheepsite.com/Scripts/file-manager.php`

Admin page for managing files in the Public and Private Drive folders without leaving the admin panel. Reuses the `manage_auth_{building}` session — no separate login needed if already logged into the admin page.

**Access:**
```
https://sheepsite.com/Scripts/file-manager.php?building=LyndhurstH
```

**Features:**
- **Upload** — drag and drop one or more files onto the drop zone, or click Browse. Files upload sequentially with per-file progress ("2 of 5 — Uploading filename… 34%"). Maximum 15 MB per file.
- **Duplicate detection** — if an uploaded filename already exists, prompts to replace. On multi-file uploads, duplicates can be skipped individually while the rest proceed.
- **Delete** — removes a file (moves to Drive trash) after confirmation.
- **Rename** — inline rename with Save/Cancel without leaving the page.
- **New Folder** — creates a subfolder inside the current folder.
- **Public / Private tabs** with breadcrumb navigation.

Files are uploaded via PHP (which base64-encodes them and POSTs to Apps Script's `doPost` handler) — the secret token never reaches the browser.

> **Note:** After uploading a replacement document with the same filename, `get-doc-byname.php` embeds will automatically serve the new version — no URL changes needed on the building site.

---

### `tag-admin.php` — File Tag Editor

**Location:** `sheepsite.com/Scripts/tag-admin.php`

Admin page for assigning free-form searchable tags to files in Public and Private folders. Tags are stored in `tags/{building}.json` and used by `search.php` to let owners find documents by topic even when they don't know the exact filename.

**Access:**
```
https://sheepsite.com/Scripts/tag-admin.php?building=LyndhurstH
```

- Browse Public or Private folder tree, click any file to open the tag editor
- Add tags by typing and pressing Enter or comma; remove tags with the × button
- Autocomplete suggests tags already used across the building's files
- Tags are stored as `{ tags, name, tree }` per file ID in `tags/{building}.json`
- The `tags/` directory and its `.htaccess` (blocks web access) are created automatically on first use

---

### `search.php` — Owner File Search

**Location:** `sheepsite.com/Scripts/search.php`

Owner-facing search page. Requires the same owner login as `display-private-dir.php` — session is shared. Searches filenames across both Public and Private folder trees (via Apps Script `search` action) and cross-references results against the tag index.

**Access (from footer script):**
```javascript
openSearch()
```

**Search logic:**
- Space-separated words are AND-ed — all words must appear in the filename or tags
- Results show filename, Public/Private badge, any assigned tags, and a download link
- Private file downloads are proxied through PHP (Drive URLs never exposed)

---

### `setup-admin.php` — Master Credential Setup

**Location:** `sheepsite.com/Scripts/setup-admin.php` (upload, run once, then **delete**)

> **Per-building admin accounts no longer need this.** They are bootstrapped automatically via `forgot-password.php` on first use.

Use `setup-admin.php` only to create or reset the **master override credentials** (`credentials/_master.json`) — the sheepsite-level account that can access any building's admin pages.

Edit the `$config` array at the top of the file, upload to the server, visit the URL once in a browser — it writes `credentials/_master.json` — then **delete the file immediately**.

It will refuse to run if any password is still set to `CHANGE_ME`.

---

### `change-password.php` — Owner Self-Service Password Change

**Location:** `sheepsite.com/Scripts/change-password.php`

Allows a logged-in owner to change their own password. Linked from the top bar of `display-private-dir.php` and `protected-report.php`.

- Requires an active `private_auth_{building}` session
- Verifies the current password before accepting a new one
- When `mustChange: true` is set on the account (imported accounts or after a password reset), the owner is redirected here automatically and cannot access files or reports until the change is complete
- After a forced change, redirects back to wherever the owner was trying to go
- Prevents reusing the temporary password when `mustChange` is active

---

### `forgot-password.php` — Self-Service Password Reset (Owners and Admin)

**Location:** `sheepsite.com/Scripts/forgot-password.php`

Handles password resets for both owners and the building admin. Linked from the login forms in `display-private-dir.php` and `admin.php`.

**Owner reset flow:**
1. Owner enters their username (e.g. `jsmith`)
2. PHP calls Apps Script (`?page=resetpw`) which reverse-engineers the username to a row in the `Database` tab and emails a temporary password via `MailApp`
3. If email sent: PHP updates `credentials/{building}.json` with a new bcrypt hash and sets `mustChange: true`
4. Owner logs in → immediately prompted to set a new password

**Admin reset flow** (`?role=admin`):
1. Page shows a message that the President of the association will receive the temporary password
2. Admin enters the **President's unit number** as a secret # (anti-prank check)
3. Apps Script looks up the `President` row in the `Board` column, verifies the unit number matches, and emails the temporary password
4. If verified: PHP updates `credentials/{building}_admin.json` with a new bcrypt hash and sets `mustChange: true`
5. Admin logs in → admin tools are hidden until a new password is set via the Change Admin Password form

**First-time admin setup** (`?role=admin&setup=1`): same as admin reset, but if `credentials/{building}_admin.json` does not exist yet it is created. This is triggered automatically by `admin.php` on a new building.

**If no email is on file or unit number is wrong:** shows "no email on file, contact administrator" — credentials are not changed.

**Adding a new building:** add an entry to the `$buildings` array in `forgot-password.php` with the same `webAppURL` used in `manage-users.php` and `protected-report.php`.

---

### `protected-report.php` — Password-Protected Sheets Reports

**Location:** `sheepsite.com/Scripts/protected-report.php`

Protects the Google Sheets Web App reports (Parking List, Elevator List, Board of Directors) behind owner login. Reuses the same session as `display-private-dir.php` — owners already logged in for private files won't need to log in again.

After login, displays the report embedded in an iframe. The Apps Script URL is stored server-side only and never exposed to the browser.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `page`     | Yes      | `parking`, `elevator`, or `resident` (`board` is public — use `public-report.php`) |
| `return`   | No       | URL to link back to — pass `window.location.href` from the button |

**Adding a new building:** add an entry to the `$buildings` array in both `protected-report.php` and `manage-users.php` with the Google Sheets Web App deployment URL. Get this URL from the building's Apps Script project: **Deploy → Manage deployments**.

**Top bar navigation:** authenticated report pages show nav links to Elevator List, Parking List, and Resident List. The current page is shown as a non-clickable label; others are clickable links — no re-login required.

---

### `footer-for-sites.js` — Reference Documentation

Documents the footer script and button HTML for building sites. See this file for ready-to-paste code blocks.

---

### `get-doc-byname.php` — Google Doc Embed Helper

**Location:** `sheepsite.com/Scripts/get-doc-byname.php`

Looks up a file by name in a building's Public folder and redirects to its Google Doc preview URL. Can be used as an `iframe src` to embed a live Google Doc, or as a button `href` to open the doc in a new tab.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `subdir`   | No       | Subfolder path containing the document (e.g. `Page1Docs`) |
| `filename` | Yes      | Exact file name to look up (use `+` for spaces) |

**As an iframe (embedded doc):**

Use `data-` attributes instead of a hardcoded `src` — the footer script fills in the building name automatically.

Wrap the iframe in a loading spinner so the page doesn't appear blank while the document loads:

```html
<div style="position:relative; width:100%; height:80vh;">
  <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f5f5f5;"
       id="doc-loader">
    <div style="width:48px; height:48px; border:5px solid #e0c0f0; border-top-color:#7A0099; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
    <p style="margin-top:14px; font-family:'Roboto',sans-serif; font-size:14px; color:#888;">Loading document...</p>
  </div>
  <style>#doc-loader { transition: opacity 0.3s; } @keyframes spin { to { transform: rotate(360deg); } }</style>
  <iframe
    data-script="get-doc-byname"
    data-subdir="Page1Docs"
    data-filename="Announcement Page1"
    style="width:100%; height:100%; border:none; display:none;"
    title="Document">
  </iframe>
</div>
```

The spinner uses the site's purple colour and disappears once the document finishes loading. Update `data-subdir` and `data-filename` as needed; omit `data-subdir` if the file is in the root public folder.

> **Note:** There is no `onload` on the iframe — the footer script attaches it automatically after setting the `src`, so the spinner stays visible during the full load.

**As a button (opens doc in new tab):**

Use a hardcoded `href` — do NOT use `class="gdrive-link"`, as the footer script will overwrite the href with the folder browser URL instead.

```html
<a href="https://sheepsite.com/Scripts/get-doc-byname.php?building=BUILDING_NAME&subdir=Page1Docs&filename=Mid_Year_report"
   target="_blank"
   style="display:block; text-align:left; padding-left:30px; width:350px; height:35px; line-height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  Mid Year Report
</a>
```

Replace `BUILDING_NAME` with the actual building key (e.g. `QGscratch`) and update `subdir`, `filename`, and button label as needed.

**As an onclick call (preferred for website builder buttons):**

The footer script includes an `openDoc()` helper. Use it in the button's JS onclick:

```js
openDoc('Page1Docs', 'Mid Year Report')
```

Pass `''` as the first argument if the file is in the root public folder (no subfolder):

```js
openDoc('', 'Welcome Letter')
```

---

## Google Drive Folder Structure

```
buildingName/
  WebSite/
    Public/    ← "Anyone with the link" — ID goes in display-public-dir.php
      Forms/
      Maintenance/
      ...
    Private/   ← Restricted to SheepSite account — ID goes in display-private-dir.php
      Financials/
      ...
```

---

## Adding Buttons to a Building Site

Buttons are added as **Custom HTML blocks** in Namecheap Website Builder.

Button style: **350×35px**, Roboto 16px, dark purple-to-pink gradient, no border.

### Public folder button

**Preferred — website builder button with onclick:**

```javascript
openFolder()              // root Public folder
openFolder('RulesDocs')   // specific subfolder
```

**Legacy — custom HTML block (still works):**

```html
<!-- Root Public folder -->
<a href="#" class="gdrive-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>

<!-- Specific subfolder (change data-subdir) -->
<a href="#" class="gdrive-link" data-subdir="Forms"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
```

### Private folder button

**Preferred — website builder button with onclick:**

```javascript
openPrivateFolder()               // root Private folder
openPrivateFolder('Financials')   // specific subfolder
```

**Legacy — custom HTML block (still works):**

```html
<!-- Root Private folder -->
<a href="#" class="local-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>

<!-- Specific subfolder (change data-path) -->
<a href="#" class="local-link" data-path="Financials"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
```

### Board of Directors report (public — no login required)

The Board of Directors report is public. Use `public-report.php` — the footer script injects the building name automatically, no hardcoded URLs needed.

**As an iframe (embedded on the page):**

```html
<div style="position:relative; width:100%; height:80vh;">
  <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f5f5f5;" id="doc-loader">
    <div style="width:48px; height:48px; border:5px solid #e0c0f0; border-top-color:#7A0099; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
    <p style="margin-top:14px; font-family:'Roboto',sans-serif; font-size:14px; color:#888;">Loading...</p>
  </div>
  <style>#doc-loader { transition: opacity 0.3s; } @keyframes spin { to { transform: rotate(360deg); } }</style>
  <iframe
    data-script="public-report"
    data-page="board"
    style="width:100%; height:100%; border:none; display:block;"
    title="Board of Directors">
  </iframe>
</div>
```

**As a button (opens in full page):**

```javascript
openPublicReport('board')
```

---

### Protected report button (parking, elevator, resident)

Used to open password-protected Google Sheets reports. Use the `openReport()` helper from the footer script in the button's onclick — no building name needed:

```javascript
openReport('parking')
```

Pages: `parking`, `elevator`, `resident`

The `&return=` parameter is added automatically so the "← Back to site" link appears after login.

**As an inline iframe (report embedded directly on the page):**

Use `data-script="protected-report"` — the footer script sets the `src` automatically using `BUILDING_NAME`. Wrap with a spinner:

```html
<div style="position:relative; width:100%; height:80vh;">
  <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#f5f5f5;" id="doc-loader">
    <div style="width:48px; height:48px; border:5px solid #e0c0f0; border-top-color:#7A0099; border-radius:50%; animation:spin 0.8s linear infinite;"></div>
    <p style="margin-top:14px; font-family:'Roboto',sans-serif; font-size:14px; color:#888;">Loading...</p>
  </div>
  <style>#doc-loader { transition: opacity 0.3s; } @keyframes spin { to { transform: rotate(360deg); } }</style>
  <iframe
    data-script="protected-report"
    data-page="parking"
    style="width:100%; height:100%; border:none; display:block;"
    title="Parking List"
    onload="document.getElementById('doc-loader').style.display='none'">
  </iframe>
</div>
```

Change `data-page` to `parking`, `elevator`, or `resident` as needed.

---

## Google Sheets Scripts (`sheets/` folder)

Three report scripts run from a master library (`DatabaseSheetMaster`) and a per-building wrapper.

### Master library files — paste into `DatabaseSheetMaster` Apps Script project

| File | Function |
|------|----------|
| `sheets/board-list.gs` | Board of Directors — generates BoardList tab + `doGet()` web page |
| `sheets/elevator-list.gs` | Elevator List — generates Elevator List tab + `doGetElevator()` web page. Includes a Print button that scales content to fit one letter page (no browser headers/footers). |
| `sheets/parking-list.gs` | Parking List — generates Parking List tab + `doGetParking()` web page |
| `sheets/resident-list.gs` | Resident List — generates Resident List tab + `doGetResident()` web page; sortable by Unit # or Last Name |
| `sheets/owner-import.gs` | Owner import — `doGetOwners(token, expectedToken)` returns Database tab owner list as JSON for use by `manage-users.php` |
| `sheets/reset-password.gs` | Password reset — `doResetPassword(params, expectedToken)` looks up a username in the Database tab and emails a temporary password via `MailApp`. For username `admin`, looks up the `President` row in the `Board` column and validates the submitted unit number before sending. Returns status JSON for `forgot-password.php`. |

After any change to master library files: **Deploy → Manage deployments → New version**. Building scripts pick up changes automatically if set to "latest version".

### Building script — paste into each building's Apps Script project

`sheets/building-script.gs` — single `doGet()` router and auto-update trigger functions:

| URL | Page |
|-----|------|
| `.../exec` | Board of Directors (public) |
| `.../exec?page=elevator` | Elevator List |
| `.../exec?page=parking` | Parking List |
| `.../exec?page=resident` | Resident List |
| `.../exec?page=owners&token=...` | Owner list JSON for import (token-protected) |
| `.../exec?page=resetpw&token=...&username=...&tmppw=...&loginurl=...` | Password reset — looks up username, sends temp password by email (token-protected) |

The `OWNER_IMPORT_TOKEN` constant in `building-script.gs` must match `OWNER_IMPORT_TOKEN` in `manage-users.php` and `forgot-password.php`.

### Conventions

- Building Google Sheet file named: `"<Building Name> Owner DB"`
- Database tab always named: `Database`
- Car data tab always named: `CarDB`
- Building name extracted from filename: `fileName.split('Owner DB')[0].trim()`
- Library identifier: `DatabaseSheetMaster`

### Deployment steps for a new building

1. Create Google Sheet named `"<Building Name> Owner DB"`
2. Open Apps Script editor (Extensions → Apps Script)
3. Paste `sheets/building-script.gs` as the script content
4. Add `DatabaseSheetMaster` as a library (set to latest version) — Script ID is in the `DatabaseSheetMaster` project under Project Settings → Script ID
5. Deploy as Web App — **Execute as: Me**, **Access: Anyone, even anonymous**
6. Copy the deployment URL into `manage-users.php`, `protected-report.php`, and `forgot-password.php`
7. Install auto-update triggers (Triggers → Add Trigger):
   - `onEditHandler` — From spreadsheet → On edit
   - `runScheduledUpdate` — Time-driven → Minutes timer → Every minute

   These keep the list tabs (Elevator List, Parking List, Resident List, BoardList) in sync automatically. Any edit to `Database` or `CarDB` triggers a regeneration within ~1 minute. See `sheets/README.md` for details.
