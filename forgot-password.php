<?php
// -------------------------------------------------------
// forgot-password.php
// Self-serve password reset: owner enters username,
// receives a new temporary password by email.
// -------------------------------------------------------
session_start();

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');
define('OWNER_IMPORT_TOKEN', 'QRF*!v2r2KgJEesq&P');  // must match building-script.gs

$buildings = require __DIR__ . '/buildings.php';

// -------------------------------------------------------
// Validate building
// -------------------------------------------------------
$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
  die('<p style="color:red;">Invalid or missing building name.</p>');
}

$buildingConfig = $buildings[$building];
$buildLabel     = ucwords(str_replace(['_', '-'], ' ', $building));
$isAdminReset   = ($_GET['role'] ?? '') === 'admin';
$isAdminSetup   = $isAdminReset && isset($_GET['setup']);

$useLocalDB = file_exists(__DIR__ . '/db/db.php') && file_exists(CREDENTIALS_DIR . 'db.json');
if ($useLocalDB) require_once __DIR__ . '/db/residents.php';
require_once __DIR__ . '/db/admin-helpers.php';

// Block owner password resets on demo/test sites (admin reset is unaffected)
$configFile = __DIR__ . '/config/' . $building . '.json';
$bldCfg     = file_exists($configFile) ? (json_decode(file_get_contents($configFile), true) ?? []) : [];
$isTestSite = !empty($bldCfg['testSite']) && !$isAdminReset;

// Login URL included in reset email so owner knows where to go
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dir      = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$loginURL = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir
          . '/display-private-dir.php?building=' . urlencode($building);

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function generateTempPassword(int $length = 8): string {
  // Exclude ambiguous characters (0/O, 1/l/I)
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
  $pw = '';
  for ($i = 0; $i < $length; $i++) {
    $pw .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $pw;
}

function loadUsers(string $building): array {
  $file = CREDENTIALS_DIR . $building . '.json';
  return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveUsers(string $building, array $users): bool {
  return file_put_contents(
    CREDENTIALS_DIR . $building . '.json',
    json_encode(array_values($users), JSON_PRETTY_PRINT)
  ) !== false;
}

// -------------------------------------------------------
// Handle form submission
// -------------------------------------------------------
$message     = '';
$messageType = 'ok';

if ($isTestSite) {
  $message     = 'Password resets are disabled on this demo site.';
  $messageType = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $isAdminReset
    ? strtolower(trim($_POST['admin_username'] ?? ''))
    : strtolower(trim($_POST['username'] ?? ''));

  if (!$isAdminReset && (!$username || !preg_match('/^[a-z][a-z0-9]*$/', $username))) {
    $message     = 'Please enter a valid username.';
    $messageType = 'error';
  } else {
    $webAppURL      = $buildingConfig['webAppURL'];
    $tmpPw          = generateTempPassword();
    $targetLoginURL = $isAdminReset
                    ? $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/admin.php?building=' . urlencode($building)
                    : $loginURL;
    $secretNum      = trim($_POST['secret_num'] ?? '');

    // In DB mode for owner resets, look up the email from MySQL and pass it
    // directly to GAS so GAS doesn't try to look it up from the Sheet.
    $directEmail = null;
    if (!$isAdminReset && $useLocalDB) {
      $directEmail = dbGetEmailByUsername($building, $username);
      if ($directEmail === null) {
        $message     = 'No email address is on file for this account. Please contact your building administrator.';
        $messageType = 'error';
      }
    }

    if (!$message) {
      if ($isAdminReset && $useLocalDB) {
        // DB mode admin reset: validate username + President unit# (magic number)
        $adminFile  = CREDENTIALS_DIR . $building . '_admin.json';
        $adminCreds = loadAdminCreds($adminFile);
        $adminEntry = $username ? findAdmin($adminCreds, $username) : null;

        // Always look up President — needed for unit# validation and setup email routing
        $president = null;
        try {
          $pdo  = getDB();
          $stmt = $pdo->prepare(
            'SELECT email, unit FROM residents WHERE building = ? AND board_role = ? LIMIT 1'
          );
          $stmt->execute([$building, 'President']);
          $president = $stmt->fetch();
        } catch (Exception $e) { /* leave null */ }

        // Validate: username must exist (or be a new bootstrap) AND secret # must match President's unit
        $unitMatches = $president && $secretNum === trim((string)$president['unit']);

        if ($isAdminSetup && !$adminEntry && $username && $unitMatches) {
          // Bootstrap: create a new admin entry using President's email
          $sendTo     = $president['email'] ?? '';
          $adminEntry = ['user' => $username, 'pass' => '', 'email' => $sendTo];
        } else {
          $sendTo = $adminEntry['email'] ?? '';
        }

        if (!$adminEntry || !$unitMatches) {
          $message     = 'Admin name or Secret does not match our records.';
          $messageType = 'error';
        } elseif (!$sendTo) {
          $message     = 'No email address is on file for this admin account. Please contact SheepSite support.';
          $messageType = 'error';
        } elseif (sendTempPasswordEmail($sendTo, $username, $tmpPw, $targetLoginURL, $buildLabel)) {
          $newHash    = password_hash($tmpPw, PASSWORD_DEFAULT);
          // updateAdminEntry adds the entry if it already exists; for bootstrap, append it
          if (findAdmin($adminCreds, $username)) {
            $adminCreds = updateAdminEntry($adminCreds, $username, [
              'pass'       => $newHash,
              'mustChange' => true,
            ]);
          } else {
            $adminCreds[] = ['user' => $username, 'pass' => $newHash, 'email' => $sendTo, 'mustChange' => true];
          }
          saveAdminCreds($adminFile, $adminCreds);
          $message = 'A new temporary password has been sent to the email address on file.';
        } else {
          $message     = 'Could not send reset email. Please try again or contact your administrator.';
          $messageType = 'error';
        }

      } elseif ($directEmail !== null) {
        // DB mode owner reset: send directly from PHP so From is noreply@sheepsite.com
        $subject = 'Your temporary password – ' . $buildLabel;
        $body    = "A password reset was requested for your account ($username).\n\n"
                 . "Your new temporary password is:\n\n"
                 . "    $tmpPw\n\n"
                 . "Please log in at the link below — you will be prompted to set a new password:\n"
                 . "$targetLoginURL\n\n"
                 . "If you did not request this, please contact your building administrator.";
        $headers = implode("\r\n", [
          'From: SheepSite.com <noreply@sheepsite.com>',
          'Reply-To: noreply@sheepsite.com',
          'Content-Type: text/plain; charset=UTF-8',
        ]);
        if (mail($directEmail, $subject, $body, $headers)) {
          $newHash = password_hash($tmpPw, PASSWORD_DEFAULT);
          $users   = loadUsers($building);
          foreach ($users as &$u) {
            if ($u['user'] === $username) { $u['pass'] = $newHash; $u['mustChange'] = true; break; }
          }
          unset($u);
          saveUsers($building, $users);
          $message = 'A new temporary password has been sent to the email address on file.';
        } else {
          $message     = 'Could not send reset email. Please try again or contact your administrator.';
          $messageType = 'error';
        }

      } else {
        // GAS path: non-DB buildings only
        $url      = $webAppURL
                  . '?page=resetpw'
                  . '&token='     . urlencode(OWNER_IMPORT_TOKEN)
                  . '&username='  . urlencode($username)
                  . '&building='  . urlencode($building)
                  . '&tmppw='     . urlencode($tmpPw)
                  . '&loginurl='  . urlencode($targetLoginURL)
                  . '&secretnum=' . urlencode($secretNum);
        $ctx      = stream_context_create(['http' => ['timeout' => 15]]);
        $response = @file_get_contents($url, false, $ctx);

        if ($response === false) {
          $message     = 'Could not reach the email service. Please try again later or contact your administrator.';
          $messageType = 'error';
        } else {
          $data   = json_decode($response, true);
          $status = $data['status'] ?? '';

          if ($status === 'ok') {
            $newHash      = password_hash($tmpPw, PASSWORD_DEFAULT);
            $savedToOwner = false;
            $users        = loadUsers($building);
            foreach ($users as &$u) {
              if ($u['user'] === $username) {
                $u['pass'] = $newHash; $u['mustChange'] = true; $savedToOwner = true; break;
              }
            }
            unset($u);
            if ($savedToOwner) {
              saveUsers($building, $users);
            } else {
              $adminFile  = CREDENTIALS_DIR . $building . '_admin.json';
              $adminCreds = loadAdminCreds($adminFile);
              if (!$adminCreds) {
                saveAdminCreds($adminFile, [['user' => $username, 'pass' => $newHash, 'email' => '', 'mustChange' => true]]);
              } else {
                $adminCreds = updateAdminEntry($adminCreds, $username, ['pass' => $newHash, 'mustChange' => true]);
                saveAdminCreds($adminFile, $adminCreds);
              }
            }
            $message = 'A new temporary password has been sent to the email address on file.';
          } elseif ($status === 'no_email' || $status === 'not_found') {
            $message     = 'No email address is on file for this account. Please contact your building administrator.';
            $messageType = 'error';
          } else {
            $message     = 'Could not send reset email. Please try again or contact your administrator.';
            $messageType = 'error';
          }
        }
      }
    } // end if (!$message)
  }
} // end elseif POST
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($buildLabel) ?> – Reset Password</title>
  <style>
    body       { font-family: sans-serif; max-width: 400px; margin: 4rem auto; padding: 0 1rem; }
    h1         { margin-bottom: 0.25rem; font-size: 1.4rem; }
    .subtitle  { color: #666; font-size: 0.9rem; margin-bottom: 2rem; }
    label      { display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.25rem; }
    input[type=text] {
                 width: 100%; box-sizing: border-box; padding: 0.5rem 0.6rem;
                 border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;
                 margin-bottom: 1rem; }
    .reset-btn { width: 100%; padding: 0.6rem; background: #0070f3; color: #fff;
                 border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
    .reset-btn:hover { background: #005bb5; }
    .message   { padding: 0.6rem 0.9rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.9rem; }
    .message.ok    { background: #e6f4ea; color: #1a7f37; }
    .message.error { background: #ffeef0; color: #c00; }
    .back-btn  { display: inline-block; margin-bottom: 1.5rem; font-size: 0.9rem;
                 color: #0070f3; text-decoration: none; }
    .back-btn:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <a href="<?= $isAdminReset ? 'admin.php' : 'display-private-dir.php' ?>?building=<?= urlencode($building) ?>" class="back-btn">← Back to login</a>

  <h1><?= htmlspecialchars($buildLabel) ?></h1>
  <div class="subtitle">Reset your password</div>

  <?php if ($message): ?>
    <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($messageType !== 'ok' || !$message): ?>
    <?php if ($isAdminReset): ?>
      <p style="font-size:0.9rem;color:#444;margin-bottom:1.5rem;">
        <?php if ($isAdminSetup): ?>
          The admin account has not been set up yet. Enter your admin username and secret #,
          and a temporary password will be sent to the email on file for that account.
        <?php else: ?>
          Enter your admin username and secret # (the President's unit number).
          A temporary password will be sent to the email on file for that account.
        <?php endif; ?>
      </p>
      <form method="post" action="forgot-password.php?building=<?= urlencode($building) ?>&role=admin<?= $isAdminSetup ? '&setup=1' : '' ?>"
            onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Processing\u2026';">
        <label for="admin_username">Admin username</label>
        <input type="text" id="admin_username" name="admin_username" autocomplete="username"
               autocapitalize="none" autofocus style="margin-bottom:1rem;"
               value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>">
        <label for="secret_num">Secret # (President's unit number)</label>
        <input type="text" id="secret_num" name="secret_num" autocomplete="off"
               inputmode="numeric" style="margin-bottom:1rem;">
        <button type="submit" class="reset-btn">Send temporary password</button>
      </form>
    <?php else: ?>
      <?php if (!$isTestSite): ?>
      <p style="font-size:0.9rem;color:#444;margin-bottom:1.5rem;">
        Enter your username (first letter of your first name + last name, e.g. <strong>jsmith</strong>).
        If we have an email address on file for your account, we'll send you a temporary password.
      </p>
      <form method="post" action="forgot-password.php?building=<?= urlencode($building) ?>"
            onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Processing\u2026';">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               autocomplete="username" autocapitalize="none" autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <button type="submit" class="reset-btn">Send temporary password</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</body>
</html>
