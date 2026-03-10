# Google Sheets Building Reports — Scripts Documentation

## Overview

This suite of Google Apps Scripts generates four reports for condo building websites:

1. **Board of Directors** — contact list for board members, sorted by role
2. **Elevator List** — alphabetical resident list in a two-column layout with today's date
3. **Parking List** — car and parking spot assignments, sortable by Unit # or Parking Spot
4. **Resident List** — owner contact list with phone number, sortable by Unit # or Last Name

Each report is:
- Published as a **responsive web page** embeddable in the building's website via iframe
- Also maintained as a **tab inside the building's Google Sheet**, auto-updated within ~1 minute of any edit to the source data

---

## Architecture

### Master Library (DatabaseSheetMaster)

A single Google Apps Script project that contains all the logic. Each building sheet links to this library and calls its functions. When the master is updated and redeployed, all buildings benefit automatically.

### Building Sheet Script

A thin wrapper script pasted into each building's own Google Apps Script project. It contains:
- A **single `doGet()` web app entry point** that routes to the correct page via URL parameter
- **Trigger functions** that auto-update list tabs when source data is edited

---

## File Structure

```
sheets/
├── board-list.gs        → Master library: Board of Directors logic
├── elevator-list.gs     → Master library: Elevator List logic
├── parking-list.gs      → Master library: Parking List logic
├── resident-list.gs     → Master library: Resident List logic
├── building-script.gs   → Paste this into each building's Apps Script project
└── README.md            → This file
```

---

## Master Library Files

### board-list.gs
- **`generateBoardList()`** — reads the `Database` tab, writes a formatted `BoardList` tab
- **`doGet()`** — serves the Board of Directors as a responsive HTML web page

### elevator-list.gs
- **`generateElevatorList()`** — reads the `Database` tab, writes a formatted `Elevator List` tab
- **`doGetElevator()`** — serves the Elevator List as a responsive HTML web page

### parking-list.gs
- **`generateParkingList()`** — reads the `CarDB` tab, writes a formatted `Parking List` tab
- **`doGetParking()`** — serves the Parking List as a responsive HTML web page with sort buttons

### resident-list.gs
- **`generateResidentList()`** — reads the `Database` tab, writes a formatted `Resident List` tab
- **`doGetResident()`** — serves the Resident List as a responsive HTML web page sortable by Unit # or Last Name

---

## Building Sheet Conventions

| Item | Convention |
|---|---|
| Google Sheet file name | `<Building Name> Owner DB` (e.g. `Lyndhurst H Owner DB`) |
| Resident data tab | `Database` |
| Car/parking data tab | `CarDB` |
| Library identifier | `DatabaseSheetMaster` |

Building name is extracted automatically from the file name:
`"Lyndhurst H Owner DB"` → building name = `"Lyndhurst H"`

---

## Database Tab — Required Column Headers (row 1)

| Column | Header name (exact) |
|---|---|
| Unit number | `Unit #` |
| First name | `First Name` |
| Last name | `Last Name` |
| Board role | `Board` |
| Email | `eMail` |
| Phone | `Phone #1` |

Board field values must be one of: `President`, `Vice President`, `Treasurer`, `Secretary`, `Director`

## CarDB Tab — Required Column Headers (row 1)

| Column | Header name (exact) |
|---|---|
| Unit number | `Unit #` |
| Parking spot | `Parking Spot` |
| Car make | `Car Make` |
| Car model | `Car Model` |
| Car color | `Car Color` |

---

## Web App URL Routing

Each building has **one Web App deployment** that serves all pages via a URL parameter:

| Page | URL |
|---|---|
| Board of Directors | `https://.../exec` |
| Elevator List | `https://.../exec?page=elevator` |
| Parking List | `https://.../exec?page=parking` |
| Resident List | `https://.../exec?page=resident` |

### Embedding in a website (iframe)

```html
<iframe
  src="https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec"
  style="width:100%; height:80vh; border:none; display:block;"
  title="Board of Directors">
</iframe>
```

The `setXFrameOptionsMode(ALLOWALL)` setting is already included in all web pages so iframe embedding works without errors.

---

## Auto-Update Triggers

Two installable triggers keep the list tabs in sync automatically:

| Trigger | Function | Type |
|---|---|---|
| On edit | `onEditHandler` | From spreadsheet → On edit |
| Every minute | `runScheduledUpdate` | Time-driven → Minutes timer → Every minute |

**How it works:**
- Any edit to `Database` or `CarDB` stamps a timestamp via `onEditHandler`
- `runScheduledUpdate` checks every minute; once 30 seconds have passed since the last edit, all four list tabs are regenerated
- If edits are still coming in, the update waits — this debounce prevents thrashing during bulk data entry
- Each generator runs independently, so a failure in one doesn't block the others

**Result:** list tabs update automatically within ~1 minute of the last edit, with no manual action required.

---

## Deployment TODO — New Building Setup

### Step 1 — Google Sheet
- [ ] Create a new Google Sheet
- [ ] Name the file: `<Building Name> Owner DB`
- [ ] Rename the first tab to `Database`
- [ ] Add a second tab named `CarDB` for parking data
- [ ] Ensure row 1 of `Database` has the required column headers (exact spelling)
- [ ] Ensure row 1 of `CarDB` has the required column headers (exact spelling)

### Step 2 — Apps Script setup
- [ ] Open the sheet > **Extensions > Apps Script**
- [ ] Delete any default code in the editor
- [ ] Paste the entire contents of `building-script.gs`
- [ ] Click **+** next to Libraries in the left sidebar
- [ ] Enter the Script ID for `DatabaseSheetMaster` and click Look up
  *(find this in the `DatabaseSheetMaster` Apps Script project: Project Settings → Script ID)*
- [ ] Set the identifier to `DatabaseSheetMaster`
- [ ] Select **latest version** (not a pinned version number)
- [ ] Click Add

### Step 3 — Web App deployment
- [ ] In Apps Script: **Deploy > New deployment**
- [ ] Select type: **Web App**
- [ ] Description: building name (for your reference)
- [ ] Execute as: **Me**
- [ ] Who has access: **Anyone**
- [ ] Click **Deploy** and copy the deployment URL

### Step 4 — Install auto-update triggers
- [ ] In Apps Script: **Triggers** (clock icon in left sidebar)
- [ ] Click **+ Add Trigger**
  - Function: `onEditHandler` | Event source: From spreadsheet | Event type: On edit
  - Click Save
- [ ] Click **+ Add Trigger** again
  - Function: `runScheduledUpdate` | Event source: Time-driven | Type: Minutes timer | Interval: Every minute
  - Click Save

### Step 5 — Test before publishing
- [ ] Edit a cell in the `Database` tab, wait ~1 minute — verify list tabs update automatically
- [ ] Open `[URL]/exec` in a browser — verify Board of Directors page
- [ ] Open `[URL]/exec?page=elevator` — verify Elevator List page
- [ ] Open `[URL]/exec?page=parking` — verify Parking List page and sort buttons
- [ ] Open `[URL]/exec?page=resident` — verify Resident List page and sort buttons

### Step 6 — Embed in the building website
- [ ] Board of Directors page: embed `[URL]/exec`
- [ ] Elevator List page: embed `[URL]/exec?page=elevator`
- [ ] Parking List page: embed `[URL]/exec?page=parking`
- [ ] Resident List page: embed `[URL]/exec?page=resident`

---

## Deployment TODO — Updating the Master Library

Do this whenever any of the master `.gs` files are changed:

- [ ] Open the `DatabaseSheetMaster` Apps Script project
- [ ] Update the relevant file(s) with the new code
- [ ] **Deploy > Manage deployments**
- [ ] Click the pencil (edit) icon on the existing deployment
- [ ] Change version to **New version**
- [ ] Click **Deploy**

Building sheets set to **latest version** will pick up the changes automatically.
No changes needed to building sheet scripts or website embed URLs.
