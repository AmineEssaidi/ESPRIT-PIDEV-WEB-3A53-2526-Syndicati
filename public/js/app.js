// Enhanced mobile menu toggle, theme toggle, and interactive animations
(function () {
  const menu = document.getElementById('layout-menu');
  const toggles = document.querySelectorAll('.layout-menu-toggle');
  const themeToggle = document.getElementById('theme-toggle');

  // Mobile menu toggle
  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!menu) return;
      menu.classList.toggle('show');
    });
  });

  // iOS Style Theme toggle functionality (fix for settings page)
  function attachThemeToggleHandler() {
    if (!themeToggle) return;
    // Remove any previous click handlers by cloning
    const newToggle = themeToggle.cloneNode(true);
    themeToggle.parentNode.replaceChild(newToggle, themeToggle);
    newToggle.addEventListener('click', function () {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      // Add smooth animation to the toggle
      this.style.transform = 'scale(0.95)';
      setTimeout(() => {
        this.style.transform = '';
      }, 150);
    });
  }
  // Get saved theme or default to dark
  const savedTheme = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);
  attachThemeToggleHandler();

  // Add expanding text animation to interactive elements
  const expandingElements = document.querySelectorAll('.menu-link, .btn, .card-title, .search-input, .theme-btn, .notification-btn');

  expandingElements.forEach(element => {
    element.addEventListener('mouseenter', function () {
      this.classList.add('expanding-text');
    });

    element.addEventListener('mouseleave', function () {
      this.classList.remove('expanding-text');
    });
  });

  // Search functionality with animations
  const searchInput = document.querySelector('.search-input');
  if (searchInput) {
    searchInput.addEventListener('focus', function () {
      this.parentElement.classList.add('expanding-text');
    });

    searchInput.addEventListener('blur', function () {
      this.parentElement.classList.remove('expanding-text');
    });
  }

  // Dashboard Carousel Functionality
  const carouselTrack = document.getElementById('carouselTrack');
  const prevBtn = document.getElementById('prevWidget');
  const nextBtn = document.getElementById('nextWidget');
  const indicators = document.querySelectorAll('.indicator');
  const toggleAutoplay = document.getElementById('toggleAutoplay');
  const autoplayIcon = document.getElementById('autoplayIcon');
  const autoplayText = document.getElementById('autoplayText');

  console.log('Carousel elements found:', {
    carouselTrack: !!carouselTrack,
    prevBtn: !!prevBtn,
    nextBtn: !!nextBtn,
    indicators: indicators.length,
    toggleAutoplay: !!toggleAutoplay
  });

  if (carouselTrack && prevBtn && nextBtn) {
    let currentSlide = 0;
    const slides = document.querySelectorAll('.carousel-slide');
    const totalSlides = slides.length;
    let autoplayInterval;
    let isAutoplayActive = true;

    console.log('Total slides found:', totalSlides);

    // Auto-play functionality
    function startAutoplay() {
      autoplayInterval = setInterval(() => {
        nextSlide();
      }, 4000); // Change slide every 4 seconds
    }

    function stopAutoplay() {
      clearInterval(autoplayInterval);
    }

    function updateSlide() {
      const translateX = -currentSlide * 100; // 100% per slide
      carouselTrack.style.transform = `translateX(${translateX}%)`;
      console.log('Updating slide:', currentSlide, 'translateX:', translateX);

      // Update indicators
      indicators.forEach((indicator, index) => {
        indicator.classList.toggle('active', index === currentSlide);
      });
    }

    function nextSlide() {
      currentSlide = (currentSlide + 1) % totalSlides;
      updateSlide();
    }

    function prevSlide() {
      currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
      updateSlide();
    }

    function goToSlide(slideIndex) {
      currentSlide = slideIndex;
      updateSlide();
    }

    // Event listeners
    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      console.log('Next button clicked');
      nextSlide();
      if (isAutoplayActive) {
        stopAutoplay();
        startAutoplay(); // Restart autoplay after manual interaction
      }
    });

    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      console.log('Previous button clicked');
      prevSlide();
      if (isAutoplayActive) {
        stopAutoplay();
        startAutoplay(); // Restart autoplay after manual interaction
      }
    });

    indicators.forEach((indicator, index) => {
      indicator.addEventListener('click', (e) => {
        e.preventDefault();
        console.log('Indicator clicked:', index);
        goToSlide(index);
        if (isAutoplayActive) {
          stopAutoplay();
          startAutoplay(); // Restart autoplay after manual interaction
        }
      });
    });

    // Toggle autoplay
    if (toggleAutoplay) {
      toggleAutoplay.addEventListener('click', () => {
        isAutoplayActive = !isAutoplayActive;

        if (isAutoplayActive) {
          startAutoplay();
          autoplayIcon.className = 'bx bx-pause';
          autoplayText.textContent = 'Pause';
        } else {
          stopAutoplay();
          autoplayIcon.className = 'bx bx-play';
          autoplayText.textContent = 'Play';
        }
      });
    }

    // Pause autoplay on hover
    const carouselWrapper = document.querySelector('.carousel-wrapper');
    if (carouselWrapper) {
      carouselWrapper.addEventListener('mouseenter', () => {
        if (isAutoplayActive) {
          stopAutoplay();
        }
      });

      carouselWrapper.addEventListener('mouseleave', () => {
        if (isAutoplayActive) {
          startAutoplay();
        }
      });
    }

    // Start autoplay initially
    startAutoplay();
  }


  // Status Badge Management
  const statusBadges = document.querySelectorAll('.status-badge');

  if (statusBadges.length > 0) {
    // Simulate realistic online/offline status changes
    function updateStatusBadges() {
      statusBadges.forEach((badge, index) => {
        // Random chance to change status (10% chance every 30 seconds)
        if (Math.random() < 0.1) {
          const isOnline = badge.classList.contains('online');
          const statusDot = badge.querySelector('.status-dot');
          const statusText = badge.querySelector('.status-text');

          if (isOnline) {
            // Switch to offline
            badge.classList.remove('online');
            badge.classList.add('offline');
            statusText.textContent = 'Offline';
            statusDot.style.animation = 'pulse-offline 2s infinite';
          } else {
            // Switch to online
            badge.classList.remove('offline');
            badge.classList.add('online');
            statusText.textContent = 'Online';
            statusDot.style.animation = 'pulse 2s infinite';
          }

          // Add a subtle animation when status changes
          badge.style.transform = 'scale(1.1)';
          setTimeout(() => {
            badge.style.transform = 'scale(1)';
          }, 200);
        }
      });
    }

    // Update status every 30 seconds
    setInterval(updateStatusBadges, 30000);

    // Add click functionality to manually toggle status
    statusBadges.forEach(badge => {
      badge.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const statusDot = badge.querySelector('.status-dot');
        const statusText = badge.querySelector('.status-text');

        if (badge.classList.contains('online')) {
          badge.classList.remove('online');
          badge.classList.add('offline');
          statusText.textContent = 'Offline';
          statusDot.style.animation = 'pulse-offline 2s infinite';
        } else {
          badge.classList.remove('offline');
          badge.classList.add('online');
          statusText.textContent = 'Online';
          statusDot.style.animation = 'pulse 2s infinite';
        }

        // Add click animation
        badge.style.transform = 'scale(0.95)';
        setTimeout(() => {
          badge.style.transform = 'scale(1)';
        }, 150);
      });
    });
  }
})();


