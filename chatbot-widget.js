// chatbot-widget.js — FAQ chatbot widget for building sites
//
// Public mode  : cheeky deflections, zero API cost, login CTA
// Resident mode: opens chatbot-page.php in an iframe overlay (session-aware)
//
// Requires BUILDING_NAME to be set by the footer script before this loads.
// Add openChatbot() to any button, or the floating bubble appears automatically.

(function () {
  const SCRIPTS_URL = 'https://sheepsite.com/Scripts';
  const WIDGET_ID   = 'ss-chatbot';

  // Cheeky deflections — picked randomly, optionally keyword-nudged
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
      #ss-chat-header {
        background: #2c5f8a;
        color: #fff;
        padding: 10px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
      }
      #ss-chat-close {
        background: none;
        border: none;
        color: #fff;
        font-size: 16px;
        cursor: pointer;
        line-height: 1;
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

  // --- Public widget (cheeky deflector) ---
  function createPublicWidget() {
    if (document.getElementById(WIDGET_ID)) return;

    injectStyles();

    const widget = document.createElement('div');
    widget.id = WIDGET_ID;
    widget.innerHTML = `
      <div id="ss-chat-header">
        <span>Woolsy</span>
        <button id="ss-chat-close" aria-label="Close">✕</button>
      </div>
      <div id="ss-chat-messages"></div>
      <div id="ss-chat-input-row">
        <input id="ss-chat-input" type="text" placeholder="Ask a question…" autocomplete="off" />
        <button id="ss-chat-send">Send</button>
      </div>
    `;
    document.body.appendChild(widget);

    // Floating button + speech bubble
    if (!document.getElementById('ss-chat-fab')) {
      const fab = document.createElement('button');
      fab.id = 'ss-chat-fab';
      fab.innerHTML = '🐑';
      fab.title = 'Woolsy';
      fab.onclick = openChatbot;
      document.body.appendChild(fab);

      const bubble = document.createElement('div');
      bubble.id = 'ss-chat-bubble';
      bubble.textContent = 'Ask Woolsy!';
      bubble.onclick = openChatbot;
      document.body.appendChild(bubble);
    }

    document.getElementById('ss-chat-close').onclick = closeChatbot;
    document.getElementById('ss-chat-send').onclick  = handleSend;
    document.getElementById('ss-chat-input').addEventListener('keydown', e => {
      if (e.key === 'Enter') handleSend();
    });

    addMessage('bot', 'Hi! I\'m Woolsy, your community assistant. What would you like to know?');
  }

  function addMessage(role, html) {
    const messages = document.getElementById('ss-chat-messages');
    const div = document.createElement('div');
    div.className = 'ss-msg ' + (role === 'user' ? 'ss-msg-user' : 'ss-msg-bot');
    div.innerHTML = html;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }

  function handleSend() {
    const input    = document.getElementById('ss-chat-input');
    const button   = document.getElementById('ss-chat-send');
    const question = input.value.trim();
    if (!question) return;

    input.value      = '';
    button.disabled  = true;

    addMessage('user', escapeHtml(question));

    const deflection = getDeflection(question);
    const building   = window.BUILDING_NAME || '';
    const chatUrl    = `${SCRIPTS_URL}/chatbot-page.php?building=${encodeURIComponent(building)}&q=${encodeURIComponent(question)}`;

    addMessage('bot',
      escapeHtml(deflection) +
      `<br><button class="ss-login-btn" onclick="window._ssChatOpenResident('${escapeAttr(chatUrl)}')">
        Open Resident Chat →
      </button>`
    );

    button.disabled = false;
    input.focus();
  }


  function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function escapeAttr(str) {
    return str.replace(/'/g, "\\'");
  }

  // Called by the login button inside the message bubble
  window._ssChatOpenResident = function (url) {
    openResidentChat(url);
  };

  function openResidentChat(url) {
    closeChatbot();
    window.open(url, 'woolsy-chat', 'width=440,height=620,resizable=yes');
  }

  // --- Public API ---
  window.openChatbot = function () {
    createPublicWidget();
    document.getElementById(WIDGET_ID).style.display = 'flex';
    const fab = document.getElementById('ss-chat-fab');
    if (fab) fab.style.display = 'none';
    const bubble = document.getElementById('ss-chat-bubble');
    if (bubble) bubble.style.display = 'none';
    document.getElementById('ss-chat-input').focus();
  };

  function closeChatbot() {
    const w = document.getElementById(WIDGET_ID);
    if (w) w.style.display = 'none';
    const fab = document.getElementById('ss-chat-fab');
    if (fab) fab.style.display = 'flex';
    const bubble = document.getElementById('ss-chat-bubble');
    if (bubble) bubble.style.display = 'block';
  }

  // Auto-init: show the floating sheep button on page load
  window.addEventListener('load', function () {
    createPublicWidget();
    const fab = document.getElementById('ss-chat-fab');
    if (fab) fab.style.display = 'flex';
  });

})();
