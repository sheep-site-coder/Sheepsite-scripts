#!/usr/bin/env python3
"""
Builds Manual-admin.html with base64-embedded images.
Run from the docs/ folder: python3 build-manual.py
"""

import base64, os, re

IMG_DIR = "../Sharefolder/SheepSite User Manual/images"
WOOLSY_LOGO = "../test/Woolsy-original-transparent.png"
WOOLSY_WORKING = "../Sharefolder/Woolsy_Working on it.png"

def img_tag(filename, alt="", width="100%", style=""):
    if os.path.isabs(filename):
        path = filename
    elif filename.startswith("../"):
        path = os.path.join(os.path.dirname(__file__), filename)
    else:
        path = os.path.join(IMG_DIR, filename)
    ext = filename.rsplit(".", 1)[-1].lower()
    mime = "image/jpeg" if ext in ("jpg", "jpeg") else "image/png"
    with open(path, "rb") as f:
        data = base64.b64encode(f.read()).decode()
    s = f' style="{style}"' if style else ""
    w = f' width="{width}"' if width else ""
    return f'<img src="data:{mime};base64,{data}" alt="{alt}"{w}{s}>'

def woolsy_section_icon():
    img = img_tag(WOOLSY_WORKING, "Woolsy", width="", style="height:34px; display:block;")
    return f'<span style="background:#fff; border-radius:4px; padding:2px 4px; display:inline-flex; align-items:center; margin-right:10px; flex-shrink:0;">{img}</span>'

def section(title):
    return f'<h2>{woolsy_section_icon()}{title}</h2>'

def divider():
    return ""

NNBSP = "\u202f"  # narrow no-break space used by macOS in screenshot filenames
public_folders_img  = img_tag(f"../Sharefolder/Screenshot 2026-03-14 at 12.33.49{NNBSP}PM.png", "Public folder structure",  "100%", "border:1px solid #ccc; border-radius:4px;")
private_folders_img = img_tag(f"../Sharefolder/Screenshot 2026-03-14 at 12.34.59{NNBSP}PM.png", "Private folder structure", "100%", "border:1px solid #ccc; border-radius:4px;")

html = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body {{
    font-family: Georgia, serif;
    max-width: 760px;
    margin: 40px auto;
    color: #222;
    line-height: 1.7;
    font-size: 15px;
  }}
  h1 {{ font-size: 1.8em; margin-bottom: 0.1em; }}
  .subtitle {{ font-size: 1.1em; color: #555; margin-top: 0; }}
  .version {{ font-size: 0.9em; color: #888; margin-top: 0.3em; }}
  h2 {{ font-size: 1.2em; margin-top: 2.5em; background: #333; color: #fff; padding: 6px 12px; border-radius: 3px; display: flex; align-items: center; }}
  h3 {{ font-size: 1em; margin-top: 1.8em; margin-bottom: 0.3em; border-bottom: 1px solid #ddd; padding-bottom: 3px; }}
  h4 {{ font-size: 0.95em; margin-top: 1.2em; margin-bottom: 0.2em; color: #444; }}
  p {{ margin: 0.6em 0; }}
  a {{ color: #1a73e8; }}
  ul, ol {{ margin: 0.5em 0 0.5em 1.5em; }}
  li {{ margin: 0.4em 0; }}
  hr {{ border: none; border-top: 1px solid #ddd; margin: 2em auto; width: 60%; }}
  code {{
    font-family: 'Courier New', monospace;
    background: #f4f4f4;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 0.9em;
  }}
  table {{
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
    font-size: 0.95em;
  }}
  th {{
    background: #f0f0f0;
    text-align: left;
    padding: 8px 10px;
    border: 1px solid #ccc;
  }}
  td {{
    padding: 8px 10px;
    border: 1px solid #ccc;
    vertical-align: top;
  }}
  tr:nth-child(even) td {{ background: #fafafa; }}
  blockquote {{
    border-left: 4px solid #f0a500;
    margin: 1em 0;
    padding: 0.5em 1em;
    background: #fffbf0;
    color: #444;
  }}
  .tip {{
    border-left: 4px solid #2a7a2a;
    margin: 1em 0;
    padding: 0.5em 1em;
    background: #f0faf0;
    color: #444;
  }}
  .toc {{ background: #f9f9f9; border: 1px solid #ddd; padding: 1em 1.5em; margin: 1.5em 0; border-radius: 4px; }}
  .toc h3 {{ border: none; margin-top: 0; }}
  .toc ol {{ margin: 0.3em 0 0 1.2em; }}
  .toc li {{ margin: 0.25em 0; }}
  .screenshot {{
    border: 1px solid #ccc;
    border-radius: 4px;
    margin: 1em 0;
    display: block;
  }}
  .illus {{
    display: block;
    margin: 1em auto;
  }}
</style>
</head>
<body>

<div style="text-align:center; margin-bottom: 1.5em;">
  {img_tag(WOOLSY_LOGO, "Woolsy", width="", style="height:90px;")}
  <div style="font-size:0.85em; color:#888; margin-top:0.4em; letter-spacing:0.05em;">POWERED BY SHEEP</div>
</div>

<h1>SheepSite Admin Manual</h1>
<p class="subtitle">For Board Members Managing the Building Website</p>
<p class="version">Version 2.1 &nbsp;&middot;&nbsp; April 2026</p>

<div class="toc">
  <h3>Contents</h3>
  <ol>
    <li>Getting Started</li>
    <li>The Admin Dashboard &mdash; Admin Accounts</li>
    <li>Manage Residents/Owners</li>
    <li>Manage User Accounts</li>
    <li>Managing Files</li>
    <li>Tag Management</li>
    <li>Storage Report</li>
    <li>Search &mdash; Training Your Residents</li>
    <li>Woolsy AI Assistant</li>
    <li>Billing &amp; Invoices</li>
    <li>Appendix: Sample Introduction Email</li>
  </ol>
</div>

{divider()}

{section("Section 1 &mdash; Getting Started")}

<h3>Who This Manual Is For</h3>
<p>This manual is for board members responsible for administering the building website &mdash; managing documents, adding or removing owner accounts, and keeping the site content current.</p>
<p>For a guide on how residents use the site, see the separate <strong>Resident User Manual</strong>.</p>


<h3>What You Need</h3>
<ul>
  <li>A computer or tablet with a modern browser (Google Chrome recommended)</li>
  <li>Your admin username and password (provided by SheepSite during setup)</li>
</ul>

<h3>Accessing the Admin Area</h3>
<p>The admin area is accessed directly from the building website. In the site menu, open the <strong>Admin</strong> dropdown and select <strong>Site Admin</strong>. Log in with your admin credentials. If this is your first login, you will be prompted to set a permanent password.</p>

<blockquote><strong>Forgot your password?</strong> Click the &ldquo;Forgot password?&rdquo; link on the login page. Enter your <strong>admin username</strong> and the <strong>President&rsquo;s unit number</strong> as the security verification. A temporary password will be sent to the email address on file for your admin account.</blockquote>

<h3>Terms of Service</h3>
<p>When you log in to the admin panel, you may be presented with the SheepSite Terms of Service agreement before accessing any admin features &mdash; on your first login, or whenever SheepSite has updated its terms. You must read the agreement in full and click <strong>I Accept</strong> to continue. Your acceptance is recorded with a timestamp.</p>
<ul>
  <li><strong>Decline &amp; Log Out</strong> &mdash; if you click Decline, you will be logged out immediately. You must accept the Terms of Service to use the admin panel.</li>
  <li><strong>Re-acceptance</strong> &mdash; from time to time SheepSite may update its Terms of Service. When this happens, you will be prompted to review and re-accept the updated agreement on your next login. Your previous acceptance is automatically archived &mdash; it is not lost.</li>
</ul>
<blockquote><strong>Questions?</strong> If you have any questions about the Terms of Service, contact SheepSite before clicking Accept.</blockquote>

{divider()}

{section("Section 2 &mdash; The Admin Dashboard")}

<p>After logging in, you will see the Admin Dashboard. It is organized into two tabs:</p>

<h3>Dashboard Tab</h3>
<p>The Dashboard tab contains all the main feature cards:</p>
<table>
  <tr><th>Card</th><th>What It Does</th></tr>
  <tr><td><strong>Manage Residents/Owners</strong></td><td>Add, edit, and remove residents across all units. Manage contact info, vehicles, and emergency contacts. Bulk import from CSV. Copy all resident email addresses for community-wide email.</td></tr>
  <tr><td><strong>Manage User Accounts</strong></td><td>Add or reset individual web login accounts. Run Sync to create accounts for all database residents at once, or identify and remove orphaned accounts.</td></tr>
  <tr><td><strong>File Management</strong></td><td>Upload, organize, rename, and delete documents in the public and private folders.</td></tr>
  <tr><td><strong>Tag Management</strong></td><td>Add and manage tags on documents to improve search and organization.</td></tr>
  <tr><td><strong>Storage Report</strong></td><td>View how much Google Drive storage your building is using, broken down by folder.</td></tr>
  <tr><td><strong>Woolsy AI Assistant</strong></td><td>Set up or update the AI assistant&rsquo;s knowledge of your building&rsquo;s governing documents. Shows current status and credit usage.</td></tr>
  <tr><td><strong>User Manual</strong></td><td>Opens this manual.</td></tr>
</table>

<h3>Settings Tab</h3>
<p>Click <strong>Settings</strong> at the top to switch to the Settings tab, which contains building configuration and admin account management:</p>
<table>
  <tr><th>Section</th><th>What It Does</th></tr>
  <tr><td><strong>Building Settings</strong></td><td>Update the building&rsquo;s <strong>billing contact email</strong> address (used for invoices). The building website URL is shown here for reference but is managed by SheepSite.</td></tr>
  <tr><td><strong>Admin Accounts</strong></td><td>View and manage all administrator accounts for this building. Add new admins, update email addresses for notifications, or remove accounts. Each admin has a separate username, password, and email address.</td></tr>
  <tr><td><strong>Change Admin Password</strong></td><td>Change your own admin password. Enter your current password, then your new password twice.</td></tr>
</table>

<h3>Admin Accounts</h3>
<p>Each building can have more than one administrator account. All admin accounts are listed in the <strong>Admin Accounts</strong> section of the <strong>Settings</strong> tab. Each account shows its username and a field to set the email address used for resident change-request notifications.</p>

<h4>Changing Your Email Address</h4>
<p>Find your account in the list and update the email field, then click <strong>Save</strong>. This email receives notifications when a resident submits a change request from the My Unit page.</p>

<h4>Adding a New Admin Account</h4>
<ol>
  <li>Click <strong>+ Add admin account</strong> below the accounts table to expand the form</li>
  <li>Enter a username (lowercase letters and numbers, e.g. &ldquo;jsmith&rdquo;), an email address, and an initial password</li>
  <li>Click <strong>Add</strong> &mdash; the new account appears in the list immediately</li>
  <li>Share the username and password with the new admin &mdash; they can log in right away</li>
</ol>

<h4>Removing an Admin Account</h4>
<p>Click <strong>Remove</strong> next to any account that should no longer have access. You cannot remove your own account, and at least one admin account must remain at all times.</p>

<h4>Changing Your Own Password</h4>
<p>Use the <strong>Change Admin Password</strong> form at the bottom of the <strong>Settings</strong> tab. Enter your current password, then your new password twice. The new password must be at least 8 characters.</p>

{divider()}

{section("Section 3 &mdash; Manage Residents/Owners")}

<h3>Overview</h3>
<p>All owner and resident data is managed from the Admin Dashboard via <strong>Manage Residents/Owners</strong>. This gives you a full in-site editor for every unit &mdash; no need to open a spreadsheet for day-to-day changes.</p>

<h3>The Unit View</h3>
<p>Units are grouped by floor. Click any unit to expand it and see four tabs:</p>
<table>
  <tr><th>Tab</th><th>What it contains</th></tr>
  <tr><td><strong>Residents</strong></td><td>Each person in the unit &mdash; name, status (Owner/Resident/Full Time), email, phones</td></tr>
  <tr><td><strong>Unit Info</strong></td><td>Insurance company and policy number, AC replacement date, water tank replacement date, unit notes</td></tr>
  <tr><td><strong>Vehicle &amp; Parking</strong></td><td>Car make/model/color, license plate, parking spot, notes</td></tr>
  <tr><td><strong>Emergency</strong></td><td>Emergency contacts and condo sitters &mdash; name, email, phones</td></tr>
</table>

<h3>Adding a Resident</h3>
<ol>
  <li>Click the unit to expand it, then click <strong>+ Add Resident</strong></li>
  <li>Fill in at minimum First Name, Last Name, and Unit # &mdash; all other fields are optional</li>
  <li>If you provide an email address, a web login is created automatically and a welcome email with a temporary password is sent to the resident</li>
  <li>If no email is provided, only the database record is created. You can add the email later &mdash; the login will be created at that point</li>
</ol>

<h3>Editing or Deleting a Resident</h3>
<p>Expand the unit, click <strong>Edit</strong> on the person card to update their details, or <strong>Delete</strong> to remove them from the database and revoke their web login at the same time.</p>

<h3>Bulk Import from CSV</h3>
<p>When onboarding a new community, use the CSV import to add all residents at once rather than one at a time.</p>
<ol>
  <li>Export a CSV from your property management system. The file must have at minimum a <strong>First Name</strong> and <strong>Last Name</strong> column. Unit #, Email, and Phone columns are also recognized if present</li>
  <li>From <strong>Manage Residents/Owners</strong>, click <strong>&#x2913; Import from CSV</strong> in the toolbar</li>
  <li>Drag the CSV file onto the drop zone, or click to browse. A preview table appears showing the rows to be imported</li>
  <li>Click <strong>Import</strong>. Rows already in the database (matched by First + Last Name) are skipped &mdash; safe to re-run</li>
  <li>After import, go to <strong>Manage User Accounts &rarr; Sync</strong> to create web logins for all newly imported residents</li>
</ol>

<div class="tip"><strong>Tip:</strong> The CSV importer is flexible &mdash; it recognizes common column name variations from most property management systems (e.g. &ldquo;First&rdquo;, &ldquo;Given Name&rdquo;, &ldquo;Apt&rdquo;, &ldquo;Cell&rdquo;, etc.). Extra columns are ignored.</div>

<h3>Copying All Resident Emails</h3>
<p>Click <strong>Get Email List</strong> in the toolbar to copy all resident email addresses to your clipboard. Paste into the <strong>BCC</strong> field of your email client to send a community-wide message.</p>

<h3>Resident Change Requests &mdash; Pending Queue</h3>
<p>When a resident submits an &ldquo;Add Resident&rdquo; request from the My Unit page, it goes into a pending queue rather than just sending an email. You will see a <strong>Pending Requests</strong> button with a badge count on the Manage Residents/Owners page whenever requests are waiting.</p>
<p>Click the button to open the queue. Each request shows the submitted details. You have two options:</p>
<ul>
  <li><strong>Accept</strong> &mdash; creates the database record, creates a web login, and sends the resident a welcome email with a temporary password automatically</li>
  <li><strong>Reject</strong> &mdash; removes the request from the queue with no further action</li>
</ul>
<p>You do not need to copy-paste any data manually &mdash; accepting a request does everything in one step.</p>

<h3>Automated Reports</h3>
<p>The following reports are served directly from the resident database and are always current:</p>
<table>
  <tr><th>Report</th><th>Access</th></tr>
  <tr><td>Resident List</td><td>Private (residents only)</td></tr>
  <tr><td>Elevator List</td><td>Private (residents only)</td></tr>
  <tr><td>Parking List</td><td>Private (residents only)</td></tr>
  <tr><td>Board of Directors</td><td>Public</td></tr>
</table>
<p>Residents access these reports the same way as always &mdash; through the building website. No Google Sheets or spreadsheet access is needed. Changes made in <strong>Manage Residents/Owners</strong> are reflected in the reports immediately.</p>

{divider()}

{section("Section 4 &mdash; Manage User Accounts")}

<div style="display:flex; gap:1.5em; align-items:flex-start; margin: 1em 0 1.5em 0;">
  <div style="flex:2;">
    <h3 style="margin-top:0;">Overview</h3>
    <p>Each resident gets their own individual login account for the private Resource Center. Accounts are tied to a person, not a unit &mdash; when a resident moves in or out, you add or remove their account accordingly.</p>
    <p>Web login accounts are managed separately from resident data. The recommended workflow is:</p>
    <ol>
      <li>Add residents to the database via <strong>Manage Residents/Owners</strong> (see Section 3)</li>
      <li>Run <strong>Sync</strong> from <strong>Manage User Accounts</strong> to create web logins for everyone in the database</li>
    </ol>
  </div>
  <div style="flex:1;">
    {img_tag("image7.jpg", "System", "100%", "border-radius:6px;")}
  </div>
</div>

<h3>Sync &mdash; Create or Clean Up Accounts</h3>
<p>Sync compares all web login accounts against the resident database in both directions and shows you what needs attention. Run it after adding residents, and whenever a resident moves out.</p>
<ol>
  <li>From the Admin Dashboard, click <strong>Manage User Accounts</strong></li>
  <li>Click <strong>Sync Now</strong> and confirm</li>
  <li>The system checks both directions and displays two panels (if applicable):</li>
</ol>
<table>
  <tr><th>Panel</th><th>What it means</th><th>What to do</th></tr>
  <tr><td><strong>Orphaned accounts</strong> (yellow)</td><td>Web logins with no matching resident in the database &mdash; e.g. someone who moved out</td><td>Check the ones to remove, click <strong>Remove checked</strong>, or dismiss to keep them</td></tr>
  <tr><td><strong>Missing accounts</strong> (blue)</td><td>Database residents with no web login &mdash; e.g. newly imported residents</td><td>Check the ones to activate, click <strong>Recreate checked</strong> &mdash; a temporary password is generated and emailed automatically</td></tr>
</table>

<div class="tip"><strong>Tip:</strong> Sync is safe to run at any time &mdash; nothing is modified until you review and confirm. You can dismiss either panel independently if you don&rsquo;t need to act on it right now.</div>

<blockquote><strong>Important:</strong> All new accounts created by Sync are flagged as &ldquo;first login&rdquo; &mdash; residents are required to change the temporary password the first time they log in. They cannot reuse the temporary password.</blockquote>

<h3>Adding or Resetting a Single Account</h3>
<ol>
  <li>From <strong>Manage User Accounts</strong>, fill in the <strong>Add / Reset Resident</strong> form</li>
  <li>Enter the username, a temporary password, and click <strong>Add / Reset</strong></li>
  <li>If the username matches a resident in the database who has an email address, the system automatically emails them the temporary password and requires them to change it on first login</li>
  <li>If no match is found in the database, no email is sent &mdash; distribute the password manually</li>
</ol>

<h3>Removing an Account</h3>
<p>Find the resident in the account list and click <strong>Remove</strong>. This immediately revokes their access. For a resident who has moved out, also remove them from the database via <strong>Manage Residents/Owners</strong>.</p>

<h3>Residents Resetting Their Own Password</h3>
<p>Residents can reset their own password without contacting you. On the login page, they click <strong>Forgot password?</strong> The system emails them a temporary password and they are prompted to change it on login.</p>

{divider()}

{section("Section 5 &mdash; Managing Files")}

<h3>How Publishing Works</h3>
<p>Files uploaded through the File Management card <strong>automatically appear on the website</strong> &mdash; there is no publish button or sync step required.</p>
<ul>
  <li><strong>Public folder</strong> &mdash; visible to anyone visiting the website, no login needed</li>
  <li><strong>Private folder</strong> &mdash; visible only to logged-in owners</li>
</ul>

<h3>The File Manager</h3>
<p>The built-in file manager lets you upload, organize, and delete documents without leaving the website. Access it from the Admin Dashboard via the <strong>File Management</strong> card, or directly from any document browser page on your site.</p>
<p>Folder listings are remembered during your session &mdash; the first time you open a folder the system fetches it from Google Drive, and every subsequent visit within the same session is instant. The listing is automatically refreshed any time you upload, delete, rename, or move files.</p>

<h4>Uploading Files</h4>
<ul>
  <li><strong>Drag and drop</strong> &mdash; drag one or more files from your computer directly onto the upload area</li>
  <li><strong>Browse</strong> &mdash; click the upload area to open a file picker and select files manually</li>
  <li><strong>Multiple files at once</strong> &mdash; both methods support selecting several files in a single operation</li>
  <li><strong>Replace prompt</strong> &mdash; if a file with the same name already exists in the folder, you will be asked whether you want to replace it before the upload proceeds. Any tags assigned to the original file are automatically carried over to the new version &mdash; you do not need to re-tag it.</li>
</ul>

<h4>Storage Limit &mdash; When an Upload Would Exceed Available Space</h4>
<p>Before uploading, the file manager checks whether the selected files fit within your remaining storage. If they do not, a warning is displayed showing the overage. You have two options:</p>
<ul>
  <li><strong>Deselect files</strong> &mdash; uncheck one or more of the selected files to bring the total size within your limit, then proceed with the reduced selection</li>
  <li><strong>Add storage</strong> &mdash; click the <strong>Add Storage</strong> link in the warning to go directly to the billing page, where you can choose a storage upgrade tier and pay online. The new limit takes effect immediately after payment and you can then proceed with your upload.</li>
</ul>

<h4>Large Files (over 30 MB)</h4>
<p>Files larger than 30 MB cannot be uploaded through the standard file manager. When one or more selected files exceed this threshold, the file manager will open a <strong>new browser tab</strong> pointing to the building&rsquo;s large-file upload area (BigUploads folder). Upload your file there directly.</p>
<p>Once the upload is complete, return to the admin site and open the <strong>Quarantine</strong> section in the file manager. Files uploaded via the large-file area land in Quarantine first and are <strong>not yet visible on the website</strong>. Review each file, then click <strong>Publish</strong> to move it to the correct folder and make it visible to residents, or <strong>Delete</strong> to remove it.</p>
<blockquote><strong>Note:</strong> Always come back to Quarantine after a large-file upload &mdash; files left there are not accessible to residents until published.</blockquote>

<h4>Organizing Files</h4>
<ul>
  <li><strong>Create folders</strong> &mdash; click <strong>+ New Folder</strong> in the toolbar to create a subfolder inside the current folder</li>
  <li><strong>Delete folders</strong> &mdash; folders you created via the file manager show a <strong>Delete</strong> button next to their name. The folder must be empty before it can be deleted. You will be asked to confirm.</li>
  <li><strong>Rename files</strong> &mdash; click the <strong>Rename</strong> button next to any file</li>
  <li><strong>Delete files</strong> &mdash; click the <strong>Delete</strong> button next to any file; you will be asked to confirm</li>
</ul>
<p>All changes are reflected on the website immediately.</p>

<blockquote><strong>System folders cannot be deleted.</strong> The top-level folders (such as <code>BoardMinutes</code>, <code>RulesDocs</code>, <code>Forms</code>, etc.) were created when your site was set up and are permanently linked to specific sections of your website. They do not show a Delete button and cannot be removed or renamed. Only folders you create yourself through the file manager can be deleted.</blockquote>

<blockquote><strong>System files</strong> &mdash; A small number of files are embedded directly on the building website by name (for example, the Announcement page or the Mid/End Year Report). These files show a <strong>Replace</strong> button instead of Rename and Delete. To update one, click <strong>Replace</strong> and select the new file from your computer &mdash; the system will upload it, rename it automatically to the correct name, and remove the old version. The website embed continues to work without any changes to the site.</blockquote>

<h3>Document Folder Structure</h3>
<div style="display:flex; gap:1em; margin:1em 0; align-items:flex-start;">
  <div style="flex:1; text-align:center;">
    {public_folders_img}
    <p style="font-size:0.85em; color:#555; margin-top:4px;">Public folders</p>
  </div>
  <div style="flex:1; text-align:center;">
    {private_folders_img}
    <p style="font-size:0.85em; color:#555; margin-top:4px;">Private folders</p>
  </div>
</div>

<h4>Public Folders (no login required)</h4>
<table>
  <tr><th>Website Section</th><th>Folder</th></tr>
  <tr><td>Home Page / Latest News</td><td><code>Page1Docs/Announcement</code></td></tr>
  <tr><td>Mid/End Year Report</td><td><code>Page1Docs/Mid-End_Year_Report</code></td></tr>
  <tr><td>Building Guides &amp; Rules</td><td><code>RulesDocs</code></td></tr>
  <tr><td>Forms &amp; Applications</td><td><code>Forms</code></td></tr>
  <tr><td>Other Documents</td><td><code>OtherDocs</code></td></tr>
  <tr><td>Incorporation Documents</td><td><code>IncorporationDocs</code></td></tr>
</table>

<h4>Private Folders (login required)</h4>
<table>
  <tr><th>Website Section</th><th>Folder</th></tr>
  <tr><td>Board Minutes</td><td><code>BoardMinutes</code></td></tr>
  <tr><td>Financial Statements</td><td><code>FinancialDocs/FinanceStatements</code></td></tr>
  <tr><td>Budgets</td><td><code>FinancialDocs/Budgets</code></td></tr>
  <tr><td>SIRs Documents</td><td><code>FinancialDocs/SIRsDocs</code></td></tr>
  <tr><td>Contracts</td><td><code>Contracts</code></td></tr>
</table>

<h3>Tips for Document Organization</h3>
<ul>
  <li>Create year subfolders inside <code>BoardMinutes</code> (e.g. <code>2025</code>, <code>2026</code>) to keep minutes organized</li>
  <li>Use consistent, descriptive filenames &mdash; owners will see the filename on the site</li>
  <li>PDFs are recommended for final documents; they display reliably across all devices</li>
</ul>

<blockquote><strong>Note:</strong> Deleting a file is permanent. Make sure you have a local copy of anything you may need in the future.</blockquote>

{divider()}

{section("Section 6 &mdash; Tag Management")}

<p>Tags are keywords you assign to individual files to make them easier for residents to find through search. A document named <em>2026-03-minutes.pdf</em> might not appear when a resident searches for &ldquo;board meeting&rdquo; &mdash; but if you tag it with <em>board meeting</em> and <em>minutes</em>, it will.</p>
<p>Access Tag Management from the Admin Dashboard via the <strong>Tag Management</strong> card.</p>

<h3>How Tagging Works</h3>
<ul>
  <li>Tags are stored on the server and linked to each file by its unique Drive ID &mdash; renaming or replacing a file does not lose its tags</li>
  <li>Both Public and Private folders can be tagged &mdash; use the <strong>Public</strong> / <strong>Private</strong> tabs at the top</li>
  <li>Files that already have tags are highlighted in blue so you can see at a glance what&rsquo;s been tagged</li>
</ul>

<h3>Adding or Editing Tags on a File</h3>
<ol>
  <li>In the Tag Management page, browse to the folder containing the file</li>
  <li>Click the file row (or the pencil icon) to open the tag editor</li>
  <li>Type a tag and press <strong>Enter</strong> or <strong>,</strong> (comma) to add it &mdash; or click <strong>Add</strong></li>
  <li>Add as many tags as are useful</li>
  <li>Click <strong>Save</strong> &mdash; the tags are stored immediately</li>
</ol>

<div class="tip"><strong>Tip:</strong> As you type, the system suggests tags already used on other files. Reusing consistent tags (e.g. always using &ldquo;budget&rdquo; rather than sometimes &ldquo;budgets&rdquo;) makes search results more predictable for residents.</div>

<h3>Removing a Tag</h3>
<p>Open the tag editor for the file and click the &times; on any tag chip to remove it, then click <strong>Save</strong>. Saving with no tags removes all tags for that file.</p>

<h3>What to Tag</h3>
<p>Focus on documents residents commonly ask about. Good candidates:</p>
<ul>
  <li>Board meeting minutes &mdash; tag with: <em>minutes, board meeting, [year]</em></li>
  <li>Annual budget &mdash; tag with: <em>budget, finances, [year]</em></li>
  <li>Rules and regulations &mdash; tag with: <em>rules, regulations, governing</em></li>
  <li>Insurance documents &mdash; tag with: <em>insurance, coverage</em></li>
  <li>Inspection reports &mdash; tag with: <em>inspection, structural, milestone</em></li>
</ul>

{divider()}

{section("Section 7 &mdash; Storage Report")}

<div style="display:flex; gap:1.5em; align-items:center; margin:1em 0;">
  <div style="flex:1;">
    {img_tag("image10.jpg", "Cloud storage", "100%")}
  </div>
  <div style="flex:2;">
    <p>The Storage Report shows how much Google Drive storage your building is using. Access it from the Admin Dashboard.</p>
    <p>The report shows:</p>
    <ul>
      <li><strong>Grand total</strong> &mdash; combined Public + Private storage</li>
      <li><strong>Per-folder breakdown</strong> &mdash; sorted largest first, so you can quickly identify what is taking the most space</li>
    </ul>
    <p>This is useful for keeping storage in check, particularly if you store large files like videos or high-resolution scans.</p>
  </div>
</div>

<h3>Storage Limit</h3>
<p>Each building has a storage limit set by SheepSite. If an upload would exceed the limit, it will be blocked and you will see a message in the file manager.</p>
<p>When the limit is reached, an invoice is automatically created and an email notification is sent to the building&rsquo;s contact address with a link to pay online. Storage is available in upgrade tiers &mdash; you choose the size that works for your building. The new limit takes effect immediately after payment. You can also pay by check &mdash; see <strong>Section 10 &mdash; Billing &amp; Invoices</strong> for details.</p>
<blockquote><strong>Tip:</strong> Running the Storage Report regularly lets you keep an eye on usage before you hit the limit. Large files such as high-resolution scans and videos fill storage quickly &mdash; PDFs are much more space-efficient for most documents.</blockquote>

{divider()}

{section("Section 8 &mdash; Search: Training Your Residents")}

<h3>What Search Does</h3>
<p>The website includes a full-text search feature that lets owners find any document across the entire document store &mdash; both public and private &mdash; with a single search. It also searches tags, so a file tagged &ldquo;budget&rdquo; will appear even if that word isn&rsquo;t in the filename. Residents do not need to know which folder a document is in.</p>

<h3>Login Required</h3>
<p>Because search covers all folders including private documents, <strong>residents will be asked to log in as soon as they open the search page</strong>. This is by design &mdash; it ensures private documents are only visible to authenticated owners. Make sure residents know their login credentials before trying to use search.</p>

<h3>How to Show Residents</h3>
<p>When introducing residents to the site, demonstrate search with a practical example:</p>
<ol>
  <li>Go to the website and click the <strong>Search</strong> button</li>
  <li>Log in when prompted</li>
  <li>Type a keyword &mdash; for example, <em>&ldquo;budget&rdquo;</em> or <em>&ldquo;minutes&rdquo;</em> or <em>&ldquo;parking&rdquo;</em></li>
  <li>Results appear instantly, showing the document name and which folder it lives in</li>
  <li>Click a result to open or download the document</li>
</ol>

<h3>Key Points for Residents</h3>
<ul>
  <li>Login is required &mdash; residents need their account credentials to use search</li>
  <li>Search works across <em>all</em> folders &mdash; they don&rsquo;t need to know where a document is stored</li>
  <li>Tags improve results &mdash; documents tagged by the admin will surface even when the filename alone wouldn&rsquo;t match</li>
  <li>If a document doesn&rsquo;t appear in search, it may not have been uploaded or tagged yet &mdash; contact the board</li>
</ul>

<div class="tip"><strong>Tip:</strong> Search is the single most useful feature for residents. A quick one-minute demo during a board meeting goes a long way toward reducing calls asking &ldquo;where do I find X?&rdquo; Make sure you&rsquo;ve tagged the most commonly requested documents before the demo.</div>

{divider()}

{section("Section 9 &mdash; Woolsy AI Assistant")}

<h3>What Is Woolsy?</h3>
<p>Woolsy is the AI assistant built into your building website. Residents can ask it questions about the building &mdash; parking rules, pet policy, renovation procedures, and more &mdash; and receive accurate answers drawn directly from your governing documents.</p>
<p>Woolsy&rsquo;s answers are grounded in a <strong>knowledge base</strong> specific to your building. This knowledge base is a summary of your governing documents (Articles of Incorporation, Bylaws, Declaration of Condominium, Board rules) that you set up once and update whenever your documents change.</p>

<h3>The Woolsy Card on Your Dashboard</h3>
<p>The Admin Dashboard always shows a <strong>Woolsy AI Assistant</strong> card. It tells you the current status at a glance:</p>
<table>
  <tr><th>Status</th><th>What It Means</th></tr>
  <tr><td><strong>Not set up</strong></td><td>Woolsy has no knowledge of your specific building rules yet. Click &ldquo;Set up now&rdquo; to begin.</td></tr>
  <tr><td><strong>&#10003; Up to date</strong></td><td>The knowledge base matches the documents currently in your Drive folders. No action needed.</td></tr>
  <tr><td><strong>&#9888; Files changed</strong></td><td>One or more documents have been added, modified, or removed since the knowledge base was last updated. Click &ldquo;Update&rdquo; to refresh it.</td></tr>
  <tr><td><strong>&#9888; Prompt updated &mdash; rebuild recommended</strong></td><td>SheepSite has expanded the list of topics Woolsy is trained to look for. A rebuild will re-read your existing documents and extract the new topic categories. Your documents have not changed &mdash; only the extraction logic has improved. See below.</td></tr>
</table>
<p>The card also shows a <strong>Check now</strong> button that triggers an immediate scan of your document folders, and a credit usage bar showing how much of your Woolsy budget has been used.</p>

<h3>Rebuilding After a Prompt Update</h3>
<p>Occasionally, SheepSite improves the Woolsy extraction logic &mdash; for example, adding new topic categories such as water damage responsibilities, amendment voting requirements, or sunroom rules. When this happens, your existing knowledge base was built using an older version of the extraction prompt and may be missing those topics entirely, even if the information is in your documents.</p>
<p>When the card shows <strong>&#9888; Prompt updated &mdash; rebuild recommended</strong>, click <strong>Rebuild now</strong> to regenerate the knowledge base. The process is the same as a normal update:</p>
<ul>
  <li>Woolsy re-reads all your current documents</li>
  <li>A checklist shows only the sections that are <strong>new or changed</strong> compared to what was previously saved &mdash; you do not need to review sections that are unchanged</li>
  <li>Accept or skip each proposed change, then save</li>
</ul>
<div class="tip"><strong>Note:</strong> This type of rebuild is triggered by improvements to the SheepSite platform, not by changes to your building&rsquo;s documents. It uses a small number of credits (same as a normal update). Your existing knowledge base remains in place until you save the rebuild &mdash; Woolsy continues working normally in the meantime.</div>

<h3>Credit Usage</h3>
<p>Woolsy uses AI credits each time a resident asks a question, and again when you update the knowledge base. Credits are tracked automatically:</p>
<ul>
  <li>The card shows <strong>used / allocated</strong> credits and a progress bar</li>
  <li>A warning appears when usage reaches 80% of the allocated amount</li>
  <li>At 90% usage, an invoice is automatically created and an email is sent to the building&rsquo;s contact address with a link to pay online &mdash; credits are applied immediately after payment. You can also pay by check &mdash; see <strong>Section 10 &mdash; Billing &amp; Invoices</strong>.</li>
  <li>When credits are exhausted, Woolsy displays a message to residents to contact the building administrator</li>
</ul>

<h3>Before You Begin &mdash; Upload Your Documents First</h3>
<blockquote><strong>Important:</strong> All governing documents must be uploaded to the correct folders <em>before</em> starting the setup process. Woolsy reads only what is in those folders at the time you run setup &mdash; documents added afterwards will not be included until you run an update.</blockquote>
<p>The two folders Woolsy reads from are:</p>
<ul>
  <li><code>IncorporationDocs</code> &mdash; Articles of Incorporation, Bylaws, Declaration of Condominium</li>
  <li><code>RulesDocs</code> &mdash; Board rules, Welcome guides, and any other governing rules documents</li>
</ul>
<p>Use the <strong>File Management</strong> card to upload documents to these folders if you have not already done so. Both folders are in the Public section.</p>

<h3>Setting Up Woolsy for the First Time</h3>
<ol>
  <li>From the Admin Dashboard, click <strong>Set up now</strong> in the Woolsy Knowledge Base card</li>
  <li>The page lists all documents found in <code>IncorporationDocs</code> and <code>RulesDocs</code>. The system checks each one for readability &mdash; a live counter shows progress (e.g. &ldquo;Checking documents… 3 of 7&rdquo;)</li>
  <li>A <strong>credit estimate</strong> is shown once all documents have been checked. Review it, then click <strong>Build Knowledge Base</strong> to proceed, or <strong>Cancel</strong> to stop without using any credits</li>
  <li>Woolsy reads all documents and generates the knowledge base. This may take up to a minute</li>
  <li>The results are presented as a <strong>checklist of sections</strong> &mdash; one item per topic (Pets, Parking, Guest Rules, etc.). All sections are checked by default. Uncheck any section you want to exclude, then click <strong>Save Knowledge Base</strong></li>
</ol>

<blockquote><strong>Note:</strong> Scanned PDFs (documents that were photographed or printed and scanned rather than created digitally) may not contain readable text. Any such files are flagged with a warning <strong>&#9888;</strong> during the checking step. The process continues with the remaining documents, but the flagged files will not contribute to Woolsy&rsquo;s knowledge. If you have important rules in a scanned document, you will need to convert it to a text-based PDF before Woolsy can use it. Most word processors (Microsoft Word, Google Docs) can export a text-based PDF directly &mdash; use the original editable document if you have it, or retype the content and export as PDF.</blockquote>

<h3>Updating Woolsy After a Document Change</h3>
<p>When the Woolsy card shows <strong>&#9888; Files changed</strong>, click <strong>Update</strong> to refresh the knowledge base. The process is the same as setup, with one key difference: instead of showing all sections, the checklist shows <strong>only what changed</strong> &mdash; sections marked <strong>NEW</strong>, <strong>CHANGED</strong>, or <strong>REMOVED</strong>. Sections with no changes are kept automatically and are not shown.</p>
<p>For each changed section, you can:</p>
<ul>
  <li><strong>Leave it checked</strong> &mdash; accept the change (new content replaces old, removed section is dropped, new section is added)</li>
  <li><strong>Uncheck it</strong> &mdash; reject the change (previous version is kept as-is)</li>
</ul>

<h3>Weekly Automatic Change Detection</h3>
<p>You do not need to manually check whether documents have changed. The system automatically scans your document folders once a week in the background &mdash; no AI is involved and no credits are used for this check. If changes are detected, the Woolsy card on your dashboard will show the alert the next time you log into the Admin Dashboard.</p>
<p>You can also trigger an immediate scan at any time using the <strong>Check now</strong> button on the dashboard card.</p>

<div class="tip"><strong>Tip:</strong> You only need to update Woolsy when you add new governing documents or significantly revise existing ones. Uploading board meeting minutes or financial statements to other folders does not affect Woolsy&rsquo;s knowledge and does not require an update.</div>

<h3>Editing the Building FAQ &mdash; Correcting or Supplementing Woolsy&rsquo;s Answers</h3>
<p>The knowledge base built from your governing documents is comprehensive, but there are things it cannot do on its own:</p>
<ul>
  <li>Board member names and contact details change after elections &mdash; Woolsy does not pull these from your documents</li>
  <li>Exact URLs for specific pages on your building website (such as the Board of Directors report) cannot be inferred from document text</li>
  <li>Policies that exist in practice but are not written in any governing document (e.g. &ldquo;contact the property manager at X for maintenance requests&rdquo;)</li>
  <li>Any answer you want Woolsy to give verbatim, without interpretation</li>
</ul>
<p>For all of these, use the <strong>Edit Building FAQ</strong> editor, which is built directly into the Woolsy Knowledge Base card on your Admin Dashboard.</p>

<h4>How to use it</h4>
<ol>
  <li>Log in to the Admin Dashboard and scroll to the <strong>Woolsy Knowledge Base</strong> card</li>
  <li>Click <strong>&#9998; Edit Building FAQ</strong> at the bottom of the card &mdash; a text editor expands inline</li>
  <li>Type or paste your content. Use plain text, organized by topic heading, for example:
<pre>Board of Directors
The current Board of Directors list is available on the website&apos;s Board of Directors page at:
https://sheepsite.com/Scripts/public-report.php?building=LyndhurstH&amp;page=board

Property Manager
For maintenance requests, contact ABC Management at 555-123-4567 or manager@abcmgmt.com.</pre>
  </li>
  <li>Click <strong>Save</strong> &mdash; changes take effect immediately on the next resident question. No rebuild required.</li>
</ol>

<div class="tip"><strong>How it works:</strong> The Building FAQ text is injected directly into Woolsy&rsquo;s context alongside the knowledge base on every request. When a resident asks about board members or anything you have added here, Woolsy uses your text as the authoritative answer. Because Woolsy reads this on every question, edits are live the moment you save &mdash; there is no need to rebuild or wait.</div>

<div class="warn"><strong>Important &mdash; Board member information:</strong> Woolsy intentionally does not extract specific board member names, unit numbers, or phone numbers from governing documents, because this information changes with elections and would quickly become outdated. Always provide current board contact information through the Building FAQ editor instead, or direct residents to the live Board of Directors page on your website.</div>

{divider()}

{section("Section 10 &mdash; Billing &amp; Invoices")}

<h3>Overview</h3>
<p>Your Admin Dashboard includes a <strong>Billing</strong> section that shows your invoice history. Invoices are created automatically when a service limit is reached (storage or Woolsy credits) or when your annual subscription is due for renewal. You can view each invoice by clicking its ID, and pay online via the <strong>Pay &rarr;</strong> link if available.</p>

<h3>Types of Invoices</h3>
<ul>
  <li><strong>Annual renewal</strong> &mdash; covers your yearly subscription. Generated about 30 days before your renewal date.</li>
  <li><strong>Storage upgrade</strong> &mdash; created when an upload is blocked because your storage is full. The invoice is for the next available storage tier.</li>
  <li><strong>Woolsy credits</strong> &mdash; created when Woolsy credits reach 90% usage. The invoice covers a credit top-up to keep Woolsy available to residents.</li>
</ul>

<h3>Paying Online</h3>
<p>When a threshold invoice is triggered (storage or Woolsy), an email is sent to your building&rsquo;s contact address with a secure payment link. Click <strong>Pay &rarr;</strong> in that email or in the Billing section of your dashboard to pay by credit card via Stripe. The service is updated immediately after payment &mdash; storage limit raised or credits added.</p>

<h3>Paying by Check</h3>
<p>If you prefer to pay by check, send the payment to SheepSite and notify your account manager. The operator will mark the invoice as paid in the system, which applies the same service changes (storage upgrade, credits added, or renewal date advanced) and sends you a receipt by email.</p>

<h3>Invoice History</h3>
<p>The Billing section of your Admin Dashboard shows all invoices for your building &mdash; both paid and unpaid. Each row shows the invoice ID (click to view the full invoice), date, amount, and status. Paid invoices show the payment date.</p>

{divider()}

{section("Appendix &mdash; Sample Introduction Email")}

<div style="display:flex; gap:1.5em; align-items:flex-start;">
  <div style="flex:2;">
    <p>Use this template when introducing residents to the new website. Customize as needed.</p>
    <blockquote>
      <p>Subject: Your Building Website Is Now Live</p>
      <p>Dear Residents,</p>
      <p>In compliance with the latest condominium requirements in the state of Florida, [BUILDING NAME] now has a website with all of the required information and resources.</p>
      <p>You can visit the site at: <strong>[WEBSITE URL]</strong></p>
      <p><strong>Public resources</strong> (no login required) include building rules, forms, incorporation documents, the board of directors list, and general announcements.</p>
      <p><strong>Private resources</strong> (login required) include board meeting minutes, financial statements, budgets, contracts, the resident directory, and the parking list.</p>
      <p>Your login credentials are:<br>
      Username: <strong>[first initial + last name, e.g. jsmith]</strong><br>
      Temporary password: <strong>[TEMP PASSWORD]</strong></p>
      <p>You will be asked to choose a new password the first time you log in.</p>
      <p>Once logged in, use the <strong>Search</strong> feature to quickly find any document &mdash; just type a keyword and the site will locate it for you.</p>
      <p>If you have trouble logging in or need your password reset, click &ldquo;Forgot password?&rdquo; on the login page.</p>
      <p>Please contact [BOARD CONTACT] if you have any questions.</p>
      <p>Regards,<br>The [BUILDING NAME] Board of Directors</p>
    </blockquote>
  </div>
  <div style="flex:1; text-align:center;">
    {img_tag("image18.png", "Sheep at computer", "100%")}
  </div>
</div>

</body>
</html>
"""

out = os.path.join(os.path.dirname(__file__), "Sheepsite-Admin-Manual.html")
with open(out, "w", encoding="utf-8") as f:
    f.write(html)

print(f"Written to {out}")
size_kb = os.path.getsize(out) // 1024
print(f"File size: {size_kb} KB")
