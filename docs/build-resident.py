#!/usr/bin/env python3
"""
Builds Sheepsite-Resident-Manual.html with base64-embedded images.
Run from the docs/ folder: python3 build-resident.py
"""

import base64, os

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

def woolsy_logo(height=22):
    return img_tag(WOOLSY_LOGO, "Woolsy", width="", style=f"height:{height}px; vertical-align:middle; display:inline;")

def woolsy_section_icon():
    img = img_tag(WOOLSY_WORKING, "Woolsy", width="", style="height:34px; display:block;")
    return f'<span style="background:#fff; border-radius:4px; padding:2px 4px; display:inline-flex; align-items:center; margin-right:10px; flex-shrink:0;">{img}</span>'

def divider():
    return ""

def section(title):
    return f'<h2>{woolsy_section_icon()}{title}</h2>'

html = f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body {{
    font-family: Georgia, serif;
    max-width: 720px;
    margin: 40px auto;
    color: #222;
    line-height: 1.7;
    font-size: 15px;
  }}
  h1 {{ font-size: 1.8em; margin-bottom: 0.1em; }}
  .subtitle {{ font-size: 1.1em; color: #555; margin-top: 0; }}
  .version {{ font-size: 0.9em; color: #888; margin-top: 0.3em; }}
  h2 {{ font-size: 1.2em; margin-top: 2.5em; background: #2a5a8a; color: #fff; padding: 6px 12px; border-radius: 3px; display: flex; align-items: center; }}
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
    border-left: 4px solid #2a5a8a;
    margin: 1em 0;
    padding: 0.5em 1em;
    background: #f0f5fb;
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
</style>
</head>
<body>

<div style="text-align:center; margin-bottom: 1.5em;">
  {img_tag(WOOLSY_LOGO, "Woolsy", width="", style="height:90px;")}
  <div style="font-size:0.85em; color:#888; margin-top:0.4em; letter-spacing:0.05em;">POWERED BY SHEEP</div>
</div>

<h1>Welcome to Your Building Website</h1>
<p class="subtitle">A Guide for Residents &amp; Unit Owners</p>
<p class="version">Version 2.0 &nbsp;&middot;&nbsp; March 2026</p>

<div class="toc">
  <h3>Contents</h3>
  <ol>
    <li>What Is the Building Website?</li>
    <li>Public Resources &mdash; No Login Needed</li>
    <li>Logging In</li>
    <li>Private Resources &mdash; Owners Only</li>
    <li>Finding Documents with Search</li>
    <li>Ask Woolsy &mdash; Your Building Assistant</li>
    <li>My Account &mdash; Unit Info, Password &amp; More</li>
    <li>Forgot Your Password?</li>
    <li>Getting Help</li>
  </ol>
</div>

{divider()}

{section("Section 1 &mdash; What Is the Building Website?")}

<p>Your building has a website that gives residents access to important documents, reports, and community information &mdash; anytime, from any device.</p>
<p>The site has two areas:</p>
<ul>
  <li><strong>Public area</strong> &mdash; open to anyone, no login required. Contains general information, building rules, forms, and public documents.</li>
  <li><strong>Private area (Resource Center)</strong> &mdash; for unit owners only. Requires a personal login. Contains financial documents, board minutes, resident reports, and more.</li>
</ul>

{divider()}

{section("Section 2 &mdash; Public Resources")}

<p>The following resources are available to anyone visiting the site &mdash; no account needed:</p>

<table>
  <tr><th>Resource</th><th>What&rsquo;s There</th></tr>
  <tr><td>Latest News &amp; Announcements</td><td>Updates from the board</td></tr>
  <tr><td>Building Guides &amp; Rules</td><td>Community rules and regulations</td></tr>
  <tr><td>Forms &amp; Applications</td><td>Move-in/move-out forms, renovation requests, etc.</td></tr>
  <tr><td>Incorporation Documents</td><td>Declaration, bylaws, articles of incorporation</td></tr>
  <tr><td>Board of Directors</td><td>Current board members and roles</td></tr>
</table>

{divider()}

{section("Section 3 &mdash; Logging In")}

<h3>Your Credentials</h3>
<p>When you were added to the system, the board sent you an email with your login credentials. If you did not receive this email, check your spam folder or contact your board &mdash; the address registered with the association may need to be updated.</p>
<ul>
  <li><strong>Username:</strong> first initial + last name (e.g. Jane Smith &rarr; <code>jsmith</code>)</li>
  <li><strong>Temporary password:</strong> provided in the email from your board</li>
</ul>

<h3>First Login</h3>
<p>The first time you log in, you will be asked to choose a new personal password. Pick something you will remember &mdash; your temporary password will no longer work after this step.</p>

<h3>How to Log In</h3>
<ol>
  <li>Visit your building&rsquo;s website</li>
  <li>In the site menu, go to <strong>Resources Private</strong></li>
  <li>Enter your username and password</li>
  <li>Click <strong>Log In</strong></li>
</ol>

<blockquote>You will stay logged in for the duration of your browser session. Closing the browser will log you out.</blockquote>

{divider()}

{section("Section 4 &mdash; Private Resources")}

<p>Once logged in, you have access to the following owner-only resources:</p>

<table>
  <tr><th>Resource</th><th>What&rsquo;s There</th></tr>
  <tr><td>Board Meeting Minutes</td><td>Records from all board meetings, organized by year</td></tr>
  <tr><td>Financial Statements</td><td>Year-end financials and audit reports</td></tr>
  <tr><td>Budgets</td><td>Annual operating budgets</td></tr>
  <tr><td>Contracts</td><td>Service agreements and vendor contracts</td></tr>
  <tr><td>SIRs Documents</td><td>Structural inspection and reserve-related documents</td></tr>
  <tr><td>Resident List</td><td>Directory of unit owners and residents</td></tr>
  <tr><td>Elevator List</td><td>Unit-by-unit elevator access information</td></tr>
  <tr><td>Parking List</td><td>Assigned parking spots by unit</td></tr>
  <tr><td>My Account</td><td>Your personal hub &mdash; view your unit info, change your password, or open Woolsy</td></tr>
</table>

<h3>Downloading Documents</h3>
<p>All documents can be opened and downloaded directly from the website. Click any file name to view it; look for a download button to save a copy to your device.</p>

{divider()}

{section("Section 5 &mdash; Finding Documents with Search")}

<h3>How Search Works</h3>
<p>The website has a built-in search that looks across <em>all</em> documents at once &mdash; public and private &mdash; so you don&rsquo;t need to know which folder something is in. It also searches tags that the board has added to documents, so you can find files even when the exact filename isn&rsquo;t obvious.</p>

<h3>Login Required</h3>
<p>Because search covers all documents including private ones, <strong>you will be asked to log in when you open the search page</strong>. Make sure you have your username and password ready before using search.</p>

<h3>How to Search</h3>
<ol>
  <li>Click the <strong>Search</strong> button in the site menu</li>
  <li>Log in when prompted</li>
  <li>Type one or more keywords &mdash; for example: <em>budget</em>, <em>minutes</em>, <em>parking</em>, <em>rules</em></li>
  <li>Matching documents appear instantly</li>
  <li>Click any result to open the document</li>
</ol>

<div class="tip"><strong>Tip:</strong> Search is the fastest way to find anything on the site. Try it before browsing through folders.</div>

{divider()}

{section("Section 6 &mdash; Ask Woolsy &mdash; Your Building Assistant")}

<h3>What Is Woolsy?</h3>
<p>Woolsy is the AI assistant built into your building website. Look for the {woolsy_logo()} button floating in the corner of every page &mdash; click it to open the Woolsy chat panel where you can ask questions about the building in plain language.</p>
<p>Woolsy is available to everyone on the public website, no login required. If you are not yet logged in, Woolsy will offer a login form right inside the panel &mdash; no need to navigate away. Logged-in owners get a full chat interface with conversation history.</p>

<h3>What Woolsy Can Help With</h3>
<p>Woolsy is trained on your building&rsquo;s governing documents &mdash; the Declaration of Condominium, Bylaws, Rules and Regulations, and Board guidelines. It can answer questions like:</p>
<ul>
  <li><em>&ldquo;Are pets allowed? What is the weight limit?&rdquo;</em></li>
  <li><em>&ldquo;What are the rules for renting out my unit?&rdquo;</em></li>
  <li><em>&ldquo;Can I install a washing machine?&rdquo;</em></li>
  <li><em>&ldquo;What are the hours for renovation work?&rdquo;</em></li>
  <li><em>&ldquo;How do I request approval for an alteration?&rdquo;</em></li>
  <li><em>&ldquo;What are the guest parking rules?&rdquo;</em></li>
</ul>

<h3>What Woolsy Cannot Do</h3>
<ul>
  <li>Woolsy cannot make decisions on behalf of the board or grant exceptions to rules</li>
  <li>Woolsy does not have access to your personal account, payment history, or violation records</li>
  <li>Woolsy cannot tell you the outcome of a pending board decision</li>
  <li>For anything requiring a formal board response, contact the board directly</li>
</ul>

<blockquote><strong>Good to know:</strong> Woolsy&rsquo;s answers are based on the governing documents on file. If a rule has changed recently and the documents haven&rsquo;t been updated yet, Woolsy may not have the latest information. When in doubt, confirm with the board.</blockquote>

{divider()}

{section("Section 7 &mdash; My Account")}

<h3>Your Account Hub</h3>
<p>When you are logged in, open <strong>My Account</strong> from the <strong>Admin</strong> menu in the navigation bar. You can also find it in the Resource Center. From there you can:</p>
<ul>
  <li><strong>My Unit Info</strong> &mdash; view what the association has on file for your unit (residents, vehicles, emergency contacts). If anything is incorrect, use the <strong>Request a Change</strong> button to notify the board &mdash; they will update the database.</li>
  <li><strong>Change Password</strong> &mdash; update your login password at any time</li>
  <li><strong>Ask Woolsy</strong> &mdash; open the Woolsy chat assistant directly from your account page</li>
</ul>

<h3>Changing Your Password</h3>
<ol>
  <li>Open the <strong>Admin</strong> menu in the navigation bar and click <strong>My Account</strong></li>
  <li>Click the <strong>Change Password</strong> card</li>
  <li>Enter your current password, then your new password twice</li>
  <li>Click <strong>Save</strong></li>
</ol>

<blockquote>Choose a password you don&rsquo;t use elsewhere. Your account gives access to financial and personal information about your fellow residents &mdash; keep it secure.</blockquote>

<h3>Requesting a Change to Your Unit Information</h3>
<p>If your name, email, phone number, vehicle, or any other detail on file is incorrect:</p>
<ol>
  <li>Open the <strong>Admin</strong> menu and click <strong>My Account</strong>, then <strong>My Unit Info</strong></li>
  <li>Review the information shown for your unit</li>
  <li>Click <strong>Request a Change</strong> and describe what needs to be updated</li>
  <li>The board will receive your request by email and update the database</li>
</ol>
<blockquote><strong>Note:</strong> You cannot edit your own record directly &mdash; all changes go through the board to maintain the integrity of the association database.</blockquote>

{divider()}

{section("Section 8 &mdash; Forgot Your Password?")}


<p>If you can&rsquo;t remember your password:</p>
<ol>
  <li>Go to the login page</li>
  <li>Click <strong>Forgot password?</strong></li>
  <li>Enter your username</li>
  <li>A temporary password will be emailed to the address the board has on file for you</li>
  <li>Log in with the temporary password &mdash; you will be prompted to set a new one immediately</li>
</ol>

<blockquote><strong>No email received?</strong> Check your spam folder. If it&rsquo;s not there, contact your board &mdash; the email address on file may need to be updated.</blockquote>

{divider()}

{section("Section 9 &mdash; Getting Help")}


<p>Before contacting the board, try <strong>Woolsy</strong> &mdash; click the {woolsy_logo()} button in the corner of the website. Many common questions about rules, procedures, and building policies can be answered instantly.</p>

<p>If you still need help, the website is managed by your board:</p>
<table>
  <tr><th>Issue</th><th>What to Do</th></tr>
  <tr><td>Can&rsquo;t log in / password issues</td><td>Use the <strong>Forgot Password</strong> link, or contact your board</td></tr>
  <tr><td>Never received login credentials</td><td>Contact your board &mdash; your email address may need to be updated in the association database</td></tr>
  <tr><td>Question about a building rule</td><td>Ask Woolsy first &mdash; click the {woolsy_logo()} button in the corner of the website</td></tr>
  <tr><td>Missing document</td><td>Contact your board; they may need to upload it</td></tr>
  <tr><td>Wrong information in the resident directory</td><td>Contact your board to have the database updated</td></tr>
  <tr><td>Technical issues with the website</td><td>Your board can contact SheepSite support</td></tr>
</table>

</body>
</html>
"""

out = os.path.join(os.path.dirname(__file__), "Sheepsite-Resident-Manual.html")
with open(out, "w", encoding="utf-8") as f:
    f.write(html)

print(f"Written to {out}")
size_kb = os.path.getsize(out) // 1024
print(f"File size: {size_kb} KB")
