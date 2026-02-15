/**
 * FormValidator Utility
 * Handles real-time input validation and mapping AJAX errors to field placeholders.
 */

class FormValidator {
    constructor(formId, config = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) return;

        this.config = Object.assign({
            errorClass: 'glass-form-error',
            inputErrorClass: 'is-invalid',
            validClass: 'is-valid'
        }, config);

        this.init();
    }

    init() {
        // Double-check: Ensure browser validation is off
        this.form.setAttribute('novalidate', 'novalidate');

        const inputs = this.form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('input', () => this.validateField(input));
            input.addEventListener('blur', () => this.validateField(input));
        });
    }

    validateField(input) {
        const name = input.getAttribute('name');
        if (!name) return;

        let error = null;
        const value = input.value.trim();

        // Get validation rules from attributes
        const minLength = input.dataset.minLength || 0;
        const required = input.hasAttribute('required') || input.dataset.required === 'true';

        if (required && !value) {
            error = "This field is required.";
        } else if (minLength > 0 && value.length < minLength) {
            error = `Minimum ${minLength} characters required. Current: ${value.length}`;
        }

        this.showError(input, error);
        return !error;
    }

    showError(input, message) {
        const group = input.closest('.glass-form-group, .syndicat-form-group, .main-home-form-group');
        if (!group) return;

        let errorEl = group.querySelector('.' + this.config.errorClass);
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = this.config.errorClass;
            errorEl.style.color = '#ff4d4d';
            errorEl.style.fontSize = '0.85rem';
            errorEl.style.marginTop = '0.5rem';
            errorEl.style.fontWeight = '600';
            errorEl.style.minHeight = '1.2rem';
            group.appendChild(errorEl);
        }

        if (message) {
            group.appendChild(errorEl);
            errorEl.textContent = message;
            input.classList.add(this.config.inputErrorClass);
            input.classList.remove(this.config.validClass);
        } else {
            errorEl.textContent = '';
            input.classList.remove(this.config.inputErrorClass);
            if (input.value.trim().length > 0) {
                input.classList.add(this.config.validClass);
            }
        }
    }

    mapErrors(errors) {
        // Clear all previous errors first
        this.form.querySelectorAll('.' + this.config.errorClass).forEach(el => el.textContent = '');
        this.form.querySelectorAll('.' + this.config.inputErrorClass).forEach(el => el.classList.remove(this.config.inputErrorClass));

        for (const [fieldName, message] of Object.entries(errors)) {
            // Find input by name (handles Symfony's nested names like form[field])
            let input = this.form.querySelector(`[name*="[${fieldName}]"]`) || this.form.querySelector(`[name="${fieldName}"]`);

            if (input) {
                this.showError(input, Array.isArray(message) ? message[0] : message);
            }
        }
    }
}

// Global initialization helper
window.initRealTimeValidation = function (formId, rules = {}) {
    const validator = new FormValidator(formId);

    // Apply dataset rules to inputs
    for (const [field, rule] of Object.entries(rules)) {
        const input = document.getElementById(field) || document.querySelector(`[name*="[${field}]"]`);
        if (input) {
            if (rule.min) input.dataset.minLength = rule.min;
            if (rule.required) input.setAttribute('required', 'required');
        }
    }

    return validator;
};
