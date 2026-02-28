# Sheepsite Scripts

Utilities for displaying Google Drive and private cPanel directory contents on building websites.

---

## ⚡ New Building Setup (Start Here)

When a new site template is copied for a new building, complete all steps below.

---

### Step 1 — Add the building to the Google Drive lookup table

Open `display-gdrive-sites.php` on **sheepsite.com/Scripts/** and add one line to the `$buildings` array:

```php
$buildings = [
  'QGscratch'       => '1Vgnk3XTKta33deoOWUfOp9Z666jHpM1c',
  'NewBuildingName' => 'GOOGLE_DRIVE_FOLDER_ID_FOR_PUBLIC',  // ← add this
];
```

- **Key** (`NewBuildingName`): must match exactly what is set as `BUILDING_NAME` on the building's site
- **Value**: the Google Drive folder ID for that building's `buildingName/WebSite/Public` folder
  - To find the folder ID: open the folder in Google Drive, copy the ID from the URL:
    `https://drive.google.com/drive/folders/`**`THIS_PART_IS_THE_ID`**

---

### Step 2 — Add the building to the private directory lookup table

Open `display-private-dir.php` on **sheepsite.com/Scripts/** and add an entry to the `$buildings` array:

```php
'NewBuildingName' => [
  'url'   => 'https://newbuilding.com/Scripts/dir-list-private.php',
  'user'  => 'USERNAME',       // must match cPanel directory lock credentials
  'pass'  => 'PASSWORD',       // must match cPanel directory lock credentials
  'token' => 'UNIQUE_TOKEN',   // must match SECRET_TOKEN in dir-list-private.php on that site
],
```

Generate a strong unique token (32+ random characters) for each building. The same token goes in both `display-private-dir.php` and `dir-list-private.php` on the building's site.

---

### Step 3 — Deploy `dir-list-private.php` on the new building site

Upload `dir-list-private.php` to `newbuilding.com/Scripts/` and set the token at the top of the file:

```php
define('SECRET_TOKEN', 'UNIQUE_TOKEN');  // must match token in display-private-dir.php
```

---

### Step 4 — Set the building name on the new site

In Namecheap Website Builder, go to **Settings** (top right menu) → **Pages**. At the top, select **Default**, then paste the script below into the **After `<body>`** section on the right side of the panel.

Change only the `BUILDING_NAME` value to match the new building:

```html
<script>
const BUILDING_NAME = 'NewBuildingName';

document.addEventListener('DOMContentLoaded', function () {
  const GDRIVE_URL = 'https://sheepsite.com/Scripts/display-gdrive-sites.php';
  const LOCAL_URL  = 'https://sheepsite.com/Scripts/display-private-dir.php';

  // Google Drive buttons
  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = GDRIVE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    btn.href = url;
  });

  // Private cPanel directory buttons
  document.querySelectorAll('.local-link').forEach(function (btn) {
    var url = LOCAL_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
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

### Google Drive file listing

```
Building Website (e.g. cvelyndhursth.com)
│
│  Footer script sets BUILDING_NAME = 'cvelyndhursth'
│  Buttons use class="gdrive-link" with optional data-subdir
│
│  [User clicks button]
│
▼
sheepsite.com/Scripts/display-gdrive-sites.php
│  ?building=cvelyndhursth&subdir=Forms
│
│  Looks up Google Drive folder ID for 'cvelyndhursth'
│  Calls Apps Script with folderId + subdir
│
▼
Google Apps Script (Dir Display Bridge)
│  Navigates Drive folder, returns JSON of folders + files
│
▼
display-gdrive-sites.php renders page:
  - Breadcrumb navigation
  - Clickable folder cards
  - File cards with Download buttons
```

### Private cPanel directory listing

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
│  ?building=cvelyndhursth&path=Private
│
│  Browser challenged for username + password (HTTP Basic Auth)
│  Calls dir-list-private.php with secret token
│
▼
cvelyndhursth.com/Scripts/dir-list-private.php
│  Validates secret token — rejects requests without it
│  Reads SiteFolders/ from filesystem
│  Returns JSON of folders + files (no direct file URLs)
│
▼
display-private-dir.php renders page:
  - Breadcrumb navigation
  - Clickable folder cards
  - File cards with Download buttons (proxied — token never exposed)

Security layers:
  - cPanel lock on SiteFolders/ → blocks direct file URL access
  - HTTP Basic Auth on display page → user must log in
  - Secret token → dir-list-private.php rejects calls without it
  - Download proxy → files stream through display page, token hidden
```

---

## Components

### `dir-display-bridge.gs` — Google Apps Script

**Location:** Google Apps Script project called "Dir Display Bridge"
**Deployed at:** `https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec`

Reads a Google Drive folder and returns its contents as JSON.

**Parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `folderId` | Yes      | Google Drive folder ID to read from |
| `subdir`   | No       | Subfolder name or path (e.g. `Forms` or `Forms/2024`) |

**Response format:**
```json
{
  "folders": [{ "name": "Forms" }],
  "files":   [{ "name": "document.pdf", "url": "https://...", "size": "142 KB", "type": "application/pdf" }]
}
```

**How to redeploy after code changes:**
1. Open the Apps Script project
2. Click **Deploy → Manage deployments**
3. Click the pencil (edit) icon
4. Set Version to **New version**
5. Click **Deploy** — the URL stays the same

---

### `display-gdrive-sites.php` — Google Drive File Listing Page

**Location:** `sheepsite.com/Scripts/display-gdrive-sites.php`

Central file browser for Google Drive content. Called from any building site.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `subdir`   | No       | Subfolder path (e.g. `Forms` or `Forms/2024`) |

**Example URLs:**
```
https://sheepsite.com/Scripts/display-gdrive-sites.php?building=QGscratch
https://sheepsite.com/Scripts/display-gdrive-sites.php?building=QGscratch&subdir=Forms
https://sheepsite.com/Scripts/display-gdrive-sites.php?building=QGscratch&subdir=Forms/2024
```

---

### `display-private-dir.php` — Private cPanel Directory Listing Page

**Location:** `sheepsite.com/Scripts/display-private-dir.php`

Central file browser for password-protected cPanel directories. Requires login before displaying anything. Downloads are proxied through this page so the secret token is never exposed.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `path`     | No       | Subdirectory path under SiteFolders/ (e.g. `Private`) |

**Example URLs:**
```
https://sheepsite.com/Scripts/display-private-dir.php?building=cvelyndhursth
https://sheepsite.com/Scripts/display-private-dir.php?building=cvelyndhursth&path=Private
```

---

### `dir-list-private.php` — Private Directory JSON Provider

**Location:** Each building site at `buildingsite.com/Scripts/dir-list-private.php`

Reads the building site's `SiteFolders/` directory and returns JSON. Rejects all requests that do not include the correct secret token. Handles file downloads by streaming directly from the filesystem.

**Security:** Keep the `Scripts/` folder unlocked in cPanel. Keep `SiteFolders/` locked. The token protects `dir-list-private.php` from direct access.

---

### `footer-for-sites.js` — Reference Documentation

Documents the footer script and button HTML for building sites. See this file for ready-to-paste code blocks.

---

## Google Drive Folder Structure

```
buildingName/
  WebSite/
    Public/          ← this folder's ID goes in display-gdrive-sites.php
      Forms/
      Maintenance/
      ...
```

## cPanel Folder Structure

```
buildingName/
  SiteFolders/       ← locked in cPanel (HTTP Basic Auth)
    Private/
    ...
  Scripts/           ← unlocked, contains dir-list-private.php
```

---

## Adding Buttons to a Building Site

Buttons are added as **Custom HTML blocks** in Namecheap Website Builder.

Button style: **350×35px**, Roboto 16px, dark purple-to-pink gradient, no border.

### Google Drive button (`class="gdrive-link"`)

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

### Private cPanel directory button (`class="local-link"`)

```html
<!-- Root SiteFolders -->
<a href="#" class="local-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>

<!-- Specific subdirectory (change data-path) -->
<a href="#" class="local-link" data-path="Private"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:none; font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
```

- `class="gdrive-link"` / `class="local-link"` — required, tells the footer script which display page to use
- `data-subdir` / `data-path` — optional, navigates directly into a subfolder
- `href="#"` is replaced automatically by the footer script using `BUILDING_NAME`
