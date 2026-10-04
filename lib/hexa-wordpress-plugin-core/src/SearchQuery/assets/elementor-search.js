(() => {
    'use strict';

    if (window.__hexaElementorSearchInstalled || typeof window.fetch !== 'function') {
        return;
    }

    window.__hexaElementorSearchInstalled = true;

    const endpoint = 'elementor-pro/v1/refresh-search';
    const nativeFetch = window.fetch.bind(window);
    const requests = new Map();
    let sequence = 0;

    const selectorEscape = (value) => {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    };

    const requestUrl = (input) => {
        if (typeof input === 'string') {
            return input;
        }
        if (input && typeof input.url === 'string') {
            return input.url;
        }

        return '';
    };

    const requestData = (options) => {
        if (!options || typeof options.body !== 'string') {
            return null;
        }

        try {
            return JSON.parse(options.body);
        } catch (error) {
            return null;
        }
    };

    const widgetRoot = (widgetId) => document.querySelector(
        `.elementor-element-${selectorEscape(widgetId)}[data-hexa-search-query-id]`
    );

    const minimumSearchLength = (root) => {
        let settings = {};
        try {
            settings = JSON.parse(root.getAttribute('data-settings') || '{}');
        } catch (error) {
            settings = {};
        }

        const configured = Number(settings.minimum_search_characters);
        return Number.isFinite(configured) && configured > 0 ? Math.floor(configured) : 3;
    };

    const clearNativeResults = (root) => {
        const input = root.querySelector('.e-search-input');
        const results = root.querySelector('.e-search-results');
        if (results) {
            results.replaceChildren();
        }
        if (input) {
            input.setAttribute('aria-expanded', 'false');
        }
    };

    const statusElement = (root) => {
        let status = root.querySelector('.hexa-elementor-search-status');
        if (status) {
            return status;
        }

        const results = root.querySelector('.e-search-results-container');
        if (!results) {
            return null;
        }

        status = document.createElement('p');
        status.className = 'hexa-elementor-search-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.hidden = true;
        results.insertBefore(status, results.firstChild);

        return status;
    };

    const setStatus = (root, state, message) => {
        const status = statusElement(root);
        const results = root.querySelector('.e-search-results-container');
        if (!status || !results) {
            return;
        }

        status.dataset.state = state;
        status.textContent = message;
        status.hidden = message === '';
        results.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');
        root.classList.toggle('hexa-elementor-search-error', state === 'error');
    };

    const abortWidgetRequest = (root, hideLoader = true) => {
        const widgetId = root.getAttribute('data-id');
        const current = widgetId ? requests.get(widgetId) : null;
        if (current) {
            current.controller.abort();
            requests.delete(widgetId);
        }

        const results = root.querySelector('.e-search-results-container');
        if (results) {
            results.setAttribute('aria-busy', 'false');
            if (hideLoader) {
                results.classList.add('hide-loader');
            }
        }
    };

    document.addEventListener('input', (event) => {
        if (!(event.target instanceof Element) || !event.target.matches('.e-search-input')) {
            return;
        }

        const root = event.target.closest('[data-hexa-search-query-id]');
        if (!root) {
            return;
        }

        abortWidgetRequest(root);
        if (event.target.value.length < minimumSearchLength(root)) {
            // Elementor clears an empty value and requests at or above its
            // threshold, but leaves stale markup for a shorter non-empty value.
            clearNativeResults(root);
        }
        setStatus(root, 'idle', '');
    });

    window.fetch = (input, options = {}) => {
        const url = requestUrl(input);
        const data = url.includes(endpoint) ? requestData(options) : null;
        const widgetId = data && typeof data.widget_id === 'string' ? data.widget_id : '';
        const root = widgetId ? widgetRoot(widgetId) : null;

        if (!root || typeof AbortController !== 'function') {
            return nativeFetch(input, options);
        }

        abortWidgetRequest(root, false);

        const controller = new AbortController();
        const requestSequence = ++sequence;
        const externalSignal = options.signal;
        if (externalSignal && typeof externalSignal.addEventListener === 'function') {
            if (externalSignal.aborted) {
                controller.abort();
            } else {
                externalSignal.addEventListener('abort', () => controller.abort(), { once: true });
            }
        }

        requests.set(widgetId, { controller, sequence: requestSequence });
        setStatus(root, 'loading', 'Searching…');

        return nativeFetch(input, { ...options, signal: controller.signal })
            .then(async (response) => {
                const current = requests.get(widgetId);
                if (!current || current.sequence !== requestSequence) {
                    throw new DOMException('A newer search request replaced this response.', 'AbortError');
                }
                if (!response.ok) {
                    throw new Error(`Search request failed with status ${response.status}.`);
                }

                const payload = await response.clone().json();
                const latest = requests.get(widgetId);
                if (!latest || latest.sequence !== requestSequence) {
                    throw new DOMException('A newer search request replaced this response.', 'AbortError');
                }
                const markup = document.createElement('div');
                markup.innerHTML = typeof payload.data === 'string' ? payload.data : '';
                const count = markup.querySelectorAll('.e-loop-item').length;
                setStatus(root, count > 0 ? 'results' : 'empty', count > 0
                    ? `${count} ${count === 1 ? 'result' : 'results'} shown.`
                    : 'No results found.');
                requests.delete(widgetId);

                return response;
            })
            .catch((error) => {
                const current = requests.get(widgetId);
                if (current && current.sequence === requestSequence) {
                    requests.delete(widgetId);
                    if (!error || error.name !== 'AbortError') {
                        const results = root.querySelector('.e-search-results-container');
                        if (results) {
                            results.classList.add('hide-loader');
                        }
                        setStatus(root, 'error', 'Search could not load. Please try again.');
                    }
                }

                throw error;
            });
    };
})();
