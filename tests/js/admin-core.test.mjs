// The admin shell's decisions (resources/js/admin-core.js), tested without a browser:
// node --test tests/js/*.test.mjs (part of `npm test`).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { confirmPresentation, confirmSource, filterItems, mergeResults, nextIndex, overflowEdges, recordItems, searchable } from '../../resources/js/admin-core.js';

/** Just enough of an element: attributes, and a name to tell them apart. */
function el(name, attrs = {}) {
    return { name, hasAttribute: (k) => Object.prototype.hasOwnProperty.call(attrs, k), getAttribute: (k) => (k in attrs ? attrs[k] : null) };
}

test('the button that submitted asks its own question, so the right submitter is confirmed and replayed', () => {
    const form = el('form', { 'data-confirm': 'Save changes?' });
    const remove = el('remove', { 'data-confirm': 'Remove {n} files?' });
    const save = el('save');

    assert.equal(confirmSource(form, remove).name, 'remove', 'a destructive button overrides the form');
    assert.equal(confirmSource(form, save).name, 'form', 'a plain button falls back to the form');
    assert.equal(confirmSource(el('plain'), save), null, 'nothing asks: the submit goes through untouched');
    assert.equal(confirmSource(form, null).name, 'form', 'Enter in a field has no submitter');
});

test('the confirmation fills every {n} and marks destructive actions', () => {
    const shown = confirmPresentation(el('b', { 'data-confirm': 'Remove {n} files? {n} cannot be restored.' }), 3);
    assert.equal(shown.text, 'Remove 3 files? 3 cannot be restored.');
    assert.equal(shown.danger, true);
    assert.equal(shown.label, 'Yes, continue');

    const plain = confirmPresentation(el('b', { 'data-confirm': 'Run now?', 'data-confirm-label': 'Run now' }), 0);
    assert.deepEqual([plain.danger, plain.label], [false, 'Run now']);
    assert.equal(confirmPresentation(el('b', { 'data-confirm': 'Run it?', 'data-confirm-danger': '' }), 1).danger, true, 'the page can mark danger itself');
    assert.equal(confirmPresentation(el('b', { 'data-confirm': 'Unpublish {n}?' }), 'all matching').text, 'Unpublish all matching?');
});

test('the palette filters pages by label and group, and skips entries with no address', () => {
    const items = [
        { label: 'Audit log', hint: 'Operations', href: '/a' },
        { label: 'Jobs and schedule', hint: 'Operations', href: '/j' },
        { label: 'Broken', hint: 'Action', href: null },
    ];
    assert.deepEqual(filterItems(items, 'AUDIT').map((i) => i.href), ['/a']);
    assert.deepEqual(filterItems(items, 'operations').map((i) => i.href), ['/a', '/j']);
    assert.equal(filterItems(items, '').length, 2);
    assert.equal(filterItems(items, '', 1).length, 1);
});

test('pages and actions come first, records after, and no address twice', () => {
    const local = [{ label: 'Users and roles', href: '/users' }];
    const remote = recordItems([
        { kind: 'user', label: 'Ivy', detail: 'ivy@example.org · Editor', url: '/users/7' },
        { kind: 'user', label: 'Users page', url: '/users' },
        { kind: 'policy', label: 'No address' },
    ]);
    const merged = mergeResults(local, remote);
    assert.deepEqual(merged.map((i) => i.href), ['/users', '/users/7']);
    assert.equal(merged[1].hint, 'Person');
    assert.equal(merged[1].detail, 'ivy@example.org · Editor');
    assert.deepEqual(recordItems(null), [], 'a malformed answer shows nothing rather than throwing');
});

test('records are searched from two letters on', () => {
    assert.equal(searchable('a'), false);
    assert.equal(searchable('  a  '), false);
    assert.equal(searchable('ai'), true);
});

test('arrow keys move within the list and stop at the ends', () => {
    assert.equal(nextIndex(0, 'ArrowDown', 3), 1);
    assert.equal(nextIndex(2, 'ArrowDown', 3), 2, 'no wrap at the bottom');
    assert.equal(nextIndex(0, 'ArrowUp', 3), 0, 'no wrap at the top');
    assert.equal(nextIndex(5, 'ArrowUp', 3), 2, 'an index left over from a longer list is brought back in range');
    assert.equal(nextIndex(1, 'Home', 3), 0);
    assert.equal(nextIndex(1, 'End', 3), 2);
    assert.equal(nextIndex(0, 'ArrowDown', 0), null, 'an empty list has nowhere to go');
    assert.equal(nextIndex(0, 'a', 3), null, 'typing is not navigation');
});

test('the sidebar fades only at an edge with more beyond it', () => {
    assert.equal(overflowEdges(0, 500, 500), '');
    assert.equal(overflowEdges(0, 900, 500), 'bottom');
    assert.equal(overflowEdges(200, 900, 500), 'both');
    assert.equal(overflowEdges(400, 900, 500), 'top');
});
