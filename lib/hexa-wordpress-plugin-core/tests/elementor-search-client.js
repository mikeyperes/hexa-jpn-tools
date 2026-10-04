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

    querySelectorAll(selector) {
        if (selector !== '.e-loop-item') {
            return [];
        }
        return Array.from({ length: (this._innerHTML.match(/class=["'][^"']*e-loop-item/g) || []).length });
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
const documentListeners = new Map();
const transport = [];

global.Element = FakeElement;
global.document = {
    addEventListener(type, listener) {
        documentListeners.set(type, listener);
    },
    createElement() {
        return new FakeElement();
    },
    querySelector(selector) {
        for (const [id, root] of roots) {
            if (selector.startsWith(`.elementor-element-${id}`)) {
                return root;
            }
        }
        return null;
    },
};
global.window = {
    CSS: { escape: (value) => String(value) },
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

    const unregistered = new SearchRoot('unregistered', 2, false);
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

    console.log('PASS: Elementor client adapter clears below-minimum stale markup and preserves scoped request states.');
})().catch((error) => {
    console.error(error);
    process.exit(1);
});
