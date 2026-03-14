# Conversation Snapshot - Sheepsite-scripts
**Date:** March 14, 2026 (updated)

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

---

## Next Steps

- **Upload file-manager.php** to server — tag-on-replace fix requires this updated file
- **Merge `feature/search-and-tagging` → main** — search, tagging, and file manager all tested and working
- **Redeploy dir-display-bridge.gs** (new version needed for all new actions to go live — already done on server)
- **Wire up Search button** on building sites using `openSearch()`
- **Create tags/ folder** on server (writable by PHP); .htaccess auto-created by tag-admin.php
- Upload docs/ folder files to Sharefolder / distribute as needed
- Onboard additional communities as they sign up (follow NEW-SITE-GUIDE.md)

---

*Snapshot updated: March 14, 2026 (session 4)*
*Working directory: /Users/alain/github/Sheepsite-scripts*
