(function () {
    'use strict';

    if (!window.hexaJpnAdmin) {
        return;
    }

    function addLine(container, label, value) {
        var line = document.createElement('p');
        var strong = document.createElement('strong');
        strong.textContent = label + ': ';
        line.appendChild(strong);
        line.appendChild(document.createTextNode(String(value)));
        container.appendChild(line);
    }

    function download(period) {
        var url = new URL(window.hexaJpnAdmin.downloadUrl, window.location.origin);
        url.searchParams.set('action', window.hexaJpnAdmin.action);
        url.searchParams.set('period', period);
        url.searchParams.set('nonce', window.hexaJpnAdmin.nonce);
        window.location.assign(url.toString());
    }

    document.addEventListener('DOMContentLoaded', function () {
        var target = document.getElementById('postbox-container-1') || document.getElementById('wpbody-content');
        if (!target) {
            return;
        }

        var card = document.createElement('div');
        card.className = 'postbox hexa-jpn-dashboard-card';
        var heading = document.createElement('h2');
        heading.className = 'hndle';
        heading.textContent = 'Events';
        var inside = document.createElement('div');
        inside.className = 'inside';
        addLine(inside, 'Today', window.hexaJpnAdmin.counts.today);
        addLine(inside, 'Tomorrow', window.hexaJpnAdmin.counts.tomorrow);
        addLine(inside, 'Next 7 days', window.hexaJpnAdmin.counts.week);

        ['today', 'tomorrow', 'week'].forEach(function (period) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'button button-primary';
            button.textContent = 'Export ' + period;
            button.addEventListener('click', function () { download(period); });
            inside.appendChild(button);
        });

        card.appendChild(heading);
        card.appendChild(inside);
        target.appendChild(card);
    });
}());
