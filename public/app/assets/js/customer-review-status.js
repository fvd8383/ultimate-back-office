// Progressive enhancement only: the visible receipt remains usable without JS.
(() => {
    'use strict';
    const receipt = document.querySelector('.site-customer-review [data-customer-review-receipt]');
    const announcer = receipt?.parentElement.querySelector('.site-customer-announcer');
    if (!receipt || !announcer) return;

    function scheduleAnnouncement() {
        // Let the empty region reach a rendered frame before a separate task
        // changes its text. Never use keyboard focus as the speech trigger.
        requestAnimationFrame(() => setTimeout(() => {
            if (announcer.textContent === '') announcer.textContent = receipt.textContent;
        }, 0));
    }
    if (document.readyState === 'complete') scheduleAnnouncement();
    else window.addEventListener('load', scheduleAnnouncement, { once: true });
})();
