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
            prepareData: (formData) => formData,
            validate: () => true,
        }, config);

        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));
        
        // Setup validation listeners for all inputs
        const inputs = this.form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(input => {
            input.addEventListener('input', () => this.checkFormValidity());
        });

        // Initial check
        this.checkFormValidity();
        
        // Initialize password toggles if they exist
        if (typeof window.initPasswordToggles === 'function') {
            window.initPasswordToggles();
        }
    }

    checkFormValidity() {
        if (!this.submitBtn) return;

        // Custom validation first
        const customValid = this.config.validate();
        
        // General "all required fields filled" check
        const inputs = Array.from(this.form.querySelectorAll('input[required], input:not([type="hidden"])'));
        const allFilled = inputs.every(input => {
            if (input.type === 'checkbox') return input.required ? input.checked : true;
            if (input.type === 'radio') {
                const name = input.name;
                return this.form.querySelector(`input[type="radio"][name="${name}"]:checked`) !== null;
            }
            // Only check non-hidden inputs that aren't buttons
            if (input.type === 'submit' || input.type === 'button') return true;
            return input.value.trim() !== "";
        });

        const isValid = allFilled && customValid;

        this.submitBtn.disabled = !isValid;
        this.submitBtn.classList.toggle("active", isValid);
        
        return isValid;
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (!this.checkFormValidity()) return;

        const originalBtnText = this.submitBtn.textContent;
        this.submitBtn.textContent = this.submitBtn.dataset.processingText || 'Processing...';
        this.submitBtn.disabled = true;
        
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
            const data = await response.json();

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
            console.error('Form submission error:', error);
            this.showError('A connection error occurred. Please try again.');
        } finally {
            this.submitBtn.textContent = originalBtnText;
            this.submitBtn.disabled = false;
            this.checkFormValidity();
        }
    }

    handleError(data, status) {
        let message = data.message || 'An error occurred. Please try again.';
        
        if (data.errors) {
            message = Object.values(data.errors).flat().join('. ');
        }

        if (status === 403 && data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        this.showError(message);
    }

    showError(message) {
        if (this.errorMsg) {
            this.errorMsg.innerHTML = message;
            this.errorMsg.style.display = 'block';
        } else {
            alert(message);
        }
    }
    
    showSuccess(message) {
        if (this.successMsg) {
            this.successMsg.textContent = message;
            this.successMsg.style.display = 'block';
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
        toggleEl.setAttribute('aria-label', 'Show password');
        toggleEl.setAttribute('aria-pressed', 'false');

        function syncToggleVisibility() {
            const hasValue = inputEl.value.trim() !== '';
            toggleEl.classList.toggle('visible', hasValue);
            
            if (!hasValue) {
                inputEl.type = 'password';
                toggleEl.innerHTML = eyeOffIcon;
                toggleEl.setAttribute('aria-label', 'Show password');
                toggleEl.setAttribute('aria-pressed', 'false');
            }
        }

        toggleEl.addEventListener('click', function () {
            const isHidden = inputEl.type === 'password';
            inputEl.type = isHidden ? 'text' : 'password';
            toggleEl.innerHTML = isHidden ? eyeIcon : eyeOffIcon;
            toggleEl.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            toggleEl.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
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
