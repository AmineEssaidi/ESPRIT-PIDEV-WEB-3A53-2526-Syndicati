/**
 * Sign-in form: require hCaptcha to be completed before allowing submit.
 * Hide hCaptcha localhost warning. Include only on sign-in page when hCaptcha is enabled.
 */
(function () {
    function hideLocalhostWarning() {
        var wrap = document.querySelector('.main-home-hcaptcha-wrap');
        if (!wrap) return;
        var walk = function (node) {
            if (node.nodeType === 1) {
                var text = (node.textContent || '').toLowerCase();
                if (text.indexOf('localhost') !== -1 && text.indexOf('valid host') !== -1) {
                    node.style.setProperty('display', 'none', 'important');
                    return;
                }
                for (var i = 0; i < node.childNodes.length; i++) walk(node.childNodes[i]);
            }
        };
        walk(wrap);
    }

    function init() {
        var form = document.querySelector('.main-home-auth-form');
        var captchaWrap = document.querySelector('.main-home-hcaptcha-wrap');
        if (!form || !captchaWrap) return;

        form.addEventListener('submit', function (e) {
            var responseInput = document.querySelector('[name="h-captcha-response"]');
            if (!responseInput || !responseInput.value || responseInput.value.trim() === '') {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                alert('Please complete the security verification (captcha) before signing in.');
                var loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.add('fade-out');
                    setTimeout(function () {
                        loader.classList.remove('active', 'fade-out');
                    }, 300);
                }
                return false;
            }
        }, true);

        hideLocalhostWarning();
        setTimeout(hideLocalhostWarning, 800);
        setTimeout(hideLocalhostWarning, 2000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
