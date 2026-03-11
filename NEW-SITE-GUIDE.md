# New Building Site Creation Guide

Complete these steps in order when setting up a new building site.

---

## Step 1 — Create the Google Drive folders

In the SheepSite Google Drive, create this structure:

```
NewBuildingName/
  WebSite/
    Public/    ← set sharing to "Anyone with the link" (Viewer)
    Private/   ← leave restricted (only SheepSite account has access)
```

Copy the folder ID from the URL for each:
`https://drive.google.com/drive/folders/`**`THIS_PART_IS_THE_ID`**

---

## Step 2 — Set up the Google Sheet

1. Create a new Google Sheet named `"<Building Name> Owner DB"` (e.g. `Lyndhurst I Owner DB`)
2. Rename the first tab to `Database`; add a second tab named `CarDB`
3. Ensure row 1 of each tab has the required column headers (see `sheets/README.md`)
4. Open **Extensions → Apps Script**
5. Delete any default code and paste the contents of `sheets/building-script.gs`
6. Click **+** next to Libraries, enter the `DatabaseSheetMaster` Script ID, set identifier to `DatabaseSheetMaster`, select **latest version**, click Add
   *(get the Script ID from the `DatabaseSheetMaster` Apps Script project: Project Settings → Script ID)*
7. **Deploy → New deployment** — type: Web App, Execute as: Me, Who has access: Anyone — click Deploy and **copy the URL**
8. Install auto-update triggers (**Triggers → Add Trigger**):
   - `onEditHandler` — From spreadsheet → On edit
   - `runScheduledUpdate` — Time-driven → Minutes timer → Every minute

---

## Step 3 — Add the building to the central config

Open **`buildings.php`** on **sheepsite.com/Scripts/** and add one entry:

```php
'NewBuildingName' => [
  'publicFolderId'  => 'GOOGLE_DRIVE_PUBLIC_FOLDER_ID',
  'privateFolderId' => 'GOOGLE_DRIVE_PRIVATE_FOLDER_ID',
  'webAppURL'       => 'APPS_SCRIPT_WEB_APP_URL',  // from Step 2
],
```

Then create an empty credentials file on the server at:
```
sheepsite.com/Scripts/credentials/NewBuildingName.json
```
with the contents `[]`.

> **Note:** All other PHP scripts load `buildings.php` automatically — no other files need to be edited.

---

## Step 4 — Set up the admin account

Visit:
```
https://sheepsite.com/Scripts/admin.php?building=NewBuildingName
```

Since no admin credentials exist yet, you will be automatically redirected to the password reset page. Enter the **President's unit number** as the secret # and click **Send temporary password**. The temporary password will be emailed to the President (whoever has `President` in the `Board` column of the Database tab).

Log in with the temporary password — you will be prompted to set a permanent one immediately.

---

## Step 5 — Import owners

Once logged into the admin page, click **Manage Users** and use **Import from Association Database Sheet**. Enter a temporary password and click **Import**.

This reads the `Database` tab from the building's Google Sheet and creates an account for every owner (username = first initial + last name, e.g. `jsmith`). All imported accounts have `mustChange: true` — owners will be forced to change the temporary password on first login.

Distribute the temporary password to owners however you like (email, letter, etc.).

---

## Step 6 — Set the building name on the new site

In Namecheap Website Builder, go to **Settings** (top right menu) → **Pages**. At the top, select **Default**, then paste the script below into the **After `<body>`** section on the right side of the panel.

Change only the `BUILDING_NAME` value on the first line:

```html
<script>
const BUILDING_NAME = 'NewBuildingName';

document.addEventListener('DOMContentLoaded', function () {
  const PUBLIC_URL  = 'https://sheepsite.com/Scripts/display-public-dir.php';
  const PRIVATE_URL = 'https://sheepsite.com/Scripts/display-private-dir.php';

  document.querySelectorAll('.gdrive-link').forEach(function (btn) {
    var url = PUBLIC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = btn.getAttribute('data-subdir');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    url += '&return=' + encodeURIComponent(window.location.href);
    btn.href = url;
  });

  document.querySelectorAll('.local-link').forEach(function (btn) {
    var url = PRIVATE_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var path = btn.getAttribute('data-path');
    if (path) url += '&path=' + encodeURIComponent(path);
    url += '&return=' + encodeURIComponent(window.location.href);
    btn.href = url;
  });

  document.querySelectorAll('a[href*="admin.php"]').forEach(function (link) {
    link.href = 'https://sheepsite.com/Scripts/admin.php?building=' + encodeURIComponent(BUILDING_NAME);
  });

  const REPORT_URL = 'https://sheepsite.com/Scripts/protected-report.php';
  document.querySelectorAll('iframe[data-script="protected-report"]').forEach(function (iframe) {
    var url = REPORT_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var page = iframe.getAttribute('data-page');
    if (page) url += '&page=' + encodeURIComponent(page);
    url += '&return=' + encodeURIComponent(window.location.href);
    iframe.onload = function () {
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });

  const PUBLIC_REPORT_URL = 'https://sheepsite.com/Scripts/public-report.php';
  document.querySelectorAll('iframe[data-script="public-report"]').forEach(function (iframe) {
    var url = PUBLIC_REPORT_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var page = iframe.getAttribute('data-page');
    if (page) url += '&page=' + encodeURIComponent(page);
    url += '&nav=0';
    iframe.onload = function () {
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });

  const DOC_URL = 'https://sheepsite.com/Scripts/get-doc-byname.php';
  document.querySelectorAll('iframe[data-script="get-doc-byname"]').forEach(function (iframe) {
    var url = DOC_URL + '?building=' + encodeURIComponent(BUILDING_NAME);
    var subdir = iframe.getAttribute('data-subdir');
    var filename = iframe.getAttribute('data-filename');
    if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
    if (filename) url += '&filename=' + encodeURIComponent(filename);
    iframe.style.display = 'none';
    iframe.onload = function () {
      iframe.style.display = 'block';
      var loader = document.getElementById('doc-loader');
      if (loader) loader.style.display = 'none';
    };
    iframe.src = url;
  });
});

function openFolder(subdir) {
  var url = 'https://sheepsite.com/Scripts/display-public-dir.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return=' + encodeURIComponent(window.location.href);
  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
  window.location.href = url;
}

function openPrivateFolder(subdir) {
  var url = 'https://sheepsite.com/Scripts/display-private-dir.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&return=' + encodeURIComponent(window.location.href);
  if (subdir) url += '&path=' + encodeURIComponent(subdir);
  window.location.href = url;
}

function openReport(page) {
  window.location.href = 'https://sheepsite.com/Scripts/protected-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page=' + encodeURIComponent(page)
    + '&return=' + encodeURIComponent(window.location.href);
}

function openPublicReport(page) {
  window.location.href = 'https://sheepsite.com/Scripts/public-report.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&page=' + encodeURIComponent(page)
    + '&return=' + encodeURIComponent(window.location.href);
}

function openAdmin() {
  window.location.href = 'https://sheepsite.com/Scripts/admin.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME);
}

function openDoc(subdir, filename) {
  var url = 'https://sheepsite.com/Scripts/get-doc-byname.php'
    + '?building=' + encodeURIComponent(BUILDING_NAME)
    + '&filename=' + encodeURIComponent(filename);
  if (subdir) url += '&subdir=' + encodeURIComponent(subdir);
  window.open(url, '_blank');
}
</script>
```

That's it. All buttons and iframes on the site will automatically point to the correct building.
