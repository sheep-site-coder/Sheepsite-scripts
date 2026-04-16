<?php
// woolsy-admin-widget.php — Floating Woolsy assistant for admin pages.
// Requires $building to be defined before including this file.
// Only renders when manage_auth_{building} session is active.
$_wadmin_key = 'manage_auth_' . ($building ?? '');
if (empty($_SESSION[$_wadmin_key])) return;
$_wadmin_base = (strpos($_SERVER['PHP_SELF'] ?? '', '/Scripts/') !== false)
    ? ''
    : 'https://sheepsite.com/Scripts/';
?>
<!-- Woolsy Admin Assistant -->
<style>
#wadmin-fab {
  position: fixed; bottom: 20px; right: 20px; z-index: 9990;
  width: 52px; height: 52px; border-radius: 50%;
  background: #2c5f8a; border: none; cursor: pointer;
  box-shadow: 0 3px 10px rgba(0,0,0,.25);
  display: flex; align-items: center; justify-content: center;
}
#wadmin-bubble {
  position: fixed; bottom: 28px; right: 82px; z-index: 9990;
  background: #fff; color: #2c5f8a;
  font-size: 13px; font-weight: bold;
  padding: 6px 10px; border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,.2);
  white-space: nowrap; cursor: pointer;
}
#wadmin-panel {
  display: none; position: fixed; bottom: 90px; right: 24px; z-index: 9990;
  width: 340px; max-height: 480px;
  background: #fff; border-radius: 10px;
  box-shadow: 0 6px 24px rgba(0,0,0,.18);
  flex-direction: column; overflow: hidden;
}
#wadmin-header {
  background: #2c5f8a; color: #fff;
  padding: 10px 14px; display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
#wadmin-header strong { font-size: 14px; }
#wadmin-close {
  background: none; border: 1px solid rgba(255,255,255,.4);
  color: #fff; border-radius: 4px; padding: 2px 8px; cursor: pointer; font-size: 12px;
}
#wadmin-messages {
  flex: 1; overflow-y: auto; padding: 12px; font-size: 13px;
  display: flex; flex-direction: column; gap: 8px; min-height: 200px;
}
.wam-bot, .wam-user {
  max-width: 88%; padding: 8px 10px; border-radius: 8px; line-height: 1.45;
  white-space: pre-wrap; word-break: break-word;
}
.wam-bot  { background: #f0f4f8; color: #222; align-self: flex-start; }
.wam-user { background: #2c5f8a; color: #fff; align-self: flex-end; }
.wam-bot ol, .wam-bot ul { padding-left: 1.2em; margin: 4px 0; }
#wadmin-input-row {
  display: flex; gap: 6px; padding: 8px 10px;
  border-top: 1px solid #e5e5e5; flex-shrink: 0;
}
#wadmin-input {
  flex: 1; padding: 7px 10px; border: 1px solid #ccc; border-radius: 6px;
  font-size: 13px; resize: none; font-family: inherit;
}
#wadmin-send {
  background: #2c5f8a; color: #fff; border: none; border-radius: 6px;
  padding: 7px 12px; cursor: pointer; font-size: 13px;
}
#wadmin-send:disabled { opacity: .5; cursor: default; }
</style>

<button id="wadmin-fab" title="Ask Woolsy">
  <span style="display:flex;align-items:center;justify-content:center;width:42px;height:42px;background:#fff;border-radius:50%;">
    <img src="<?= $_wadmin_base ?>assets/Woolsy-standing-transparent.png" height="34" alt="Woolsy" style="display:block;">
  </span>
</button>
<div id="wadmin-bubble">Need Help?</div>

<div id="wadmin-panel">
  <div id="wadmin-header">
    <strong><img src="<?= $_wadmin_base ?>assets/Woolsy-standing-transparent.png" height="22" alt="" style="vertical-align:middle;margin-right:6px;">Ask Woolsy</strong>
    <button id="wadmin-close">✕ Close</button>
  </div>
  <div id="wadmin-messages"></div>
  <div id="wadmin-input-row">
    <textarea id="wadmin-input" rows="2" placeholder="Ask about managing residents, files, billing…"></textarea>
    <button id="wadmin-send">Send</button>
  </div>
</div>

<script>
(function () {
  const BUILDING   = <?= json_encode($building) ?>;
  const ENDPOINT   = <?= json_encode($_wadmin_base . 'chatbot-admin.php') ?>;
  const panel      = document.getElementById('wadmin-panel');
  const fab        = document.getElementById('wadmin-fab');
  const bubble     = document.getElementById('wadmin-bubble');
  const msgs       = document.getElementById('wadmin-messages');
  const input      = document.getElementById('wadmin-input');
  const sendBtn    = document.getElementById('wadmin-send');
  const closeBtn   = document.getElementById('wadmin-close');

  let history = [];
  let opened  = false;

  function openPanel() {
    panel.style.display = 'flex';
    bubble.style.display = 'none';
    if (!opened) {
      addMsg('bot', "Hi! I'm Woolsy. Ask me anything about managing your SheepSite — residents, user accounts, files, billing, reports, and settings.");
      opened = true;
    }
    input.focus();
  }

  fab.addEventListener('click', openPanel);
  bubble.addEventListener('click', openPanel);
  closeBtn.addEventListener('click', function () {
    panel.style.display = 'none';
    bubble.style.display = 'block';
  });

  function addMsg(role, text) {
    var div = document.createElement('div');
    div.className = role === 'bot' ? 'wam-bot' : 'wam-user';
    div.textContent = text;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    return div;
  }

  function send() {
    var q = input.value.trim();
    if (!q || sendBtn.disabled) return;
    addMsg('user', q);
    input.value = '';
    sendBtn.disabled = true;
    var thinking = addMsg('bot', '…');
    fetch(ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({building: BUILDING, question: q, history: history})
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var answer = d.answer || d.error || 'Something went wrong.';
      thinking.textContent = answer;
      msgs.scrollTop = msgs.scrollHeight;
      history.push({role: 'user', content: q});
      history.push({role: 'assistant', content: answer});
      if (history.length > 20) history = history.slice(-20);
    })
    .catch(function () {
      thinking.textContent = 'Could not connect. Please try again.';
    })
    .finally(function () {
      sendBtn.disabled = false;
      input.focus();
    });
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
  });
})();
</script>
