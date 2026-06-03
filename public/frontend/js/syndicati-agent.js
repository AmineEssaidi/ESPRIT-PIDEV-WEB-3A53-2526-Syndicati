/**
 * Syndicati Agent UI v1.0 - Visible Version
 */

(function () {
  'use strict';

  if (window.SyndicatiAgentLoaded) return;
  window.SyndicatiAgentLoaded = true;

  // Create styles
  const style = document.createElement('style');
  style.textContent = `
    #syndicati-agent-trigger {
      position: fixed !important;
      bottom: 30px !important;
      right: 30px !important;
      width: 64px !important;
      height: 64px !important;
      background: rgba(10, 10, 10, 0.8) !important;
      backdrop-filter: blur(12px) saturate(160%) !important;
      -webkit-backdrop-filter: blur(12px) saturate(160%) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      border-radius: 50% !important;
      cursor: pointer !important;
      z-index: 2147483000 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), inset 0 0 15px rgba(255, 255, 255, 0.05) !important;
      font-size: 32px !important;
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    }
    #syndicati-agent-trigger:hover {
      transform: scale(1.1) translateY(-5px) !important;
      border-color: rgba(255, 255, 255, 0.3) !important;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(255, 255, 255, 0.1) !important;
    }
    #syndicati-agent-panel {
      position: fixed !important;
      bottom: 110px !important;
      right: 30px !important;
      width: 400px !important;
      height: 600px !important;
      background: rgba(15, 15, 15, 0.85) !important;
      backdrop-filter: blur(20px) saturate(180%) !important;
      -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      border-radius: 28px !important;
      box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6), inset 0 0 30px rgba(255, 255, 255, 0.02) !important;
      z-index: 2147483100 !important;
      display: none !important;
      flex-direction: column !important;
      overflow: hidden !important;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      transform: scale(0.9) translateY(20px) !important;
      opacity: 0 !important;
      transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    }
    #syndicati-agent-panel.open {
      display: flex !important;
      transform: scale(1) translateY(0) !important;
      opacity: 1 !important;
    }
    .agent-loader-overlay {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      background: rgba(10, 10, 10, 0.95) !important;
      backdrop-filter: blur(20px) !important;
      z-index: 100 !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      transition: opacity 0.5s ease !important;
    }
    .agent-loader-spinner {
      width: 50px !important;
      height: 50px !important;
      border: 3px solid rgba(255, 255, 255, 0.1) !important;
      border-top-color: #fff !important;
      border-radius: 50% !important;
      animation: agent-spin 1s linear infinite !important;
      margin-bottom: 20px !important;
    }
    @keyframes agent-spin {
      to { transform: rotate(360deg); }
    }
    .agent-loader-text {
      font-size: 14px !important;
      font-weight: 600 !important;
      color: #fff !important;
      letter-spacing: 1px !important;
      text-transform: uppercase !important;
    }
    .agent-loader-subtext {
      font-size: 11px !important;
      color: rgba(255, 255, 255, 0.4) !important;
      margin-top: 8px !important;
    }
    .agent-header {
      padding: 20px 24px !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
      display: flex !important;
      justify-content: space-between !important;
      align-items: center !important;
      background: linear-gradient(to bottom, rgba(255, 255, 255, 0.03), transparent) !important;
    }
    .agent-header-title {
      font-weight: 700 !important;
      font-size: 18px !important;
      color: #fff !important;
      display: flex !important;
      align-items: center !important;
      gap: 10px !important;
      letter-spacing: -0.5px !important;
    }
    .agent-close {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: rgba(255, 255, 255, 0.6) !important;
      width: 32px !important;
      height: 32px !important;
      border-radius: 50% !important;
      cursor: pointer !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      font-size: 18px !important;
      transition: all 0.2s ease !important;
    }
    .agent-close:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      color: #fff !important;
      transform: rotate(90deg) !important;
    }
    .agent-tabs {
      display: flex !important;
      padding: 0 12px !important;
      background: rgba(0, 0, 0, 0.2) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
    }
    .agent-tab {
      flex: 1 !important;
      padding: 14px !important;
      border: none !important;
      background: transparent !important;
      cursor: pointer !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      color: rgba(255, 255, 255, 0.4) !important;
      text-transform: uppercase !important;
      letter-spacing: 1px !important;
      transition: all 0.3s ease !important;
      border-bottom: 2px solid transparent !important;
    }
    .agent-tab.active {
      color: #fff !important;
      border-bottom-color: rgba(255, 255, 255, 0.8) !important;
    }
    .agent-content {
      display: none !important;
      flex: 1 !important;
      overflow-y: auto !important;
      padding: 24px !important;
    }
    .agent-content.active {
      display: flex !important;
      flex-direction: column !important;
    }
    .agent-messages {
      flex: 1 !important;
      overflow-y: auto !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 16px !important;
      margin-bottom: 20px !important;
      padding-right: 4px !important;
    }
    .agent-messages::-webkit-scrollbar { width: 4px; }
    .agent-messages::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
    
    .agent-message {
      max-width: 85% !important;
      padding: 12px 16px !important;
      border-radius: 18px !important;
      font-size: 14px !important;
      line-height: 1.5 !important;
      position: relative !important;
    }
    .agent-message.user {
      align-self: flex-end !important;
      background: rgba(255, 255, 255, 0.1) !important;
      color: #fff !important;
      border-bottom-right-radius: 4px !important;
      border: 1px solid rgba(255, 255, 255, 0.05) !important;
    }
    .agent-message.assistant {
      align-self: flex-start !important;
      background: rgba(255, 255, 255, 0.05) !important;
      color: rgba(255, 255, 255, 0.9) !important;
      border-bottom-left-radius: 4px !important;
      border: 1px solid rgba(255, 255, 255, 0.03) !important;
    }
    .agent-input-row {
      display: flex !important;
      gap: 10px !important;
      background: rgba(0, 0, 0, 0.3) !important;
      padding: 8px !important;
      border-radius: 16px !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }
    .agent-input-row input {
      flex: 1 !important;
      background: transparent !important;
      border: none !important;
      color: #fff !important;
      padding: 8px 12px !important;
      font-size: 14px !important;
      outline: none !important;
    }
    .agent-input-row button {
      background: rgba(255, 255, 255, 0.1) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #fff !important;
      padding: 8px 16px !important;
      border-radius: 12px !important;
      cursor: pointer !important;
      font-weight: 600 !important;
      transition: all 0.2s ease !important;
    }
    .agent-input-row button:hover {
      background: rgba(255, 255, 255, 0.2) !important;
    }
    .agent-goal-box {
      margin-bottom: 20px !important;
    }
    .agent-goal-box label {
      display: block !important;
      font-size: 11px !important;
      font-weight: 700 !important;
      color: rgba(255, 255, 255, 0.4) !important;
      margin-bottom: 8px !important;
      text-transform: uppercase !important;
      letter-spacing: 1px !important;
    }
    .agent-goal-box textarea {
      width: 100% !important;
      height: 100px !important;
      background: rgba(0, 0, 0, 0.3) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      border-radius: 16px !important;
      padding: 16px !important;
      color: #fff !important;
      font-size: 14px !important;
      resize: none !important;
      margin-bottom: 12px !important;
      outline: none !important;
      transition: all 0.3s ease !important;
    }
    .agent-goal-box textarea:focus {
      border-color: rgba(255, 255, 255, 0.3) !important;
      background: rgba(0, 0, 0, 0.4) !important;
    }
    .agent-go-btn {
      width: 100% !important;
      padding: 16px !important;
      background: #fff !important;
      color: #000 !important;
      border: none !important;
      border-radius: 16px !important;
      cursor: pointer !important;
      font-weight: 700 !important;
      font-size: 15px !important;
      transition: all 0.3s ease !important;
    }
    .agent-go-btn:hover:not(:disabled) {
      transform: translateY(-2px) !important;
      box-shadow: 0 10px 20px rgba(255, 255, 255, 0.1) !important;
    }
    .agent-go-btn:disabled {
      opacity: 0.5 !important;
      cursor: not-allowed !important;
    }
    #agent-status {
      margin-top: 16px !important;
      font-size: 13px !important;
      color: rgba(255, 255, 255, 0.6) !important;
      line-height: 1.4 !important;
    }
    .route-choice-btn {
      display: block !important;
      width: 100% !important;
      text-align: left !important;
      padding: 12px 16px !important;
      margin-bottom: 8px !important;
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      border-radius: 12px !important;
      color: #fff !important;
      font-size: 13px !important;
      font-weight: 500 !important;
      cursor: pointer !important;
      transition: all 0.2s ease !important;
    }
    .route-choice-btn:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      border-color: rgba(255, 255, 255, 0.2) !important;
      transform: translateX(4px) !important;
    }

    /* Amazing Scanning Overlay */
    #agent-scanning-overlay {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      background: rgba(0, 0, 0, 0.4) !important;
      backdrop-filter: blur(4px) !important;
      z-index: 2147483645 !important;
      display: none !important;
      pointer-events: none !important;
      overflow: hidden !important;
    }
    #agent-scanning-overlay.active {
      display: block !important;
    }
    .scanning-line {
      position: absolute !important;
      width: 100% !important;
      height: 4px !important;
      background: linear-gradient(to bottom, transparent, rgba(120, 80, 255, 0.8), transparent) !important;
      box-shadow: 0 0 20px rgba(120, 80, 255, 0.6) !important;
      top: 0 !important;
      left: 0 !important;
      animation: scanning-animation 4s linear infinite !important;
    }
    @keyframes scanning-animation {
      0% { top: -5%; }
      100% { top: 105%; }
    }
    .scanning-grid {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      background-image: 
        linear-gradient(rgba(120, 80, 255, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(120, 80, 255, 0.05) 1px, transparent 1px) !important;
      background-size: 50px 50px !important;
    }
    .scanning-status-box {
      position: absolute !important;
      bottom: 50px !important;
      left: 50% !important;
      transform: translateX(-50%) !important;
      background: rgba(10, 10, 10, 0.8) !important;
      backdrop-filter: blur(10px) !important;
      border: 1px solid rgba(120, 80, 255, 0.3) !important;
      padding: 15px 30px !important;
      border-radius: 40px !important;
      color: #fff !important;
      font-family: 'Inter', sans-serif !important;
      font-weight: 600 !important;
      font-size: 14px !important;
      display: flex !important;
      align-items: center !important;
      gap: 15px !important;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
      pointer-events: auto !important;
    }
    .scanning-pulse {
      width: 10px !important;
      height: 10px !important;
      background: #7850ff !important;
      border-radius: 50% !important;
      box-shadow: 0 0 10px #7850ff !important;
      animation: pulse-animation 1.5s ease-in-out infinite !important;
    }
    @keyframes pulse-animation {
      0% { transform: scale(0.8); opacity: 0.5; }
      50% { transform: scale(1.2); opacity: 1; }
      100% { transform: scale(0.8); opacity: 0.5; }
    }

    /* Radar UI */
    .scanning-radar {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 300px;
      height: 300px;
      border: 2px solid rgba(120, 80, 255, 0.2);
      border-radius: 50%;
      pointer-events: none;
    }
    .radar-line {
      position: absolute;
      top: 0;
      left: 50%;
      width: 2px;
      height: 50%;
      background: linear-gradient(to top, #7850ff, transparent);
      transform-origin: bottom;
      animation: radar-rotate 4s linear infinite;
    }
    .radar-circle {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: 1px solid rgba(120, 80, 255, 0.1);
      border-radius: 50%;
    }
    @keyframes radar-rotate {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    /* Target Locked FX */
    .agent-target-locked {
      animation: target-glow 0.5s ease-in-out infinite alternate !important;
      position: relative !important;
      z-index: 2147483648 !important;
    }
    @keyframes target-glow {
      from { box-shadow: 0 0 5px #7850ff; }
      to { box-shadow: 0 0 20px #7850ff; }
    }
    .agent-lock-effect {
      position: absolute;
      pointer-events: none;
      z-index: 2147483649;
      border: 2px solid #7850ff;
      border-radius: 8px;
      box-shadow: 0 0 30px #7850ff;
      animation: lock-ping 1s ease-out infinite;
    }
    @keyframes lock-ping {
      0% { transform: scale(1); opacity: 1; }
      100% { transform: scale(1.5); opacity: 0; }
    }
  `;
  document.head.appendChild(style);

  // Create trigger button
  const trigger = document.createElement('button');
  trigger.id = 'syndicati-agent-trigger';
  trigger.title = 'Syndicati Agent';

  // Create Net Canvas
  const netCanvas = document.createElement('canvas');
  netCanvas.id = 'syndicati-net-canvas';
  netCanvas.style.position = 'absolute';
  netCanvas.style.top = '0';
  netCanvas.style.left = '0';
  netCanvas.style.width = '100%';
  netCanvas.style.height = '100%';
  netCanvas.style.pointerEvents = 'none';
  netCanvas.style.borderRadius = '50%';
  netCanvas.style.opacity = '0.8';
  trigger.appendChild(netCanvas);

  const iconSpan = document.createElement('span');
  iconSpan.innerHTML = '🤖';
  iconSpan.style.position = 'relative';
  iconSpan.style.zIndex = '2';
  trigger.appendChild(iconSpan);

  document.body.appendChild(trigger);

  // Bubble Net Animation Engine
  (function initBubbleNet() {
    const ctx = netCanvas.getContext('2d');
    let width, height;
    let particles = [];
    let isHovering = false;
    const particleCount = 12;
    const connectionDist = 35;

    function resize() {
      width = netCanvas.width = 128; // Higher res for retina
      height = netCanvas.height = 128;
    }

    class Particle {
      constructor() {
        this.reset();
      }
      reset() {
        this.x = Math.random() * 128;
        this.y = Math.random() * 128;
        this.vx = (Math.random() - 0.5) * 0.5;
        this.vy = (Math.random() - 0.5) * 0.5;
        this.radius = 1 + Math.random() * 1.5;
      }
      update() {
        let speedMult = isHovering ? 2.5 : 1;
        this.x += this.vx * speedMult;
        this.y += this.vy * speedMult;

        if (this.x < 0 || this.x > 128) this.vx *= -1;
        if (this.y < 0 || this.y > 128) this.vy *= -1;
      }
      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = isHovering ? '#fff' : 'rgba(255, 255, 255, 0.5)';
        ctx.fill();
      }
    }

    function init() {
      resize();
      for (let i = 0; i < particleCount; i++) {
        particles.push(new Particle());
      }
    }

    function animate() {
      ctx.clearRect(0, 0, 128, 128);

      // Draw Connections
      ctx.lineWidth = 0.5;
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const dx = particles[i].x - particles[j].x;
          const dy = particles[i].y - particles[j].y;
          const dist = Math.sqrt(dx * dx + dy * dy);

          if (dist < connectionDist) {
            ctx.beginPath();
            ctx.moveTo(particles[i].x, particles[i].y);
            ctx.lineTo(particles[j].x, particles[j].y);
            let alpha = 1 - (dist / connectionDist);
            ctx.strokeStyle = `rgba(120, 80, 255, ${alpha * (isHovering ? 0.8 : 0.4)})`;
            ctx.stroke();
          }
        }
      }

      particles.forEach(p => {
        p.update();
        p.draw();
      });

      requestAnimationFrame(animate);
    }

    trigger.addEventListener('mouseenter', () => isHovering = true);
    trigger.addEventListener('mouseleave', () => isHovering = false);

    init();
    animate();
  })();

  // Create panel
  const panel = document.createElement('div');
  panel.id = 'syndicati-agent-panel';
  panel.innerHTML = `
    <div class="agent-loader-overlay" id="agent-loader">
      <div class="agent-loader-spinner"></div>
      <div class="agent-loader-text">Syndicati AI Services Loading</div>
      <div class="agent-loader-subtext" id="agent-loader-detail">Initializing core modules...</div>
    </div>
    <div class="agent-header">
      <div class="agent-header-title"><span>🤖</span> Syndicati Agent</div>
      <button class="agent-close">×</button>
    </div>
    <div class="agent-tabs">
      <button class="agent-tab active" data-tab="chat">Chat</button>
      <button class="agent-tab" data-tab="agent">Agent</button>
    </div>
    <div class="agent-content active" data-content="chat">
      <div class="agent-messages" id="agent-chat-messages"></div>
      <div class="agent-input-row">
        <input type="text" id="agent-chat-input" placeholder="Ask anything..." />
        <button id="agent-chat-send">Send</button>
      </div>
    </div>
    <div class="agent-content" data-content="agent">
      <div class="agent-messages" id="agent-action-messages"></div>
      <div class="agent-goal-box">
        <label>Your Objective</label>
        <textarea id="agent-goal" placeholder="e.g. I want to file a new reclamation..."></textarea>
        <button class="agent-go-btn" id="agent-run">Execute Action</button>
      </div>
      <div id="agent-status" style="padding: 10px 20px; font-size: 12px; color: rgba(255,255,255,0.6); font-style: italic;"></div>
    </div>
  `;
  document.body.appendChild(panel);

  // Create scanning overlay (Futuristic Radar)
  const scanningOverlay = document.createElement('div');
  scanningOverlay.id = 'agent-scanning-overlay';
  scanningOverlay.innerHTML = `
    <div class="scanning-grid"></div>
    <div class="scanning-radar">
      <div class="radar-line"></div>
      <div class="radar-circle"></div>
      <div class="radar-circle" style="width: 60%; height: 60%; opacity: 0.5;"></div>
    </div>
    <div class="scanning-status-box">
      <div class="scanning-pulse"></div>
      <div id="scanning-status-text">Syndicati Vision: Contextualizing Page...</div>
    </div>
  `;
  document.body.appendChild(scanningOverlay);

  // Function to show Target Locked FX
  function showTargetLocked(selector) {
    const el = document.querySelector(selector);
    if (!el) return;

    el.classList.add('agent-target-locked');
    const rect = el.getBoundingClientRect();
    const lock = document.createElement('div');
    lock.className = 'agent-lock-effect';
    lock.style.top = (rect.top + window.scrollY) + 'px';
    lock.style.left = (rect.left + window.scrollX) + 'px';
    lock.style.width = rect.width + 'px';
    lock.style.height = rect.height + 'px';
    document.body.appendChild(lock);

    setTimeout(() => {
      lock.remove();
      el.classList.remove('agent-target-locked');
    }, 2000);
  }

  // State
  let isOpen = false;
  let currentTab = 'chat';
  let isBootstrapped = false;

  // Persistent state (per browser tab)
  const CHAT_STORAGE_KEY = 'syndicatiChatHistory'; // Separate from Agent
  const AGENT_STORAGE_KEY = 'syndicatiAgentHistory'; // Agent tab history
  const AGENT_SESSION_KEY = 'syndicatiAgentSessionId';
  const BOOTSTRAP_STORAGE_KEY = 'syndicatiAgentBootstrap';

  let chatHistory = []; // Chat tab history
  let agentHistory = []; // Agent tab history (for context, not displayed)
  let agentSessionId = null;
  let bootstrapRetryTimer = null;

  // Event handlers
  trigger.addEventListener('click', async () => {
    isOpen = !isOpen;
    panel.classList.toggle('open', isOpen);

    // Always check status when opening (for console logging), but only show loader if not ready
    if (isOpen) {
      await checkServicesStatus();
    }
  });

  /**
   * Check services status - always logs to console, only shows loader if services aren't ready.
   */
  async function checkServicesStatus() {
    console.log('%c[Syndicati AI] Checking direct Gemini/Groq agent status...', 'color: #764ba2; font-weight: bold;');

    try {
      const boot = await bootstrapAgentSilent(!isBootstrapped);
      const directReady = boot.directApi?.running || boot.ready;

      console.log('%c[Syndicati AI] Service status:', 'color: #888;');
      console.table({
        'Direct API': directReady ? 'Ready' : 'Missing key',
        'Model': boot.directApi?.model || 'unknown',
        'Mode': 'Browser local actions'
      });

      if (directReady) {
        console.log('%c[Syndicati AI] Direct agent is ready.', 'color: #22c55e; font-weight: bold;');
        isBootstrapped = true;
        const loader = document.getElementById('agent-loader');
        if (loader) {
          loader.style.opacity = '0';
          loader.style.pointerEvents = 'none';
          setTimeout(() => { loader.style.display = 'none'; }, 300);
        }
      } else {
        await performBootstrap();
      }
    } catch (e) {
      console.error('[Syndicati AI] Status check error:', e);
      await performBootstrap();
    }
  }

  /**
   * Perform bootstrap with loader animation when the direct API key is not ready.
   */
  async function performBootstrap() {
    const loader = document.getElementById('agent-loader');
    const detail = document.getElementById('agent-loader-detail');
    if (!loader || !detail) return;
    if (!isOpen) return;

    loader.style.display = 'flex';
    loader.style.opacity = '1';
    loader.style.pointerEvents = 'auto';
    detail.textContent = 'Checking direct AI...';

    try {
      const boot = await bootstrapAgentSilent(true);
      const directReady = boot.directApi?.running || boot.ready;

      if (directReady) {
        console.log('%c[Syndicati AI] Direct agent is ready.', 'color: #22c55e; font-weight: bold;');
        detail.textContent = 'Direct AI ready.';
        isBootstrapped = true;
        if (bootstrapRetryTimer) {
          clearTimeout(bootstrapRetryTimer);
          bootstrapRetryTimer = null;
        }
        loader.style.opacity = '0';
        loader.style.pointerEvents = 'none';
        setTimeout(() => { loader.style.display = 'none'; }, 500);
      } else {
        detail.textContent = 'Waiting for GEMINI_API_KEY or GROQ_API_KEY...';
        console.warn('[Syndicati AI] Direct API key is missing. Retrying in 2s...');
        if (bootstrapRetryTimer) clearTimeout(bootstrapRetryTimer);
        bootstrapRetryTimer = setTimeout(() => {
          bootstrapRetryTimer = null;
          if (isOpen) performBootstrap();
        }, 2000);
      }
    } catch (e) {
      console.error('[Syndicati AI] Bootstrap error:', e);
      detail.textContent = 'Bootstrap failed. Check console. Retrying...';
      if (bootstrapRetryTimer) clearTimeout(bootstrapRetryTimer);
      bootstrapRetryTimer = setTimeout(() => {
        bootstrapRetryTimer = null;
        if (isOpen) performBootstrap();
      }, 2000);
    }
  }

  panel.querySelector('.agent-close').addEventListener('click', () => {
    isOpen = false;
    panel.classList.remove('open');
    if (bootstrapRetryTimer) {
      clearTimeout(bootstrapRetryTimer);
      bootstrapRetryTimer = null;
    }
  });

  // Tab switching - load correct history when switching tabs
  panel.querySelectorAll('.agent-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const tabName = tab.dataset.tab;
      currentTab = tabName;

      panel.querySelectorAll('.agent-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');

      panel.querySelectorAll('.agent-content').forEach(c => c.classList.remove('active'));
      panel.querySelector(`[data-content="${tabName}"]`).classList.add('active');

      // Sync views
      renderHistory();
    });
  });

  // Chat/Agent UI references
  const chatInput = document.getElementById('agent-chat-input');
  const chatSend = document.getElementById('agent-chat-send');
  const chatMessages = document.getElementById('agent-chat-messages');
  const actionMessages = document.getElementById('agent-action-messages');

  // Load persisted state (chat history, agent session, bootstrap)
  function loadPersistedState() {
    try {
      const storedChat = sessionStorage.getItem(CHAT_STORAGE_KEY);
      if (storedChat) chatHistory = JSON.parse(storedChat);
    } catch (e) { chatHistory = []; }

    try {
      const storedAgent = sessionStorage.getItem(AGENT_STORAGE_KEY);
      if (storedAgent) agentHistory = JSON.parse(storedAgent);
    } catch (e) { agentHistory = []; }

    renderHistory();

    try {
      const storedSession = sessionStorage.getItem(AGENT_SESSION_KEY);
      if (storedSession) agentSessionId = storedSession;
    } catch (e) { agentSessionId = null; }

    try {
      const cached = sessionStorage.getItem(BOOTSTRAP_STORAGE_KEY);
      if (cached) {
        const parsed = JSON.parse(cached);
        const now = Date.now();
        // Restore bootstrapped status if cache is fresh (< 30 mins)
        if (parsed && parsed.ready && (now - (parsed.timestamp || 0) < 1800000)) {
          isBootstrapped = true;
          console.log('[Syndicati Agent] Restored bootstrap status from cache');

          // Immediately hide loader if session is fresh
          const loader = document.getElementById('agent-loader');
          if (loader) {
            loader.style.display = 'none';
          }
        }
      }
    } catch (e) { }
  }

  function renderHistory() {
    if (!chatMessages || !actionMessages) return;

    chatMessages.innerHTML = '';
    actionMessages.innerHTML = '';

    chatHistory.forEach(msg => {
      const el = document.createElement('div');
      el.className = `agent-message ${msg.role}`;
      el.textContent = msg.content;
      chatMessages.appendChild(el);
    });
    chatMessages.scrollTop = chatMessages.scrollHeight;

    agentHistory.forEach(msg => {
      const el = document.createElement('div');
      el.className = `agent-message ${msg.role}`;
      el.textContent = msg.content;
      actionMessages.appendChild(el);
    });
    actionMessages.scrollTop = actionMessages.scrollHeight;
  }

  function persistChatHistory() {
    try {
      sessionStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(chatHistory));
      sessionStorage.setItem(AGENT_STORAGE_KEY, JSON.stringify(agentHistory));
    } catch (e) {
      // ignore storage failures
    }
  }

  // Render + optionally persist a message
  function addMessage(role, text, persist = true) {
    const isAgentTab = (currentTab === 'agent');
    const targetHistory = isAgentTab ? agentHistory : chatHistory;
    const targetContainer = isAgentTab ? actionMessages : chatMessages;

    if (!targetContainer) return;

    const msg = document.createElement('div');
    msg.className = `agent-message ${role}`;
    msg.textContent = text;
    targetContainer.appendChild(msg);
    targetContainer.scrollTop = targetContainer.scrollHeight;

    if (persist) {
      targetHistory.push({ role, content: text });
      persistChatHistory();
    }
  }

  // Initial load
  loadPersistedState();

  async function sendChat() {
    const text = chatInput.value.trim();
    if (!text) return;

    addMessage('user', text);
    chatInput.value = '';

    try {
      const res = await fetch('/ai/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          // Send full conversational history so backend has context
          messages: chatHistory.map(m => ({ role: m.role, content: m.content })),
          pageContext: { url: location.href, title: document.title }
        })
      });
      const data = await res.json();

      if (data.reply) {
        addMessage('assistant', data.reply);
      }
    } catch (e) {
      addMessage('assistant', 'Error: ' + e.message);
    }
  }

  chatSend.addEventListener('click', sendChat);
  chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendChat();
  });

  // Agent functionality
  const goalInput = document.getElementById('agent-goal');
  const runBtn = document.getElementById('agent-run');
  const statusDiv = document.getElementById('agent-status');

  let bootstrapPromise = null;
  let bootstrapForcePromise = null;

  /**
   * @param {boolean} forceRefresh - If true, skip cache and always hit the API (for 3-step verification when opening bubble).
   */
  async function bootstrapAgentSilent(forceRefresh = false) {
    const now = Date.now();

    // Check cache first for non-forced refresh
    if (!forceRefresh) {
      try {
        const cached = sessionStorage.getItem(BOOTSTRAP_STORAGE_KEY);
        if (cached) {
          const parsed = JSON.parse(cached);
          // 30 minute cache (1800000ms)
          if (parsed && parsed.ready && (now - (parsed.timestamp || 0) < 1800000)) {
            console.log('[Syndicati AI] Using cached bootstrap status (' + Math.round((now - parsed.timestamp) / 1000) + 's old)');
            isBootstrapped = true;
            return parsed;
          }
        }
      } catch (e) { }
    }

    if (forceRefresh) {
      bootstrapForcePromise = (async () => {
        try {
          const res = await fetch('/syndicati/agent/bootstrap', { method: 'GET' });
          const data = await res.json();
          if (data && data.ready) {
            data.timestamp = now;
            try {
              sessionStorage.setItem(BOOTSTRAP_STORAGE_KEY, JSON.stringify(data));
            } catch (e) { }
          }
          return data;
        } catch (e) {
          return { success: false, ready: false, error: e.message };
        } finally {
          bootstrapForcePromise = null;
        }
      })();
      return bootstrapForcePromise;
    }

    if (bootstrapPromise) return bootstrapPromise;
    bootstrapPromise = (async () => {
      try {
        const res = await fetch('/syndicati/agent/bootstrap', { method: 'GET' });
        const data = await res.json();
        if (data && data.ready) {
          data.timestamp = now;
          try {
            sessionStorage.setItem(BOOTSTRAP_STORAGE_KEY, JSON.stringify(data));
          } catch (e) { }
          isBootstrapped = true;
        }
        return data;
      } catch (e) {
        return { success: false, ready: false, error: e.message };
      } finally {
        bootstrapPromise = null;
      }
    })();
    return bootstrapPromise;
  }

  // Gather form structure from current page (including forms in modals)
  function gatherPageForms() {
    const forms = [];
    document.querySelectorAll('form').forEach(form => {
      const formId = form.id || form.getAttribute('name') || ('form-' + forms.length);
      const fields = [];
      form.querySelectorAll('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="file"]), textarea, select').forEach(el => {
        const name = el.name || el.id || '';
        let label = '';
        if (el.id) {
          const labelEl = document.querySelector('label[for="' + el.id.replace(/"/g, '\\"') + '"]');
          if (labelEl) label = labelEl.textContent.trim();
        }
        if (!label && el.closest) {
          const group = el.closest('.syndicat-form-group, .form-group, .glass-form-group, [class*="form-group"]');
          if (group) {
            const lab = group.querySelector('.syndicat-form-label, .form-label, .glass-label, label');
            if (lab) label = lab.textContent.trim();
          }
        }
        if (!label) label = el.placeholder || el.getAttribute('aria-label') || name;
        if (name || label) {
          fields.push({ name: name, label: (label || name).trim(), id: el.id || '' });
        }
      });
      if (fields.length) {
        forms.push({ id: formId, formId: formId, fields: fields });
      }
    });
    return forms;
  }

  // === BULLETPROOF PRECISION ENGINE ===

  // Helper: Get element's OWN text only (not children's) to prevent text pollution
  function getOwnText(el) {
    let own = '';
    for (const n of el.childNodes) {
      if (n.nodeType === Node.TEXT_NODE) own += n.textContent;
    }
    own = own.replace(/\s+/g, ' ').trim();
    if (!own && el.value) own = String(el.value).trim();
    if (!own && el.getAttribute('aria-label')) own = el.getAttribute('aria-label').trim();
    return own;
  }

  // Helper: Display text - own text first, fallback to short textContent
  function getDisplayText(el) {
    const own = getOwnText(el);
    if (own) return own;
    const full = (el.textContent || '').replace(/\s+/g, ' ').trim();
    return full.length > 80 ? full.substring(0, 80) : full;
  }

  // Helper: Generate a guaranteed-unique selector
  function generateUniqueSelector(el, agentId) {
    if (agentId) return '[data-agent-id="' + agentId + '"]';
    if (el.id) return '#' + el.id;
    const tag = el.tagName.toLowerCase();
    const classes = Array.from(el.classList)
      .filter(c => c && !c.includes('active') && !c.includes('hover') && !c.includes('scanned'))
      .slice(0, 3);
    let base = tag + (classes.length ? '.' + classes.join('.') : '');
    const parentWithId = el.closest('[id]');
    let scoped = base;
    if (parentWithId && parentWithId !== el) scoped = '#' + parentWithId.id + ' ' + base;
    if (document.querySelectorAll(scoped).length === 1) return scoped;
    const parent = el.parentElement;
    if (parent) {
      let idx = 1;
      for (const sib of parent.children) { if (sib === el) break; idx++; }
      return (parentWithId && parentWithId !== el ? '#' + parentWithId.id + ' ' : '') + base + ':nth-child(' + idx + ')';
    }
    return scoped;
  }

  function gatherInteractiveElements() {
    const elements = [];
    const seen = new Set();
    let idCounter = 0;

    const selectors = [
      'a', 'button', '[role="button"]',
      'input[type="submit"]', 'input[type="button"]', 'input[type="checkbox"]',
      '.btn', '.button', '[onclick]', '[data-action]',
      '.tag-pill', '.event-host-pill-trigger', '[class*="-trigger"]', '[class*="-btn"]',
      '.glass-switcher-pill'
    ];

    document.querySelectorAll(selectors.join(', ')).forEach(el => {
      if (seen.has(el)) return;
      seen.add(el);
      const style = window.getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden') return;
      if (el.offsetWidth === 0 && el.offsetHeight === 0 && !el.closest('.glass-switcher')) return;

      const ownText = getOwnText(el);
      const displayText = getDisplayText(el);
      const aria = el.getAttribute('aria-label') || '';
      if (!displayText && !el.id && !aria) return;

      // Stamp data-agent-id for guaranteed re-find
      const agentId = 'agi-' + (++idCounter);
      el.setAttribute('data-agent-id', agentId);

      const containerSel = ['.event-card', '.forum-card', '.card', '.card-body', '.glass-switcher', 'li', 'section', 'article', '.glass-form-group'];
      const container = el.closest(containerSel.join(', '));
      let context = '', titleContext = '', containerId = '';
      if (container) {
        context = container.innerText.substring(0, 300).replace(/\s+/g, ' ').trim();
        const h = container.querySelector('h1,h2,h3,h4,h5,h6,.card-title,.title,[class*="title"]');
        if (h) titleContext = h.innerText.trim();
        containerId = container.id || container.getAttribute('data-id') || container.className.split(' ').slice(0, 2).join(' ');
      }

      const selector = generateUniqueSelector(el, agentId);

      let semanticAnchor = '';
      const sec = el.closest('section, main, article, footer, header, nav, .sidebar');
      const nh = container ? container.querySelector('h1,h2,h3,h4,h5,h6,.card-title,.title') : null;
      if (nh && nh !== el) semanticAnchor = nh.innerText.trim();
      const parentSection = sec ? (sec.getAttribute('aria-label') || sec.className.split(' ')[0]) : '';

      elements.push({
        agentId, text: displayText, ownText, selector,
        tag: el.tagName.toLowerCase(),
        ariaLabel: aria,
        href: el.tagName === 'A' ? (el.getAttribute('href') || '') : '',
        context, titleContext, containerId,
        semanticAnchor, parentSection,
        isPrimary: el.classList.contains('btn-primary') || el.classList.contains('main-btn') || el.classList.contains('cta-btn')
      });
    });
    console.log('[Agent Precision] Gathered ' + elements.length + ' fingerprinted elements');
    return elements.slice(0, 120);
  }




  function findFieldByLabelOrName(labelOrName) {
    const q = (labelOrName || '').toString().trim().toLowerCase();
    if (!q) return null;
    const all = document.querySelectorAll('form input:not([type="hidden"]):not([type="file"]), form textarea, form select');
    let bestMatch = null;
    let bestScore = 0;

    for (const el of all) {
      let label = '';
      if (el.id) {
        const labelEl = document.querySelector('label[for="' + el.id.replace(/"/g, '\\"') + '"]');
        if (labelEl) label = labelEl.textContent.trim().toLowerCase();
      }
      if (!label && el.closest) {
        const group = el.closest('.syndicat-form-group, .form-group, .glass-form-group, [class*="form-group"]');
        if (group) {
          const lab = group.querySelector('.syndicat-form-label, .form-label, .glass-label, label');
          if (lab) label = lab.textContent.trim().toLowerCase();
        }
      }
      if (!label) label = (el.placeholder || el.getAttribute('aria-label') || '').toLowerCase();
      const name = (el.name || el.id || '').toLowerCase();

      let score = 0;
      if (name === q || label === q) score = 100;
      else if (name.includes(q) || label.includes(q)) score = 50;
      else if (q.includes(name) || q.includes(label)) score = 30;

      if (score > bestScore) {
        bestScore = score;
        bestMatch = el;
      }
      if (score === 100) break; // Perfect match found
    }
    return bestMatch;
  }

  // Run fill_field and submit_form actions in sequence; ensure form/modal is visible
  function runFormActions(actions) {
    const fillAndSubmit = actions.filter(a => a && (a.type === 'fill_field' || a.type === 'submit_form'));
    if (fillAndSubmit.length === 0) return Promise.resolve();
    return fillAndSubmit.reduce((p, a) => p.then(() => {
      if (a.type === 'fill_field') {
        const el = findFieldByLabelOrName(a.labelOrName);
        if (el) {
          const form = el.closest('form');
          if (form) {
            const modal = form.closest('.modal, [role="dialog"]');
            if (modal && !modal.classList.contains('show') && !modal.classList.contains('active')) {
              const trigger = document.querySelector('[data-bs-toggle="modal"][data-bs-target="#' + modal.id + '"], [data-toggle="modal"][data-target="#' + modal.id + '"], [href="#' + modal.id + '"]');
              if (trigger) trigger.click();
            }
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          el.focus();
          el.value = (a.value != null) ? String(a.value) : '';
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        }
      } else if (a.type === 'submit_form') {
        const form = (a.formId && document.getElementById(a.formId)) || document.querySelector('form[id="' + a.formId + '"]') || document.querySelector('form');
        if (form) {
          form.requestSubmit ? form.requestSubmit() : form.submit();
        }
      }
    }), Promise.resolve());
  }

  // Run native local actions in the current tab (BULLETPROOF RE-FINDING)
  async function runLocalActions(actions) {
    for (const action of actions) {
      if (action.type === 'local_click') {
        const selector = action.selector;
        const text = action.text;
        let el = null;

        // Phase 1: data-agent-id selector (100% hit rate if stamped)
        if (selector && selector.startsWith('[data-agent-id=')) {
          el = document.querySelector(selector);
        }

        // Phase 2: Any other selector
        if (!el && selector) {
          try { el = document.querySelector(selector); } catch (e) { /* invalid selector */ }
        }

        // Phase 3: Score-based text re-finding (no first-match-wins)
        if (!el && text) {
          const search = text.toLowerCase().trim();
          const allSelectors = [
            'a', 'button', '[role="button"]',
            'input[type="submit"]', 'input[type="button"]',
            '.btn', '.button', '[onclick]', '[data-action]',
            '.tag-pill', '.event-host-pill-trigger', '[class*="-trigger"]', '[class*="-btn"]',
            '.glass-switcher-pill'
          ];
          const all = document.querySelectorAll(allSelectors.join(', '));
          let bestEl = null, bestScore = 0;
          for (const item of all) {
            const ownText = getOwnText(item).toLowerCase();
            const fullText = (item.textContent || '').toLowerCase().trim();
            const aria = (item.getAttribute('aria-label') || '').toLowerCase();
            let score = 0;
            if (ownText === search || aria === search) score = 100;
            else if (fullText === search) score = 80;
            else if (ownText.includes(search) || aria.includes(search)) score = 60;
            else if (fullText.includes(search)) score = 40;
            else if (search.includes(ownText) && ownText.length > 3) score = 30;
            if (score > bestScore) { bestScore = score; bestEl = item; }
            if (score === 100) break;
          }
          el = bestEl;
        }

        if (el) {
          console.log('[Agent Precision] Re-found element:', el.tagName, el.textContent?.substring(0, 40));
          statusDiv.textContent = '👆 Clicking element...';
          showTargetLocked(selector || '');
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          await new Promise(r => setTimeout(r, 600));
          try {
            el.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
            el.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
            el.click();
            console.log('[Agent Precision] Click dispatched successfully');
          } catch (err) {
            console.error('[Agent Precision] Click failed:', err);
          }
          return true;
        } else {
          console.warn('[Agent Precision] Could not re-find element:', selector, text);
        }
      }
    }
    return false;
  }

  runBtn.addEventListener('click', async () => {
    const goal = goalInput.value.trim();
    if (!goal) return;

    runBtn.disabled = true;
    statusDiv.textContent = 'Executing: ' + goal;

    const boot = await bootstrapAgentSilent();
    if (boot && boot.success && boot.ready === false) {
      statusDiv.textContent = 'Starting AI services...';
    }

    const pageForms = gatherPageForms();
    const previousMessages = (agentHistory || []).slice(-16).map(m => ({ role: m.role, content: m.content }));

    const contextPayload = {
      url: location.href,
      title: document.title,
      pageForms: pageForms.length ? pageForms : undefined,
      interactiveElements: gatherInteractiveElements(),
      previousMessages: previousMessages.length ? previousMessages : undefined
    };
    console.log('[Agent Debug] Sending execute request:', { goal, context: contextPayload });

    showScanning(true);

    try {
      const res = await fetch('/syndicati/agent/execute', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          goal: goal,
          context: contextPayload,
          sessionId: agentSessionId || null
        })
      });

      // Stop scanning when first response arrives (or move it to final completion)
      showScanning(false);
      const data = await res.json();
      console.log('[Agent Debug] Response:', data);

      // Persist native agent session so browser reloads keep same agent mind
      if (data && typeof data.sessionId === 'string' && data.sessionId) {
        agentSessionId = data.sessionId;
        try {
          sessionStorage.setItem(AGENT_SESSION_KEY, agentSessionId);
        } catch (e) {
          // ignore
        }
      }

      // New agent response shape: actions[]
      if (data && data.success && Array.isArray(data.actions)) {
        // Check for local actions first (Primary "In-Tab" Strategy)
        const localActionPerformed = await runLocalActions(data.actions);
        if (localActionPerformed) {
          // Local actions often trigger reloads or navigations, so we're done here
          runBtn.disabled = false;
          return;
        }

        // Playwright browser takeover (Perplexity-style): agent filled the form or clicked in its browser
        const playwrightDone = data.actions.find(a => a && (a.type === 'playwright_fill_done' || a.type === 'playwright_click_done'));
        if (playwrightDone) {
          const msg = data.reply || (playwrightDone.type === 'playwright_click_done'
            ? 'Browser takeover complete. I\'ve clicked "' + (playwrightDone.element || 'the element') + '".'
            : 'Browser takeover complete. The form was filled and ' + (playwrightDone.submitted ? 'submitted.' : 'is ready to submit.'));
          statusDiv.textContent = msg;
          addMessage('assistant', msg);
          runBtn.disabled = false;
          return;
        }
        // Run form fill/submit actions in sequence first (same page)
        const hasFormActions = data.actions.some(a => a && (a.type === 'fill_field' || a.type === 'submit_form'));
        if (hasFormActions) {
          statusDiv.textContent = 'Filling form...';
          await runFormActions(data.actions);
          const reply = data.reply || '✅ Form filled.';
          statusDiv.textContent = reply;
          addMessage('assistant', reply);
          runBtn.disabled = false;
          return;
        }

        const chooser = data.actions.find(a => a && a.type === 'choose_route' && Array.isArray(a.options));
        if (chooser) {
          if (data.reply) addMessage('assistant', data.reply);
          statusDiv.innerHTML = '';
          const title = document.createElement('div');
          title.textContent = 'Choose a destination:';
          title.style.fontSize = '11px';
          title.style.fontWeight = '700';
          title.style.color = 'rgba(255,255,255,0.4)';
          title.style.textTransform = 'uppercase';
          title.style.letterSpacing = '1px';
          title.style.marginBottom = '12px';
          statusDiv.appendChild(title);

          chooser.options.slice(0, 5).forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'route-choice-btn';
            btn.textContent = (opt.label || opt.url || 'Open');
            btn.addEventListener('click', () => {
              const url = opt.url;
              if (!url) return;
              statusDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:#fff;">🚀 <span>Navigating...</span></div>';
              setTimeout(() => {
                window.location.href = url;
              }, 200);
            });
            statusDiv.appendChild(btn);
          });
          return;
        }

        const nav = data.actions.find(a => a && a.type === 'navigate' && a.url);
        if (nav) {
          if (data.reply) addMessage('assistant', data.reply);
          // Check if there are also suggestions to show alongside the main navigation
          const suggestions = data.actions.find(a => a && a.type === 'suggest_routes' && Array.isArray(a.options));

          if (suggestions) {
            statusDiv.innerHTML = '<div style="color:#fff;margin-bottom:12px;">Navigating to best match in 2s...</div>';
            const sugDiv = document.createElement('div');
            sugDiv.style.marginTop = '12px';
            sugDiv.style.borderTop = '1px solid rgba(255,255,255,0.1)';
            sugDiv.style.paddingTop = '12px';
            const sugTitle = document.createElement('div');
            sugTitle.textContent = 'Or select another:';
            sugTitle.style.fontSize = '11px';
            sugTitle.style.color = 'rgba(255,255,255,0.4)';
            sugTitle.style.marginBottom = '8px';
            sugDiv.appendChild(sugTitle);

            suggestions.options.forEach(opt => {
              const sBtn = document.createElement('button');
              sBtn.className = 'route-choice-btn';
              sBtn.textContent = opt.label || opt.url;
              sBtn.onclick = (e) => {
                e.preventDefault();
                clearTimeout(navTimeout);
                window.location.href = opt.url;
              };
              sugDiv.appendChild(sBtn);
            });
            statusDiv.appendChild(sugDiv);

            const navTimeout = setTimeout(() => {
              window.location.href = nav.url;
            }, 2000);
          } else {
            statusDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px;color:#fff;">🚀 <span>Navigating...</span></div>';
            setTimeout(() => {
              window.location.href = nav.url;
            }, 500);
          }
          return;
        }
      }

      // Check for navigation in results array structure
      if (data.success && data.results && Array.isArray(data.results)) {
        console.log('[Agent Debug] Checking', data.results.length, 'results');
        const navigateStep = data.results.find(r => {
          console.log('[Agent Debug] Checking result:', r);
          return r.type === 'navigate' ||
            (r.result && (r.result.method === 'route' || r.result.method === 'instruction'));
        });

        if (navigateStep) {
          const target = navigateStep.target || navigateStep.result?.target;
          console.log('[Agent Debug] Found navigation target:', target);
          if (target) {
            statusDiv.textContent = '🚀 Navigating to ' + target + '...';
            setTimeout(() => {
              window.location.href = target;
            }, 500);
            return;
          }
        }
      }

      // Check intent for navigation
      if (data.intent?.requires_navigation && data.intent?.destination_route) {
        const target = data.intent.destination_route;
        console.log('[Agent Debug] Using intent route:', target);
        statusDiv.textContent = '🚀 Navigating to ' + target + '...';
        setTimeout(() => {
          window.location.href = target;
        }, 500);
        return;
      }

      // Handle fallback navigation
      if (data.fallback?.fallback_type === 'navigation' && data.fallback?.target) {
        console.log('[Agent Debug] Using fallback:', data.fallback.target);
        statusDiv.textContent = '🚀 Navigating to ' + data.fallback.target + '...';
        setTimeout(() => {
          window.location.href = data.fallback.target;
        }, 500);
        return;
      }

      if (data.success && data.reply) addMessage('assistant', data.reply);
      statusDiv.textContent = data.success ? (data.reply || '✅ Done (no navigation)') : '❌ Failed: ' + (data.error || '');
    } catch (e) {
      statusDiv.textContent = '❌ Error: ' + e.message;
      showScanning(false);
    } finally {
      runBtn.disabled = false;
    }
  });

  // Scanning UI Logic
  const scanningStatusText = document.getElementById('scanning-status-text');
  let scanningInterval = null;
  const scanningPhases = [
    "Analyzing visual structure...",
    "Mapping interactive elements...",
    "Extracting semantic content...",
    "Scanning for forms & inputs...",
    "Checking accessibility tree...",
    "Correlating elements with goal...",
    "Formulating navigation path...",
    "Optimizing interaction strategy..."
  ];

  function showScanning(show) {
    if (show) {
      if (scanningInterval) clearInterval(scanningInterval);
      scanningOverlay.classList.add('active');
      let phase = 0;
      scanningStatusText.textContent = "Syndicati AI: Starting scan...";
      scanningInterval = setInterval(() => {
        phase = (phase + 1) % scanningPhases.length;
        scanningStatusText.textContent = "Syndicati AI: " + scanningPhases[phase];
      }, 1000);
    } else {
      scanningOverlay.classList.remove('active');
      if (scanningInterval) {
        clearInterval(scanningInterval);
        scanningInterval = null;
      }
    }
  }

  console.log('[Syndicati Agent] UI loaded and ready');
})();
