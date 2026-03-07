# Sheepsite Scripts

Utilities for displaying Google Drive folder contents on building websites. Public folders are open access; Private folders require owners to log in with individual usernames and passwords.

---

## ⚡ New Building Setup (Start Here)

When a new site template is copied for a new building, complete all steps below.

---

### Step 1 — Create the Google Drive folders

In the SheepSite Google Drive, create this structure for the new building:

```
NewBuildingName/
  WebSite/
    Public/    ← set sharing to "Anyone with the link" (Viewer)
    Private/   ← leave restricted (only SheepSite account has access)
```

Copy the folder ID from the URL for each:
`https://drive.google.com/drive/folders/`**`THIS_PART_IS_THE_ID`**

---

### Step 2 — Add the building to the public lookup table

Open `display-public-dir.php` on **sheepsite.com/Scripts/** and add one line to the `$buildings` array:

```php
$buildings = [
  'QGscratch'       => '1Vgnk3XTKta33deoOWUfOp9Z666jHpM1c',
  'NewBuildingName' => 'GOOGLE_DRIVE_PUBLIC_FOLDER_ID',  // ← add this
];
```

- **Key** (`NewBuildingName`): must match exactly what is set as `BUILDING_NAME` on the building's site
- **Value**: the folder ID for `NewBuildingName/WebSite/Public`

Also add the same building to `get-doc-byname.php` if the building will use embedded Google Doc pages.

---

### Step 3 — Add the building to the private lookup table

Open `display-private-dir.php` on **sheepsite.com/Scripts/** and add an entry to the `$buildings` array:

```php
'NewBuildingName' => [
  'folderId' => 'GOOGLE_DRIVE_PRIVATE_FOLDER_ID',  // NewBuildingName/WebSite/Private
],
```

---

### Step 4 — Add the building to the user management page

Open `manage-users.php` on **sheepsite.com/Scripts/** and add an entry to both the `$buildings` array:

```php
'NewBuildingName' => ['adminUser' => 'admin', 'adminPass' => 'CHOOSE_A_PASSWORD'],
```

Then create an empty credentials file on the server at:
```
sheepsite.com/Scripts/credentials/NewBuildingName.json
```
with the contents `[]`.

---

### Step 5 — Add owners as users

Go to the building's user management URL and log in with the building's admin credentials:

```
https://sheepsite.com/Scripts/manage-users.php?building=NewBuildingName
```

Add a username and password for each owner. They will use these to log in to the private files section.

---

### Step 6 — Set the building name on the new site

In Namecheap Website Builder, go to **Settings** (top right menu) → **Pages**. At the top, select **Default**, then paste the script below into the **After `<body>`** section on the right side of the panel.

Change only the `BUILDING_NAME` value to match the new building:

```html
<script>
const BUILDING_NAME = 'NewBuildingName';

document.addEventListener('DOMContentLoaded', function () {
  const PUBLIC_URL  = 'https://sheepsite.com/Scripts/display-public-dir.php';
  const PRIVATE_URL = 'https://sheepsite.com/Scripts/display-private-dir.php';

  // Public Google Drive buttons
  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = PUBLIC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    url += '&return=' + encodeURIComponent(window.location.href);
    btn.href = url;
  });

  // Private directory buttons
  document.querySelectorAll('.local-link').forEach(function (btn) {
    var url = PRIVATE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var path = btn.getAttribute('data-path');
    if (path) url += '&path=' + encodeURIComponent(path);
    url += '&return=' + encodeURIComponent(window.location.href);
    btn.href = url;
  });

  // Document iframes (get-doc-byname)
  const DOC_URL = 'https://sheepsite.com/Scripts/get-doc-byname.php';
  document.querySelectorAll('iframe[data-script="get-doc-byname"]').forEach(function (iframe) {
    var url = DOC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = iframe.getAttribute('data-subdir');
    var filename = iframe.getAttribute('data-filename');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    if (filename) url += '&filename=' + encodeURIComponent(filename);
    iframe.src = url;
  });
});

// Report page buttons — call from button onclick (building name comes from BUILDING_NAME above)
function openReport(page) {
  window.location.href = 'https://sheepsite.com/Scripts/protected-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page=' + encodeURIComponent(page)
    + '&return=' + encodeURIComponent(window.location.href);
}
</script>
```

That's it. All buttons on the site will automatically point to the correct building.

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

Handles all Drive access on behalf of the SheepSite account. Three actions:

| Action         | Token required | Description |
|----------------|---------------|-------------|
| `list`         | No            | Lists a Public folder — returns folders + files with download URLs |
| `listPrivate`  | Yes           | Lists a Private folder — returns folders + file IDs (no URLs) |
| `download`     | Yes           | Fetches a file by ID — returns base64-encoded bytes for PHP to stream |

**Parameters:**

| Parameter  | Action(s)               | Description |
|------------|-------------------------|-------------|
| `action`   | all                     | `list`, `listPrivate`, or `download` |
| `folderId` | `list`, `listPrivate`   | Google Drive folder ID |
| `subdir`   | `list`, `listPrivate`   | Subfolder path (e.g. `Forms` or `Forms/2024`) |
| `fileId`   | `download`              | Google Drive file ID |
| `token`    | `listPrivate`, `download` | Must match `SECRET_TOKEN` in the script |

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
- **Building admin** — logs in with the building's `adminUser` / `adminPass` defined in `manage-users.php`
- **Master override** — logs in with `MASTER_USER` / `MASTER_PASS` (defined at the top of `manage-users.php`), can access any building

**Actions available:** Add user, remove user, change password.

---

### `credentials/` — Per-Building User Credentials

**Location:** `sheepsite.com/Scripts/credentials/`

One JSON file per building storing owner usernames and bcrypt-hashed passwords:

```json
[
  { "user": "john.smith", "pass": "$2y$10$..." },
  { "user": "jane.doe",   "pass": "$2y$10$..." }
]
```

- `credentials/.htaccess` blocks all direct web access to this folder
- Files are read and written only by PHP — never exposed to the browser
- Passwords are always stored as bcrypt hashes (`password_hash()`) — never plaintext

---

### `protected-report.php` — Password-Protected Sheets Reports

**Location:** `sheepsite.com/Scripts/protected-report.php`

Protects the Google Sheets Web App reports (Parking List, Elevator List, Board of Directors) behind owner login. Reuses the same session as `display-private-dir.php` — owners already logged in for private files won't need to log in again.

After login, displays the report embedded in an iframe. The Apps Script URL is stored server-side only and never exposed to the browser.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `page`     | Yes      | `parking`, `elevator`, `board`, or `resident` |
| `return`   | No       | URL to link back to — pass `window.location.href` from the button |

**Adding a new building:** add an entry to the `$buildings` array in `protected-report.php` with the Google Sheets Web App deployment URL. Get this URL from the building's Apps Script project: **Deploy → Manage deployments**.

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

Use `data-` attributes instead of a hardcoded `src` — the footer script fills in the building name automatically:

```html
<iframe
  data-script="get-doc-byname"
  data-subdir="Page1Docs"
  data-filename="Announcement Page1"
  style="width:100%; height:80vh; border:none; display:block;"
  title="Announcement">
</iframe>
```

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

### Public folder button (`class="gdrive-link"`)

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

### Private folder button (`class="local-link"`)

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

- `class="gdrive-link"` / `class="local-link"` — required, tells the footer script which display page to use
- `data-subdir` / `data-path` — optional, navigates directly into a subfolder
- `href="#"` is replaced automatically by the footer script using `BUILDING_NAME`

### Protected report button (parking, elevator, board)

Used to open password-protected Google Sheets reports. Uses a hardcoded onclick — do NOT use `class="gdrive-link"`.

In Namecheap Website Builder, add a button with this **onclick** event (change `BUILDING_NAME` and `page` as needed):

```javascript
window.location.href = 'https://sheepsite.com/Scripts/protected-report.php?building=BUILDING_NAME&page=parking&return=' + encodeURIComponent(window.location.href);
```

Pages: `page=parking`, `page=elevator`, `page=board`, `page=resident`

The `&return=` parameter passes the current page URL so the "← Back to site" link appears after login.

However, instead of hardcoding the building name in the onclick, use the `openReport()` helper defined in the footer script:

```javascript
openReport('parking')
```

This pulls `BUILDING_NAME` from the footer automatically — no building name in the button.
