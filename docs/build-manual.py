#!/usr/bin/env python3
"""
Builds Manual-admin.html with base64-embedded images.
Run from the docs/ folder: python3 build-manual.py
"""

import base64, os, re

IMG_DIR = "../Sharefolder/SheepSite User Manual/images"

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

def divider():
    return f'''
<div style="text-align:center; margin: 2em 0;">
  {img_tag("image2.jpg", "SheepSite", "80px")}
</div>
<hr>
'''

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
  h2 {{ font-size: 1.2em; margin-top: 2.5em; background: #333; color: #fff; padding: 6px 12px; border-radius: 3px; }}
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

<h1>SheepSite Admin Manual</h1>
<p class="subtitle">For Board Members Managing the Building Website</p>
<p class="version">Version 2.0 &nbsp;&middot;&nbsp; March 2026</p>

<div class="toc">
  <h3>Contents</h3>
  <ol>
    <li>Getting Started</li>
    <li>The Admin Dashboard</li>
    <li>Managing User Accounts</li>
    <li>Managing Documents</li>
    <li>Tag Management</li>
    <li>Storage Report</li>
    <li>The Owner &amp; Resident Database</li>
    <li>Search &mdash; Training Your Residents</li>
    <li>Woolsy Knowledge Base</li>
    <li>Appendix: Sample Introduction Email</li>
  </ol>
</div>

{divider()}

<h2>Section 1 &mdash; Getting Started</h2>

<div style="display:flex; gap:1.5em; align-items:flex-start; margin: 1em 0;">
  <div style="flex:1;">
    {img_tag("image16.jpg", "Get Started", "100%", "border-radius:6px;")}
  </div>
  <div style="flex:2;">
    <h3 style="margin-top:0;">Who This Manual Is For</h3>
    <p>This manual is for board members responsible for administering the building website &mdash; managing documents, adding or removing owner accounts, and keeping the site content current.</p>
    <p>For a guide on how residents use the site, see the separate <strong>Resident User Manual</strong>.</p>
  </div>
</div>

<h3>How the System Works</h3>
<p>The diagram below shows the overall architecture. Documents stored in Google Drive flow automatically to the public-facing website. The owner database feeds dynamic reports. All private content sits behind a security layer requiring individual owner logins.</p>

{img_tag("image4.jpg", "System architecture diagram", "100%", "border-radius:4px; border:1px solid #ccc; margin: 0.8em 0;")}

<h3>The Website</h3>
{img_tag("image9.png", "Website home page", "100%", "border:1px solid #ccc; border-radius:4px; margin:0.5em 0;")}

<h3>What You Need</h3>
<ul>
  <li>A computer or tablet with a modern browser (Google Chrome recommended)</li>
  <li>Your admin username and password (provided by SheepSite during setup)</li>
  <li>Access to the building&rsquo;s Google Drive (required for maintaining the owner and resident database)</li>
</ul>

<h3>Accessing the Admin Area</h3>
<p>The admin area is accessed directly from the building website. In the site menu, go to <strong>Resources Private &rarr; Admin</strong>. Log in with your admin credentials. If this is your first login, you will be prompted to set a permanent password.</p>

<blockquote><strong>Forgot your password?</strong> Click the &ldquo;Forgot password?&rdquo; link on the login page. The system will email a temporary password to the President. Enter the <strong>President&rsquo;s unit number</strong> as the security verification.</blockquote>

{divider()}

<h2>Section 2 &mdash; The Admin Dashboard</h2>

<p>After logging in, you will see the Admin Dashboard with the following options:</p>

<table>
  <tr><th>Card</th><th>What It Does</th></tr>
  <tr><td><strong>Manage Users</strong></td><td>Add, remove, and manage owner login accounts. Import owners from the database sheet.</td></tr>
  <tr><td><strong>File Management</strong></td><td>Upload, organize, rename, and delete documents in the public and private folders.</td></tr>
  <tr><td><strong>Tag Management</strong></td><td>Add and manage tags on documents to improve search and organization.</td></tr>
  <tr><td><strong>Storage Report</strong></td><td>View how much Google Drive storage your building is using, broken down by folder.</td></tr>
  <tr><td><strong>Woolsy Knowledge Base</strong></td><td>Set up or update the AI assistant&rsquo;s knowledge of your building&rsquo;s governing documents. Shows current status and credit usage.</td></tr>
  <tr><td><strong>User Manual</strong></td><td>Opens this manual.</td></tr>
</table>

{divider()}

<h2>Section 3 &mdash; Managing User Accounts</h2>

<div style="display:flex; gap:1.5em; align-items:flex-start; margin: 1em 0 1.5em 0;">
  <div style="flex:2;">
    <h3 style="margin-top:0;">Overview</h3>
    <p>Each unit owner gets their own individual login account for the private Resource Center. Accounts are tied to a person, not a unit &mdash; when an owner moves in or out, you add or remove their account accordingly.</p>
  </div>
  <div style="flex:1;">
    {img_tag("image7.jpg", "System", "100%", "border-radius:6px;")}
  </div>
</div>

<h3>Importing &amp; Syncing from the Database Sheet</h3>
<p>The fastest way to set up accounts for all owners at once is the Import / Sync function.</p>
<ol>
  <li>From the Admin Dashboard, click <strong>Manage Users</strong></li>
  <li>Enter a temporary password in the <strong>Import / Sync from Association Database Sheet</strong> section</li>
  <li>Click <strong>Import / Sync</strong></li>
</ol>
<p>The system reads the <code>Database</code> tab from your building&rsquo;s Google Sheet and creates an account for every owner. Usernames are generated automatically as first initial + last name (e.g. Jane Smith &rarr; <code>jsmith</code>). Duplicate names get a number suffix (<code>jsmith2</code>, etc.).</p>

<div class="tip"><strong>Tip:</strong> Import / Sync is safe to run more than once &mdash; existing accounts are never overwritten. Only new owners (not already in the system) get accounts created. After each sync, you will be shown a list of accounts that no longer appear in the database, and given the option to remove them.</div>

<blockquote><strong>Important:</strong> All imported accounts are flagged as &ldquo;first login&rdquo; &mdash; owners are required to change the temporary password the first time they log in. They cannot reuse the temporary password.</blockquote>

<h3>Adding or Resetting a Single Owner</h3>
<ol>
  <li>From Manage Users, fill in the <strong>Add / Reset Resident</strong> form</li>
  <li>Enter the username, a temporary password, and click <strong>Add / Reset</strong></li>
  <li>If the username matches a resident in the database who has an email address, the system automatically emails them the temporary password and requires them to change it on first login</li>
  <li>If no match is found in the database, no email is sent &mdash; distribute the password manually</li>
</ol>

<h3>Removing an Owner</h3>
<p>Find the owner in the user list and click <strong>Delete</strong>. This immediately revokes their access. Do this when an owner sells their unit.</p>

<h3>Owners Resetting Their Own Password</h3>
<p>Owners can reset their own password without contacting you. On the login page, they click <strong>Forgot password?</strong> The system emails them a temporary password and they are prompted to change it on login.</p>

{divider()}

<h2>Section 4 &mdash; Managing Documents</h2>

<h3>How Publishing Works</h3>
<p>All documents live in Google Drive. Files in the Public and Private folders <strong>automatically appear on the website</strong> &mdash; there is no publish button or sync step required.</p>
<ul>
  <li><strong>Public folder</strong> &mdash; visible to anyone visiting the website, no login needed</li>
  <li><strong>Private folder</strong> &mdash; visible only to logged-in owners</li>
</ul>

<div style="display:flex; gap:1em; margin:1em 0;">
  <div style="flex:1; text-align:center;">
    {img_tag("image15.png", "Resource Center public page", "100%", "border:1px solid #ccc; border-radius:4px;")}
    <p style="font-size:0.85em; color:#555; margin-top:4px;">Public Resource Center</p>
  </div>
  <div style="flex:1; text-align:center;">
    {img_tag("image19.png", "Association Documents private page", "100%", "border:1px solid #ccc; border-radius:4px;")}
    <p style="font-size:0.85em; color:#555; margin-top:4px;">Private Association Documents</p>
  </div>
</div>

<h3>The File Manager (Primary Method)</h3>
<p>The built-in file manager is the easiest way to manage documents without leaving the website. Access it from the Admin Dashboard via the <strong>File Management</strong> card, or directly from any document browser page on your site.</p>

<h4>Uploading Files</h4>
<ul>
  <li><strong>Drag and drop</strong> &mdash; drag one or more files from your computer directly onto the upload area</li>
  <li><strong>Browse</strong> &mdash; click the upload area to open a file picker and select files manually</li>
  <li><strong>Multiple files at once</strong> &mdash; both methods support selecting several files in a single operation</li>
  <li><strong>Replace prompt</strong> &mdash; if a file with the same name already exists in the folder, you will be asked whether you want to replace it before the upload proceeds. Any tags assigned to the original file are automatically carried over to the new version &mdash; you do not need to re-tag it.</li>
</ul>

<h4>Organizing Files</h4>
<ul>
  <li><strong>Create folders</strong> &mdash; keep documents organized by year, type, or topic</li>
  <li><strong>Rename files and folders</strong> &mdash; click the rename option next to any item</li>
  <li><strong>Delete files and folders</strong> &mdash; click the delete option; you will be asked to confirm</li>
</ul>
<p>All changes are reflected on the website immediately.</p>

<h3>Google Drive (Alternative Method)</h3>
<p>You can also manage files directly in Google Drive. Navigate to the shared building folder under <strong>Shared with me</strong> and add, move, or delete files there.</p>

<div style="display:flex; gap:1em; margin:1em 0;">
  <div style="flex:1; text-align:center;">
    {img_tag("image13.jpg", "Google Drive Shared with me", "100%", "border:1px solid #ccc; border-radius:4px;")}
    <p style="font-size:0.85em; color:#555; margin-top:4px;">Find your building under Shared with me</p>
  </div>
  <div style="flex:1; text-align:center;">
    {img_tag("image11.png", "Building folder in Drive", "100%", "border:1px solid #ccc; border-radius:4px;")}
    <p style="font-size:0.85em; color:#555; margin-top:4px;">Open the building folder</p>
  </div>
</div>

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
  <tr><th>Website Section</th><th>Google Drive Folder</th></tr>
  <tr><td>Home Page / Latest News</td><td><code>Page1Docs/Announcement</code></td></tr>
  <tr><td>Mid/End Year Report</td><td><code>Page1Docs/Mid-End_Year_Report</code></td></tr>
  <tr><td>Building Guides &amp; Rules</td><td><code>RulesDocs</code></td></tr>
  <tr><td>Forms &amp; Applications</td><td><code>Forms</code></td></tr>
  <tr><td>Other Documents</td><td><code>OtherDocs</code></td></tr>
  <tr><td>Incorporation Documents</td><td><code>IncorporationDocs</code></td></tr>
</table>

<h4>Private Folders (login required)</h4>
<table>
  <tr><th>Website Section</th><th>Google Drive Folder</th></tr>
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

<blockquote><strong>Note:</strong> Deleting a file removes it from Google Drive as well. Make sure you have a local copy of anything you may need in the future.</blockquote>

{divider()}

<h2>Section 5 &mdash; Tag Management</h2>

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

<h2>Section 6 &mdash; Storage Report</h2>

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

{divider()}

<h2>Section 7 &mdash; The Owner &amp; Resident Database</h2>

<h3>Overview</h3>
<p>The building&rsquo;s owner and resident data lives in a Google Sheet named <strong>&ldquo;[Building Name] Owner DB&rdquo;</strong>. This sheet feeds the website&rsquo;s resident reports and is the source used when importing owner accounts.</p>

<blockquote><strong>Important:</strong> Never modify the structure of the sheet &mdash; do not add, remove, or rename columns. Only enter data in the existing fields.</blockquote>

<p>Access the sheet from Google Drive under <strong>Shared with me &rarr; [Building] &rarr; Databases</strong>:</p>
{img_tag("image14.png", "Databases folder in Google Drive", "100%", "border:1px solid #ccc; border-radius:4px; margin:0.5em 0;")}

<h3>The Main Tab</h3>
<p>Enter all owner and tenant information here. Each row represents one unit. This tab is the source for the Resident List report, the owner import function, the Board of Directors list, and password reset emails.</p>
{img_tag("image17.png", "Main tab of Owner DB", "100%", "border:1px solid #ccc; border-radius:4px; margin:0.5em 0;")}

<h3>The Emergency &amp; Condo Sitter Tab</h3>
<p>Records emergency contacts, family members, and condo sitters. Select unit numbers from the dropdown menus populated from the Main tab.</p>
{img_tag("image8.png", "Emergency and Condo Sitter tab", "100%", "border:1px solid #ccc; border-radius:4px; margin:0.5em 0;")}

<h3>Automated Reports</h3>
<p>Reports update automatically within about a minute of any data change. No action is needed.</p>
<table>
  <tr><th>Report</th><th>Access</th><th>Source Tab</th></tr>
  <tr><td>Resident List</td><td>Private (owners only)</td><td>Database</td></tr>
  <tr><td>Elevator List</td><td>Private (owners only)</td><td>Database</td></tr>
  <tr><td>Parking List</td><td>Private (owners only)</td><td>CarDB</td></tr>
  <tr><td>Board of Directors</td><td>Public</td><td>Database</td></tr>
</table>

<h3>The Reports Tab</h3>
<p>The Reports tab provides a display-only view for looking up emergency contacts and resident information directly in the sheet.</p>
{img_tag("image5.png", "Reports tab", "100%", "border:1px solid #ccc; border-radius:4px; margin:0.5em 0;")}

{divider()}

<h2>Section 8 &mdash; Search: Training Your Residents</h2>

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

<h2>Section 9 &mdash; Woolsy Knowledge Base</h2>

<h3>What Is Woolsy?</h3>
<p>Woolsy is the AI assistant built into your building website. Residents can ask it questions about the building &mdash; parking rules, pet policy, renovation procedures, and more &mdash; and receive accurate answers drawn directly from your governing documents.</p>
<p>Woolsy&rsquo;s answers are grounded in a <strong>knowledge base</strong> specific to your building. This knowledge base is a summary of your governing documents (Articles of Incorporation, Bylaws, Declaration of Condominium, Board rules) that you set up once and update whenever your documents change.</p>

<h3>The Woolsy Card on Your Dashboard</h3>
<p>The Admin Dashboard always shows a <strong>Woolsy Knowledge Base</strong> card. It tells you the current status at a glance:</p>
<table>
  <tr><th>Status</th><th>What It Means</th></tr>
  <tr><td><strong>Not set up</strong></td><td>Woolsy has no knowledge of your specific building rules yet. Click &ldquo;Set up now&rdquo; to begin.</td></tr>
  <tr><td><strong>&#10003; Up to date</strong></td><td>The knowledge base matches the documents currently in your Drive folders. No action needed.</td></tr>
  <tr><td><strong>&#9888; Files changed</strong></td><td>One or more documents have been added, modified, or removed since the knowledge base was last updated. Click &ldquo;Update&rdquo; to refresh it.</td></tr>
</table>
<p>The card also shows a <strong>Check now</strong> button that triggers an immediate scan of your document folders, and a credit usage bar showing how much of your Woolsy budget has been used.</p>

<h3>Credit Usage</h3>
<p>Woolsy uses AI credits each time a resident asks a question, and again when you update the knowledge base. Credits are tracked automatically:</p>
<ul>
  <li>The card shows <strong>used / allocated</strong> credits and a progress bar</li>
  <li>A warning appears when usage reaches 80% of the allocated amount</li>
  <li>When credits are exhausted, Woolsy displays a message to residents to contact the building administrator. Contact SheepSite to top up credits.</li>
</ul>

<h3>Before You Begin &mdash; Upload Your Documents First</h3>
<blockquote><strong>Important:</strong> All governing documents must be uploaded to the correct Drive folders <em>before</em> starting the setup process. Woolsy reads only what is in those folders at the time you run setup &mdash; documents added afterwards will not be included until you run an update.</blockquote>
<p>The two folders Woolsy reads from are:</p>
<ul>
  <li><code>IncorporationDocs</code> &mdash; Articles of Incorporation, Bylaws, Declaration of Condominium</li>
  <li><code>RulesDocs</code> &mdash; Board rules, Welcome guides, and any other governing rules documents</li>
</ul>
<p>Use the <strong>File Management</strong> card to upload documents to these folders if you have not already done so. Both folders are in the Public section of your Drive.</p>

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

{divider()}

<h2>Appendix &mdash; Sample Introduction Email</h2>

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
