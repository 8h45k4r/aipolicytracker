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

    // Correction form: show the current value of the field being disputed and suggest a summary.
    var fieldSelect = document.querySelector('[data-field-select]');
    if (fieldSelect) {
        var currentBox = document.querySelector('[data-current-value]');
        var summaryInput = document.querySelector('[data-summary-input]');
        var context = document.querySelector('[data-correction-context]');
        var recordTitle = context ? (context.querySelector('a') || {}).textContent || '' : '';
        fieldSelect.addEventListener('change', function () {
            var opt = fieldSelect.options[fieldSelect.selectedIndex];
            if (currentBox) { currentBox.value = opt.value ? (opt.getAttribute('data-current') || '\u2014') : ''; }
            if (summaryInput && opt.value && (!summaryInput.value || summaryInput.getAttribute('data-auto') === '1')) {
                summaryInput.value = opt.textContent.trim() + ' for ' + recordTitle.trim() + ' is wrong';
                summaryInput.setAttribute('data-auto', '1');
            }
        });
        if (summaryInput) { summaryInput.addEventListener('input', function () { summaryInput.removeAttribute('data-auto'); }); }
    }

    // Saved records: a per-browser reading list kept in localStorage (no account, no server copy).
    var savedKey = 'apt-saved';
    // Links are rebuilt from the record type and id; stored URLs are never used as hrefs.
    var savedRoutes = { policy: '/policies/', jurisdiction: '/jurisdictions/', obligation: '/obligations/', incident: '/ai-risk/incidents/', risk: '/ai-risk/risks/' };
    function savedHref(item) {
        var prefix = savedRoutes[item.type];
        var slug = String(item.id || '').split(':').slice(1).join(':');
        if (!prefix || !/^[A-Za-z0-9._-]{1,160}$/.test(slug)) { return '/saved'; }
        return prefix + encodeURIComponent(slug);
    }
    function readSaved() {
        try {
            var v = JSON.parse(localStorage.getItem(savedKey) || '[]');
            return Array.isArray(v) ? v.filter(function (i) { return i && typeof i.id === 'string' && typeof i.type === 'string'; }) : [];
        } catch (e) { return []; }
    }
    function writeSaved(items) {
        try { localStorage.setItem(savedKey, JSON.stringify(items)); } catch (e) { /* storage unavailable */ }
        updateSavedCount(items);
    }
    function updateSavedCount(items) {
        var n = (items || readSaved()).length;
        document.querySelectorAll('[data-saved-count]').forEach(function (el) { el.textContent = n ? String(n) : ''; el.hidden = !n; });
    }
    function paintSaveButton(btn, saved) {
        btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
        btn.textContent = saved ? 'Saved \u2713' : (btn.getAttribute('data-save-label') || 'Save');
    }
    document.querySelectorAll('[data-save]').forEach(function (btn) {
        var id = btn.getAttribute('data-save');
        paintSaveButton(btn, readSaved().some(function (i) { return i.id === id; }));
        btn.addEventListener('click', function () {
            var items = readSaved();
            var idx = items.findIndex(function (i) { return i.id === id; });
            if (idx >= 0) { items.splice(idx, 1); } else {
                items.unshift({ id: id, type: btn.getAttribute('data-save-type'), title: btn.getAttribute('data-save-title'),  meta: btn.getAttribute('data-save-meta') || '', saved_at: new Date().toISOString() });
            }
            writeSaved(items);
            paintSaveButton(btn, idx < 0);
            track(idx < 0 ? 'save_record' : 'unsave_record', { label: id });
        });
    });
    var savedList = document.querySelector('[data-saved-list]');
    if (savedList) {
        var renderSaved = function () {
            var items = readSaved();
            var empty = document.querySelector('[data-saved-empty]');
            var tools = document.querySelector('[data-saved-tools]');
            if (empty) empty.hidden = items.length > 0;
            if (tools) tools.hidden = items.length === 0;
            savedList.innerHTML = '';
            items.forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'flex flex-wrap items-start justify-between gap-3 py-3';
                var a = document.createElement('a'); a.setAttribute('href', savedHref(item)); a.className = 'font-medium text-brand-navy hover:underline'; a.textContent = item.title;
                var meta = document.createElement('div'); meta.className = 'text-xs text-brand-muted'; meta.textContent = (item.type ? item.type.charAt(0).toUpperCase() + item.type.slice(1) : '') + (item.meta ? ' \u00b7 ' + item.meta : '') + ' \u00b7 saved ' + new Date(item.saved_at).toLocaleDateString();
                var wrap = document.createElement('div'); wrap.appendChild(a); wrap.appendChild(meta);
                var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'btn-secondary !min-h-[36px] !py-1'; rm.textContent = 'Remove';
                rm.addEventListener('click', function () { writeSaved(readSaved().filter(function (i) { return i.id !== item.id; })); renderSaved(); });
                li.appendChild(wrap); li.appendChild(rm);
                savedList.appendChild(li);
            });
        };
        renderSaved();
        var copyBtn = document.querySelector('[data-saved-copy]');
        if (copyBtn) copyBtn.addEventListener('click', function () {
            var text = readSaved().map(function (i) { return '- [' + i.title + '](' + location.origin + savedHref(i) + ')'; }).join('\n');
            if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { copyBtn.textContent = 'Copied as Markdown'; setTimeout(function () { copyBtn.textContent = 'Copy list as Markdown'; }, 1500); });
        });
        var clearBtn = document.querySelector('[data-saved-clear]');
        if (clearBtn) clearBtn.addEventListener('click', function () { if (window.confirm('Remove all saved records from this browser?')) { writeSaved([]); renderSaved(); } });
        var jsonBtn = document.querySelector('[data-saved-json]');
        if (jsonBtn) jsonBtn.addEventListener('click', function () {
            var text = JSON.stringify(readSaved().map(function (i) { return { id: i.id, type: i.type, title: i.title, url: location.origin + savedHref(i), saved_at: i.saved_at }; }), null, 2);
            if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { jsonBtn.textContent = 'Copied as JSON'; setTimeout(function () { jsonBtn.textContent = 'Copy list as JSON'; }, 1500); });
        });
    }
    updateSavedCount();

    // Compact multi-select dropdowns: close on outside click or Escape; submit the form when a
    // panel closes with changed selections; "Clear" unticks the group and submits.
    document.querySelectorAll('[data-multi-select]').forEach(function (details) {
        var form = details.closest('form');
        var changed = false;
        details.addEventListener('change', function () { changed = true; });
        details.addEventListener('toggle', function () {
            if (!details.open && changed && form) { changed = false; form.requestSubmit ? form.requestSubmit() : form.submit(); }
        });
        var clear = details.querySelector('[data-multi-clear]');
        if (clear) clear.addEventListener('click', function (e) { e.preventDefault(); details.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; }); changed = true; details.open = false; });
    });
    document.addEventListener('click', function (e) {
        document.querySelectorAll('[data-multi-select][open]').forEach(function (d) { if (!d.contains(e.target)) { d.open = false; } });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { document.querySelectorAll('[data-multi-select][open]').forEach(function (d) { d.open = false; }); } });

    // Chart tooltips: one floating element for every [data-tip] (SVG bars, dots, treemap blocks).
    var tip = document.createElement('div');
    tip.className = 'pointer-events-none fixed z-50 hidden rounded-sm bg-brand-ink px-2 py-1 text-xs text-white shadow-lg';
    tip.setAttribute('role', 'status');
    document.body.appendChild(tip);
    function moveTip(e) { tip.style.left = (e.clientX + 12) + 'px'; tip.style.top = (e.clientY + 12) + 'px'; }
    document.addEventListener('mouseover', function (e) {
        var el = e.target.closest && e.target.closest('[data-tip]');
        if (!el) { return; }
        tip.textContent = el.getAttribute('data-tip');
        tip.classList.remove('hidden');
        moveTip(e);
    });
    document.addEventListener('mousemove', function (e) { if (!tip.classList.contains('hidden')) { moveTip(e); } });
    document.addEventListener('mouseout', function (e) { if (e.target.closest && e.target.closest('[data-tip]')) { tip.classList.add('hidden'); } });

    // Chart export: SVG as-is, PNG rendered through a canvas, CSV from the accessible data table.
    function saveBlob(blob, name) {
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-chart-export]');
        if (!btn) { return; }
        var fig = btn.closest('figure[data-chart]');
        var svg = fig && fig.querySelector('svg');
        var name = (fig && fig.getAttribute('data-chart')) || 'chart';
        var kind = btn.getAttribute('data-chart-export');
        if (kind === 'csv') {
            var rows = [];
            fig.querySelectorAll('table tr').forEach(function (tr) {
                rows.push(Array.prototype.map.call(tr.querySelectorAll('th,td'), function (c) { return '"' + c.textContent.trim().replace(/"/g, '""') + '"'; }).join(','));
            });
            rows.push('"Source: aipolicytracker.org, ' + document.title.replace(/"/g, '') + ', ' + new Date().toISOString().slice(0, 10) + '"');
            saveBlob(new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8' }), name + '.csv');
            return;
        }
        if (!svg) { return; }
        var clone = svg.cloneNode(true);
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.querySelectorAll('[data-tip]').forEach(function (n) { n.removeAttribute('data-tip'); });
        var box = svg.viewBox.baseVal;
        var w = box && box.width ? box.width : svg.clientWidth, h = box && box.height ? box.height : svg.clientHeight;
        clone.setAttribute('width', w); clone.setAttribute('height', h);
        var credit = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        credit.setAttribute('x', 4); credit.setAttribute('y', h - 4); credit.setAttribute('font-size', '9'); credit.setAttribute('fill', '#5D6B7E');
        credit.textContent = 'aipolicytracker.org · ' + new Date().toISOString().slice(0, 10);
        clone.appendChild(credit);
        var xml = new XMLSerializer().serializeToString(clone);
        if (kind === 'svg') { saveBlob(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }), name + '.svg'); return; }
        var img = new Image();
        var url = URL.createObjectURL(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }));
        img.onload = function () {
            var canvas = document.createElement('canvas'); canvas.width = w * 2; canvas.height = h * 2;
            var ctx = canvas.getContext('2d'); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height); ctx.scale(2, 2); ctx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            canvas.toBlob(function (blob) { if (blob) { saveBlob(blob, name + '.png'); } }, 'image/png');
        };
        img.src = url;
    });

    // Jurisdiction picker: reveal the type-ahead and the chip row, keep both in step
    // with the checkboxes, and warn when a capped picker is over its limit. Everything
    // here is enhancement: the checkboxes submit on their own if this never runs.
    document.querySelectorAll('[data-jurisdiction-picker]').forEach(function (picker) {
        var searchWrap = picker.querySelector('[data-picker-search-wrap]');
        var search = picker.querySelector('[data-picker-search]');
        var summary = picker.querySelector('[data-picker-summary]');
        var chips = picker.querySelector('[data-picker-chips]');
        var countEl = picker.querySelector('[data-picker-count]');
        var limitEl = picker.querySelector('[data-picker-limit]');
        var emptyEl = picker.querySelector('[data-picker-empty]');
        var max = parseInt(picker.getAttribute('data-max') || '0', 10);
        var boxes = Array.prototype.slice.call(picker.querySelectorAll('input[type=checkbox]'));
        if (!boxes.length) { return; }

        if (searchWrap) { searchWrap.hidden = false; }
        if (summary) { summary.hidden = false; }

        function checked() { return boxes.filter(function (b) { return b.checked; }); }

        function label(box) {
            var span = box.parentNode.querySelector('span');
            return span ? span.textContent.trim() : box.value;
        }

        function paint() {
            var on = checked();
            picker.querySelectorAll('[data-picker-quick-pick]').forEach(function (chip) {
                var isOn = on.some(function (b) { return b.value === chip.getAttribute('data-picker-quick-pick'); });
                chip.classList.toggle('chip-active', isOn);
                chip.setAttribute('aria-pressed', isOn ? 'true' : 'false');
            });
            if (countEl) { countEl.textContent = String(on.length); }
            if (limitEl) { limitEl.hidden = !(max && on.length > max); }
            if (chips) {
                chips.innerHTML = '';
                on.forEach(function (box) {
                    var li = document.createElement('li');
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'chip !min-h-0 !py-1 inline-flex items-center gap-1';
                    btn.setAttribute('data-picker-remove', box.value);
                    btn.appendChild(document.createTextNode(label(box)));
                    var x = document.createElement('span');
                    x.setAttribute('aria-hidden', 'true');
                    x.textContent = '\u00d7';
                    btn.appendChild(x);
                    var sr = document.createElement('span');
                    sr.className = 'sr-only';
                    sr.textContent = 'Remove ' + label(box);
                    btn.appendChild(sr);
                    li.appendChild(btn);
                    chips.appendChild(li);
                });
            }
            picker.querySelectorAll('[data-picker-group]').forEach(function (group) {
                var n = group.querySelectorAll('input[type=checkbox]:checked').length;
                var out = group.querySelector('[data-picker-group-count]');
                if (out) { out.textContent = n ? n + ' selected' : ''; }
            });
        }

        picker.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'checkbox') { paint(); }
        });

        picker.addEventListener('click', function (e) {
            var quick = e.target.closest('[data-picker-quick-pick]');
            if (quick) {
                var qbox = boxes.filter(function (b) { return b.value === quick.getAttribute('data-picker-quick-pick'); })[0];
                if (qbox) {
                    qbox.checked = !qbox.checked;
                    quick.classList.toggle('chip-active', qbox.checked);
                    quick.setAttribute('aria-pressed', qbox.checked ? 'true' : 'false');
                    qbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }
            var remove = e.target.closest('[data-picker-remove]');
            if (!remove) { return; }
            e.preventDefault();
            var box = boxes.filter(function (b) { return b.value === remove.getAttribute('data-picker-remove'); })[0];
            if (box) { box.checked = false; paint(); box.focus(); }
        });

        if (search) {
            search.addEventListener('input', function () {
                var term = search.value.trim().toLowerCase();
                var anyVisible = false;
                picker.querySelectorAll('[data-picker-group]').forEach(function (group) {
                    var shown = 0;
                    group.querySelectorAll('[data-picker-option]').forEach(function (opt) {
                        var hit = !term || opt.getAttribute('data-picker-label').indexOf(term) !== -1;
                        opt.hidden = !hit;
                        if (hit) { shown++; }
                    });
                    group.hidden = shown === 0;
                    // Open every group while filtering so matches are not hidden behind a closed heading.
                    if (term && shown) { group.open = true; }
                    if (shown) { anyVisible = true; }
                });
                if (emptyEl) { emptyEl.hidden = anyVisible; }
            });
        }

        paint();
    });

    // Auto-submit filter selects on desktop (forms still submit normally).
    document.querySelectorAll('form[data-autosubmit] select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (window.matchMedia('(min-width: 768px)').matches) { sel.form.requestSubmit ? sel.form.requestSubmit() : sel.form.submit(); }
        });
    });

    // Primary-nav dropdowns. Each group is a <details>, so it already opens, closes and
    // takes keyboard focus without this. What native <details> does not do is close when
    // you click elsewhere, close on Escape, or close a sibling when another group opens,
    // which is what a menu bar is expected to do. All three are added here.
    var navGroups = Array.prototype.slice.call(document.querySelectorAll('[data-nav] [data-nav-group]'));
    if (navGroups.length) {
        var closeAll = function (except) {
            navGroups.forEach(function (g) { if (g !== except) { g.open = false; } });
        };

        navGroups.forEach(function (group) {
            // 'toggle' fires after the browser has changed state, so reading .open is safe.
            group.addEventListener('toggle', function () {
                if (group.open) { closeAll(group); }
            });
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-nav-group]')) { closeAll(null); }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') { return; }
            var open = navGroups.filter(function (g) { return g.open; })[0];
            if (!open) { return; }
            closeAll(null);
            // Return focus to the trigger, or the menu is dismissed with focus nowhere.
            var summary = open.querySelector('summary');
            if (summary) { summary.focus(); }
        });
    }
})();

// Admin tables: selection, counts and confirmations.
//
// A bulk form sits beside its table (a form cannot contain another form, and each
// row keeps its own publish form), so the rows point at it with form="<id>". Everything
// here is keyed on that id: the header checkbox ticks the rows of its own table only,
// the bar shows how many are ticked, buttons that need a selection wait for one, and
// shift-click ticks a range. Nothing here decides anything; the attestation and the
// submit are still the reviewer's.
(function () {
    function items(id) {
        return Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item][form="' + id + '"]'));
    }
    function selected(id) {
        return items(id).filter(function (box) { return box.checked; }).length;
    }
    function paint(id) {
        var all = items(id), n = selected(id);
        var scope = document.querySelector('[data-bulk-scope="' + id + '"]');
        var whole = !!(scope && scope.checked);
        document.querySelectorAll('[data-bulk-all="' + id + '"]').forEach(function (master) {
            master.checked = all.length > 0 && n === all.length;
            master.indeterminate = n > 0 && n < all.length;
        });
        document.querySelectorAll('[data-bulk-count="' + id + '"]').forEach(function (el) {
            el.textContent = whole ? el.getAttribute('data-bulk-count-all') : (n + ' selected');
        });
        document.querySelectorAll('[data-bulk-needs="' + id + '"]').forEach(function (btn) {
            btn.disabled = !(whole || n > 0);
        });
    }

    var forms = {};
    document.querySelectorAll('[data-bulk-item]').forEach(function (box) {
        var id = box.getAttribute('form');
        if (!id || forms[id]) { return; }
        forms[id] = true;
        var last = null;
        items(id).forEach(function (item) {
            item.addEventListener('click', function (event) {
                if (event.shiftKey && last && last !== item) {
                    var list = items(id), a = list.indexOf(last), b = list.indexOf(item);
                    list.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (other) { other.checked = item.checked; });
                }
                last = item;
            });
            item.addEventListener('change', function () { paint(id); });
        });
        document.querySelectorAll('[data-bulk-all="' + id + '"]').forEach(function (master) {
            master.addEventListener('change', function () {
                items(id).forEach(function (item) { item.checked = master.checked; });
                paint(id);
            });
        });
        var scope = document.querySelector('[data-bulk-scope="' + id + '"]');
        if (scope) { scope.addEventListener('change', function () { paint(id); }); }
        paint(id);
    });

    // Confirmations. The CSP allows no inline handlers, so onsubmit="return confirm()"
    // never ran and a delete went through on the first click. A form or the button
    // that submits it carries data-confirm instead; {n} is the number of ticked rows.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var source = (event.submitter && event.submitter.getAttribute('data-confirm')) ? event.submitter : form;
        var message = source.getAttribute('data-confirm');
        if (!message) { return; }
        var scope = document.querySelector('[data-bulk-scope="' + form.id + '"]');
        var n = (scope && scope.checked) ? (scope.getAttribute('data-bulk-scope-count') || 'all matching') : selected(form.id);
        if (!window.confirm(message.replace('{n}', n))) { event.preventDefault(); }
    });
})();
