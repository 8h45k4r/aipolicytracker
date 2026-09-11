// Progressive enhancement for the server-rendered public site.
// Everything works without this script; it only adds conveniences.
(function () {
    'use strict';

    // Analytics consent + event hooks (data-track="event_name").
    var consentKey = 'apt-consent';
    function consent() {
        try { return localStorage.getItem(consentKey); } catch (e) { return null; }
    }
    function setConsent(value) {
        try { localStorage.setItem(consentKey, value); } catch (e) { /* ignore */ }
    }
    function loadAnalytics() {
        var cfg = window.APT_ANALYTICS || {};
        if (cfg.gaId && !window.__aptGaLoaded) {
            window.__aptGaLoaded = true;
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.gaId);
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };
            window.gtag('js', new Date());
            window.gtag('config', cfg.gaId, { anonymize_ip: true });
        }
        if (cfg.cfToken && !window.__aptCfLoaded) {
            window.__aptCfLoaded = true;
            var c = document.createElement('script');
            c.defer = true;
            c.src = 'https://static.cloudflareinsights.com/beacon.min.js';
            c.setAttribute('data-cf-beacon', JSON.stringify({ token: cfg.cfToken }));
            document.head.appendChild(c);
        }
    }
    function track(name, params) {
        if (window.gtag) { window.gtag('event', name, params || {}); }
    }
    var banner = document.getElementById('consent-banner');
    var cfg = window.APT_ANALYTICS || {};
    if (cfg.gaId || cfg.cfToken) {
        if (!cfg.requireConsent || consent() === 'yes') {
            loadAnalytics();
            if (banner) banner.hidden = true;
        } else if (consent() === 'no') {
            if (banner) banner.hidden = true;
        } else if (banner) {
            banner.hidden = false;
        }
    }
    document.addEventListener('click', function (e) {
        var accept = e.target.closest('[data-consent="yes"]');
        var decline = e.target.closest('[data-consent="no"]');
        if (accept) { setConsent('yes'); loadAnalytics(); if (banner) banner.hidden = true; }
        if (decline) { setConsent('no'); if (banner) banner.hidden = true; }
        var tracked = e.target.closest('[data-track]');
        if (tracked) {
            track(tracked.getAttribute('data-track'), { label: tracked.getAttribute('data-track-label') || tracked.textContent.trim().slice(0, 80) });
        }
    });
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.getAttribute('data-track')) {
            track(form.getAttribute('data-track'), {});
        }
    });
    if (document.body.getAttribute('data-page-track')) {
        track(document.body.getAttribute('data-page-track'), { path: location.pathname });
    }

    // Mobile filter sheet (bottom sheet) toggle.
    var openBtns = document.querySelectorAll('[data-open-filters]');
    var sheet = document.getElementById('filter-sheet');
    if (sheet && openBtns.length) {
        var closeSheet = function () { sheet.setAttribute('data-open', 'false'); document.body.style.overflow = ''; };
        openBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                sheet.setAttribute('data-open', 'true');
                document.body.style.overflow = 'hidden';
                var first = sheet.querySelector('input, select, button');
                if (first) first.focus();
            });
        });
        sheet.querySelectorAll('[data-close-filters]').forEach(function (b) { b.addEventListener('click', closeSheet); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheet(); });
    }

    // Copy-link buttons.
    document.querySelectorAll('[data-copy-link]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-copy-link') || location.href;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    var orig = btn.textContent;
                    btn.textContent = 'Link copied';
                    setTimeout(function () { btn.textContent = orig; }, 1500);
                });
            }
        });
    });

    // Native share where available.
    document.querySelectorAll('[data-share]').forEach(function (btn) {
        if (!navigator.share) { return; }
        btn.hidden = false;
        btn.addEventListener('click', function () {
            navigator.share({ title: document.title, url: location.href }).catch(function () {});
        });
    });

    // Auto-submit filter selects on desktop (forms still submit normally).
    document.querySelectorAll('form[data-autosubmit] select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (window.matchMedia('(min-width: 768px)').matches) { sel.form.requestSubmit ? sel.form.requestSubmit() : sel.form.submit(); }
        });
    });
})();
