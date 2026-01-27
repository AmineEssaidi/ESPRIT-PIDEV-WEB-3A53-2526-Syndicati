/**
 * Address Bar Rewrite Script
 * Changes the visual appearance of the address bar to show www.amipidev.tn
 * while keeping the actual server running on 127.0.0.1:8000
 */

console.log('🚀 Address Bar Rewrite Script Loading...');

// Only run if we're on localhost or 127.0.0.1
const currentHost = window.location.hostname;
console.log('Current host:', currentHost);

if (currentHost !== '127.0.0.1' && currentHost !== 'localhost') {
    console.log('Not on localhost, skipping address bar rewrite');
} else {
    console.log('✅ On localhost, proceeding with address bar rewrite');
    
    const customDomain = 'www.amipidev.tn';
    
    // Function to rewrite the address bar visually
    function rewriteAddressBar() {
        // Create a fake URL that shows the custom domain without port
        const fakeUrl = `http://${customDomain}${window.location.pathname}${window.location.search}${window.location.hash}`;
        
        console.log('Attempting to rewrite address bar to:', fakeUrl);
        
        // Use history.replaceState to change the URL in the address bar
        try {
            window.history.replaceState(null, '', fakeUrl);
            console.log('✅ Address bar successfully rewritten to:', fakeUrl);
            
            // Update the page title to reflect the custom domain
            const originalTitle = document.title;
            if (!originalTitle.includes(customDomain)) {
                document.title = `${originalTitle} - ${customDomain}`;
            }
            
        } catch (error) {
            console.error('❌ Could not rewrite address bar:', error);
        }
    }
    
    // Function to handle navigation
    function handleNavigation() {
        // Intercept clicks on links to maintain the custom domain appearance
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href) {
                const url = new URL(link.href);
                
                // If it's a relative link or same origin, rewrite it
                if (url.hostname === currentHost || url.hostname === 'localhost') {
                    e.preventDefault();
                    
                    // Create new URL with custom domain (no port)
                    const newUrl = `http://${customDomain}${url.pathname}${url.search}${url.hash}`;
                    
                    console.log('Navigating to:', newUrl);
                    // Navigate to the new URL
                    window.location.href = newUrl;
                }
            }
        });
    }
    
    // Function to handle browser back/forward buttons
    function handlePopState() {
        window.addEventListener('popstate', function(e) {
            console.log('Popstate event, rewriting address bar...');
            // Rewrite the address bar again after navigation
            setTimeout(rewriteAddressBar, 100);
        });
    }
    
    // Initialize immediately
    console.log('Initializing address bar rewrite...');
    rewriteAddressBar();
    handleNavigation();
    handlePopState();
    
    // Also rewrite on page load
    window.addEventListener('load', function() {
        console.log('Page loaded, rewriting address bar...');
        rewriteAddressBar();
    });
    
    // Rewrite periodically to ensure it stays
    setInterval(function() {
        if (window.location.hostname !== customDomain) {
            console.log('Periodic rewrite triggered');
            rewriteAddressBar();
        }
    }, 2000);
    
    console.log('✅ Address Bar Rewrite Script Initialized');
}
