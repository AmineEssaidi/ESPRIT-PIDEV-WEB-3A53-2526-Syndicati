/**
 * Page Loader Script
 * Shows an animated loading screen when navigating between pages
 */

(function() {
    'use strict';
    
    const LOADER_DURATION = 400; // 0.4 seconds
    const loader = document.getElementById('pageLoader');
    
    if (!loader) return;
    
    // Function to show the loader immediately
    function showLoader() {
        loader.classList.remove('fade-out');
        loader.classList.add('active');
        // Force browser to paint immediately
        loader.offsetHeight;
    }
    
    // Function to hide the loader
    function hideLoader() {
        loader.classList.add('fade-out');
        setTimeout(() => {
            loader.classList.remove('active', 'fade-out');
        }, 300);
    }
    
    // Function to navigate after loader is visible
    function navigateWithLoader(url) {
        // Show loader first
        showLoader();
        
        // Use requestAnimationFrame to ensure loader is rendered before navigating
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                setTimeout(() => {
                    window.location.href = url;
                }, LOADER_DURATION);
            });
        });
    }
    
    // Intercept all internal link clicks
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        
        if (!link) return;
        
        const href = link.getAttribute('href');
        
        // Skip if no href
        if (!href) return;
        
        // Force loader for cross-section links (Dashboard, Main Home, etc.)
        const forceLoader = link.hasAttribute('data-page-loader');
        
        if (!forceLoader) {
            // Skip external links, anchors, javascript:, mailto:, tel:, etc.
            if (href.startsWith('#') || 
                href.startsWith('javascript:') || 
                href.startsWith('mailto:') || 
                href.startsWith('tel:') ||
                (href.startsWith('http://') && !href.includes(window.location.host)) ||
                (href.startsWith('https://') && !href.includes(window.location.host))) {
                return;
            }
        } else {
            // data-page-loader: only skip anchors and empty
            if (href.startsWith('#') || href === '') return;
        }
        
        // Skip links that open in new tab
        if (link.target === '_blank') return;
        
        // Skip links with download attribute
        if (link.hasAttribute('download')) return;
        
        // Skip if modifier keys are pressed (for opening in new tab)
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        
        // Skip dropdown items that don't navigate
        if (link.classList.contains('dropdown-toggle')) return;
        
        // Prevent default navigation
        e.preventDefault();
        e.stopPropagation();
        
        // Navigate with loader
        navigateWithLoader(href);
    }, true); // Use capture phase to intercept early
    
    // Handle form submissions
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.nodeName !== 'FORM') return;
        
        // Skip forms that open in new tab
        if (form.target === '_blank') return;
        
        // Skip AJAX forms
        if (form.hasAttribute('data-ajax')) return;
        
        // Skip loader for sign-in (shows TOTP popup without full-page reload feel)
        if (form.hasAttribute('data-no-page-loader')) return;
        if (form.id === 'signin-form') return;
        if (form.classList && form.classList.contains('main-home-auth-form')) return;
        try {
            const action = (form.getAttribute('action') || '').toLowerCase();
            if (action.indexOf('sign-in') !== -1) return;
        } catch (err) {}
        
        showLoader();
    }, true);
    
    // Handle browser back/forward buttons
    window.addEventListener('pageshow', function(e) {
        // Hide loader when page is shown from cache
        if (e.persisted) {
            hideLoader();
        }
    });
    
    // Hide loader when page fully loads (for initial page load)
    window.addEventListener('load', function() {
        hideLoader();
    });
    
    // Also hide on DOMContentLoaded as backup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure smooth transition
            setTimeout(hideLoader, 100);
        });
    } else {
        setTimeout(hideLoader, 100);
    }
    
})();
