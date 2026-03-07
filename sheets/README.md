# Google Sheets Building Reports — Scripts Documentation

## Overview

This suite of Google Apps Scripts generates four reports for condo building websites:

1. **Board of Directors** — contact list for board members, sorted by role
2. **Elevator List** — alphabetical resident list in a two-column layout with today's date
3. **Parking List** — car and parking spot assignments, sortable by Unit # or Parking Spot
4. **Resident List** — owner contact list with phone number, sortable by Unit # or Last Name

Each report can be:
- Generated as a **tab inside the building's Google Sheet** for easy review
- Published as a **responsive web page** embeddable in the building's website via iframe

---

## Architecture

### Master Library (DatabaseSheetMaster)

A single Google Apps Script project that contains all the logic. Each building sheet links to this library and calls its functions. When the master is updated and redeployed, all buildings benefit automatically.

### Building Sheet Script

A thin wrapper script pasted into each building's own Google Apps Script project. It contains:
- A **SheepSite menu** in the Google Sheets toolbar
- **Wrapper functions** that call the master library
- A **single `doGet()` web app entry point** that routes to the correct page via URL parameter

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

Each building has **one Web App deployment** that serves all three pages via a URL parameter:

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

The `setXFrameOptionsMode(ALLOWALL)` setting is already included in all three web pages so iframe embedding works without errors.

---

## SheepSite Menu (in each building sheet)

When the building sheet opens, a **SheepSite** menu appears in the toolbar with:

| Menu item | What it does |
|---|---|
| Elevator List Update | Regenerates the `Elevator List` tab |
| Board List Update | Regenerates the `BoardList` tab |
| Parking List Update | Regenerates the `Parking List` tab |
| Resident List Update | Regenerates the `Resident List` tab |

Use these to verify data before checking the published web pages.

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

### Step 4 — Test before publishing
- [ ] Return to the sheet and reload — verify **SheepSite** menu appears
- [ ] Run **Elevator List Update** — check the `Elevator List` tab looks correct
- [ ] Run **Board List Update** — check the `BoardList` tab looks correct
- [ ] Run **Parking List Update** — check the `Parking List` tab looks correct
- [ ] Run **Resident List Update** — check the `Resident List` tab looks correct
- [ ] Open `[URL]/exec` in a browser — verify Board of Directors page
- [ ] Open `[URL]/exec?page=elevator` — verify Elevator List page
- [ ] Open `[URL]/exec?page=parking` — verify Parking List page and sort buttons
- [ ] Open `[URL]/exec?page=resident` — verify Resident List page and sort buttons

### Step 5 — Embed in the building website
- [ ] Board of Directors page: embed `[URL]/exec`
- [ ] Elevator List page: embed `[URL]/exec?page=elevator`
- [ ] Parking List page: embed `[URL]/exec?page=parking`
- [ ] Resident List page: embed `[URL]/exec?page=resident`

---

## Deployment TODO — Updating the Master Library

Do this whenever any of the three master `.gs` files are changed:

- [ ] Open the `DatabaseSheetMaster` Apps Script project
- [ ] Update the relevant file(s) with the new code
- [ ] **Deploy > Manage deployments**
- [ ] Click the pencil (edit) icon on the existing deployment
- [ ] Change version to **New version**
- [ ] Click **Deploy**

Building sheets set to **latest version** will pick up the changes automatically.
No changes needed to building sheet scripts or website embed URLs.
