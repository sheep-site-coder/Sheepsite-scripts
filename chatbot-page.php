<?php
// chatbot-page.php — Resident chatbot UI (served in iframe from building sites)
//
// Checks private_auth_{building} session (same as display-private-dir.php).
// If not logged in: shows login form.
// If logged in: shows full chatbot UI backed by chatbot.php.

session_start();

define('LOGIN_STATS_FILE', __DIR__ . '/credentials/login_stats.json');

function logLogin(string $building, string $username): void {
    $today  = date('Y-m-d');
    $cutoff = date('Y-m-d', strtotime('-12 months'));
    $data   = file_exists(LOGIN_STATS_FILE)
        ? (json_decode(file_get_contents(LOGIN_STATS_FILE), true) ?? [])
        : [];
    $data[$building][$username][$today] = ($data[$building][$username][$today] ?? 0) + 1;
    foreach ($data[$building][$username] as $date => $count) {
        if ($date < $cutoff) unset($data[$building][$username][$date]);
    }
    file_put_contents(LOGIN_STATS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

define('CREDENTIALS_DIR', __DIR__ . '/credentials/');

$buildings = require __DIR__ . '/buildings.php';

$building = $_GET['building'] ?? '';
if (!$building || !array_key_exists($building, $buildings)) {
    die('<p style="color:red;font-family:sans-serif;padding:20px;">Invalid building.</p>');
}

$buildLabel = ucwords(str_replace(['_','-'], ' ', $building));
$sessionKey = 'private_auth_' . $building;

$q = ''; // unused — question is passed via postMessage from the public widget

// --- Login POST ---
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $credFile = CREDENTIALS_DIR . $building . '.json';
    $users    = file_exists($credFile) ? json_decode(file_get_contents($credFile), true) : [];

    foreach ($users as $u) {
        if ($u['user'] === $username && password_verify($password, $u['pass'])) {
            $_SESSION[$sessionKey] = $username;
            logLogin($building, $username);
            $qParam = !empty($_GET['q']) ? '&q=' . urlencode(trim($_GET['q'])) : '';
            header('Location: ?building=' . urlencode($building) . $qParam);
            exit;
        }
    }
    $loginError = 'Incorrect username or password.';
}

if (isset($_GET['logout'])) {
    unset($_SESSION[$sessionKey]);
    header('Location: ?building=' . urlencode($building));
    exit;
}

$loggedIn = !empty($_SESSION[$sessionKey]);
$username = $loggedIn ? $_SESSION[$sessionKey] : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Woolsy — <?= htmlspecialchars($buildLabel) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: sans-serif;
    font-size: 14px;
    background: #f7f7f7;
    height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* --- Header --- */
  #chat-header {
    background: #2c5f8a;
    color: #fff;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
  }
  #chat-header strong { font-size: 15px; }
  #chat-header span   { font-size: 12px; opacity: .8; }
  #logout-btn {
    background: none;
    border: 1px solid rgba(255,255,255,.5);
    color: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
  }

  /* --- Messages --- */
  #chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .msg {
    max-width: 85%;
    padding: 9px 12px;
    border-radius: 10px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-wrap: break-word;
  }
  .msg-user {
    align-self: flex-end;
    background: #2c5f8a;
    color: #fff;
    border-bottom-right-radius: 3px;
  }
  .msg-bot {
    align-self: flex-start;
    background: #fff;
    color: #222;
    border: 1px solid #e0e0e0;
    border-bottom-left-radius: 3px;
  }
  .msg-thinking { color: #999; font-style: italic; }

  /* --- Input row --- */
  #chat-input-row {
    display: flex;
    border-top: 1px solid #ddd;
    padding: 8px;
    gap: 6px;
    background: #fff;
    flex-shrink: 0;
  }
  #chat-input {
    flex: 1;
    padding: 8px 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 13px;
    outline: none;
  }
  #chat-send {
    padding: 8px 16px;
    background: #2c5f8a;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
  }
  #chat-send:disabled { opacity: .5; cursor: default; }

  /* --- Login form --- */
  #login-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  #login-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 28px 24px;
    width: 100%;
    max-width: 320px;
    text-align: center;
  }
  #login-box h2 { font-size: 17px; margin-bottom: 6px; color: #2c5f8a; }
  #login-box p  { font-size: 13px; color: #666; margin-bottom: 18px; }
  #login-box input {
    width: 100%;
    padding: 9px 11px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 13px;
    margin-bottom: 10px;
    outline: none;
  }
  #login-box button {
    width: 100%;
    padding: 10px;
    background: #2c5f8a;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
  }
  .login-error {
    color: #c0392b;
    font-size: 13px;
    margin-bottom: 10px;
  }
</style>
</head>
<body>

<div id="chat-header">
  <div>
    <strong>Woolsy</strong><br>
    <span><?= htmlspecialchars($buildLabel) ?></span>
  </div>
  <?php if ($loggedIn): ?>
  <form method="get" style="display:inline">
    <input type="hidden" name="building" value="<?= htmlspecialchars($building) ?>">
    <input type="hidden" name="logout" value="1">
    <button type="submit" id="logout-btn">Log out</button>
  </form>
  <?php endif ?>
</div>

<?php if (!$loggedIn): ?>

<div id="login-wrap">
  <div id="login-box">
    <h2>Resident Login</h2>
    <p>Log in with your resident account to chat with Woolsy.</p>
    <?php if ($loginError): ?>
      <div class="login-error"><?= htmlspecialchars($loginError) ?></div>
    <?php endif ?>
    <form method="post">
      <input type="hidden" name="building" value="<?= htmlspecialchars($building) ?>">
      <input type="text"     name="username" placeholder="Username" autocomplete="username" required>
      <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
      <button type="submit">Log in</button>
    </form>
  </div>
</div>

<?php else: ?>

<div id="chat-messages"></div>
<div id="chat-input-row">
  <input id="chat-input" type="text" placeholder="Ask anything about your community…" autocomplete="off">
  <button id="chat-send">Send</button>
</div>

<script>
const BUILDING  = <?= json_encode($building) ?>;
const USERNAME  = <?= json_encode($username) ?>;
// Resolve question: sessionStorage (set before login redirect) takes priority
// over PHP session fallback. Clear sessionStorage immediately after reading.
let   history   = [];

function addMessage(role, text) {
  const el = document.createElement('div');
  el.className = 'msg ' + (role === 'user' ? 'msg-user' : 'msg-bot');
  if (text === '…') el.classList.add('msg-thinking');
  el.textContent = text;
  document.getElementById('chat-messages').appendChild(el);
  document.getElementById('chat-messages').scrollTop = 99999;
  return el;
}

async function send() {
  const input  = document.getElementById('chat-input');
  const btn    = document.getElementById('chat-send');
  const q      = input.value.trim();
  if (!q) return;

  input.value   = '';
  btn.disabled  = true;

  addMessage('user', q);
  const thinking = addMessage('bot', '…');

  try {
    const res  = await fetch('chatbot.php', {
      method:      'POST',
      credentials: 'same-origin',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ building: BUILDING, question: q, history }),
    });
    const data   = await res.json();
    const answer = data.answer || 'Sorry, I couldn\'t get a response. Please try again.';

    thinking.textContent = answer;
    thinking.classList.remove('msg-thinking');

    history.push({ role: 'user',      content: q      });
    history.push({ role: 'assistant', content: answer });
    if (history.length > 12) history = history.slice(-12);

  } catch (e) {
    thinking.textContent = 'Something went wrong. Please try again.';
    thinking.classList.remove('msg-thinking');
  }

  btn.disabled = false;
  input.focus();
}

document.getElementById('chat-send').onclick = send;
document.getElementById('chat-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') send();
});

// Greeting
addMessage('bot', 'Hi <?= htmlspecialchars(ucfirst($username)) ?>! I\'m Woolsy, your community assistant. Ask me anything about <?= htmlspecialchars($buildLabel) ?> — rules, procedures, documents, amenities.');

// Read question directly from URL — works whether logged in already or after login redirect.
const _urlQ = new URLSearchParams(window.location.search).get('q') || '';
if (_urlQ) {
  document.getElementById('chat-input').value = _urlQ;
  send();
} else {
  document.getElementById('chat-input').focus();
}
</script>

<?php endif ?>

</body>
</html>
