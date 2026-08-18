(function () {
    'use strict';

    var events = window.marketingAnalyticsEvents || {};

    function emit(name, detail) {
        if (!name) {
            return;
        }

        var payload = Object.assign({ event: name }, detail || {});
        window.dispatchEvent(new CustomEvent('247sp:analytics', { detail: payload }));

        if (Array.isArray(window.dataLayer)) {
            window.dataLayer.push(payload);
        }
    }

    var navToggle = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.primary-nav');

    function closeNavigation() {
        if (!navToggle || !nav) {
            return;
        }

        navToggle.setAttribute('aria-expanded', 'false');
        nav.classList.remove('is-open');
        document.body.classList.remove('nav-open');
    }

    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            var willOpen = navToggle.getAttribute('aria-expanded') !== 'true';
            navToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            nav.classList.toggle('is-open', willOpen);
            document.body.classList.toggle('nav-open', willOpen);
        });

        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeNavigation();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNavigation();
            }
        });
    }

    document.querySelectorAll('[data-analytics-event]').forEach(function (element) {
        element.addEventListener('click', function () {
            emit(element.dataset.analyticsEvent, { location: element.dataset.analyticsLocation || null });
            if (element.hasAttribute('data-signup-cta')) {
                emit(events.signup_initiation || 'marketing_signup_initiation', { location: element.dataset.analyticsLocation || null });
            }
        });
    });

    document.querySelectorAll('[data-faq-item]').forEach(function (details) {
        details.addEventListener('toggle', function () {
            if (details.open) {
                emit(events.faq_engagement || 'marketing_faq_engagement', {
                    faq_index: details.dataset.faqIndex || null,
                    question: details.querySelector('summary').textContent.trim(),
                });
            }
        });
    });

    var video = document.querySelector('[data-vsl-player]');
    if (video) {
        video.addEventListener('play', function () {
            emit(events.vsl_play || 'marketing_vsl_play');
        }, { once: true });
        video.addEventListener('ended', function () {
            emit(events.vsl_complete || 'marketing_vsl_complete');
        });
    }

    emit(events.page_view || 'marketing_page_view', { page: document.body.dataset.pageName || 'marketing' });
}());
