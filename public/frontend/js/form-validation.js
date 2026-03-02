/**
 * FormValidator Utility (V2)
 * Handles real-time input validation and mapping AJAX errors to field placeholders.
 */

class FormValidator {
    constructor(formId, config = {}) {
        this.form = (typeof formId === 'string') ? document.getElementById(formId) : formId;
        if (!this.form) return;

        this.config = Object.assign({
            errorClass: 'error',
            successClass: 'success',
            activeClass: 'active',
            inputErrorClass: 'input-error',
            inputSuccessClass: 'input-success',
            validationContainerSelector: '.validation-container, .main-home-validation-message'
        }, config);

        this.rules = {};
        this.init();
    }

    init() {
        // Disable browser validation
        this.form.setAttribute('novalidate', 'novalidate');

        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            // Standard validation events
            input.addEventListener('input', () => this.validateField(input));
            input.addEventListener('blur', () => this.validateField(input));

            // Auto-detect rules from HTML attributes
            this.detectRules(input);
        });
    }

    detectRules(input) {
        const name = this.getFieldName(input);
        if (!name) return;

        const rules = {};
        if (input.hasAttribute('required')) rules.required = true;
        if (input.hasAttribute('minlength')) rules.min = parseInt(input.getAttribute('minlength'));
        if (input.getAttribute('type') === 'email') rules.email = true;
        if (input.dataset.match) rules.match = input.dataset.match;
        if (input.dataset.pattern) rules.pattern = new RegExp(input.dataset.pattern);

        this.rules[name] = Object.assign(this.rules[name] || {}, rules);
    }

    validateField(input) {
        const name = this.getFieldName(input);
        const rules = this.rules[name];
        if (!rules) return true;

        const value = input.value.trim();
        let error = null;

        // 1. Required
        if (rules.required && !value) {
            error = "This field is required.";
        }
        // 2. Email
        else if (rules.email && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            error = "Please enter a valid email address.";
        }
        // 3. Min Length
        else if (rules.min && value && value.length < rules.min) {
            error = `Minimum ${rules.min} characters required.`;
        }
        // 4. Pattern (Regex)
        else if (rules.pattern && value && !rules.pattern.test(value)) {
            error = input.dataset.patternMessage || "Invalid format.";
        }
        // 5. Match (e.g., password confirm)
        else if (rules.match) {
            const target = document.getElementById(rules.match) || document.querySelector(`[name*="${rules.match}"]`);
            if (target && value !== target.value.trim()) {
                error = "Fields do not match.";
            }
        }

        this.showFeedback(input, error);
        return !error;
    }

    showFeedback(input, message) {
        const fieldName = this.getFieldName(input);
        const group = input.closest('.glass-form-group, .syndicat-form-group, .main-home-form-group, .form-group');
        let container = null;

        if (group) {
            container = group.querySelector(`[data-field="${fieldName}"]`) ||
                group.querySelector(this.config.validationContainerSelector);
        }

        // Fallback: search anywhere in form if not in group
        if (!container) {
            container = this.form.querySelector(`[data-field="${fieldName}"]`) ||
                this.form.querySelector(`[data-field$="[${fieldName}]"]`);
        }

        if (message) {
            // Error State
            input.classList.add(this.config.inputErrorClass);
            input.classList.remove(this.config.inputSuccessClass);
            if (container) {
                container.innerHTML = `<i class='bx bx-error-circle'></i> ${message}`;
                container.classList.add(this.config.activeClass, this.config.errorClass);
                container.classList.remove(this.config.successClass);
            }
        } else {
            // Success or Neutral State
            input.classList.remove(this.config.inputErrorClass);
            const value = input.value.trim();

            if (value.length > 0) {
                input.classList.add(this.config.inputSuccessClass);
            } else {
                input.classList.remove(this.config.inputSuccessClass);
            }

            if (container) {
                container.textContent = '';
                container.classList.remove(this.config.activeClass, this.config.errorClass);
            }
        }
    }

    getFieldName(input) {
        const nameAttr = input.getAttribute('name');
        if (!nameAttr) return input.id || null;

        // Strip Symfony's naming convention: 
        // Handles flat: form[field] -> field
        // Handles nested: form[sub][field] -> field
        // Handles deeply nested: form[a][b][c] -> c
        const matches = nameAttr.match(/\[(.*?)\]/g);
        if (matches && matches.length > 0) {
            const lastMatch = matches[matches.length - 1];
            return lastMatch.replace(/[\[\]]/g, '');
        }

        return nameAttr;
    }

    mapErrors(errors) {
        // Clear all previous feedbacks
        this.form.querySelectorAll(this.config.validationContainerSelector).forEach(el => {
            el.textContent = '';
            el.classList.remove(this.config.activeClass, this.config.errorClass, this.config.successClass);
        });
        this.form.querySelectorAll('.' + this.config.inputErrorClass).forEach(el => el.classList.remove(this.config.inputErrorClass));

        for (const [fieldName, message] of Object.entries(errors)) {
            const input = this.form.querySelector(`[name*="[${fieldName}]"]`) ||
                this.form.querySelector(`[name="${fieldName}"]`) ||
                this.form.querySelector(`#${fieldName}`);

            if (input) {
                const msg = Array.isArray(message) ? message[0] : message;
                this.showFeedback(input, msg);
            }
        }
    }
}

// Global initialization helper
window.initRealTimeValidation = function (formId, customRules = {}) {
    const validator = new FormValidator(formId);
    if (validator.form) {
        validator.rules = Object.assign(validator.rules, customRules);
    }
    return validator;
};

// Global Auto-Init for AJAX forms
document.addEventListener('DOMContentLoaded', () => {
    const initAllAjaxForms = () => {
        document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
            if (!form.validator) {
                form.validator = new FormValidator(form);
            }
        });
    };

    initAllAjaxForms();

    // Handle dynamic forms (modals, AJAX loaded content)
    const observer = new MutationObserver((mutations) => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) {
                    if (node.tagName === 'FORM' && node.getAttribute('data-ajax') === 'true') {
                        if (!node.validator) node.validator = new FormValidator(node);
                    } else {
                        node.querySelectorAll('form[data-ajax="true"]').forEach(form => {
                            if (!form.validator) form.validator = new FormValidator(form);
                        });
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
});
