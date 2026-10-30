import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.window = { history: { length: 1 }, location: { href: '' } };
globalThis.document = {
    addEventListener() {},
    getElementById() { return null; },
    querySelector() { return null; },
    querySelectorAll() { return []; },
};
globalThis.CSS = { escape: (value) => value };
globalThis.FormData = class FormDataStub {};

const { AuthForm } = await import('../../resources/js/auth.js');

function makeAuthForm({ valid = true, onSuccess = null } = {}) {
    const classes = new Set();
    const button = {
        textContent: 'Submit',
        disabled: false,
        dataset: { processingText: 'Sending…' },
        classList: {
            add: (value) => classes.add(value),
            remove: (value) => classes.delete(value),
            toggle: (value, enabled) => enabled ? classes.add(value) : classes.delete(value),
        },
    };
    const form = {
        action: '/submit',
        method: 'post',
        checkValidity: () => valid,
        querySelector: (selector) => selector === 'input[name="_token"]' ? null : null,
        querySelectorAll: () => [],
    };
    const auth = Object.create(AuthForm.prototype);
    Object.assign(auth, {
        form,
        submitBtn: button,
        errorMsg: { textContent: '', style: {} },
        successMsg: { textContent: '', style: {} },
        config: { validate: () => true, prepareData: (data) => data, onSuccess, onError: null },
        cooldownUntil: 0,
        submitting: false,
    });
    return { auth, button, classes };
}

test('invalid submit focuses and associates the first invalid field without a request', async () => {
    const { auth } = makeAuthForm({ valid: false });
    let focused = false;
    const invalid = {
        id: 'email',
        name: 'email',
        validationMessage: 'Enter a valid email address.',
        checkValidity: () => false,
        classList: { add() {} },
        setAttribute() {},
        focus() { focused = true; },
    };
    auth.form.querySelectorAll = () => [invalid];
    auth.form.querySelector = () => null;
    auth.markFieldError = (field, message) => { field.message = message; };

    let prevented = false;
    await auth.handleSubmit({ preventDefault() { prevented = true; } });

    assert.equal(prevented, true);
    assert.equal(focused, true);
    assert.equal(invalid.message, 'Enter a valid email address.');
    assert.match(auth.errorMsg.textContent, /valid email/);
});

test('server error copy is rendered as text, never interpreted as HTML', () => {
    const { auth } = makeAuthForm();
    auth.errorMsg.textContent = '';

    auth.showError('<img src=x onerror=alert(1)>');

    assert.equal(auth.errorMsg.textContent, '<img src=x onerror=alert(1)>');
});

test('server field errors are associated with the matching input', () => {
    const { auth } = makeAuthForm();
    const attributes = new Map();
    let focused = false;
    const input = {
        id: 'email',
        name: 'email',
        classList: { add() {}, remove() {} },
        setAttribute: (name, value) => attributes.set(name, value),
        getAttribute: (name) => name === 'aria-describedby' ? 'email-error' : attributes.get(name),
        removeAttribute: (name) => attributes.delete(name),
        focus: () => { focused = true; },
    };
    const feedback = { textContent: '' };
    auth.form.querySelector = (selector) => {
        if (selector === '[name="email"]') return input;
        if (selector === '#email-error') return feedback;
        if (selector === '[aria-invalid="true"]') return input;
        return null;
    };

    auth.handleError({ message: 'Check your email address.', errors: { email: ['Enter a valid email.'] } }, 422);

    assert.equal(attributes.get('aria-invalid'), 'true');
    assert.equal(feedback.textContent, 'Enter a valid email.');
    assert.equal(focused, true);
});

test('request loading clears and a repeated submit does not send twice', async () => {
    const { auth, button, classes } = makeAuthForm({ onSuccess: (data) => { window.location.href = data.redirect; } });
    let resolveFetch;
    let requests = 0;
    globalThis.fetch = () => {
        requests++;
        return new Promise((resolve) => { resolveFetch = resolve; });
    };

    const event = { preventDefault() {} };
    const first = auth.handleSubmit(event);
    const second = auth.handleSubmit(event);

    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Sending…');
    assert.equal(requests, 1);

    resolveFetch({ ok: true, status: 200, json: async () => ({ redirect: '/dashboard' }) });
    await Promise.all([first, second]);

    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Submit');
    assert.equal(classes.has('is-busy'), false);
    assert.equal(window.location.href, '/dashboard');
});

test('a network failure shows recovery copy and restores the submit control', async () => {
    const { auth, button, classes } = makeAuthForm();
    globalThis.fetch = async () => { throw new Error('offline'); };

    await auth.handleSubmit({ preventDefault() {} });

    assert.match(auth.errorMsg.textContent, /connection error/i);
    assert.equal(button.disabled, false);
    assert.equal(button.textContent, 'Submit');
    assert.equal(classes.has('is-busy'), false);
});

test('cooldown prevents keyboard resubmission until the deadline', async () => {
    const { auth, button } = makeAuthForm();
    let requests = 0;
    globalThis.fetch = async () => { requests++; };
    auth.setCooldown(30);

    await auth.handleSubmit({ preventDefault() {} });

    assert.equal(button.disabled, true);
    assert.equal(requests, 0);
});
