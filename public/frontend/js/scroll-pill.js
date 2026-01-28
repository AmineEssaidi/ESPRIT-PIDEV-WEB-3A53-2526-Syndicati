(() => {
  function getScrollHeight() {
    const doc = document.documentElement;
    const body = document.body || { scrollHeight: 0 };
    return Math.max(doc.scrollHeight, body.scrollHeight);
  }

  function initScrollPill() {
    const pill = document.getElementById('scrollDownPill');
    const btnDown = document.getElementById('scrollPillBtnDown');
    const btnUp = document.getElementById('scrollPillBtnUp');

    if (!pill || !btnDown || !btnUp) return;

    let morphed = false;
    let downClickTimeout = null;
    let upClickTimeout = null;
    const dblDelay = 300;

    function ensureSplitShown() {
      if (morphed) return;
      morphed = true;
      btnUp.style.display = 'flex';
      btnDown.classList.add('morph');
      window.setTimeout(() => btnDown.classList.remove('morph'), 120);
    }

    function scrollOneDown() {
      window.scrollBy({ top: window.innerHeight, left: 0, behavior: 'smooth' });
    }

    function scrollToBottom() {
      const target = Math.max(0, getScrollHeight() - window.innerHeight);
      window.scrollTo({ top: target, left: 0, behavior: 'smooth' });
    }

    function scrollOneUp() {
      const target = Math.max(0, window.scrollY - window.innerHeight);
      window.scrollTo({ top: target, left: 0, behavior: 'smooth' });
    }

    function scrollToTop() {
      window.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
    }

    function resetIfAtTop() {
      if (window.scrollY > 0) return;
      btnUp.style.display = 'none';
      btnDown.style.display = 'flex';
      morphed = false;
    }

    function resetIfAtBottom() {
      // Avoid conflicting with "top" logic on very small pages
      if (window.scrollY <= 0) return;

      const scrollHeight = getScrollHeight();
      if (window.innerHeight + window.scrollY >= scrollHeight - 2) {
        btnDown.style.display = 'none';
        btnUp.style.display = 'flex';
      } else if (morphed) {
        btnDown.style.display = 'flex';
        btnUp.style.display = 'flex';
      }
    }

    function updateHeroVisibility() {
      const hero = document.querySelector('.main-home-hero');
      if (!hero) return;
      const rect = hero.getBoundingClientRect();
      if (rect.bottom < 120) pill.classList.add('hide');
      else pill.classList.remove('hide');
    }

    // Down: single click = one viewport down, double click = bottom
    btnDown.addEventListener('click', (e) => {
      e.preventDefault();
      window.clearTimeout(downClickTimeout);
      downClickTimeout = window.setTimeout(() => {
        scrollOneDown();
        ensureSplitShown();
      }, dblDelay);
    });

    btnDown.addEventListener('dblclick', (e) => {
      e.preventDefault();
      window.clearTimeout(downClickTimeout);
      scrollToBottom();
      ensureSplitShown();
    });

    // Up: single click = one viewport up, double click = top
    btnUp.addEventListener('click', (e) => {
      e.preventDefault();
      window.clearTimeout(upClickTimeout);
      upClickTimeout = window.setTimeout(() => {
        scrollOneUp();
      }, dblDelay);
    });

    btnUp.addEventListener('dblclick', (e) => {
      e.preventDefault();
      window.clearTimeout(upClickTimeout);
      scrollToTop();
    });

    function handleScroll() {
      resetIfAtTop();
      resetIfAtBottom();
      updateHeroVisibility();
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollPill);
  } else {
    initScrollPill();
  }
})();
