// ---------------------------------------------------------------------------
// doResetPassword — called by forgot-password.php
//
// Looks up the username in the Database tab by reverse-engineering the
// username generation algorithm (same as manage-users.php), finds the
// owner's email, and sends them a temporary password.
//
// Returns JSON:
//   { status: "ok" }          — email sent; PHP will update credentials
//   { status: "no_email" }    — owner found but no email on file
//   { status: "not_found" }   — username not found in Database
//   { error: "..." }          — unexpected failure
//
// Token is OWNER_IMPORT_TOKEN, defined in building-script.gs.
// ---------------------------------------------------------------------------

function doResetPassword(params, expectedToken) {
  const token       = params.token       || '';
  const username    = (params.username   || '').toLowerCase().trim();
  const tmpPw       = params.tmppw       || '';
  const building    = params.building    || '';
  const loginUrl    = params.loginurl    || '';
  const secretNum   = (params.secretnum  || '').toString().trim();
  const directEmail = (params.directemail || '').trim();  // bypass DB lookup when provided

  if (!token || token !== expectedToken) {
    return _rpJson({ error: 'Unauthorized' });
  }
  if (!username || !tmpPw || !building) {
    return _rpJson({ error: 'Missing required parameters' });
  }

  // If a direct email is supplied, skip the Database lookup entirely
  if (directEmail) {
    const ss           = SpreadsheetApp.getActiveSpreadsheet();
    const fileName     = ss.getName();
    const buildingName = fileName.split('Owner DB')[0].trim() || building;
    const subject      = 'Your temporary password – ' + buildingName;
    const body = [
      'A login account has been created for you.',
      '',
      '    username --> ' + username + '   (note: all lower case)',
      '    password --> ' + tmpPw,
      '',
      'Please log in at the link below — you will be prompted to set a new password:',
      loginUrl,
      '',
      'If you have any questions, please contact your building administrator.',
    ].join('\n');
    try {
      MailApp.sendEmail(directEmail, subject, body, { name: 'SheepSite.com' });
    } catch (e) {
      return _rpJson({ error: 'Failed to send email: ' + e.message });
    }
    return _rpJson({ status: 'ok' });
  }

  const ss    = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName('Database');
  if (!sheet) return _rpJson({ error: 'No tab named "Database" found' });

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return _rpJson({ status: 'not_found' });

  const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn())
    .getValues()[0].map(h => h.toString().trim());
  const iFirst = headers.indexOf('First Name');
  const iLast  = headers.indexOf('Last Name');
  const iEmail = headers.indexOf('eMail');

  const iUnit  = headers.indexOf('Unit #');

  if (iFirst === -1 || iLast === -1 || iEmail === -1) {
    return _rpJson({ error: 'Missing required columns (First Name, Last Name, eMail) in Database' });
  }

  const rows   = sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).getValues();
  let foundEmail = null;  // null = not found; '' = found but no email

  if (username === 'admin') {
    // For the admin account, use the email of the board President.
    // secretNum must match the President's unit number as an anti-prank check.
    const iBoard = headers.indexOf('Board');
    if (iBoard === -1) return _rpJson({ error: 'No "Board" column found in Database' });
    for (const row of rows) {
      const role = String(row[iBoard] || '').trim();
      if (role === 'President') {
        const unit = String(iUnit !== -1 ? (row[iUnit] || '') : '').trim();
        if (!secretNum || secretNum !== unit) return _rpJson({ status: 'not_found' });
        foundEmail = String(row[iEmail] || '').trim();
        break;
      }
    }
  } else {
    // Normal owners: reverse-engineer username from first initial + last name
    const counts = {};
    for (const row of rows) {
      const firstName = String(row[iFirst] || '').trim();
      const lastName  = String(row[iLast]  || '').trim();
      if (!lastName) continue;

      // Same username generation as manage-users.php
      const base  = (firstName.charAt(0) + lastName).toLowerCase().replace(/[^a-z]/g, '');
      counts[base] = (counts[base] || 0) + 1;
      const uname = counts[base] === 1 ? base : base + counts[base];

      if (uname === username) {
        foundEmail = String(row[iEmail] || '').trim();
        break;
      }
    }
  }

  if (foundEmail === null) return _rpJson({ status: 'not_found' });
  if (!foundEmail)         return _rpJson({ status: 'no_email' });

  // Send email with temp password
  const fileName    = ss.getName();
  const buildingName = fileName.split('Owner DB')[0].trim() || building;
  const subject     = 'Your temporary password – ' + buildingName;
  const body = [
    'A password reset was requested for your account (' + username + ').',
    '',
    'Your new temporary password is:',
    '',
    '    ' + tmpPw,
    '',
    'Please log in at the link below — you will be prompted to set a new password:',
    loginUrl,
    '',
    'If you did not request this, please contact your building administrator.',
  ].join('\n');

  try {
    MailApp.sendEmail(foundEmail, subject, body, { name: 'SheepSite.com' });
  } catch (e) {
    return _rpJson({ error: 'Failed to send email: ' + e.message });
  }

  return _rpJson({ status: 'ok' });
}

function _rpJson(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
