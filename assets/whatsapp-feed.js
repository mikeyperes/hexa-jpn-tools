(() => {
    'use strict';

    const FIRST_TICK_DELAY = 1200;
    const TYPING_DURATION = 1100;
    const BETWEEN_MESSAGES = 3400;
    const NOTIFICATION_DURATION = 2600;
    const NOTIFICATION_EXIT = 550;
    const MAX_CHILDREN = 14;

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (typeof text === 'string') node.textContent = text;
        return node;
    };

    const init = (mount) => {
        if (mount.dataset.jpnWhatsappInitialized === '1') return;
        const dataNode = mount.nextElementSibling;
        if (!dataNode || !dataNode.classList.contains('jpn-whatsapp-feed-data')) return;

        let payload;
        try {
            payload = JSON.parse(dataNode.textContent || '{}');
        } catch (error) {
            return;
        }

        const events = Array.isArray(payload.events) ? payload.events : [];
        const totalCount = Math.max(0, Number.parseInt(payload.total_count, 10) || 0);
        const phone = mount.closest('[data-jpn-whatsapp-phone]');
        const notification = phone ? phone.querySelector('[data-jpn-whatsapp-notification]') : null;
        const count = phone ? phone.querySelector('[data-jpn-whatsapp-count]') : null;
        if (count) count.textContent = `${totalCount} events · next 2 weeks`;

        mount.dataset.jpnWhatsappInitialized = '1';
        let clock = 11 * 60 + 37;
        let notificationTimer = 0;
        let notificationCleanupTimer = 0;

        const time = () => {
            const hour = Math.floor(clock / 60);
            const minute = clock % 60;
            return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`;
        };

        const append = (node) => {
            mount.appendChild(node);
            while (mount.children.length > MAX_CHILDREN) mount.firstElementChild.remove();
            return node;
        };

        const timestamp = () => element('span', 'jpn-wa-time', time());
        const sender = () => {
            const from = element('span', 'jpn-wa-from', 'JPN Miami');
            from.appendChild(element('small', '', '~ admin'));
            return from;
        };

        const intro = () => {
            append(element('div', 'jpn-wa-day', 'Today'));

            const images = events.filter((event) => event && event.image).slice(0, 4);
            if (images.length) {
                const bubble = element('div', 'jpn-wa-bubble is-first');
                bubble.appendChild(sender());
                const album = element('div', 'jpn-wa-album');
                images.forEach((event, index) => {
                    const frame = element('span');
                    if (index === images.length - 1 && totalCount > images.length) {
                        frame.dataset.more = `+${totalCount - images.length}`;
                    }
                    const image = element('img');
                    image.src = String(event.image);
                    image.alt = '';
                    image.loading = 'lazy';
                    frame.appendChild(image);
                    album.appendChild(frame);
                });
                bubble.append(album, timestamp());
                append(bubble);
            }

            const welcome = element('div', 'jpn-wa-bubble');
            welcome.append(
                document.createTextNode('Hey JPN fam!'),
                document.createElement('br'),
                document.createElement('br'),
                document.createTextNode('🚨 New events added for the next 2 weeks!'),
                timestamp()
            );
            append(welcome);
        };

        const eventMessage = (event) => {
            clock += 1;
            const bubble = element('div', 'jpn-wa-bubble');
            const title = element('span', 'jpn-wa-message-title', `📣 ${event.title}${event.host ? ` (${event.host})` : ''}`);
            const link = element('a', '', String(event.url || ''));
            link.href = String(event.url || '');
            link.target = '_blank';
            link.rel = 'noopener';
            bubble.append(
                title,
                document.createElement('br'),
                document.createTextNode(`🗓️ ${event.date || ''}`),
                document.createElement('br'),
                document.createTextNode('Learn more: '),
                link,
                timestamp()
            );
            return bubble;
        };

        const showNotification = (event) => {
            if (!notification) return;
            window.clearTimeout(notificationTimer);
            window.clearTimeout(notificationCleanupTimer);
            notification.replaceChildren();

            if (event.image) {
                const image = element('img', 'jpn-wa-drop-image');
                image.src = String(event.image);
                image.alt = '';
                notification.appendChild(image);
            }
            const body = element('div', 'jpn-wa-drop-body');
            body.append(
                element('b', '', 'JPN Miami · Events'),
                element('p', '', `📣 ${event.title} · 🗓️ ${event.date || ''}`)
            );
            notification.appendChild(body);
            notification.classList.add('is-visible');

            notificationTimer = window.setTimeout(() => {
                notification.classList.remove('is-visible');
                notificationCleanupTimer = window.setTimeout(() => notification.replaceChildren(), NOTIFICATION_EXIT);
            }, NOTIFICATION_DURATION);
        };

        const typing = () => {
            const node = element('div', 'jpn-wa-typing');
            node.append(element('i'), element('i'), element('i'));
            return append(node);
        };

        intro();
        if (!events.length) return;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            events.slice(0, 3).forEach((event) => append(eventMessage(event)));
            return;
        }

        let index = 0;
        const tick = () => {
            const indicator = typing();
            window.setTimeout(() => {
                indicator.remove();
                const event = events[index % events.length];
                append(eventMessage(event));
                if (index % 3 === 0) showNotification(event);
                index += 1;
                window.setTimeout(tick, BETWEEN_MESSAGES);
            }, TYPING_DURATION);
        };
        window.setTimeout(tick, FIRST_TICK_DELAY);
    };

    const initAll = (root = document) => {
        root.querySelectorAll('[data-jpn-whatsapp-feed]').forEach(init);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAll());
    } else {
        initAll();
    }
})();
