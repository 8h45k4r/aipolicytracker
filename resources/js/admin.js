/*
 * Admin behaviour, loaded after public.js on backend pages only. Everything here is
 * progressive: each page works without it (forms post, links navigate, the server
 * renders the messages), and this adds side panels, toasts, a styled confirmation,
 * busy buttons, the account menu and a command palette. The CSP forbids inline
 * handlers, so every hook is a data- attribute. The decisions themselves live in
 * admin-core.js, which is tested without a browser.
 */
import { confirmPresentation, confirmSource, filterItems, mergeResults, nextIndex, overflowEdges, recordItems, searchable } from './admin-core.js';

(function () {
    'use strict';

    var doc = document;
    if (!doc.documentElement.hasAttribute('data-admin')) { return; }

    function all(selector, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(selector)); }
    function anotherModalOpen(except) { return all('dialog[open]').some(function (d) { return d !== except; }); }

    // ---------------------------------------------------------------------------------
    // Toasts. The server renders messages into [data-toasts]; here they become
    // dismissible, and successes leave on their own. Errors stay until closed.
    // ---------------------------------------------------------------------------------
    all('[data-toast]').forEach(function (toast) {
        var close = toast.querySelector('[data-toast-close]');
        if (close) { close.hidden = false; close.addEventListener('click', function () { toast.remove(); }); }
        if (toast.getAttribute('data-toast') === 'success') {
            window.setTimeout(function () { toast.classList.add('adm-toast-leaving'); window.setTimeout(function () { toast.remove(); }, 250); }, 6000);
        }
    });

    // ---------------------------------------------------------------------------------
    // Side panels: <dialog class="adm-drawer" id="…"> opened by [data-drawer-open="id"].
    // A panel holding validation errors reopens on load, so a failed form is not lost.
    // ---------------------------------------------------------------------------------
    function openDrawer(dialog, opener) {
        if (!dialog || typeof dialog.showModal !== 'function' || dialog.open) { return false; }
        dialog.showModal();
        dialog._opener = opener || null;
        var first = dialog.querySelector('[autofocus], input:not([type=hidden]), select, textarea, button');
        if (first) { first.focus(); }
        return true;
    }
    function closeDrawer(dialog) {
        if (!dialog.open) { return; }
        dialog.close();
    }
    all('[data-drawer-open]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            var dialog = doc.getElementById(button.getAttribute('data-drawer-open'));
            // A link opener still navigates when the panel cannot open (no <dialog> support).
            if (openDrawer(dialog, button)) { event.preventDefault(); }
        });
    });
    all('dialog.adm-drawer').forEach(function (dialog) {
        all('[data-drawer-close]', dialog).forEach(function (b) { b.addEventListener('click', function () { closeDrawer(dialog); }); });
        // A click on the backdrop (the dialog element itself, outside the panel) closes it.
        dialog.addEventListener('click', function (event) { if (event.target === dialog) { closeDrawer(dialog); } });
        // However it closes (button, backdrop, Escape), focus goes back to what opened it.
        dialog.addEventListener('close', function () { if (dialog._opener && doc.contains(dialog._opener)) { dialog._opener.focus(); } dialog._opener = null; });
        if (dialog.hasAttribute('data-open-on-load')) { openDrawer(dialog); }
    });

    // ---------------------------------------------------------------------------------
    // Confirmation. public.js asks window.confirm() for [data-confirm]; in the admin a
    // styled dialog asks instead, names the count ({n} = ticked rows) and marks
    // destructive actions. It runs first (capture) and, when confirmed, replays the
    // submit with the same submitter, so the button's own name/value and formaction go.
    // ---------------------------------------------------------------------------------
    var confirmDialog = doc.getElementById('adm-confirm');
    if (confirmDialog && typeof confirmDialog.showModal === 'function') {
        var confirmText = confirmDialog.querySelector('[data-confirm-text]');
        var confirmOk = confirmDialog.querySelector('[data-confirm-ok]');
        var pending = null;
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (form._admConfirmed) { form._admConfirmed = false; return; }
            var source = confirmSource(form, event.submitter || null);
            if (!source) { return; }
            event.preventDefault();
            event.stopImmediatePropagation();
            // Same count as the bulk bar: "all matching" when that scope is ticked, else the ticked rows.
            var scope = form.id ? doc.querySelector('[data-bulk-scope="' + form.id + '"]') : null;
            var n = (scope && scope.checked) ? (scope.getAttribute('data-bulk-scope-count') || 'all matching')
                : all('input[type=checkbox][name$="[]"]:checked', form).length + (form.id ? all('input[type=checkbox][form="' + form.id + '"]:checked').length : 0);
            var shown = confirmPresentation(source, n);
            confirmText.textContent = shown.text;
            confirmOk.textContent = shown.label;
            confirmOk.className = shown.danger ? 'btn-danger' : 'btn-primary';
            pending = { form: form, submitter: event.submitter || null };
            if (!confirmDialog.open) { confirmDialog.showModal(); }
            // A destructive action starts on Cancel, so Enter does not delete by reflex.
            (shown.danger ? confirmDialog.querySelector('[data-confirm-cancel]') : confirmOk).focus();
        }, true);
        confirmOk.addEventListener('click', function () {
            confirmDialog.close();
            if (!pending) { return; }
            var p = pending; pending = null;
            p.form._admConfirmed = true;
            // A submitter that left the page (re-rendered row) cannot be replayed; submit the form as asked.
            var submitter = p.submitter && p.submitter.isConnected && p.submitter.form === p.form ? p.submitter : undefined;
            if (typeof p.form.requestSubmit === 'function') { p.form.requestSubmit(submitter); } else { p.form._admConfirmed = false; p.form.submit(); }
        });
        all('[data-confirm-cancel]', confirmDialog).forEach(function (b) { b.addEventListener('click', function () { pending = null; confirmDialog.close(); }); });
        confirmDialog.addEventListener('cancel', function () { pending = null; });
    }

    // ---------------------------------------------------------------------------------
    // Busy state: a submitted form's button says so and cannot be pressed twice. Not for
    // GET forms (filters), forms that open elsewhere (target) or that download a file,
    // which never leave the page and would stay "Working…".
    // ---------------------------------------------------------------------------------
    doc.addEventListener('submit', function (event) {
        if (event.defaultPrevented) { return; }
        var form = event.target;
        if ((form.getAttribute('method') || 'get').toLowerCase() === 'get' || form.hasAttribute('target') || form.hasAttribute('data-no-busy')) { return; }
        var button = event.submitter;
        if (!button || button.hasAttribute('data-no-busy') || button.hasAttribute('formtarget')) { return; }
        window.setTimeout(function () {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.label = button.innerHTML;
            button.textContent = 'Working…';
        }, 0);
    });

    // Coming back with the browser's Back button restores the page as it was left.
    window.addEventListener('pageshow', function () {
        all('button[aria-busy="true"]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); if (b.dataset.label) { b.innerHTML = b.dataset.label; } });
    });

    // ---------------------------------------------------------------------------------
    // Sidebar: the menu fades at an edge with more beyond it, and the current page's
    // entry is scrolled into view on a short window. The account menu closes on Escape
    // and on a click anywhere else.
    // ---------------------------------------------------------------------------------
    var navScroll = doc.querySelector('[data-admin-nav-scroll]');
    if (navScroll) {
        var edges = function () { navScroll.setAttribute('data-overflow', overflowEdges(navScroll.scrollTop, navScroll.scrollHeight, navScroll.clientHeight)); };
        navScroll.addEventListener('scroll', edges, { passive: true });
        window.addEventListener('resize', edges);
        var here = navScroll.querySelector('[aria-current="page"]');
        if (here && here.offsetTop + here.offsetHeight > navScroll.clientHeight) { navScroll.scrollTop = here.offsetTop - navScroll.clientHeight / 2; }
        edges();
    }
    var account = doc.querySelector('[data-admin-account]');
    if (account) {
        doc.addEventListener('click', function (e) { if (account.open && !account.contains(e.target)) { account.open = false; } });
        account.addEventListener('keydown', function (e) { if (e.key === 'Escape' && account.open) { account.open = false; account.querySelector('summary').focus(); } });
    }

    // ---------------------------------------------------------------------------------
    // Command palette: Ctrl/⌘ K. Lists every page the sidebar offers this account and the
    // actions registered with [data-command], filtered as you type; from two letters on,
    // records from data-search-url (only the kinds this account may open) follow them.
    // ---------------------------------------------------------------------------------
    var palette = doc.getElementById('adm-palette');
    if (palette && typeof palette.showModal === 'function') {
        var input = palette.querySelector('input');
        var list = palette.querySelector('[role=listbox]');
        var empty = palette.querySelector('[data-palette-empty]');
        var status = palette.querySelector('[data-palette-status]');
        var searchUrl = palette.getAttribute('data-search-url');
        var items = all('[data-admin-nav-link]').map(function (a) {
            var group = a.closest('[data-admin-nav-group]');
            return { label: a.textContent.trim(), hint: group ? group.querySelector('p').textContent.trim() : '', href: a.getAttribute('href') };
        }).concat(all('[data-command]').map(function (el) {
            return { label: el.getAttribute('data-command'), hint: 'Action', href: el.getAttribute('href') || el.getAttribute('data-command-href') };
        }));
        var active = 0;
        var shown = [];
        var remote = { query: '', items: [] };
        var cache = {};
        var timer = null;
        var inflight = null;
        var seq = 0;

        var say = function (text) { if (status) { status.textContent = text; } };
        var go = function (item, newTab) { if (newTab) { window.open(item.href, '_blank', 'noopener'); } else { window.location.href = item.href; } };

        var render = function () {
            var q = input.value.trim();
            var records = remote.query === q ? remote.items : [];
            shown = mergeResults(filterItems(items, q), records);
            if (active >= shown.length) { active = Math.max(shown.length - 1, 0); }
            list.innerHTML = '';
            shown.forEach(function (item, index) {
                var li = doc.createElement('li');
                li.id = 'adm-palette-' + index;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', index === active ? 'true' : 'false');
                li.className = 'adm-palette-item';
                var text = doc.createElement('span');
                text.className = 'min-w-0';
                var label = doc.createElement('span'); label.textContent = item.label; text.appendChild(label);
                if (item.detail) { var detail = doc.createElement('span'); detail.className = 'adm-palette-detail truncate'; detail.textContent = item.detail; text.appendChild(detail); }
                var hint = doc.createElement('span'); hint.className = 'adm-palette-hint shrink-0'; hint.textContent = item.hint;
                li.appendChild(text); li.appendChild(hint);
                li.addEventListener('mousedown', function (e) { e.preventDefault(); go(item, e.ctrlKey || e.metaKey || e.button === 1); });
                li.addEventListener('mousemove', function () { if (active !== index) { active = index; paintActive(); } });
                list.appendChild(li);
            });
            input.setAttribute('aria-expanded', shown.length > 0 ? 'true' : 'false');
            empty.hidden = shown.length > 0 || Boolean(searchUrl && searchable(q) && remote.query !== q);
            paintActive();
        };
        var paintActive = function () {
            all('[role=option]', list).forEach(function (li, index) { li.setAttribute('aria-selected', index === active ? 'true' : 'false'); });
            var current = shown.length ? doc.getElementById('adm-palette-' + active) : null;
            input.setAttribute('aria-activedescendant', current ? current.id : '');
            if (current && current.scrollIntoView) { current.scrollIntoView({ block: 'nearest' }); }
        };

        var search = function () {
            var q = input.value.trim();
            window.clearTimeout(timer);
            if (!searchUrl || !searchable(q)) { say(''); return; }
            if (cache[q]) { remote = { query: q, items: cache[q] }; render(); return; }
            // One request after typing pauses; an older one still running is abandoned,
            // and a late answer for a query no longer in the box is ignored.
            timer = window.setTimeout(function () {
                if (inflight) { inflight.abort(); }
                inflight = typeof AbortController === 'function' ? new AbortController() : null;
                var mine = ++seq;
                say('Searching records…');
                window.fetch(searchUrl + (searchUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: inflight ? inflight.signal : undefined,
                }).then(function (r) {
                    if (!r.ok) { throw new Error(String(r.status)); }
                    return r.json();
                }).then(function (data) {
                    if (mine !== seq) { return; }
                    cache[q] = recordItems(data && data.results);
                    if (input.value.trim() !== q) { return; }
                    remote = { query: q, items: cache[q] };
                    render();
                    say(cache[q].length ? cache[q].length + (cache[q].length === 1 ? ' record found' : ' records found') : 'No records match');
                }).catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                    if (mine === seq) { remote = { query: q, items: [] }; render(); say('Record search is unavailable right now.'); }
                });
            }, 180);
        };

        var openPalette = function () {
            if (anotherModalOpen(palette)) { return; }
            input.value = ''; active = 0; remote = { query: '', items: [] }; say('');
            render(); palette.showModal(); input.focus();
        };
        input.addEventListener('input', function () { active = 0; render(); search(); });
        input.addEventListener('keydown', function (e) {
            var to = (e.key === 'ArrowDown' || e.key === 'ArrowUp') ? nextIndex(active, e.key, shown.length) : null;
            if (to !== null) { e.preventDefault(); active = to; paintActive(); }
            else if (e.key === 'Enter' && shown[active]) { e.preventDefault(); go(shown[active], e.ctrlKey || e.metaKey); }
        });
        palette.addEventListener('click', function (event) { if (event.target === palette) { palette.close(); } });
        palette.addEventListener('close', function () { window.clearTimeout(timer); if (inflight) { inflight.abort(); } input.setAttribute('aria-expanded', 'false'); });
        all('[data-palette-open]').forEach(function (b) { b.hidden = false; b.addEventListener('click', openPalette); });
        doc.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (palette.open) { palette.close(); } else { openPalette(); }
            }
        });
    }
})();
