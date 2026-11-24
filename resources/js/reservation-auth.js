export function enableReservationAuth() {
    const dialog = document.querySelector('[data-reservation-auth]');
    if (!dialog) return;
    const form = dialog.querySelector('[data-reservation-login]');
    const submit = dialog.querySelector('[data-auth-submit]');
    const signup = dialog.querySelector('[data-auth-signup]');
    const password = dialog.querySelector('#reservation-login-password');
    const passwordToggle = dialog.querySelector('[data-auth-password-toggle]');
    const status = dialog.querySelector('[data-auth-status]');
    const error = dialog.querySelector('[data-auth-error]');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let attempt = 0;
    let trigger;
    const requestTimeout = () => AbortSignal.timeout(15000);
    const showError = (message) => { error.textContent = message; error.hidden = false; };
    const readResponse = async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Please try again.');
        return data;
    };

    document.addEventListener('click', async (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || event.defaultPrevented || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const url = new URL(link.href, location.href);
        const match = url.pathname.match(/^\/(?:reservation|book)\/(\d+)\/?$/);
        if (url.origin !== location.origin || !match) return;
        event.preventDefault();
        trigger = link;
        const current = ++attempt;
        error.hidden = true;
        submit.disabled = signup.disabled = true;
        status.textContent = 'Keeping your selection…';
        dialog.querySelector('[data-selected-service]').textContent = 'your selected service';
        if (!dialog.open) dialog.showModal();
        try {
            const selection = {facility_id: Number(match[1])};
            for (const key of ['check_in', 'check_out', 'arrival', 'departure', 'guests', 'date', 'session', 'availability_checked', 'early_check_in_hours', 'pax_tier']) {
                if (url.searchParams.has(key)) selection[key] = url.searchParams.get(key);
            }
            const data = await fetch(dialog.dataset.intentUrl, {
                method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
                signal: requestTimeout(),
                body: JSON.stringify(selection),
            }).then(readResponse);
            if (current !== attempt) return;
            if (data.authenticated) { location.assign(data.redirect); return; }
            dialog.querySelector('[data-selected-service]').textContent = data.name;
            status.textContent = 'Your service and visit details are saved for after sign-in.';
            submit.disabled = signup.disabled = false;
            dialog.querySelector('[name="email"]').focus();
        } catch (failure) {
            if (current === attempt) {
                status.textContent = '';
                showError(failure.name === 'TimeoutError' ? 'This is taking too long. Close this window and try your selection again.' : failure.message);
            }
        }
    });
    dialog.querySelector('[data-auth-close]').addEventListener('click', () => dialog.close());
    passwordToggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        passwordToggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', String(visible));
        passwordToggle.querySelector('[data-eye-open]').classList.toggle('hidden', visible);
        passwordToggle.querySelector('[data-eye-closed]').classList.toggle('hidden', !visible);
    });
    dialog.addEventListener('close', () => {
        ++attempt; form.reset(); password.type = 'password';
        passwordToggle.setAttribute('aria-label', 'Show password');
        passwordToggle.setAttribute('aria-pressed', 'false');
        passwordToggle.querySelector('[data-eye-open]').classList.remove('hidden');
        passwordToggle.querySelector('[data-eye-closed]').classList.add('hidden');
        trigger?.focus();
    });
    signup.addEventListener('click', () => location.assign(dialog.dataset.registerUrl));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (submit.disabled) return;
        error.hidden = true;
        submit.disabled = signup.disabled = true;
        status.textContent = 'Signing in…';
        try {
            const data = await fetch(form.action, {
                method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf},
                signal: requestTimeout(), body: new FormData(form),
            }).then(readResponse);
            if (!data.redirect) throw new Error('Sign-in could not finish. Please try again.');
            location.assign(data.redirect);
        } catch (failure) {
            showError(failure.name === 'TimeoutError' ? 'Sign-in is taking too long. Please try again.' : failure.message);
            status.textContent = 'Your selection is still saved.';
            submit.disabled = signup.disabled = false;
        }
    });
}
