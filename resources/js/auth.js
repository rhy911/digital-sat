/**
 * Auth shared logic for handling forms and UI components
 */

export class AuthForm {
    constructor(formId, config = {}) {
        this.form = document.getElementById(formId);
        if (!this.form) return;

        this.submitBtn = this.form.querySelector('button[type="submit"]') || document.getElementById('submitBtn');
        this.errorMsg = document.getElementById('errorMessage');
        this.successMsg = document.getElementById('successMessage');
        
        this.config = Object.assign({
            onSuccess: null,
            onError: null,
            onCooldown: null,
            prepareData: (formData) => formData,
            validate: () => true,
        }, config);
        this.cooldownUntil = 0;

        this.init();
    }

    init() {
        this.form.noValidate = true;
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Setup validation listeners for all inputs
        const inputs = this.form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                this.clearFieldError(input);
                if (this.errorMsg) {
                    this.errorMsg.textContent = '';
                    this.errorMsg.style.display = 'none';
                }
                this.checkFormValidity();
            });
        });

        // Initial check
        this.checkFormValidity();
        
        // Initialize password toggles if they exist
        if (typeof window.initPasswordToggles === 'function') {
            window.initPasswordToggles();
        }
    }

    getInvalidInput() {
        return Array.from(this.form.querySelectorAll('input:not([type="hidden"]), select, textarea'))
            .find(input => !input.checkValidity());
    }

    checkFormValidity() {
        if (!this.submitBtn) return true;

        const isValid = this.config.validate() && this.form.checkValidity();
        this.submitBtn.classList.toggle("active", isValid);
        
        return isValid;
    }

    async handleSubmit(e) {
        e.preventDefault();
        if (this.submitting || Date.now() < this.cooldownUntil) return;

        if (!this.checkFormValidity()) {
            const invalidInput = this.getInvalidInput();
            if (invalidInput) {
                invalidInput.focus();
                this.markFieldError(invalidInput, invalidInput.validationMessage || 'Check this field.');
                this.showError(invalidInput.validationMessage || 'Check the highlighted field.');
            } else {
                const firstInvalid = this.form.querySelector('.is-invalid, :invalid');
                if (firstInvalid) firstInvalid.focus();
                this.showError('Please check the form for errors.');
            }
            return;
        }

        const originalBtnText = this.submitBtn.textContent;
        this.submitting = true;
        this.submitBtn.textContent = this.submitBtn.dataset.processingText || 'Processing...';
        this.submitBtn.disabled = true;
        this.submitBtn.classList.add('is-busy');
        
        if (this.errorMsg) this.errorMsg.style.display = 'none';
        if (this.successMsg) this.successMsg.style.display = 'none';

        try {
            let formData = new FormData(this.form);
            const preparedData = this.config.prepareData(formData);
            
            // Use provided action/method or defaults
            const url = this.form.action || window.location.href;
            const method = (this.form.method || 'POST').toUpperCase();

            const fetchOptions = {
                method: method,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || 
                                   (this.form.querySelector('input[name="_token"]')?.value),
                    'Accept': 'application/json'
                }
            };

            if (method !== 'GET' && method !== 'HEAD') {
                fetchOptions.body = preparedData instanceof FormData ? preparedData : JSON.stringify(preparedData);
                if (!(preparedData instanceof FormData)) {
                    fetchOptions.headers['Content-Type'] = 'application/json';
                }
            }

            const response = await fetch(url, fetchOptions);
            let data;
            try {
                data = await response.json();
            } catch {
                throw new Error(response.status === 419
                    ? 'Your session expired. Reload this page and try again.'
                    : 'We could not read the response. Please try again.');
            }

            if (response.status === 419) {
                this.showError('Your session expired. Reload this page and try again.');
                return;
            }

            if (response.ok) {
                if (this.config.onSuccess) {
                    this.config.onSuccess(data);
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else if (data.message) {
                    this.showSuccess(data.message);
                }
            } else {
                if (this.config.onError) {
                    this.config.onError(data, response.status);
                }
                this.handleError(data, response.status);
            }
        } catch (error) {
            this.showError('A connection error occurred. Please try again.');
        } finally {
            this.submitting = false;
            this.submitBtn.textContent = originalBtnText;
            this.submitBtn.disabled = Date.now() < this.cooldownUntil;
            this.submitBtn.classList.remove('is-busy');
            this.checkFormValidity();
        }
    }

    handleError(data, status) {
        const message = data.message || 'We could not complete this request. Please try again.';

        if (status === 403 && data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        if (data.errors) {
            Object.entries(data.errors).forEach(([name, messages]) => {
                const input = this.form.querySelector(`[name="${CSS.escape(name)}"]`);
                if (input) this.markFieldError(input, Array.isArray(messages) ? messages[0] : messages);
            });
        }
        this.showError(message);
        const firstInvalid = this.form.querySelector('[aria-invalid="true"]');
        firstInvalid?.focus();
    }

    markFieldError(input, message) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        const describedBy = input.getAttribute('aria-describedby') || '';
        const feedbackId = input.name === 'role'
            ? 'role-error'
            : describedBy.split(/\s+/).find(id => id.endsWith('-error')) || `${input.id}-error`;
        const feedback = this.form.querySelector(`#${CSS.escape(feedbackId)}`);
        if (feedback) feedback.textContent = message;
    }

    clearFieldError(input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        const feedback = input.id && this.form.querySelector(`#${CSS.escape(input.id)}-error`);
        if (feedback) feedback.textContent = '';
        if (input.type === 'password' && input.id === 'password_confirmation') input.setCustomValidity('');
    }

    setCooldown(seconds) {
        this.cooldownUntil = Date.now() + Math.max(0, Number(seconds)) * 1000;
        this.submitBtn.disabled = true;
        this.config.onCooldown?.(seconds);
    }

    showError(message) {
        if (this.errorMsg) {
            this.errorMsg.textContent = message;
            this.errorMsg.style.display = '';
        } else {
            alert(message);
        }
    }

    showSuccess(message) {
        if (this.successMsg) {
            this.successMsg.textContent = message;
            this.successMsg.style.display = '';
        } else {
            alert(message);
        }
    }
}

window.AuthForm = AuthForm;

/**
 * Password visibility toggler
 */
window.initPasswordToggles = function () {
    const eyeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="18" height="18"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    function bindPasswordToggle(inputEl, toggleEl) {
        if (!inputEl || !toggleEl || toggleEl.dataset.bound === '1') {
            return;
        }

        toggleEl.dataset.bound = '1';
        toggleEl.innerHTML = eyeOffIcon;
        if (!toggleEl.hasAttribute('aria-label')) {
            toggleEl.setAttribute('aria-label', 'Show password');
        }
        toggleEl.setAttribute('aria-pressed', 'false');

        function syncToggleVisibility() {
            const hasValue = inputEl.value !== '';
            toggleEl.classList.toggle('visible', hasValue);
            
            if (!hasValue) {
                inputEl.type = 'password';
                toggleEl.innerHTML = eyeOffIcon;
                toggleEl.setAttribute('aria-pressed', 'false');
            }
        }

        toggleEl.addEventListener('click', function () {
            const isHidden = inputEl.type === 'password';
            inputEl.type = isHidden ? 'text' : 'password';
            toggleEl.innerHTML = isHidden ? eyeIcon : eyeOffIcon;
            toggleEl.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
            toggleEl.setAttribute('aria-label', `${isHidden ? 'Hide' : 'Show'} ${inputEl.labels?.[0]?.textContent.trim().toLowerCase() || 'password'}`);
            inputEl.focus();
        });

        inputEl.addEventListener('input', syncToggleVisibility);
        syncToggleVisibility();
    }

    document.querySelectorAll('.password-toggle[data-password-target]').forEach(function (toggleEl) {
        const targetId = toggleEl.getAttribute('data-password-target');
        const inputEl = document.getElementById(targetId);
        bindPasswordToggle(inputEl, toggleEl);
    });
};

// Auto-init password toggles on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    window.initPasswordToggles();
});

document.addEventListener('click', (event) => {
    const backButton = event.target.closest('[data-auth-back-fallback]');
    if (!backButton || event.defaultPrevented) return;

    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    window.location.href = backButton.dataset.authBackFallback || '/signin';
});
