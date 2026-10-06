/* Removes "recently added" markers whose 24-hour window has passed, including on cached pages and content loaded later (calendar months, map panels). */
(() => {
    'use strict';

    let queued = false;
    const sweep = () => {
        queued = false;
        const now = Date.now() / 1000;
        document.querySelectorAll('[data-jpn-new-until]').forEach((node) => {
            if (Number(node.dataset.jpnNewUntil) > now) return;
            const scope = node.closest('.is-recent');
            if (scope) scope.classList.remove('is-recent');
            node.remove();
        });
    };
    const queue = () => {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(sweep);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', queue);
    } else {
        queue();
    }
    new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
    window.setInterval(queue, 60000);
})();
