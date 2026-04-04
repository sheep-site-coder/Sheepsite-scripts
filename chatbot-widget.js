// chatbot-widget.js — FAQ chatbot widget for building sites
//
// Public mode  : cheeky deflections, zero API cost, login CTA
// Resident mode: full inline chat backed by chatbot.php (session-aware)
//
// Requires BUILDING_NAME to be set by the footer script before this loads.
// Add openChatbot() to any button, or the floating bubble appears automatically.

(function () {
  const SCRIPTS_URL = 'https://sheepsite.com/Scripts';
  const WIDGET_ID   = 'ss-chatbot';

  let chatHistory = [];
  let widgetMode  = null; // 'public' | 'resident'

  // --- Cheeky deflections ---
  const DEFLECTIONS = [
    "Great question! Log in as a resident and I'll have a proper answer for you right away.",
    "Oh, I know exactly where to find that — just log in and I'll pull it up!",
    "I'd love to help with that! The answer is in your building's documents, which I can access once you log in.",
    "That's a really good one. I have the full details ready for you — just need you to log in first.",
    "I'm fully briefed on your community's rules! One small thing: resident login required.",
    "Ask me anything! Well… anything you're happy to log in for. Which this definitely is.",
    "Resident portal, please! I promise the answer is worth the 10 seconds it takes to log in.",
    "You're so close to getting a great answer. Log in and let's chat properly!",
    "I know this one! And I'll tell you — right after you log in. It'll be worth it.",
    "My lips are sealed until you log in. It's a whole thing. You'll understand once you're in.",
  ];

  const KEYWORD_DEFLECTIONS = {
    pet:     "I know exactly what your building says about pets! Log in and I'll tell you everything 🐾",
    dog:     "I know exactly what your building says about pets! Log in and I'll tell you everything 🐾",
    cat:     "I know exactly what your building says about pets! Log in and I'll tell you everything 🐾",
    park:    "Parking rules, spots, permits — I have it all. Log in and I'll lay it out for you 🚗",
    rent:    "Rental and subletting rules are very building-specific. Log in and I'll give you the exact policy.",
    sublet:  "Rental and subletting rules are very building-specific. Log in and I'll give you the exact policy.",
    noise:   "Quiet hours, noise policies — yep, it's all in there. Log in and I'll share the details.",
    repair:  "Maintenance responsibilities can get nuanced. Log in and I'll walk you through what's yours vs. the association's.",
    fee:     "I have full details on fees and assessments — just log in to unlock them.",
    fine:    "I have full details on fees and assessments — just log in to unlock them.",
    pool:    "Amenity rules and hours are ready and waiting for you. Log in to get the full picture 🏊",
    gym:     "Amenity rules and hours are ready and waiting for you. Log in to get the full picture.",
    move:    "Move-in and move-out procedures vary by building. Log in and I'll walk you through yours.",
    guest:   "Guest policies are building-specific. Log in and I'll give you the exact rules.",
    balcony: "Balcony rules — what you can store, hang, or put out there — it's all in your documents. Log in!",
  };

  function getDeflection(question) {
    const q = question.toLowerCase();
    for (const [keyword, response] of Object.entries(KEYWORD_DEFLECTIONS)) {
      if (q.includes(keyword)) return response;
    }
    return DEFLECTIONS[Math.floor(Math.random() * DEFLECTIONS.length)];
  }

  // --- Session helpers ---
  async function whoami(building) {
    try {
      const res  = await fetch(`${SCRIPTS_URL}/chatbot-auth.php?action=whoami&building=${encodeURIComponent(building)}`, {
        credentials: 'same-origin',
        cache:       'no-store',
      });
      const data = await res.json();
      return data.loggedIn ? data.username : null;
    } catch {
      return null;
    }
  }

  async function logout(building) {
    try {
      await fetch(`${SCRIPTS_URL}/chatbot-auth.php?action=logout&building=${encodeURIComponent(building)}`);
    } catch { /* ignore */ }
  }

  // --- Styles ---
  function injectStyles() {
    if (document.getElementById('ss-chatbot-styles')) return;
    const style = document.createElement('style');
    style.id = 'ss-chatbot-styles';
    style.textContent = `
      #ss-chatbot {
        display: none;
        position: fixed;
        bottom: 80px;
        right: 20px;
        width: 340px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,.2);
        font-family: sans-serif;
        font-size: 14px;
        z-index: 9999;
        flex-direction: column;
        overflow: hidden;
      }
      #ss-chatbot.ss-resident {
        width: 380px;
      }
      #ss-chat-header {
        background: #2c5f8a;
        color: #fff;
        padding: 10px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        flex-shrink: 0;
      }
      #ss-chat-header-right {
        display: flex;
        align-items: center;
        gap: 10px;
      }
      #ss-chat-username {
        font-size: 12px;
        font-weight: normal;
        opacity: 0.85;
      }
      #ss-chat-logout {
        background: none;
        border: 1px solid rgba(255,255,255,.5);
        color: #fff;
        padding: 3px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 11px;
      }
      #ss-chat-logout:hover { background: rgba(255,255,255,.15); }
      #ss-chat-close {
        background: none;
        border: none;
        color: #fff;
        font-size: 16px;
        cursor: pointer;
        line-height: 1;
        padding: 0;
      }
      #ss-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 320px;
      }
      #ss-chatbot.ss-resident #ss-chat-messages {
        max-height: 400px;
      }
      .ss-msg {
        max-width: 88%;
        padding: 8px 11px;
        border-radius: 10px;
        line-height: 1.5;
        white-space: pre-wrap;
        word-wrap: break-word;
      }
      .ss-msg-user {
        align-self: flex-end;
        background: #2c5f8a;
        color: #fff;
        border-bottom-right-radius: 3px;
      }
      .ss-msg-bot {
        align-self: flex-start;
        background: #f0f0f0;
        color: #222;
        border-bottom-left-radius: 3px;
      }
      #ss-chatbot.ss-resident .ss-msg-bot {
        background: #fff;
        border: 1px solid #e0e0e0;
      }
      .ss-msg-thinking {
        color: #999;
        font-style: italic;
      }
      .ss-login-btn {
        display: inline-block;
        margin-top: 8px;
        padding: 7px 14px;
        background: #2c5f8a;
        color: #fff !important;
        border-radius: 6px;
        text-decoration: none;
        font-size: 13px;
        font-weight: bold;
        cursor: pointer;
        border: none;
      }
      .ss-login-btn:hover { background: #1e4a6d; }
      #ss-chat-input-row {
        display: flex;
        border-top: 1px solid #ddd;
        padding: 8px;
        gap: 6px;
        flex-shrink: 0;
      }
      #ss-chat-input {
        flex: 1;
        padding: 7px 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 13px;
        outline: none;
      }
      #ss-chat-send {
        padding: 7px 14px;
        background: #2c5f8a;
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
      }
      #ss-chat-send:disabled { opacity: .5; cursor: default; }
      #ss-chat-fab {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 52px;
        height: 52px;
        background: #2c5f8a;
        color: #fff;
        border: none;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 3px 10px rgba(0,0,0,.25);
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      #ss-chat-bubble {
        position: fixed;
        bottom: 28px;
        right: 82px;
        background: #fff;
        color: #2c5f8a;
        font-family: sans-serif;
        font-size: 13px;
        font-weight: bold;
        padding: 6px 10px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,.2);
        z-index: 9998;
        white-space: nowrap;
        cursor: pointer;
      }
      #ss-chat-bubble::after {
        content: '';
        position: absolute;
        right: -7px;
        top: 50%;
        transform: translateY(-50%);
        border-width: 6px 0 6px 8px;
        border-style: solid;
        border-color: transparent transparent transparent #fff;
      }
    `;
    document.head.appendChild(style);
  }

  // --- Widget shell ---
  function getOrCreateWidget() {
    let w = document.getElementById(WIDGET_ID);
    if (!w) {
      w = document.createElement('div');
      w.id = WIDGET_ID;
      document.body.appendChild(w);
    }
    return w;
  }

  // --- Public (deflector) mode ---
  function showPublicMode(building) {
    widgetMode = 'public';
    chatHistory = [];
    const widget = getOrCreateWidget();
    widget.className = '';
    widget.innerHTML = `
      <div id="ss-chat-header">
        <span>Woolsy</span>
        <div id="ss-chat-header-right">
          <button id="ss-chat-close" aria-label="Close">✕</button>
        </div>
      </div>
      <div id="ss-chat-messages"></div>
      <div id="ss-chat-input-row">
        <input id="ss-chat-input" type="text" placeholder="Ask a question…" autocomplete="off" />
        <button id="ss-chat-send">Send</button>
      </div>
    `;
    widget.style.display = 'flex';

    document.getElementById('ss-chat-close').onclick = closeChatbot;
    document.getElementById('ss-chat-send').onclick  = () => handlePublicSend(building);
    document.getElementById('ss-chat-input').addEventListener('keydown', e => {
      if (e.key === 'Enter') handlePublicSend(building);
    });

    addMessage('bot', 'Hi! I\'m Woolsy, your community assistant. What would you like to know?');
    document.getElementById('ss-chat-input').focus();
  }

  function handlePublicSend(building) {
    const input    = document.getElementById('ss-chat-input');
    const button   = document.getElementById('ss-chat-send');
    const question = input.value.trim();
    if (!question) return;

    input.value     = '';
    button.disabled = true;

    addMessage('user', escapeHtml(question));

    const deflection = getDeflection(question);
    const loginUrl   = `${SCRIPTS_URL}/display-private-dir.php?building=${encodeURIComponent(building)}`;

    addMessage('bot',
      escapeHtml(deflection) +
      `<br><a class="ss-login-btn" href="${loginUrl}" target="_blank">Log in to chat →</a>`
    );

    button.disabled = false;
    document.getElementById('ss-chat-input').focus();
  }

  // --- Resident (full chat) mode ---
  function showResidentMode(username, building) {
    widgetMode  = 'resident';
    chatHistory = [];
    const widget = getOrCreateWidget();
    widget.className = 'ss-resident';
    widget.innerHTML = `
      <div id="ss-chat-header">
        <span>Woolsy</span>
        <div id="ss-chat-header-right">
          <span id="ss-chat-username">${escapeHtml(username)}</span>
          <button id="ss-chat-logout">Log out</button>
          <button id="ss-chat-close" aria-label="Close">✕</button>
        </div>
      </div>
      <div id="ss-chat-messages"></div>
      <div id="ss-chat-input-row">
        <input id="ss-chat-input" type="text" placeholder="Ask anything about your community…" autocomplete="off" />
        <button id="ss-chat-send">Send</button>
      </div>
    `;
    widget.style.display = 'flex';

    document.getElementById('ss-chat-close').onclick  = closeChatbot;
    document.getElementById('ss-chat-logout').onclick = () => handleLogout(building);
    document.getElementById('ss-chat-send').onclick   = () => handleResidentSend(building);
    document.getElementById('ss-chat-input').addEventListener('keydown', e => {
      if (e.key === 'Enter') handleResidentSend(building);
    });

    const greeting = `Hi ${escapeHtml(capitalize(username))}! I\'m Woolsy, your community assistant. Ask me anything about your community — rules, procedures, documents, amenities.`;
    addMessage('bot', greeting);
    document.getElementById('ss-chat-input').focus();
  }

  async function handleResidentSend(building) {
    const input = document.getElementById('ss-chat-input');
    const btn   = document.getElementById('ss-chat-send');
    const q     = input.value.trim();
    if (!q) return;

    input.value  = '';
    btn.disabled = true;

    addMessage('user', escapeHtml(q));
    const thinking = addMessage('bot', '…');
    thinking.classList.add('ss-msg-thinking');

    try {
      const res  = await fetch(`${SCRIPTS_URL}/chatbot.php`, {
        method:      'POST',
        credentials: 'same-origin',
        headers:     { 'Content-Type': 'application/json' },
        body:        JSON.stringify({ building, question: q, history: chatHistory }),
      });
      const data   = await res.json();
      const answer = data.answer || 'Sorry, I couldn\'t get a response. Please try again.';

      thinking.textContent = answer;
      thinking.classList.remove('ss-msg-thinking');

      chatHistory.push({ role: 'user',      content: q      });
      chatHistory.push({ role: 'assistant', content: answer });
      if (chatHistory.length > 12) chatHistory = chatHistory.slice(-12);

    } catch {
      thinking.textContent = 'Something went wrong. Please try again.';
      thinking.classList.remove('ss-msg-thinking');
    }

    btn.disabled = false;
    document.getElementById('ss-chat-input')?.focus();
  }

  async function handleLogout(building) {
    await logout(building);
    showPublicMode(building);
  }

  // --- Shared helpers ---
  function addMessage(role, html) {
    const messages = document.getElementById('ss-chat-messages');
    const div = document.createElement('div');
    div.className = 'ss-msg ' + (role === 'user' ? 'ss-msg-user' : 'ss-msg-bot');
    div.innerHTML = html;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function capitalize(str) {
    return str ? str.charAt(0).toUpperCase() + str.slice(1) : str;
  }

  // --- FAB ---
  function createFab() {
    if (document.getElementById('ss-chat-fab')) return;

    const fab = document.createElement('button');
    fab.id      = 'ss-chat-fab';
    fab.title   = 'Ask Woolsy';
    fab.innerHTML = '<span style="display:flex;align-items:center;justify-content:center;width:46px;height:46px;background:#fff;border-radius:50%;"><img src="https://sheepsite.com/Scripts/assets/Woolsy-standing-transparent.png" height="38" alt="Woolsy" style="display:block;"></span>';
    fab.onclick = openChatbot;
    document.body.appendChild(fab);

    const bubble = document.createElement('div');
    bubble.id      = 'ss-chat-bubble';
    bubble.textContent = 'Ask Woolsy!';
    bubble.onclick = openChatbot;
    document.body.appendChild(bubble);
  }

  function hideFab() {
    const fab    = document.getElementById('ss-chat-fab');
    const bubble = document.getElementById('ss-chat-bubble');
    if (fab)    fab.style.display    = 'none';
    if (bubble) bubble.style.display = 'none';
  }

  function showFab() {
    const fab    = document.getElementById('ss-chat-fab');
    const bubble = document.getElementById('ss-chat-bubble');
    if (fab)    fab.style.display    = 'flex';
    if (bubble) bubble.style.display = 'block';
  }

  function closeChatbot() {
    const w = document.getElementById(WIDGET_ID);
    if (w) w.style.display = 'none';
    showFab();
  }

  // --- Public API ---
  async function openChatbot() {
    injectStyles();
    createFab();
    hideFab();

    const building  = window.BUILDING_NAME || '';
    const username  = await whoami(building);

    if (username) {
      showResidentMode(username, building);
    } else {
      showPublicMode(building);
    }
  }

  window.openChatbot = openChatbot;

  // Auto-init: show FAB on page load
  window.addEventListener('load', function () {
    injectStyles();
    createFab();
    showFab();
  });

})();
