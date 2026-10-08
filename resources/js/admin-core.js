/*
 * The decisions behind admin.js, kept free of the DOM so they can be tested with
 * `node --test` (tests/js/admin-core.test.mjs). admin.js does the wiring; anything
 * here takes plain values, or objects that only need getAttribute/hasAttribute.
 */

/** Words that mark a confirmation as destructive when the page did not say so itself. */
const DANGER = /delete|remove|suspend|revoke|reset|archive|unpublish/i;

/**
 * Which element's data-confirm applies to a submit: the button that submitted the form
 * when it carries its own question, otherwise the form. Null when neither asks.
 */
export function confirmSource(form, submitter) {
    if (submitter && submitter.hasAttribute && submitter.hasAttribute('data-confirm')) { return submitter; }
    if (form && form.hasAttribute && form.hasAttribute('data-confirm')) { return form; }
    return null;
}

/**
 * What the confirmation dialog says and how its button looks. `n` fills {n} (every
 * occurrence), the selection count or "all matching".
 */
export function confirmPresentation(source, n) {
    const message = String(source.getAttribute('data-confirm') || '');
    const text = message.split('{n}').join(String(n));
    const danger = source.hasAttribute('data-confirm-danger') || DANGER.test(message);
    return {
        text,
        danger,
        label: source.getAttribute('data-confirm-label') || (danger ? 'Yes, continue' : 'Confirm'),
    };
}

/** Pages and actions whose label or group contains the query, case-insensitively. */
export function filterItems(items, query, limit = 12) {
    const q = String(query || '').trim().toLowerCase();
    return items
        .filter((item) => item.href && (q === '' || `${item.label} ${item.hint || ''}`.toLowerCase().includes(q)))
        .slice(0, limit);
}

/**
 * Pages and actions first, then records from the search endpoint, with no address listed
 * twice (a record can be the same page as an action on it).
 */
export function mergeResults(local, remote, limit = 30) {
    const seen = new Set();
    const out = [];
    for (const item of [...local, ...remote]) {
        if (!item.href || seen.has(item.href)) { continue; }
        seen.add(item.href);
        out.push(item);
        if (out.length >= limit) { break; }
    }
    return out;
}

/** Labels for the kinds the search endpoint returns. */
export const KIND_LABELS = { user: 'Person', policy: 'Policy record', submission: 'Submission' };

/** The endpoint's rows as palette items. Rows without an address are dropped. */
export function recordItems(results) {
    return (Array.isArray(results) ? results : [])
        .filter((r) => r && typeof r.url === 'string' && r.url !== '')
        .map((r) => ({ label: String(r.label || ''), hint: KIND_LABELS[r.kind] || 'Record', detail: r.detail ? String(r.detail) : '', href: r.url, record: true }));
}

/** Whether a query is long enough to be sent to the record search. */
export function searchable(query, min = 2) {
    return String(query || '').trim().length >= min;
}

/**
 * The option a key moves to in a list of `length` options. Up and down stop at the ends
 * (no wrap, so holding a key does not spin); Home and End jump. Null for any other key.
 */
export function nextIndex(current, key, length) {
    if (length <= 0) { return null; }
    const last = length - 1;
    switch (key) {
        case 'ArrowDown': return Math.min(Math.max(current, -1) + 1, last);
        case 'ArrowUp': return Math.max(Math.min(current, length) - 1, 0);
        case 'Home': return 0;
        case 'End': return last;
        default: return null;
    }
}

/** Which edges of a scrolling box have more content beyond them: '', 'top', 'bottom' or 'both'. */
export function overflowEdges(scrollTop, scrollHeight, clientHeight) {
    const above = scrollTop > 1;
    const below = scrollTop + clientHeight < scrollHeight - 1;
    return above && below ? 'both' : above ? 'top' : below ? 'bottom' : '';
}
