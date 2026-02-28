# Sheepsite Scripts

Utilities for displaying Google Drive folder contents on building websites.

---

## ⚡ New Building Setup (Start Here)

When a new site template is copied for a new building, there are **two things** to update:

### 1. Add the building to the PHP lookup table

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

### 2. Set the building name on the new site

In Namecheap Website Builder, go to **Settings** (top right menu) → **Pages**. At the top, select **Default**, then paste the script below into the **After `<body>`** section on the right side of the panel.

Change only the `BUILDING_NAME` value to match the new building:

```html
<script>
const BUILDING_NAME = 'NewBuildingName';   <!-- ← change this only

document.addEventListener('DOMContentLoaded', function () {
  const BASE_URL = 'https://sheepsite.com/Scripts/display-gdrive-sites.php';
  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = BASE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    btn.href = url;
  });
});
</script>
```

That's it. All buttons on the site will automatically point to the correct building folder.

---

## Architecture Overview

```
Building Website (e.g. cvelyndhursth.com)
│
│  Footer script sets BUILDING_NAME = 'cvelyndhurst'
│  Buttons use class="gdrive-link" with optional data-subdir
│
│  [User clicks button]
│
▼
sheepsite.com/Scripts/display-gdrive-sites.php
│  ?building=cvelyndhurst&subdir=Forms
│
│  Looks up Google Drive folder ID for 'cvelyndhurst'
│  Calls Apps Script with folderId + subdir
│
▼
Google Apps Script (Dir Display Bridge)
│  script.google.com/macros/s/.../exec
│  ?folderId=...&subdir=Forms
│
│  Navigates to the correct Drive folder
│  Returns JSON list of subfolders and files
│
▼
display-gdrive-sites.php (renders HTML)
│
▼
User sees file listing page with:
  - Breadcrumb navigation (clickable path back to parent folders)
  - Folder cards (clickable, navigate deeper)
  - File cards with Download buttons
```

---

## Components

### `dir-display-bridge.gs` — Google Apps Script

**Location:** Google Apps Script project called "Dir Display Bridge"
**Deployed at:** `https://script.google.com/macros/s/AKfycbz6AnLGRWvm6ibJC-Mi4mc4JuNholXDcBIF6I04uTSH_ybe14xcRoMr4OIDDUBbOAaP/exec`

A web app that reads a Google Drive folder and returns its contents as JSON.

**Parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `folderId` | Yes      | Google Drive folder ID to read from |
| `subdir`   | No       | Subfolder name or path (e.g. `Forms` or `Forms/2024`) |

**Response format:**
```json
{
  "folders": [
    { "name": "Forms" },
    { "name": "Maintenance" }
  ],
  "files": [
    { "name": "document.pdf", "url": "https://...", "size": "142 KB", "type": "application/pdf" }
  ]
}
```

**How to redeploy after code changes:**
1. Open the Apps Script project
2. Click **Deploy → Manage deployments**
3. Click the pencil (edit) icon
4. Set Version to **New version**
5. Click **Deploy** — the URL stays the same

---

### `display-gdrive-sites.php` — File Listing Page

**Location:** `sheepsite.com/Scripts/display-gdrive-sites.php`

A PHP page that serves as the central file browser for all buildings. Called via URL from any building site.

**URL parameters:**

| Parameter  | Required | Description |
|------------|----------|-------------|
| `building` | Yes      | Building name — must match a key in `$buildings` array |
| `subdir`   | No       | Subfolder path to navigate into (e.g. `Forms` or `Forms/2024`) |

**Example URLs:**
```
# Root Public folder for QGscratch
https://sheepsite.com/Scripts/display-gdrive-sites.php?building=QGscratch

# Specific subfolder
https://sheepsite.com/Scripts/display-gdrive-sites.php?building=QGscratch&subdir=Forms

# Nested subfolder
https://sheepsite.com/Scripts/display-gdrive-sites.php?building=QGscratch&subdir=Forms/2024
```

**Features:**
- Breadcrumb navigation with clickable parent folder links
- Folders displayed as clickable cards (navigate deeper)
- Files displayed with name, size, and Download button
- Error messages if building name is invalid or folder not found

---

### `gdrive-links.js` — Reference Documentation

Documents the inline JavaScript approach used in building site templates. Not loaded as an external file (Namecheap Website Builder does not support external scripts in this context).

---

## Google Drive Folder Structure

Each building's files must follow this structure in Google Drive:

```
buildingName/
  WebSite/
    Public/          ← this folder's ID goes in the $buildings array
      Forms/
      Maintenance/
      ...
```

The PHP lookup table stores the ID of the **Public** folder. All navigation from there is handled dynamically.

---

## Adding Buttons to a Building Site

Buttons are added as **Custom HTML blocks** in Namecheap Website Builder.

Button size: **350×34px**, Roboto 16px, dark purple-to-pink gradient with a dashed white border.

**Button pointing to the root Public folder:**
```html
<a href="#" class="gdrive-link"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:2px dashed rgba(255,255,255,0.6); font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
```

**Button pointing to a specific subfolder:**
```html
<a href="#" class="gdrive-link" data-subdir="Forms"
   style="display:inline-flex; align-items:center; justify-content:center; gap:0.4rem; width:350px; height:35px; background:linear-gradient(to right, #3D0066, #BB0099); color:#fff; text-decoration:none; border-radius:5px; border:2px dashed rgba(255,255,255,0.6); font-family:'Roboto',sans-serif; font-size:16px; font-weight:400;">
  &#128196; Click to open
</a>
```

- `class="gdrive-link"` — required, tells the footer script to process this button
- `data-subdir="Forms"` — optional, navigates directly into a subfolder
- The `href="#"` is replaced automatically by the footer script using `BUILDING_NAME`
