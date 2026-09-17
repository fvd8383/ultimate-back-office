// One visible live receipt; the inert source and no-JS fallback are not announced.
(() => {
    'use strict';
    const receipts = document.querySelectorAll('.site-customer-review [data-customer-review-receipt]');
    if (receipts.length !== 1) return;
    const receipt = receipts[0];
    const sources = receipt.parentElement.querySelectorAll('template[data-customer-review-receipt-source]');
    if (sources.length !== 1) return;
    const source = sources[0];

    function scheduleAnnouncement() {
        // Let the empty region reach a rendered frame before a separate task
        // changes its text. Never use keyboard focus as the speech trigger.
        requestAnimationFrame(() => setTimeout(() => {
            if (receipt.textContent === '') receipt.textContent = source.content.textContent;
        }, 0));
    }
    if (document.readyState === 'complete') scheduleAnnouncement();
    else window.addEventListener('load', scheduleAnnouncement, { once: true });
})();
