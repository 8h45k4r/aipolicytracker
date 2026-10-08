/*
 * Admin behaviour, loaded after public.js on backend pages only. Everything here is
 * progressive: each page works without it (forms post, links navigate, the server
 * renders the messages), and this adds side panels, toasts, a styled confirmation,
 * busy buttons and a command palette. The CSP forbids inline handlers, so every hook
 * is a data- attribute.
 */
(function () {
    'use strict';

    var doc = document;
    if (!doc.documentElement.hasAttribute('data-admin')) { return; }

    function all(selector, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(selector)); }

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
        if (!dialog || typeof dialog.showModal !== 'function') { return false; }
        dialog.showModal();
        dialog._opener = opener || null;
        var first = dialog.querySelector('[autofocus], input:not([type=hidden]), select, textarea, button');
        if (first) { first.focus(); }
        return true;
    }
    function closeDrawer(dialog) {
        if (!dialog.open) { return; }
        dialog.close();
        if (dialog._opener) { dialog._opener.focus(); }
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
        if (dialog.hasAttribute('data-open-on-load')) { openDrawer(dialog); }
    });

    // ---------------------------------------------------------------------------------
    // Confirmation. public.js asks window.confirm() for [data-confirm]; in the admin a
    // styled dialog asks instead, names the count ({n} = ticked rows) and marks
    // destructive actions. It runs first (capture) and replays the submit when confirmed.
    // ---------------------------------------------------------------------------------
    var confirmDialog = doc.getElementById('adm-confirm');
    if (confirmDialog && typeof confirmDialog.showModal === 'function') {
        var confirmText = confirmDialog.querySelector('[data-confirm-text]');
        var confirmOk = confirmDialog.querySelector('[data-confirm-ok]');
        var pending = null;
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (form._admConfirmed) { form._admConfirmed = false; return; }
            var source = (event.submitter && event.submitter.hasAttribute('data-confirm')) ? event.submitter : form;
            var message = source.getAttribute('data-confirm');
            if (!message) { return; }
            event.preventDefault();
            event.stopImmediatePropagation();
            // Same count as the bulk bar: "all matching" when that scope is ticked, else the ticked rows.
            var scope = form.id ? doc.querySelector('[data-bulk-scope="' + form.id + '"]') : null;
            var n = (scope && scope.checked) ? (scope.getAttribute('data-bulk-scope-count') || 'all matching')
                : all('input[type=checkbox][name$="[]"]:checked', form).length + (form.id ? all('input[type=checkbox][form="' + form.id + '"]:checked').length : 0);
            confirmText.textContent = message.replace('{n}', String(n));
            var danger = source.hasAttribute('data-confirm-danger') || /delete|remove|suspend|revoke|reset/i.test(message);
            confirmOk.textContent = source.getAttribute('data-confirm-label') || (danger ? 'Yes, continue' : 'Confirm');
            confirmOk.className = danger ? 'btn-danger' : 'btn-primary';
            pending = { form: form, submitter: event.submitter || null };
            confirmDialog.showModal();
            confirmOk.focus();
        }, true);
        confirmOk.addEventListener('click', function () {
            confirmDialog.close();
            if (!pending) { return; }
            var p = pending; pending = null;
            p.form._admConfirmed = true;
            if (typeof p.form.requestSubmit === 'function') { p.form.requestSubmit(p.submitter || undefined); } else { p.form.submit(); }
        });
        all('[data-confirm-cancel]', confirmDialog).forEach(function (b) { b.addEventListener('click', function () { pending = null; confirmDialog.close(); }); });
        confirmDialog.addEventListener('cancel', function () { pending = null; });
    }

    // ---------------------------------------------------------------------------------
    // Busy state: a submitted form's button says so and cannot be pressed twice.
    // ---------------------------------------------------------------------------------
    doc.addEventListener('submit', function (event) {
        if (event.defaultPrevented) { return; }
        var form = event.target;
        if (form.method && form.method.toLowerCase() === 'get') { return; }
        var button = event.submitter;
        if (!button || button.hasAttribute('data-no-busy')) { return; }
        window.setTimeout(function () {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.label = button.textContent;
            button.textContent = 'Working…';
        }, 0);
    });

    // Coming back with the browser's Back button restores the page as it was left.
    window.addEventListener('pageshow', function () {
        all('button[aria-busy="true"]').forEach(function (b) { b.disabled = false; b.removeAttribute('aria-busy'); if (b.dataset.label) { b.textContent = b.dataset.label; } });
    });

    // ---------------------------------------------------------------------------------
    // Command palette: Ctrl/⌘ K. Lists every page the sidebar offers this account plus
    // the actions the page registered in [data-command], filtered as you type.
    // ---------------------------------------------------------------------------------
    var palette = doc.getElementById('adm-palette');
    if (palette && typeof palette.showModal === 'function') {
        var input = palette.querySelector('input');
        var list = palette.querySelector('[role=listbox]');
        var items = all('[data-admin-nav-link]').map(function (a) {
            var group = a.closest('[data-admin-nav-group]');
            return { label: a.textContent.trim(), hint: group ? group.querySelector('p').textContent.trim() : '', href: a.getAttribute('href') };
        }).concat(all('[data-command]').map(function (el) {
            return { label: el.getAttribute('data-command'), hint: 'Action', href: el.getAttribute('href') || el.getAttribute('data-command-href') };
        }));
        var active = 0;
        var shown = [];
        function render() {
            var q = input.value.trim().toLowerCase();
            shown = items.filter(function (i) { return i.href && (q === '' || (i.label + ' ' + i.hint).toLowerCase().indexOf(q) !== -1); }).slice(0, 12);
            if (active >= shown.length) { active = 0; }
            list.innerHTML = '';
            shown.forEach(function (item, index) {
                var li = doc.createElement('li');
                li.id = 'adm-palette-' + index;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', index === active ? 'true' : 'false');
                li.className = 'adm-palette-item';
                var label = doc.createElement('span'); label.textContent = item.label;
                var hint = doc.createElement('span'); hint.className = 'adm-palette-hint'; hint.textContent = item.hint;
                li.appendChild(label); li.appendChild(hint);
                li.addEventListener('mousedown', function (e) { e.preventDefault(); window.location.href = item.href; });
                list.appendChild(li);
            });
            input.setAttribute('aria-activedescendant', shown.length ? 'adm-palette-' + active : '');
            palette.querySelector('[data-palette-empty]').hidden = shown.length > 0;
        }
        function openPalette() { input.value = ''; active = 0; render(); palette.showModal(); input.focus(); }
        input.addEventListener('input', function () { active = 0; render(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, shown.length - 1); render(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
            else if (e.key === 'Enter' && shown[active]) { e.preventDefault(); window.location.href = shown[active].href; }
        });
        palette.addEventListener('click', function (event) { if (event.target === palette) { palette.close(); } });
        all('[data-palette-open]').forEach(function (b) { b.hidden = false; b.addEventListener('click', openPalette); });
        doc.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); if (palette.open) { palette.close(); } else { openPalette(); } }
        });
    }
})();

// ==== Users and roles (backend/admin/users, user, permissions) ===================================
(function () {
    'use strict';

    var doc = document;
    if (!doc.documentElement.hasAttribute('data-admin')) { return; }

    // "Copy invitation link": [data-copy-from="input-id"] copies that input's value. The
    // button is hidden in the markup and shown here; without it the text can be selected.
    Array.prototype.forEach.call(doc.querySelectorAll('[data-copy-from]'), function (button) {
        var source = doc.getElementById(button.getAttribute('data-copy-from'));
        if (!source) { return; }
        var label = button.textContent;
        button.hidden = false;
        button.addEventListener('click', function () {
            var done = function (ok) {
                button.textContent = ok ? 'Copied' : 'Select the link and copy it';
                window.setTimeout(function () { button.textContent = label; }, 2500);
            };
            source.select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(source.value).then(function () { done(true); }, function () { done(false); });
            } else {
                var ok;
                try { ok = doc.execCommand('copy'); } catch (e) { ok = false; }
                done(ok);
            }
        });
    });

    // Unsaved changes on a [data-unsaved-form] (the role permissions matrix): every checkbox
    // that differs from the page as loaded is tinted, the bar counts them, the save
    // confirmation names the count, and leaving the page with changes asks first.
    Array.prototype.forEach.call(doc.querySelectorAll('form[data-unsaved-form]'), function (form) {
        var boxes = Array.prototype.slice.call(form.querySelectorAll('input[type=checkbox]'));
        var status = form.querySelector('[data-unsaved-status]');
        var bar = form.querySelector('[data-unsaved-bar]');
        var save = form.querySelector('button[type=submit][data-confirm]');
        var submitting = false;
        var changed = 0;

        function paint() {
            changed = 0;
            boxes.forEach(function (box) {
                var differs = box.checked !== box.defaultChecked;
                if (differs) { changed++; }
                var cell = box.closest('td');
                if (cell) { cell.classList.toggle('adm-cell-changed', differs); }
            });
            if (status) {
                status.textContent = changed === 0 ? 'No unsaved changes.'
                    : 'Unsaved changes: ' + changed + (changed === 1 ? ' permission differs' : ' permissions differ') + ' from what is saved.';
            }
            if (bar) { if (changed) { bar.setAttribute('data-dirty', ''); } else { bar.removeAttribute('data-dirty'); } }
            if (save) {
                save.setAttribute('data-confirm', changed === 0
                    ? 'Nothing has changed. Save anyway?'
                    : 'Save ' + changed + (changed === 1 ? ' change' : ' changes') + ' to role permissions? They apply to every holder of each role from their next page load.');
            }
        }

        form.addEventListener('change', paint);
        // A reset restores defaultChecked after the event, so repaint on the next tick.
        form.addEventListener('reset', function () { window.setTimeout(paint, 0); });
        form.addEventListener('submit', function () { submitting = true; });
        window.addEventListener('beforeunload', function (event) {
            if (changed && !submitting) { event.preventDefault(); event.returnValue = ''; }
        });
        paint();
    });
})();
// ==== end Users and roles =========================================================================
