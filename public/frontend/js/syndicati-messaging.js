/**
 * Syndicati Messaging UI v1.6
 * Group Chats, Attachments, and Premium 1-to-1 Messaging
 */

(function () {
  'use strict';

  // Duplicate Protection
  if (window.SyndicatiMessagingLoaded) return;
  window.SyndicatiMessagingLoaded = true;

  function initMessaging() {
    console.log('Syndicati Messaging (Premium UI) Initializing...');

    // Create styles
    const style = document.createElement('style');
    style.textContent = `
      #syndicati-messaging-trigger {
        position: fixed !important; bottom: 30px !important; right: 104px !important;
        width: 64px !important; height: 64px !important;
        background: rgba(10, 10, 10, 0.8) !important;
        backdrop-filter: blur(12px) saturate(160%) !important;
        -webkit-backdrop-filter: blur(12px) saturate(160%) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 50% !important; cursor: pointer !important;
        z-index: 2147483000 !important; display: flex !important;
        align-items: center !important; justify-content: center !important;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), inset 0 0 15px rgba(255, 255, 255, 0.05) !important;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
        padding: 0 !important; outline: none !important;
      }
      #syndicati-messaging-trigger:hover {
        transform: scale(1.1) translateY(-5px) !important;
        border-color: rgba(0, 198, 255, 0.5) !important;
      }
      
      #syndicati-messaging-panel {
        position: fixed !important; bottom: 110px !important; right: 30px !important;
        width: 440px !important; height: 680px !important;
        background: rgba(10, 10, 12, 0.95) !important;
        backdrop-filter: blur(30px) saturate(200%) !important;
        -webkit-backdrop-filter: blur(30px) saturate(200%) !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
        border-radius: 32px !important;
        box-shadow: 0 40px 80px -15px rgba(0, 0, 0, 0.8) !important;
        z-index: 2147483100 !important; display: none !important;
        flex-direction: column !important; overflow: hidden !important;
        font-family: 'Outfit', 'Inter', sans-serif !important;
        transform: scale(0.95) translateY(30px) !important; opacity: 0 !important;
        transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1) !important;
      }
      #syndicati-messaging-panel.open { display: flex !important; transform: scale(1) translateY(0) !important; opacity: 1 !important; }

      .msg-header {
        padding: 20px 24px !important; border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        display: flex !important; justify-content: space-between !important; align-items: center !important;
        background: linear-gradient(to bottom, rgba(255, 255, 255, 0.03), transparent) !important;
      }
      .msg-header-title { font-weight: 800 !important; font-size: 18px !important; color: #fff !important; display: flex !important; align-items: center !important; gap: 12px !important; }
      .header-actions { display: flex; gap: 14px; align-items: center; }
      .msg-close, .msg-info-btn { background: transparent !important; border: none !important; color: rgba(255, 255, 255, 0.4) !important; font-size: 22px !important; cursor: pointer !important; transition: all 0.2s; }
      .msg-close:hover, .msg-info-btn:hover { color: #fff !important; transform: rotate(90deg); }
      .msg-info-btn:hover { transform: scale(1.1); }

      .msg-body { flex: 1 !important; overflow: hidden !important; padding: 0 !important; display: flex !important; flex-direction: column !important; }
      
      /* View System */
      .msg-view { 
        display: none !important; flex-direction: column; height: 100%; width: 100%; 
        padding: 20px !important; box-sizing: border-box !important;
        animation: slideIn 0.3s ease-out;
      }
      .msg-view.active { display: flex !important; }
      
      @keyframes slideIn {
        from { opacity: 0; transform: translateX(10px); }
        to { opacity: 1; transform: translateX(0); }
      }

      .scroll-container { flex: 1; overflow-y: auto; padding-right: 5px; }
      .scroll-container::-webkit-scrollbar { width: 4px; }
      .scroll-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }

      /* Conversation List */
      .conv-item {
        display: flex !important; align-items: center !important; gap: 16px !important;
        padding: 14px 16px !important; border-radius: 20px !important; margin-bottom: 10px !important;
        background: rgba(255, 255, 255, 0.02) !important; cursor: pointer !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; border: 1px solid rgba(255, 255, 255, 0.03) !important;
      }
      .conv-item:hover { 
        background: rgba(255, 255, 255, 0.06) !important; 
        border-color: rgba(0, 198, 255, 0.3) !important;
        transform: translateY(-2px);
      }
      .conv-avatar { 
        width: 52px !important; height: 52px !important; border-radius: 18px !important; 
        object-fit: cover !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; 
        background: linear-gradient(135deg, #1a1a1a, #0a0a0a); display: flex; 
        align-items: center; justify-content: center; font-size: 24px; position: relative;
      }
      .conv-avatar img { width: 100%; height: 100%; border-radius: 18px; object-fit: cover; }
      .conv-info { flex: 1 !important; min-width: 0 !important; }
      .conv-name { font-weight: 700 !important; color: #fff !important; font-size: 15px !important; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between; }
      .conv-last { font-size: 13px !important; color: rgba(255, 255, 255, 0.4) !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; display: block; }
      .conv-group-badge { font-size: 9px !important; background: rgba(0, 198, 255, 0.15) !important; color: #00c6ff !important; padding: 2px 8px !important; border-radius: 100px !important; text-transform: uppercase; letter-spacing: 0.5px; }

      /* Action Buttons */
      .main-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
      .action-btn { 
        padding: 14px !important; border-radius: 16px !important; border: 1px solid rgba(255, 255, 255, 0.05) !important;
        background: rgba(255, 255, 255, 0.03) !important; color: #fff !important; font-weight: 700 !important;
        cursor: pointer !important; transition: all 0.3s !important; font-size: 13px !important;
        display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important;
      }
      .action-btn:hover { background: rgba(255, 255, 255, 0.08) !important; border-color: rgba(0, 198, 255, 0.4) !important; transform: translateY(-2px); }
      .action-btn.primary { background: linear-gradient(135deg, #00c6ff, #0072ff) !important; color: #fff !important; border: none !important; }
      .action-btn.primary:hover { box-shadow: 0 8px 20px rgba(0, 198, 255, 0.3) !important; }

      /* Friend Selection Grid */
      .friend-grid { 
        display: grid !important; grid-template-columns: repeat(2, 1fr) !important; 
        gap: 12px !important; margin-bottom: 10px !important; 
      }
      .friend-card {
        background: rgba(255, 255, 255, 0.02) !important; border: 1px solid rgba(255, 255, 255, 0.05) !important;
        border-radius: 20px !important; padding: 12px !important; display: flex !important; flex-direction: column !important;
        align-items: center !important; text-align: center !important; cursor: pointer !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; position: relative !important;
      }
      .friend-card:hover { background: rgba(255, 255, 255, 0.05) !important; border-color: rgba(255, 255, 255, 0.1) !important; transform: scale(1.02); }
      .friend-card.selected { 
        background: rgba(0, 198, 255, 0.08) !important; 
        border-color: #00c6ff !important; 
        box-shadow: 0 0 15px rgba(0, 198, 255, 0.2) !important;
      }
      .friend-card .card-avatar { width: 48px; height: 48px; border-radius: 16px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.1); }
      .friend-card .card-name { font-size: 12px; font-weight: 700; color: #fff; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .card-check { position: absolute; top: 8px; right: 8px; width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 10px; color: transparent; transition: all 0.2s; }
      .selected .card-check { background: #00c6ff; border-color: #00c6ff; color: #fff; }

      /* Chat Elements */
      .chat-messages { flex: 1 !important; overflow-y: auto !important; padding: 5px !important; display: flex !important; flex-direction: column !important; gap: 14px !important; }
      .chat-bubble { max-width: 82% !important; padding: 12px 16px !important; border-radius: 22px !important; font-size: 14px !important; line-height: 1.5 !important; position: relative !important; }
      .chat-bubble.mine { align-self: flex-end !important; background: linear-gradient(135deg, #00c6ff, #0072ff) !important; color: #fff !important; border-bottom-right-radius: 4px !important; box-shadow: 0 4px 15px rgba(0, 114, 255, 0.2) !important; }
      .chat-bubble.theirs { align-self: flex-start !important; background: rgba(255, 255, 255, 0.06) !important; color: #fff !important; border-bottom-left-radius: 4px !important; border: 1px solid rgba(255,255,255,0.03) !important; }
      .msg-sender-name { font-size: 11px !important; color: rgba(0, 198, 255, 0.8) !important; font-weight: 700 !important; margin-bottom: 4px !important; display: block; }
      
      .chat-input-area { padding: 16px 0 0 0 !important; border-top: 1px solid rgba(255, 255, 255, 0.05) !important; display: flex !important; flex-direction: column !important; gap: 10px !important; }
      .input-row { display: flex !important; gap: 10px !important; align-items: center !important; }
      .chat-input { flex: 1 !important; background: rgba(255, 255, 255, 0.04) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 16px !important; padding: 12px 18px !important; color: #fff !important; outline: none !important; font-size: 14px !important; transition: all 0.2s; }
      .chat-input:focus { background: rgba(255, 255, 255, 0.07); border-color: rgba(0, 198, 255, 0.4); }
      .chat-btn { 
        background: rgba(255, 255, 255, 0.04) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; 
        color: #fff !important; border-radius: 16px !important; width: 44px !important; height: 44px !important; 
        display: flex !important; align-items: center !important; justify-content: center !important; 
        cursor: pointer !important; font-size: 18px !important; transition: all 0.3s !important; 
      }
      .chat-btn:hover { background: rgba(0, 198, 255, 0.1) !important; border-color: #00c6ff !important; color: #00c6ff !important; transform: rotate(5deg); }
      
      .msg-attachments { display: flex !important; flex-wrap: wrap !important; gap: 8px !important; margin-top: 10px !important; }
      .attach-item { border-radius: 12px !important; overflow: hidden !important; border: 1px solid rgba(255,255,255,0.1) !important; max-width: 100%; transition: all 0.2s; }
      .attach-item:hover { transform: scale(1.02); }

      .msg-back { color: rgba(255,255,255,0.5); cursor: pointer; font-size: 14px; font-weight: 700; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; transition: color 0.2s; }
      .msg-back:hover { color: #00c6ff; }

      .search-box { position: relative; margin-bottom: 15px; }
      .search-box input { width: 100%; background: rgba(255, 255, 255, 0.03) ; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; padding: 12px 16px; color: #fff; font-size: 14px; outline: none; transition: all 0.2s; box-sizing: border-box; }
      .search-box input:focus { border-color: rgba(0,198,255,0.4); background: rgba(255, 255, 255, 0.05); }

      /* Empty State */
      .empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: rgba(255,255,255,0.2); gap: 15px; text-align: center; }
      .empty-icon { font-size: 48px; opacity: 0.5; }
    `;
    document.head.appendChild(style);

    // Build Static structure
    const trigger = document.createElement('button');
    trigger.id = 'syndicati-messaging-trigger';
    trigger.innerHTML = '<canvas id="msg-net-canvas" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:50%;"></canvas><span style="position:relative;z-index:2;font-size:30px">💬</span>';
    document.body.appendChild(trigger);

    const panel = document.createElement('div');
    panel.id = 'syndicati-messaging-panel';
    panel.innerHTML = `
      <div class="msg-header">
        <div class="msg-header-title"><span id="header-icon">💬</span> <span id="msg-title-text">Messaging</span></div>
        <div class="header-actions">
           <button class="msg-info-btn" id="btn-group-info" style="display:none" title="Group Info">ℹ️</button>
           <button class="msg-close">×</button>
        </div>
      </div>
      
      <div class="msg-body msg-view active" id="view-list">
        <div class="main-actions">
           <button class="action-btn" id="btn-msg-friend">👤 Message Friend</button>
           <button class="action-btn primary" id="btn-start-group">👥 Create Group</button>
        </div>
        <div class="scroll-container" id="conv-list-container"></div>
      </div>

      <div class="msg-body msg-view" id="view-chat">
        <div class="msg-back" id="btn-back-to-list">← Back to Messages</div>
        <div class="chat-messages" id="chat-messages-container"></div>
        <div class="preview-area" id="file-preview-area" style="display:none; padding:10px; background:rgba(255,255,255,0.03); border-radius:12px; margin-top:10px; display:flex; gap:8px; overflow-x:auto;"></div>
        <div class="chat-input-area">
          <div class="input-row">
            <button class="chat-btn" id="btn-attach" title="Attach Files">📎</button>
            <input type="text" class="chat-input" id="chat-msg-input" placeholder="Type a message..." />
            <button class="chat-btn" id="btn-send-msg">▶</button>
          </div>
          <input type="file" id="file-hidden-input" multiple style="display:none" />
        </div>
      </div>

      <div class="msg-body msg-view" id="view-create">
        <div class="msg-back" id="btn-cancel-create">← Back</div>
        <input type="text" class="chat-input" id="group-name-input" placeholder="Give your group a name..." style="margin-bottom:15px" />
        <div class="search-box">
          <input type="text" id="group-friend-search" placeholder="Search friends to add..." />
        </div>
        <div class="scroll-container">
          <div class="friend-grid" id="create-friends-list"></div>
        </div>
        <button class="action-btn primary" id="btn-finalize-group" style="margin-top:20px">Create Group</button>
      </div>

      <div class="msg-body msg-view" id="view-friends">
        <div class="msg-back" id="btn-cancel-friends">← Back</div>
        <div class="search-box">
          <input type="text" id="single-friend-search" placeholder="Who would you like to message?" />
        </div>
        <div class="scroll-container">
          <div class="friend-grid" id="single-friends-list"></div>
        </div>
      </div>

      <div class="msg-body msg-view" id="view-info">
        <div class="msg-back" id="btn-back-to-chat">← Back to Chat</div>
        <div style="font-size:18px;font-weight:800;color:#fff;margin-bottom:20px;text-align:center;" id="info-group-name">Members</div>
        <div class="scroll-container participant-list" id="group-participants-list"></div>
      </div>
    `;
    document.body.appendChild(panel);

    // Particle Effect Logic
    (function () {
      const canvas = document.getElementById('msg-net-canvas');
      const ctx = canvas.getContext('2d');
      let p = []; let h = false;
      canvas.width = 124; canvas.height = 124;
      class Part {
        constructor() { this.r(); }
        r() { this.x = Math.random() * 124; this.y = Math.random() * 124; this.vx = (Math.random() - 0.5) * 0.6; this.vy = (Math.random() - 0.5) * 0.6; }
        u() { let s = h ? 3.8 : 1.3; this.x += this.vx * s; this.y += this.vy * s; if (this.x < 0 || this.x > 124) this.vx *= -1; if (this.y < 0 || this.y > 124) this.vy *= -1; }
      }
      for (let i = 0; i < 12; i++) p.push(new Part());
      function l() {
        ctx.clearRect(0, 0, 124, 124);
        p.forEach((pi, i) => {
          pi.u();
          p.slice(i + 1).forEach(pj => {
            let d = Math.hypot(pi.x - pj.x, pi.y - pj.y);
            if (d < 45) {
              ctx.beginPath(); ctx.moveTo(pi.x, pi.y); ctx.lineTo(pj.x, pj.y);
              ctx.strokeStyle = `rgba(0,198,255,${(1 - d / 45) * (h ? 0.9 : 0.4)})`; ctx.lineWidth = 0.7; ctx.stroke();
            }
          });
          ctx.beginPath(); ctx.arc(pi.x, pi.y, 1.5, 0, 7); ctx.fillStyle = h ? '#fff' : 'rgba(0,198,255,0.5)'; ctx.fill();
        });
        requestAnimationFrame(l);
      }
      trigger.onmouseenter = () => h = true; trigger.onmouseleave = () => h = false;
      l();
    })();

    // State Variables
    let curConvId = null;
    let curIsGrp = false;
    let curOwned = false;
    let myUid = null;
    let files = [];
    let pollId = null;
    let friendsList = [];
    let activeRecipientId = null;

    // Navigation
    function switchView(vid) {
      document.querySelectorAll('.msg-view').forEach(v => v.classList.remove('active'));
      const activeView = document.getElementById('view-' + vid);
      if (activeView) activeView.classList.add('active');
    }

    async function initInternal() {
      try { const r = await fetch('/api/messaging/me'); const d = await r.json(); myUid = d.id; } catch (e) { }
    }
    initInternal();

    trigger.onclick = () => {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) { switchView('list'); loadConvs(); }
      else stopPoll();
    };
    panel.querySelector('.msg-close').onclick = () => { panel.classList.remove('open'); stopPoll(); };

    // Conversations List
    async function loadConvs() {
      document.getElementById('msg-title-text').innerText = 'Messaging';
      document.getElementById('btn-group-info').style.display = 'none';
      activeRecipientId = null;
      const c = document.getElementById('conv-list-container');
      c.innerHTML = '<div class="empty-state"><div class="empty-icon">📂</div><span>Syncing secure channels...</span></div>';
      try {
        const r = await fetch('/api/messaging/conversations'); const d = await r.json();
        if (!d.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">💎</div><span>No active conversations.<br>Select a friend to begin.</span></div>'; return; }
        c.innerHTML = '';
        d.forEach(cv => {
          const i = document.createElement('div'); i.className = 'conv-item';
          const av = cv.is_group ? '👥' : (cv.avatar ? `<img src="/${cv.avatar}">` : '👤');
          i.innerHTML = `
            <div class="conv-avatar">${av}</div>
            <div class="conv-info">
              <div class="conv-name">
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${cv.name}</span>
                ${cv.is_group ? '<span class="conv-group-badge">Group</span>' : ''}
              </div>
              <div class="conv-last">${cv.last_message ? (cv.last_message.is_user ? 'You: ' : '') + cv.last_message.content : 'Secure signal detected'}</div>
            </div>
          `;
          i.onclick = () => { curIsGrp = cv.is_group; curOwned = cv.creator_id === myUid; openChat(cv.id, cv.name); };
          c.appendChild(i);
        });
      } catch (e) { c.innerHTML = '<div style="color:#ff4d4d;text-align:center;padding:20px">Error connecting to relay.</div>'; }
    }

    // Messaging UI
    async function openChat(id, name) {
      curConvId = id; activeRecipientId = null;
      document.getElementById('msg-title-text').innerText = name;
      document.getElementById('btn-group-info').style.display = curIsGrp ? 'block' : 'none';
      switchView('chat');
      document.getElementById('chat-messages-container').innerHTML = '<div class="empty-state"><div class="empty-icon">🔒</div><span>Handshaking secure link...</span></div>';
      await fetchMsgs();
      startPoll();
    }

    let isFetching = false;

    async function fetchMsgs() {
      if (!curConvId || isFetching) return;
      isFetching = true;
      try {
        const r = await fetch('/api/messaging/messages/' + curConvId);
        if (!r.ok) {
          const text = await r.text();
          if (text.includes('<!-- Typed')) throw new Error('Server Error (500 HTML)');
          throw new Error('HTTP ' + r.status);
        }
        const d = await r.json();
        const c = document.getElementById('chat-messages-container');
        const bot = c.scrollHeight - c.scrollTop <= c.clientHeight + 150;
        if (d.error) { c.innerHTML = `<div style="color:red;padding:20px;text-align:center">${d.error}</div>`; stopPoll(); return; }
        c.innerHTML = '';
        if (!d.length) { c.innerHTML = '<div class="empty-state"><div class="empty-icon">🤝</div><span>Secure channel established.<br>Start the conversation.</span></div>'; }
        d.forEach(m => {
          const b = document.createElement('div'); b.className = 'chat-bubble ' + (m.is_user ? 'mine' : 'theirs');
          let h = '';
          if (curIsGrp && !m.is_user) h += `<span class="msg-sender-name">${m.sender_name}</span>`;
          h += `<div>${m.content}</div>`;
          if (m.attachments?.length) {
            h += '<div class="msg-attachments">';
            m.attachments.forEach(a => {
              if (a.kind === 'IMAGE') h += `<div class="attach-item"><img src="/${a.path}" style="max-width:100%;border-radius:12px;cursor:pointer" onclick="window.open('/${a.path}')"></div>`;
              else h += `<div class="attach-item"><a href="/${a.path}" target="_blank" style="padding:10px;background:rgba(255,255,255,0.05);display:block;color:#fff;text-decoration:none;border-radius:12px;font-size:12px">📄 ${a.name}</a></div>`;
            });
            h += '</div>';
          }
          b.innerHTML = h; c.appendChild(b);
        });
        if (bot) c.scrollTop = c.scrollHeight;
      } catch (e) {
        console.error('Signal Interruption:', e);
      } finally {
        isFetching = false;
        if (curConvId && pollId) {
          pollId = setTimeout(fetchMsgs, 5000);
        }
      }
    }

    async function sendMsg() {
      const i = document.getElementById('chat-msg-input'); const t = i.value.trim();
      if (!t && !files.length) return;

      const fd = new FormData();
      fd.append('content', t);
      if (curConvId) fd.append('conversation_id', curConvId);
      if (activeRecipientId) fd.append('recipient_id', activeRecipientId);

      files.forEach(f => fd.append('files[]', f));
      i.value = ''; files = []; updatePre();
      try {
        const r = await fetch('/api/messaging/send', { method: 'POST', body: fd }); const d = await r.json();
        if (d.success) {
          if (d.conversation_id && !curConvId) {
            curConvId = d.conversation_id;
            activeRecipientId = null;
            startPoll();
          }
          fetchMsgs();
        }
      } catch (e) { }
    }

    document.getElementById('btn-send-msg').onclick = sendMsg;
    document.getElementById('chat-msg-input').onkeypress = (e) => { if (e.key === 'Enter') sendMsg(); };
    document.getElementById('btn-back-to-list').onclick = () => { stopPoll(); curConvId = null; loadConvs(); switchView('list'); };

    // Attachments Logic
    const hIn = document.getElementById('file-hidden-input');
    document.getElementById('btn-attach').onclick = () => hIn.click();
    hIn.onchange = () => {
      Array.from(hIn.files).forEach(f => { if (!files.some(ef => ef.name === f.name)) files.push(f); });
      updatePre(); hIn.value = '';
    };
    function updatePre() {
      const a = document.getElementById('file-preview-area'); a.innerHTML = '';
      if (!files.length) { a.style.display = 'none'; return; }
      a.style.display = 'flex';
      files.forEach((f, idx) => {
        const d = document.createElement('div'); d.className = 'preview-item';
        d.style.cssText = 'position:relative;width:56px;height:56px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,0.1)';
        if (f.type.startsWith('image/')) { const img = document.createElement('img'); img.src = URL.createObjectURL(f); img.style.cssText = 'width:100%;height:100%;object-fit:cover'; d.appendChild(img); }
        else d.innerHTML = '<div style="background:#222;height:100%;display:flex;align-items:center;justify-content:center;font-size:24px">📄</div>';
        const rem = document.createElement('div'); rem.className = 'preview-remove'; rem.innerText = '×';
        rem.style.cssText = 'position:absolute;top:0;right:0;background:#ff4d4d;color:#fff;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer;border-radius:0 0 0 8px';
        rem.onclick = () => { files.splice(idx, 1); updatePre(); };
        d.appendChild(rem); a.appendChild(d);
      });
    }

    // Group Management Logic
    document.getElementById('btn-start-group').onclick = () => loadSelection('create');
    document.getElementById('btn-cancel-create').onclick = () => switchView('list');
    document.getElementById('group-friend-search').oninput = () => renderSelection('create');

    document.getElementById('btn-finalize-group').onclick = async () => {
      const btn = document.getElementById('btn-finalize-group');
      const name = document.getElementById('group-name-input').value.trim() || 'Syndicati Group';
      const uids = Array.from(document.querySelectorAll('#create-friends-list .selected')).map(el => parseInt(el.dataset.id));

      if (!uids.length) return window.pushNotif('Groupe', 'Veuillez sélectionner des amis.', 'ERROR');

      btn.disabled = true;
      btn.innerText = 'Creating Room...';

      try {
        const r = await fetch('/api/messaging/create-group', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, participants: uids })
        });

        const d = await r.json();
        if (d.success) {
          curIsGrp = true;
          curOwned = true;
          openChat(d.conversation_id, name);
          window.pushNotif('Messenger', 'Group chat created successfully', 'SUCCESS');
        } else {
          window.pushNotif('Signal Error', d.error || 'Failed to create group', 'ERROR');
        }
      } catch (e) {
        console.error('Group Creation Failure:', e);
        window.pushNotif('System Error', 'Could not establish secure group link', 'ERROR');
      } finally {
        btn.disabled = false;
        btn.innerText = 'Create Group';
      }
    };

    // Single Messaging Logic
    document.getElementById('btn-msg-friend').onclick = () => loadSelection('friends');
    document.getElementById('btn-cancel-friends').onclick = () => switchView('list');
    document.getElementById('single-friend-search').oninput = () => renderSelection('friends');

    async function loadSelection(vid) {
      if (vid === 'create') {
        document.getElementById('group-name-input').value = '';
        document.getElementById('group-friend-search').value = '';
      } else {
        document.getElementById('single-friend-search').value = '';
      }

      switchView(vid);
      const c = document.getElementById(vid === 'create' ? 'create-friends-list' : 'single-friends-list');
      c.innerHTML = '<div style="text-align:center;padding:20px;grid-column:1/span 2;color:rgba(255,255,255,0.2)">Syncing friend signatures...</div>';

      try {
        const r = await fetch('/api/messaging/friends');
        if (!r.ok) throw new Error('Network error: ' + r.status);

        friendsList = await r.json();
        if (Array.isArray(friendsList)) {
          renderSelection(vid);
        } else {
          throw new Error('Invalid friend data received');
        }
      } catch (e) {
        console.error('Load Selection Failure:', e);
        c.innerHTML = `<div style="color:#ff4d4d;text-align:center;padding:20px;grid-column:1/span 2">
          Link failure: ${e.message}<br>
          <button onclick="location.reload()" style="margin-top:10px;padding:5px 10px;background:#333;color:#fff;border:none;border-radius:5px;cursor:pointer">Retry</button>
        </div>`;
      }
    }

    function renderSelection(vid) {
      const isCreate = vid === 'create';
      const q = document.getElementById(isCreate ? 'group-friend-search' : 'single-friend-search').value.toLowerCase();
      const c = document.getElementById(isCreate ? 'create-friends-list' : 'single-friends-list');
      const filt = friendsList.filter(f => f.name.toLowerCase().includes(q));

      c.innerHTML = filt.length ? '' : '<div style="text-align:center;padding:20px;grid-column:1/span 2;color:rgba(255,255,255,0.1)">No matching subjects.</div>';
      filt.forEach(f => {
        const i = document.createElement('div'); i.className = 'friend-card'; i.dataset.id = f.id;
        i.innerHTML = `
          <div class="card-check">✓</div>
          <img src="${f.avatar ? '/' + f.avatar : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'}" class="card-avatar">
          <div class="card-name">${f.name}</div>
        `;
        i.onclick = async () => {
          if (isCreate) { i.classList.toggle('selected'); }
          else {
            const r = await fetch('/api/messaging/conversations'); const d = await r.json();
            const ex = d.find(cv => !cv.is_group && cv.other_user_id === f.id);
            if (ex) { curIsGrp = false; curOwned = false; openChat(ex.id, f.name); }
            else {
              curConvId = null; curIsGrp = false; activeRecipientId = f.id;
              document.getElementById('msg-title-text').innerText = f.name;
              switchView('chat');
              document.getElementById('chat-messages-container').innerHTML = '<div class="empty-state"><div class="empty-icon">🤝</div><span>Secure peer-to-peer link.<br>Initiate transmission.</span></div>';
            }
          }
        };
        c.appendChild(i);
      });
    }

    // Participants View
    document.getElementById('btn-group-info').onclick = async () => {
      switchView('info'); document.getElementById('info-group-name').innerText = document.getElementById('msg-title-text').innerText;
      const c = document.getElementById('group-participants-list');
      c.innerHTML = '<div style="text-align:center;padding:20px;color:rgba(255,255,255,0.2)">Scanning roster...</div>';
      try {
        const r = await fetch('/api/messaging/participants/' + curConvId); const d = await r.json();
        c.innerHTML = '';
        d.forEach(p => {
          if (p.is_banned) return;
          const i = document.createElement('div'); i.className = 'participant-item';
          i.style.cssText = 'display:flex;align-items:center;gap:15px;padding:12px;background:rgba(255,255,255,0.02);border-radius:16px;margin-bottom:10px';
          i.innerHTML = `
            <img src="${p.avatar ? '/' + p.avatar : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'}" style="width:40px;height:40px;border-radius:14px;border:1px solid rgba(255,255,255,0.1)">
            <div style="flex:1">
              <div style="font-size:14px;font-weight:700;color:#fff">${p.name} ${p.is_creator ? '<span style="color:#00c6ff;font-size:10px;margin-left:5px">👑 Owner</span>' : ''}</div>
            </div>
            ${(curOwned && p.id !== myUid) ? `
              <div style="display:flex;gap:8px">
                <button class="btn-kick" onclick="window.managePart(${p.id}, 'kick')" style="padding:6px 12px;background:rgba(255,255,255,0.05);border:none;border-radius:8px;color:#fff;font-size:11px;cursor:pointer">Kick</button>
                <button class="btn-ban" onclick="window.managePart(${p.id}, 'ban')" style="padding:6px 12px;background:rgba(255,77,77,0.1);border:none;border-radius:8px;color:#ff4d4d;font-size:11px;cursor:pointer;font-weight:700">Ban</button>
              </div>
            ` : ''}
          `;
          c.appendChild(i);
        });
      } catch (e) { c.innerHTML = '<div style="color:red;padding:20px;text-align:center">Roster sync failed.</div>'; }
    };
    document.getElementById('btn-back-to-chat').onclick = () => switchView('chat');

    window.managePart = async (uid, act) => {
      if (!confirm(`Authorize ${act} protocol?`)) return;
      try {
        const r = await fetch('/api/messaging/manage-participant', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ conversation_id: curConvId, target_user_id: uid, action: act }) });
        const d = await r.json(); if (d.success) document.getElementById('btn-group-info').click();
      } catch (e) { }
    };

    function startPoll() { stopPoll(); pollId = setTimeout(fetchMsgs, 5000); }
    function stopPoll() { if (pollId) { clearTimeout(pollId); pollId = null; } }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMessaging);
  else initMessaging();
})();
