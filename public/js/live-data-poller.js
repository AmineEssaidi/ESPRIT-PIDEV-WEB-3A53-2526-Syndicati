(function () {
  'use strict';

  const endpoint = '/api/live/snapshot';
  const cacheKey = 'horizon.live.snapshot.v1';
  const baseDelay = 45000;
  const hiddenDelay = 120000;
  let timer = null;
  let inFlight = null;
  let lastVersion = null;
  let misses = 0;

  function text(value) {
    return String(value ?? '0');
  }

  function setText(selector, value) {
    document.querySelectorAll(selector).forEach((el) => {
      const next = text(value);
      if (el.textContent.trim() !== next) {
        el.textContent = next;
        el.classList.add('live-data-updated');
        window.setTimeout(() => el.classList.remove('live-data-updated'), 450);
      }
    });
  }

  function updateDataFields(payload) {
    document.querySelectorAll('[data-live-field]').forEach((el) => {
      const path = el.getAttribute('data-live-field');
      const value = path.split('.').reduce((carry, key) => carry && carry[key], payload);
      if (value !== undefined && value !== null) setTextElement(el, value);
    });
  }

  function setTextElement(el, value) {
    const next = text(value);
    if (el.textContent.trim() !== next) {
      el.textContent = next;
      el.classList.add('live-data-updated');
      window.setTimeout(() => el.classList.remove('live-data-updated'), 450);
    }
  }

  function applySnapshot(payload) {
    if (!payload || payload.success === false) return;
    if (payload.version && payload.version === lastVersion) return;
    lastVersion = payload.version || lastVersion;

    sessionStorage.setItem(cacheKey, JSON.stringify({ payload, savedAt: Date.now() }));

    setText('#count-pubs', payload.forum && payload.forum.publications);
    setText('#count-comms', payload.forum && payload.forum.comments);
    setText('#count-reacts', payload.forum && payload.forum.reactions);
    setText('#count-bookmarks', payload.forum && payload.forum.bookmarks);

    setText('[data-live-count="circle.friends"]', payload.circle && payload.circle.friends);
    setText('[data-live-count="circle.pending"]', payload.circle && payload.circle.pending);
    setText('[data-live-count="reclamations.total"]', payload.reclamations && payload.reclamations.total);
    setText('[data-live-count="reclamations.pending"]', payload.reclamations && payload.reclamations.pending);
    setText('[data-live-count="standing.level"]', payload.standing && payload.standing.level);
    setText('[data-live-count="standing.points"]', payload.standing && payload.standing.points);
    setText('[data-live-count="standing.label"]', payload.standing && payload.standing.label);
    updateDataFields(payload);

    window.dispatchEvent(new CustomEvent('horizon:live-data', { detail: payload }));
  }

  async function poll() {
    if (!document.body || document.body.dataset.userInfo !== 'true') {
      schedule();
      return;
    }

    if (inFlight) inFlight.abort();
    inFlight = new AbortController();

    try {
      const response = await fetch(endpoint, {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal: inFlight.signal
      });
      if (response.status === 401) return;
      if (!response.ok) throw new Error('Live snapshot failed: ' + response.status);
      applySnapshot(await response.json());
      misses = 0;
    } catch (error) {
      if (error.name !== 'AbortError') misses = Math.min(misses + 1, 5);
    } finally {
      inFlight = null;
      schedule();
    }
  }

  function schedule() {
    window.clearTimeout(timer);
    const delay = document.hidden ? hiddenDelay : baseDelay * Math.max(1, misses);
    timer = window.setTimeout(poll, delay);
  }

  function hydrateFromCache() {
    try {
      const cached = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
      if (cached && cached.payload && Date.now() - cached.savedAt < 60000) {
        applySnapshot(cached.payload);
      }
    } catch (error) {
      sessionStorage.removeItem(cacheKey);
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
    else schedule();
  });

  document.addEventListener('DOMContentLoaded', function () {
    hydrateFromCache();
    window.setTimeout(poll, 2200);
  });
})();
