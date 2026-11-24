const LIVE_FILTER_HEADER = 'X-Live-Filter';
const TEXT_DEBOUNCE_MS = 300;

const textualInputSelector = 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="date"]):not([type="datetime-local"]):not([type="number"]):not([type="range"])';

const collectParams = (root) => {
    const params = new URLSearchParams();

    root.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
            return;
        }

        if (field.disabled) {
            return;
        }

        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
            return;
        }

        const value = field.value.trim();

        if (value === '') {
            return;
        }

        params.set(field.name, value);
    });

    return params;
};

const hasNamedValues = (root, names = null) => {
    const fields = [...root.querySelectorAll('input[name], select[name], textarea[name]')];

    return fields.some((field) => {
        if (names && !names.includes(field.name)) {
            return false;
        }

        if (field.disabled) {
            return false;
        }

        if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
            return false;
        }

        return field.value.trim() !== '';
    });
};

const bindLiveFilter = (root) => {
    const content = root.querySelector('[data-live-filter-content]');
    const loading = root.querySelector('[data-live-filter-loading]');
    const results = root.querySelector('[data-live-filter-results]');
    const endpoint = root.dataset.liveFilterUrl || window.location.pathname;

    if (!content || !results) {
        return;
    }

    let debounceId = 0;
    let requestSequence = 0;
    let abortController = null;

    const setLoading = (isLoading) => {
        results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        loading?.classList.toggle('hidden', !isLoading);
    };

    const syncChrome = () => {
        const hasFilters = hasNamedValues(root);

        root.querySelectorAll('[data-live-filter-reset]').forEach((button) => {
            button.classList.toggle('hidden', !hasFilters);
        });

        root.querySelectorAll('[data-live-filter-hint]').forEach((hint) => {
            const fields = hint.dataset.liveFilterHintFields
                ? hint.dataset.liveFilterHintFields.split(',').map((name) => name.trim()).filter(Boolean)
                : null;
            const active = hasNamedValues(root, fields);
            const idleText = hint.dataset.liveFilterHintIdle;
            const activeText = hint.dataset.liveFilterHintActive;

            if (idleText && activeText) {
                hint.textContent = active ? activeText : idleText;
            }
        });
    };

    const requestUrl = (page = null) => {
        const url = new URL(endpoint, window.location.origin);
        const params = collectParams(root);

        params.forEach((value, key) => {
            url.searchParams.set(key, value);
        });

        if (page) {
            url.searchParams.set('page', page);
        }

        return url;
    };

    const render = async (url) => {
        requestSequence += 1;
        const sequence = requestSequence;

        abortController?.abort();
        abortController = new AbortController();
        setLoading(true);

        try {
            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                    [LIVE_FILTER_HEADER]: '1',
                },
                signal: abortController.signal,
            });

            if (!response.ok) {
                throw new Error(`Live filter request failed: ${response.status}`);
            }

            const markup = await response.text();

            if (sequence !== requestSequence) {
                return;
            }

            content.innerHTML = markup;
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error?.name === 'AbortError' || sequence !== requestSequence) {
                return;
            }

            content.innerHTML = '<p class="py-8 text-center text-sm text-text-muted">Results could not be updated. Try again.</p>';
        } finally {
            if (sequence === requestSequence) {
                setLoading(false);
            }
        }
    };

    const refresh = (immediate = false, page = null) => {
        window.clearTimeout(debounceId);
        syncChrome();

        const run = () => {
            render(requestUrl(page));
        };

        if (immediate) {
            run();
            return;
        }

        debounceId = window.setTimeout(run, TEXT_DEBOUNCE_MS);
    };

    root.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.hasAttribute('data-live-filter-native-submit')) {
                window.clearTimeout(debounceId);
                abortController?.abort();
                return;
            }

            event.preventDefault();
            refresh(true);
        });
    });

    root.addEventListener('change', (event) => {
        const field = event.target;

        if (!(field instanceof HTMLElement) || !field.matches('select[name], input[name], textarea[name]')) {
            return;
        }

        if (field.matches(textualInputSelector)) {
            return;
        }

        refresh(true);
    });

    root.addEventListener('input', (event) => {
        const field = event.target;

        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) {
            return;
        }

        if (!field.matches(textualInputSelector)) {
            return;
        }

        refresh(false);
    });

    root.querySelectorAll('[data-live-filter-reset]').forEach((button) => {
        button.addEventListener('click', () => {
            root.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
                if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                    field.checked = false;
                    return;
                }

                field.value = '';
            });

            refresh(true);
        });
    });

    content.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a') : null;

        if (!(link instanceof HTMLAnchorElement)) {
            return;
        }

        const url = new URL(link.href, window.location.origin);

        if (url.origin !== window.location.origin) {
            return;
        }

        const endpointUrl = new URL(endpoint, window.location.origin);

        if (url.pathname !== endpointUrl.pathname) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();
        syncChrome();
        render(url);
    });

    syncChrome();
};

export const enableAdminLiveFilters = () => {
    document.querySelectorAll('[data-live-filter]').forEach((root) => bindLiveFilter(root));
};
