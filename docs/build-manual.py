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
<p class="version">Version 2.2 &nbsp;&middot;&nbsp; April 2026</p>

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

<blockquote>
  <strong>Forgot your password?</strong> Click the <strong>Forgot password?</strong> link on the admin login page. You will be asked for two things: your <strong>admin username</strong> and a <strong>Secret #</strong> known to authorized board members. Both must match what is on file &mdash; if either is wrong, no hint is given. If they match, a temporary password is emailed immediately to the email address registered to your admin account. Log in with it and you will be prompted to choose a new password right away.<br><br>
  <em>Important:</em> this works only if your admin account has an email address on file. If you are not sure, ask another admin to check your account in the <strong>Admin Accounts</strong> section of the Settings tab and add your email if it is missing.
</blockquote>

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
  <tr><td><strong>Storage Report</strong></td><td>View how much storage your building is using, broken down by folder.</td></tr>
  <tr><td><strong>Woolsy AI Assistant</strong></td><td>Set up or update the AI assistant&rsquo;s knowledge of your building&rsquo;s governing documents. Shows current status and credit usage.</td></tr>
  <tr><td><strong>Billing &amp; Invoices</strong></td><td>View all invoices for your building &mdash; renewal, storage upgrades, and Woolsy credits. Pay open invoices online via the <strong>Pay &rarr;</strong> link. See Section 10 for full details.</td></tr>
  <tr><td><strong>User Manual</strong></td><td>Opens this manual.</td></tr>
</table>

<h3>Settings Tab</h3>
<p>Click <strong>Settings</strong> at the top to switch to the Settings tab, which contains building configuration and admin account management:</p>
<table>
  <tr><th>Section</th><th>What It Does</th></tr>
  <tr><td><strong>Website Settings</strong></td><td>Configure the association display name, branding, social and calendar links, property management company details, and billing contact email. See below for a full description of each field.</td></tr>
  <tr><td><strong>License Agreement</strong></td><td>Shows the version and effective date of the Terms of Service you have accepted, along with who accepted it and when. Includes a <strong>View Terms of Service &rarr;</strong> link to open the full document at any time.</td></tr>
  <tr><td><strong>Admin Accounts</strong></td><td>View and manage all administrator accounts for this building. Add new admins, update email addresses for notifications, or remove accounts. Each admin has a separate username, password, and email address.</td></tr>
  <tr><td><strong>Change Admin Password</strong></td><td>Change your own admin password. Enter your current password, then your new password twice.</td></tr>
</table>

<h3>Need Help? &mdash; Ask Woolsy</h3>
<p>A <strong>Need Help?</strong> button is always visible in the bottom-right corner of every admin page. Click it to open an inline chat with Woolsy, who can answer questions about using the admin panel &mdash; how to add a resident, reset a password, manage files, handle billing, and anything else covered in this manual. Woolsy credit usage applies.</p>

<h3>Website Settings</h3>
<p>The <strong>Website Settings</strong> card in the Settings tab lets you control how your building&rsquo;s website presents itself to residents and what optional integrations are active. Each field is described below.</p>

<table>
  <tr><th>Field</th><th>What It Does</th></tr>
  <tr>
    <td><strong>Association display name</strong></td>
    <td>The full name of your association as it appears in welcome emails, password reset messages, and other system-generated communications sent to residents (e.g. &ldquo;Lyndhurst H Condominium&rdquo;).</td>
  </tr>
  <tr>
    <td><strong>Building website URL</strong></td>
    <td>The address of your building&rsquo;s public website. Shown here for reference &mdash; this is set by SheepSite and cannot be changed from this page.</td>
  </tr>
  <tr>
    <td><strong>Header image filename</strong></td>
    <td>The filename of the banner image displayed at the top of your site (e.g. <code>LyndhurstH-header.jpg</code>). The image must be uploaded to the <code>Scripts/assets/</code> folder on the server by SheepSite. Contact SheepSite to update the header image.</td>
  </tr>
  <tr>
    <td><strong>Google Calendar URL</strong></td>
    <td>Embeds or links a Google Calendar on your website so residents can see upcoming building events. See the note below for how to obtain the correct URL.</td>
  </tr>
  <tr>
    <td><strong>Facebook URL</strong></td>
    <td>A link to your building&rsquo;s Facebook group or page. When set, a Facebook button appears on your site so residents can easily find the group.</td>
  </tr>
  <tr>
    <td><strong>Billing contact email</strong></td>
    <td>The email address that receives SheepSite invoices and payment notifications. This should be a board email address that is actively monitored. It does <em>not</em> receive resident change request notifications &mdash; those go to individual admin account email addresses.</td>
  </tr>
  <tr>
    <td><strong>Property Management Company</strong></td>
    <td>Name, phone number, portal URL, and portal button label for your property management company (e.g. Seacrest / Vantaca). When filled in, a button linking to the management portal appears on your site for residents. Leave blank if not applicable.</td>
  </tr>
</table>

<div class="tip">
  <strong>Google Calendar — using the correct URL:</strong> To embed or link your Google Calendar without requiring residents to log in, you must use the <strong>Public URL</strong>, not the shareable link.<br><br>
  In Google Calendar, open <strong>Settings &rarr; Settings for my calendars</strong>, select your calendar, then scroll down to <strong>Integrate calendar</strong>. Copy the <strong>Public URL to this calendar</strong> field and paste it here.<br><br>
  <strong>Note:</strong> The <em>Get shareable link</em> option available near the top of calendar settings is designed for sharing with specific Google users and will require residents to log in to their Google account before they can view the calendar. Always use the <strong>Public URL</strong> from the Integrate calendar section instead.
</div>

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
  <tr><td><strong>Residents</strong></td><td>Each person in the unit &mdash; name, status (Owner/Resident/Full Time/Renter), email, phones</td></tr>
  <tr><td><strong>Unit Info</strong></td><td>Insurance company and policy number, AC replacement date, water tank replacement date, unit notes</td></tr>
  <tr><td><strong>Vehicle &amp; Parking</strong></td><td>Car make/model/color, license plate, parking spot, notes</td></tr>
  <tr><td><strong>Emergency</strong></td><td>Emergency contacts and condo sitters &mdash; name, email, phones</td></tr>
</table>

<h3>Resident Status Flags</h3>
<p>Each person in the database carries one or more status flags that describe their role in the unit. These flags control how the system treats them.</p>
<table>
  <tr><th>Status</th><th>Meaning</th></tr>
  <tr><td><strong>Owner</strong></td><td>Unit owner of record</td></tr>
  <tr><td><strong>Resident</strong></td><td>Lives in the unit (may or may not be the owner)</td></tr>
  <tr><td><strong>Full Time</strong></td><td>Full-time occupant (as opposed to seasonal)</td></tr>
  <tr><td><strong>Renter</strong></td><td>Tenant renting the unit &mdash; see important note below</td></tr>
</table>
<p>Owner, Resident, and Full Time can be combined freely. <strong>Renter is mutually exclusive</strong> &mdash; selecting it automatically clears the other three, and the others are disabled while Renter is checked.</p>

<div class="tip"><strong>Renters &mdash; what changes and what does not</strong><br><br>
A renter is a full resident of the building for record-keeping purposes:
<ul>
  <li>They appear on <strong>all resident lists</strong> and reports</li>
  <li>Their email address is included in the <strong>community-wide email list</strong></li>
  <li>Their vehicle, emergency contact, and unit info are tracked the same as any other resident</li>
</ul>
The <em>only</em> difference is access to the building website portal:
<ul>
  <li>No web login account is created for a renter, even if an email address is on file</li>
  <li>Renters cannot log in to view private documents or the resident directory</li>
  <li>Sync (in Manage User Accounts) skips renters and reports a count rather than flagging them as missing accounts</li>
</ul>
This reflects Florida condo law: the password-protected website section is an owner entitlement. Renters who need access to building documents should contact the unit owner or the board directly.
</div>

<h3>Adding a Resident</h3>
<ol>
  <li>Click the unit to expand it, then click <strong>+ Add Resident</strong></li>
  <li>Fill in at minimum First Name and Last Name &mdash; all other fields are optional</li>
  <li>Set the appropriate <strong>Status</strong> flags. If the person is a tenant, check <strong>Renter</strong> &mdash; this will clear and disable the other status checkboxes automatically</li>
  <li>If the person is <em>not</em> a renter and you provide an email address, a web login is created automatically and a welcome email with a temporary password is sent to them</li>
  <li>If the person is a renter, only the database record is created regardless of whether an email is provided</li>
  <li>If no email is provided for a non-renter, only the database record is created. You can add the email later &mdash; the login will be created at that point</li>
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

<h3>How Residents Manage Their Own Information</h3>
<p>Residents can update certain information themselves through <strong>My Account</strong> on the building website &mdash; no need to contact the board for routine changes. Once logged in, a resident can:</p>
<ul>
  <li>View all details on file for their unit (residents, vehicle &amp; parking, emergency contacts)</li>
  <li>Submit a change request for any field &mdash; name, email, phone, vehicle, parking spot, etc.</li>
  <li>Request to add a new resident to their unit</li>
</ul>
<p>Change requests land in your <strong>Pending Queue</strong> (see above) and do not take effect until you review and accept them. The resident is notified by email either way.</p>
<p><strong>Adding a resident:</strong> When a resident submits an &ldquo;Add Resident&rdquo; request, it appears in the Pending Queue with the submitted details. Clicking <strong>Accept</strong> creates the database record, generates a web login, and sends the new resident a welcome email with a temporary password &mdash; all in one step.</p>
<p><strong>Removing a resident:</strong> Resident removals are handled by the admin only &mdash; residents cannot remove themselves or others. Expand the unit in <strong>Manage Residents/Owners</strong>, click <strong>Edit</strong> on the person card, and use <strong>Delete</strong> to remove the record and revoke their web login at the same time.</p>

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
<p>Sync also reports how many <strong>renters</strong> were found in the database and skipped. This is normal &mdash; renters are intentionally excluded from web account creation and are never listed as missing accounts.</p>

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
    <p>The Storage Report shows how much storage your building is using across all files. Access it from the Admin Dashboard.</p>
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
<p>When storage fills up, SheepSite will contact you to discuss a storage upgrade. Storage is available in upgrade tiers &mdash; you choose the size that works for your building. The new limit takes effect once the upgrade is arranged. See <strong>Section 10 &mdash; Billing &amp; Invoices</strong> for details.</p>
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
  <li>At 90% usage, SheepSite will contact you to arrange a credit top-up &mdash; credits are applied immediately after payment. You can also pay by check &mdash; see <strong>Section 10 &mdash; Billing &amp; Invoices</strong>.</li>
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
<p>Your Admin Dashboard includes a <strong>Billing</strong> section that shows your invoice history. Invoices are issued for storage upgrades, Woolsy credit top-ups, and annual subscription renewals. You can view each invoice by clicking its ID, and pay online via the <strong>Pay &rarr;</strong> link if available.</p>

<h3>Types of Invoices</h3>
<ul>
  <li><strong>Annual renewal</strong> &mdash; covers your yearly subscription. SheepSite will reach out when your renewal is approaching.</li>
  <li><strong>Storage upgrade</strong> &mdash; issued when a storage tier upgrade is arranged after your storage is full.</li>
  <li><strong>Woolsy credits</strong> &mdash; issued when Woolsy credits reach 90% usage. The invoice covers a credit top-up to keep Woolsy available to residents.</li>
</ul>

<h3>Paying Online</h3>
<p>When an invoice is ready, an email is sent to your building&rsquo;s contact address with a secure payment link. Click <strong>Pay &rarr;</strong> in that email or in the Billing section of your dashboard to pay by credit card. Online payments are processed through <strong>Stripe</strong> &mdash; a leading payment platform trusted by millions of businesses worldwide. SheepSite never sees or stores your card details; they go directly to Stripe&rsquo;s secure servers. The service is updated immediately after payment &mdash; storage limit raised or credits added.</p>

<h3>Paying by Check</h3>
<p>If you prefer to pay by check, send the payment to SheepSite and notify your account manager. The operator will mark the invoice as paid in the system, which applies the same service changes (storage upgrade, credits added, or renewal date advanced) and sends you a receipt by email.</p>

<h3>Invoice History</h3>
<p>The Billing section of your Admin Dashboard shows all invoices for your building &mdash; both paid and unpaid. Each row shows the invoice ID (click to view the full invoice), date, amount, and status. Paid invoices show the payment date.</p>

{divider()}

{section("Appendix &mdash; Sample Introduction Email")}

<div style="display:flex; gap:1.5em; align-items:flex-start;">
  <div style="flex:2;">
    <p>Use the template below to announce the site to your residents. Copy it into your email client, fill in the bracketed placeholders, and send it to all unit owners. Customize the tone as you see fit &mdash; the goal is to get residents excited about what&rsquo;s available to them.</p>
    <blockquote>
      <p><strong>Subject:</strong> Welcome to Your New Building Website</p>
      <p>Dear [BUILDING NAME] Residents,</p>
      <p>We&rsquo;re pleased to announce that your building now has a modern, easy-to-use website designed to keep you informed and connected. You can access it any time at:</p>
      <p style="text-align:center;"><strong>[WEBSITE URL]</strong></p>
      <p><strong>No login needed</strong> for general information &mdash; building rules, forms, board of directors, announcements, and more are available to anyone who visits the site.</p>
      <p><strong>Log in as a resident</strong> to unlock the full experience:</p>
      <ul>
        <li><strong>Private documents</strong> &mdash; board meeting minutes, financial statements, budgets, contracts, and other member-only files</li>
        <li><strong>Resident directory</strong> &mdash; contact information for your neighbors (owners only)</li>
        <li><strong>Document search</strong> &mdash; type any keyword and instantly find it across all building documents</li>
        <li><strong>Woolsy AI Assistant</strong> &mdash; ask questions about building rules, policies, or documents and get answers in plain language, no digging required</li>
        <li><strong>My Account</strong> &mdash; view your unit details and submit updates (vehicle changes, contact info, etc.) directly to the board</li>
      </ul>
      <p>You should have received a separate welcome email with your username and a temporary password. Check your inbox (and spam folder) for a message from SheepSite. Log in with those credentials &mdash; you will be prompted to set a permanent password the first time.</p>
      <p>If you cannot find the welcome email, go to the site, click <strong>Forgot password?</strong> on the login page, and enter your username (typically first initial + last name, e.g. <em>jsmith</em>). A new temporary password will be sent to your email address on file &mdash; no need to contact the board.</p>
      <p>Questions? Reach out to [BOARD CONTACT] and we&rsquo;ll be happy to help.</p>
      <p>Regards,<br>The [BUILDING NAME] Board of Directors</p>
    </blockquote>
  </div>
  <div style="flex:1; text-align:center;">
    {img_tag(WOOLSY_WORKING, "Woolsy", "100%")}
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
