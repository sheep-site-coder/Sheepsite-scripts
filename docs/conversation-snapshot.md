# Conversation Snapshot - Sheepsite-scripts
**Date:** March 12, 2026 (updated)

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

### Apps Scripts
| File | Deployment | Purpose |
|------|-----------|---------|
| `dir-display-bridge.gs` | Single shared URL (all buildings) | list, listPrivate, download, storageReport, search |
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
        from existing tags; accessible from admin.php File Tags card
      - dir-display-bridge.gs: new `search` action using Folder.searchFiles()
        for recursive filename matching across both Public and Private trees
      - footer-for-sites.js: openSearch() helper added

---

## Next Steps

- **Deploy search feature**: merge `feature/search-and-tagging` → main when tested
- **Redeploy dir-display-bridge.gs** (new version needed for `search` action to go live)
- **Wire up Search button** on building sites using `openSearch()`
- **Create tags/ folder** on server (writable by PHP); .htaccess auto-created by tag-admin.php
- Onboard additional communities as they sign up (follow NEW-SITE-GUIDE.md)

---

*Snapshot updated: March 12, 2026 (session 2)*
*Working directory: /Users/alain/github/Sheepsite-scripts*
