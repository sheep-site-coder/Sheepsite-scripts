# Sheepsite Scripts

Utilities for displaying Google Drive folder contents on building websites. Public folders are open access; Private folders require a building-specific password.

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

---

### Step 3 — Add the building to the private lookup table

Open `display-private-dir.php` on **sheepsite.com/Scripts/** and add an entry to the `$buildings` array:

```php
'NewBuildingName' => [
  'folderId' => 'GOOGLE_DRIVE_PRIVATE_FOLDER_ID',  // NewBuildingName/WebSite/Private
  'user'     => 'USERNAME',
  'pass'     => 'PASSWORD',
],
```

---

### Step 4 — Set the building name on the new site

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
    btn.href = url;
  });

  // Private directory buttons
  document.querySelectorAll('.local-link').forEach(function (btn) {
    var url = PRIVATE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var path = btn.getAttribute('data-path');
    if (path) url += '&path=' + encodeURIComponent(path);
    btn.href = url;
  });
});
</script>
```

That's it. All buttons on the site will automatically point to the correct building.

---

## Architecture Overview

### Public folder listing

```
Building Website (e.g. cvelyndhursth.com)
│
│  Footer script sets BUILDING_NAME = 'cvelyndhursth'
│  Buttons use class="gdrive-link" with optional data-subdir
│
│  [User clicks button]
│
▼
sheepsite.com/Scripts/display-public-dir.php
│  ?building=cvelyndhursth&subdir=Forms
│
│  Looks up Google Drive Public folder ID for 'cvelyndhursth'
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
Building Website (e.g. cvelyndhursth.com)
│
│  Footer script sets BUILDING_NAME = 'cvelyndhursth'
│  Buttons use class="local-link" with optional data-path
│
│  [User clicks button]
│
▼
sheepsite.com/Scripts/display-private-dir.php
│  ?building=cvelyndhursth&path=SubFolder
│
│  Browser challenged for username + password (HTTP Basic Auth)
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
  - HTTP Basic Auth on display page → user must log in with building password
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

**Example URLs:**
```
https://sheepsite.com/Scripts/display-public-dir.php?building=QGscratch
https://sheepsite.com/Scripts/display-public-dir.php?building=QGscratch&subdir=Forms
https://sheepsite.com/Scripts/display-public-dir.php?building=QGscratch&subdir=Forms/2024
```

---

### `display-private-dir.php` — Private Directory File Listing Page

**Location:** `sheepsite.com/Scripts/display-private-dir.php`

Central file browser for the Private Google Drive folder. Requires building-specific login before displaying anything. Downloads are proxied through this page so Drive URLs and the secret token are never exposed.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `path`     | No       | Subfolder path within Private (e.g. `Financials`) |

**Example URLs:**
```
https://sheepsite.com/Scripts/display-private-dir.php?building=cvelyndhursth
https://sheepsite.com/Scripts/display-private-dir.php?building=cvelyndhursth&path=Financials
```

---

### `footer-for-sites.js` — Reference Documentation

Documents the footer script and button HTML for building sites. See this file for ready-to-paste code blocks.

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
