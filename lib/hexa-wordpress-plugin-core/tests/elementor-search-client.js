'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');

class ClassList {
    constructor(...values) {
        this.values = new Set(values);
    }

    add(value) {
        this.values.add(value);
    }

    remove(value) {
        this.values.delete(value);
    }

    contains(value) {
        return this.values.has(value);
    }

    toggle(value, force) {
        if (force === true) {
            this.values.add(value);
            return true;
        }
        if (force === false) {
            this.values.delete(value);
            return false;
        }
        if (this.values.has(value)) {
            this.values.delete(value);
            return false;
        }
        this.values.add(value);
        return true;
    }
}

class FakeElement {
    constructor(classes = []) {
        this.attributes = new Map();
        this.children = [];
        this.classList = new ClassList(...classes);
        this.dataset = {};
        this.hidden = false;
        this.ownerRoot = null;
        this.rectHeight = 0;
        this.selection = null;
        this.style = {
            values: new Map(),
            setProperty: (name, value) => this.style.values.set(name, value),
        };
        this.textContent = '';
        this._innerHTML = '';
    }

    get className() {
        return Array.from(this.classList.values).join(' ');
    }

    set className(value) {
        this.classList = new ClassList(...String(value).split(/\s+/).filter(Boolean));
    }

    get innerHTML() {
        return this._innerHTML;
    }

    set innerHTML(value) {
        this._innerHTML = String(value);
    }

    getAttribute(name) {
        return this.attributes.get(name) ?? null;
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value));
    }

    matches(selector) {
        return selector === '.e-search-input' && this.classList.contains('e-search-input');
    }

    closest(selector) {
        if (selector === '[data-hexa-search-query-id]') {
            return this.ownerRoot && this.ownerRoot.registered ? this.ownerRoot : null;
        }
        return null;
    }

    contains(element) {
        return element === this || (element && (
            element.ownerRoot === this
            || (this.ownerRoot && element.ownerRoot === this.ownerRoot)
        ));
    }

    focus() {
        document.activeElement = this;
    }

    getBoundingClientRect() {
        return { height: this.rectHeight };
    }

    setSelectionRange(start, end) {
        this.selection = [start, end];
    }

    querySelectorAll(selector) {
        if (selector !== '.e-loop-item') {
            return [];
        }
        return Array.from({ length: (this._innerHTML.match(/class=["'][^"']*e-loop-item/g) || []).length });
    }

    querySelector(selector) {
        if (selector === '.e-loop-item, .e-search-nothing-found-message') {
            return /e-loop-item|e-search-nothing-found-message/.test(this._innerHTML) ? new FakeElement() : null;
        }
        return null;
    }

    insertBefore(child) {
        this.children.unshift(child);
        if (this.ownerRoot && child.classList.contains('hexa-elementor-search-status')) {
            this.ownerRoot.status = child;
        }
    }

    replaceChildren(...children) {
        this.children = children;
        this._innerHTML = '';
    }
}

class SearchRoot extends FakeElement {
    constructor(id, minimum = 2, registered = true) {
        super();
        this.registered = registered;
        this.setAttribute('data-id', id);
        this.setAttribute('data-settings', JSON.stringify({ minimum_search_characters: minimum }));
        this.input = new FakeElement(['e-search-input']);
        this.input.ownerRoot = this;
        this.input.rectHeight = 60;
        this.resultsContainer = new FakeElement(['e-search-results-container']);
        this.resultsContainer.ownerRoot = this;
        this.results = new FakeElement(['e-search-results']);
        this.results.ownerRoot = this;
        this.resultsContainer.children.push(this.results);
        this.status = null;
    }

    querySelector(selector) {
        if (selector === '.e-search-input') {
            return this.input;
        }
        if (selector === '.e-search-results-container') {
            return this.resultsContainer;
        }
        if (selector === '.e-search-results') {
            return this.results;
        }
        if (selector === '.hexa-elementor-search-status') {
            return this.status;
        }
        return null;
    }
}

const roots = new Map();
const defaultContent = [];
const documentListeners = new Map();
const transport = [];
const elementorActions = new Map();

global.Element = FakeElement;
global.document = {
    activeElement: null,
    readyState: 'complete',
    addEventListener(type, listener) {
        documentListeners.set(type, listener);
    },
    createElement() {
        return new FakeElement();
    },
    querySelector(selector) {
        for (const [id, root] of roots) {
            if (root.registered && selector.startsWith(`.elementor-element-${id}`)) {
                return root;
            }
        }
        return null;
    },
    querySelectorAll(selector) {
        const registered = Array.from(roots.values()).filter((root) => root.registered);
        if (selector === '[data-hexa-search-query-id]') {
            return registered;
        }
        if (selector === '.hexa-search-page-results[data-hexa-search-query-id]') {
            return registered.filter((root) => root.classList.contains('hexa-search-page-results'));
        }
        if (selector === '.hexa-search-default') {
            return defaultContent;
        }
        return [];
    },
};
global.window = {
    CSS: { escape: (value) => String(value) },
    elementorFrontend: {
        hooks: {
            addAction(name, callback) {
                elementorActions.set(name, callback);
            },
            doAction(name, ...args) {
                const callback = elementorActions.get(name);
                if (callback) {
                    callback(...args);
                }
            },
        },
    },
    addEventListener() {},
    setTimeout() {},
    fetch(input, options) {
        return new Promise((resolve, reject) => {
            transport.push({ input, options, resolve, reject });
        });
    },
};

require(path.join(__dirname, '..', 'src', 'SearchQuery', 'assets', 'elementor-search.js'));

const input = (root, value) => {
    root.input.value = value;
    documentListeners.get('input')({ target: root.input });
};

const response = (status, data = '') => ({
    ok: status >= 200 && status < 300,
    status,
    clone() {
        return { json: async () => ({ data }) };
    },
});

const flush = () => new Promise((resolve) => setImmediate(resolve));

(async () => {
    const belowMinimum = new SearchRoot('registered-a', 2);
    roots.set('registered-a', belowMinimum);
    belowMinimum.results.innerHTML = '<article class="e-loop-item">Old result</article>';
    belowMinimum.input.setAttribute('aria-expanded', 'true');

    const pending = window.fetch('/wp-json/elementor-pro/v1/refresh-search', {
        method: 'POST',
        body: JSON.stringify({ widget_id: 'registered-a' }),
    }).catch((error) => error);
    assert.equal(transport.length, 1, 'a registered Elementor request is wrapped');
    assert.equal(belowMinimum.resultsContainer.classList.contains('hide-loader'), false, 'starting a current request preserves Elementor\'s native loader');

    input(belowMinimum, 'a');
    assert.equal(transport[0].options.signal.aborted, true, 'typing aborts the prior request');
    assert.equal(belowMinimum.results.innerHTML, '', 'a nonempty value below the native minimum clears stale results');
    assert.equal(belowMinimum.input.getAttribute('aria-expanded'), 'false', 'clearing stale results closes the result combobox');
    transport[0].resolve(response(200, '<article class="e-loop-item">Stale</article>'));
    assert.equal((await pending).name, 'AbortError', 'an aborted stale response cannot become current');

    const atMinimum = new SearchRoot('registered-b', 2);
    roots.set('registered-b', atMinimum);
    atMinimum.results.innerHTML = '<article class="e-loop-item">Native owns this transition</article>';
    input(atMinimum, 'ab');
    assert.match(atMinimum.results.innerHTML, /Native owns/, 'Core leaves at-threshold rendering to Elementor');
    assert.equal(atMinimum.style.values.get('--hexa-elementor-search-input-height'), '60px', 'the marked widget exposes the native input height for in-flow icon anchoring');

    const unregistered = new SearchRoot('unregistered', 2, false);
    roots.set('unregistered', unregistered);
    unregistered.results.innerHTML = '<article class="e-loop-item">Unrelated widget</article>';
    input(unregistered, 'a');
    assert.match(unregistered.results.innerHTML, /Unrelated widget/, 'unregistered search widgets remain untouched');

    const recovery = new SearchRoot('registered-c', 2);
    roots.set('registered-c', recovery);
    const failed = window.fetch('/wp-json/elementor-pro/v1/refresh-search', {
        method: 'POST',
        body: JSON.stringify({ widget_id: 'registered-c' }),
    }).catch((error) => error);
    transport[1].resolve(response(503));
    assert.match((await failed).message, /503/, 'transport failures remain visible to Elementor');
    assert.equal(recovery.classList.contains('hexa-elementor-search-error'), true, 'a current failure exposes the accessible error state');
    input(recovery, 'ok');
    assert.equal(recovery.classList.contains('hexa-elementor-search-error'), false, 'the next input clears the prior error state');
    assert.equal(recovery.status.hidden, true, 'the recovered idle status is hidden');

    const overlap = new SearchRoot('registered-d', 2);
    roots.set('registered-d', overlap);
    const first = window.fetch('/wp-json/elementor-pro/v1/refresh-search', {
        method: 'POST',
        body: JSON.stringify({ widget_id: 'registered-d' }),
    }).catch((error) => error);
    const second = window.fetch('/wp-json/elementor-pro/v1/refresh-search', {
        method: 'POST',
        body: JSON.stringify({ widget_id: 'registered-d' }),
    });
    assert.equal(transport[2].options.signal.aborted, true, 'a newer request aborts the older request for the same widget');
    transport[3].resolve(response(200, '<article class="e-loop-item">Current</article>'));
    await second;
    assert.equal(overlap.status.dataset.state, 'results', 'the latest successful response announces a result');
    transport[2].resolve(response(200, '<article class="e-loop-item">Old</article>'));
    assert.equal((await first).name, 'AbortError', 'an overlapped older response stays rejected');
    await flush();

    const keyboard = new SearchRoot('registered-e', 2);
    roots.set('registered-e', keyboard);
    keyboard.input.value = 'Davie';
    keyboard.input.setAttribute('aria-expanded', 'true');
    const resultLink = new FakeElement();
    resultLink.ownerRoot = keyboard;
    document.activeElement = resultLink;
    const escape = {
        key: 'Escape',
        prevented: false,
        stopped: false,
        preventDefault() { this.prevented = true; },
        stopImmediatePropagation() { this.stopped = true; },
    };
    documentListeners.get('keydown')(escape);
    assert.equal(document.activeElement, keyboard.input, 'Escape returns focus from a registered result to its search input');
    assert.equal(keyboard.resultsContainer.classList.contains('hidden'), true, 'Escape closes the registered result container');
    assert.equal(keyboard.input.getAttribute('aria-expanded'), 'false', 'Escape collapses the registered search combobox');
    assert.deepEqual(keyboard.input.selection, [5, 5], 'Escape keeps the text cursor at the end of the query');
    assert.equal(escape.prevented && escape.stopped, true, 'the scoped handler prevents a parent keyboard component from stealing Escape');

    const inputKeyboard = new SearchRoot('registered-f', 2);
    roots.set('registered-f', inputKeyboard);
    inputKeyboard.input.value = 'Miami';
    inputKeyboard.input.setAttribute('aria-expanded', 'true');
    document.activeElement = inputKeyboard.input;
    const inputEscape = {
        key: 'Escape',
        prevented: false,
        stopped: false,
        preventDefault() { this.prevented = true; },
        stopImmediatePropagation() { this.stopped = true; },
    };
    documentListeners.get('keydown')(inputEscape);
    assert.equal(document.activeElement, inputKeyboard.input, 'Escape preserves focus when the registered search input is active');
    assert.equal(inputKeyboard.resultsContainer.classList.contains('hidden'), true, 'Escape from a registered input closes its result container');
    assert.equal(inputKeyboard.input.getAttribute('aria-expanded'), 'false', 'Escape from a registered input collapses its combobox');
    assert.equal(inputKeyboard.input.selection, null, 'Escape from the input preserves its existing cursor state');
    assert.equal(inputEscape.prevented && inputEscape.stopped, true, 'the input-scoped handler prevents a parent keyboard component from stealing Escape');

    unregistered.input.setAttribute('aria-expanded', 'true');
    document.activeElement = unregistered.input;
    const unregisteredEscape = {
        key: 'Escape',
        prevented: false,
        stopped: false,
        preventDefault() { this.prevented = true; },
        stopImmediatePropagation() { this.stopped = true; },
    };
    documentListeners.get('keydown')(unregisteredEscape);
    assert.equal(unregistered.resultsContainer.classList.contains('hidden'), false, 'Core leaves Escape rendering to an unregistered search widget');
    assert.equal(unregistered.input.getAttribute('aria-expanded'), 'true', 'Core leaves an unregistered search combobox state unchanged');
    assert.equal(unregisteredEscape.prevented || unregisteredEscape.stopped, false, 'Core does not intercept Escape from an unregistered search input');

    keyboard.results.innerHTML = '<article class="e-loop-item">Reopened result</article>';
    keyboard.resultsContainer.classList.remove('hidden');
    window.elementorFrontend.hooks.doAction('search:results-displayed', 'registered-e');
    assert.equal(keyboard.input.getAttribute('aria-expanded'), 'true', 'Elementor\'s native reopen lifecycle expands a visible registered result list');

    documentListeners.get('click')({ target: keyboard.input });
    assert.equal(keyboard.input.getAttribute('aria-expanded'), 'true', 'a click inside the registered widget preserves its open combobox state');

    keyboard.resultsContainer.classList.add('hidden');
    documentListeners.get('click')({ target: new FakeElement() });
    assert.equal(keyboard.input.getAttribute('aria-expanded'), 'false', 'a click outside the registered widget follows Elementor\'s native close state');

    unregistered.input.setAttribute('aria-expanded', 'true');
    documentListeners.get('click')({ target: new FakeElement() });
    assert.equal(unregistered.input.getAttribute('aria-expanded'), 'true', 'the outside-click state synchronization leaves unregistered widgets untouched');

    unregistered.input.setAttribute('aria-expanded', 'false');
    window.elementorFrontend.hooks.doAction('search:results-displayed', 'unregistered');
    assert.equal(unregistered.input.getAttribute('aria-expanded'), 'false', 'the native lifecycle hook leaves unregistered search widgets untouched');

    const page = new SearchRoot('registered-page', 2);
    page.classList.add('hexa-search-page-results');
    roots.set('registered-page', page);
    const latest = new FakeElement(['hexa-search-default']);
    defaultContent.push(latest);
    const firstPage = window.fetch('/wp-json/elementor-pro/v1/refresh-search', {
        method: 'POST',
        body: JSON.stringify({ widget_id: 'registered-page' }),
    });
    assert.equal(page.getAttribute('data-hexa-search-view'), 'loading', 'a page-results widget enters its loading view');
    assert.equal(latest.getAttribute('data-hexa-search-default'), 'loading', 'default content dims under the first loading request');
    transport[transport.length - 1].resolve(response(200, '<article class="e-loop-item">Page result</article>'));
    await firstPage;
    page.results.innerHTML = '<article class="e-loop-item">Page result</article>';
    window.elementorFrontend.hooks.doAction('search:results-updated');
    assert.equal(page.getAttribute('data-hexa-search-view'), 'results', 'the rendered response settles the results view');
    assert.equal(latest.getAttribute('data-hexa-search-default'), 'hidden', 'default content hides while live results show');

    const nextPage = window.fetch('/wp-json/elementor-pro/v1/refresh-search', {
        method: 'POST',
        body: JSON.stringify({ widget_id: 'registered-page' }),
    });
    assert.equal(page.getAttribute('data-hexa-search-view'), 'loading', 'a later request keeps the current results under the loader');
    assert.equal(latest.getAttribute('data-hexa-search-default'), 'hidden', 'default content stays hidden while current results are dimmed');
    transport[transport.length - 1].resolve(response(200, '<p class="e-search-nothing-found-message">None</p>'));
    await nextPage;
    page.results.innerHTML = '<p class="e-search-nothing-found-message">None</p>';
    window.elementorFrontend.hooks.doAction('search:results-updated');
    assert.equal(latest.getAttribute('data-hexa-search-default'), 'hidden', 'the no-match message also replaces default content');

    page.input.setAttribute('aria-expanded', 'true');
    documentListeners.get('click')({ target: new FakeElement() });
    assert.equal(page.input.getAttribute('aria-expanded'), 'true', 'an outside click leaves page results open');

    input(page, '');
    assert.equal(page.getAttribute('data-hexa-search-view'), 'idle', 'clearing the field returns the page view to idle');
    assert.equal(latest.getAttribute('data-hexa-search-default'), 'visible', 'clearing the field restores default content');

    console.log('PASS: Elementor client adapter preserves scoped request, layout, keyboard, native close/reopen, and page-results states.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
