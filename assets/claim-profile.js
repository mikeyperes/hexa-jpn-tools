/* Claim a profile: a host card's "Claim this profile" button writes that host into the
   claim form's host field, marks the card as chosen and brings the form into view. */
(function () {
    'use strict';

    var FIELD = 'form_fields[host_profile]';

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-jpn-claim]');
        if (!button) {
            return;
        }
        var field = document.querySelector('[name="' + FIELD + '"]');
        if (!field) {
            return;
        }

        field.value = button.getAttribute('data-jpn-claim') + ' (' + button.getAttribute('data-jpn-claim-url') + ')';
        field.dispatchEvent(new Event('input', { bubbles: true }));

        document.querySelectorAll('[data-jpn-claim].is-selected').forEach(function (other) {
            other.classList.remove('is-selected');
            other.textContent = other.getAttribute('data-jpn-label') || other.textContent;
            var card = other.closest('.jpn-host');
            if (card) {
                card.classList.remove('is-selected');
            }
        });
        button.setAttribute('data-jpn-label', button.textContent);
        button.classList.add('is-selected');
        button.textContent = 'Selected';
        var card = button.closest('.jpn-host');
        if (card) {
            card.classList.add('is-selected');
        }

        var form = field.closest('form');
        (form || field).scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
        var next = form ? form.querySelector('[name="form_fields[name]"]') : null;
        if (next) {
            next.focus({ preventScroll: true });
        }
    });
})();
