import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
    findSetByUid,
    collectAncestorSets,
    expandSet,
    highlightSet,
    handleFocus,
    handleHover,
    createMessageListener,
} from '../resources/js/cp.js';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeReplicatorSet(uid) {
    const set = document.createElement('div');

    set.setAttribute('data-replicator-set', '');

    if (uid) {
        const input = document.createElement('input');

        input.type = 'hidden';
        input.value = uid;
        input.setAttribute('data-visual-id', uid);
        set.appendChild(input);
    }

    return set;
}

function makeBardSet(uid) {
    const set = document.createElement('div');

    set.setAttribute('data-bard-set', '');

    if (uid) {
        const input = document.createElement('input');

        input.type = 'hidden';
        input.value = uid;
        input.setAttribute('data-visual-id', uid);
        set.appendChild(input);
    }

    return set;
}

function addHeaderToggle(setEl, className) {
    const header = document.createElement('div');

    header.className = className;
    setEl.insertBefore(header, setEl.firstChild);

    return header;
}

// ---------------------------------------------------------------------------
// findSetByUid
// ---------------------------------------------------------------------------

describe('findSetByUid', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        container.remove();
    });

    it('returns null for unknown UID', () => {
        const set = makeReplicatorSet('known-uid');

        container.appendChild(set);

        expect(findSetByUid('unknown-uid', document)).toBeNull();
    });

    it('returns null when no sets exist', () => {
        expect(findSetByUid('any-uid', document)).toBeNull();
    });

    it('finds a replicator set by UID', () => {
        const set = makeReplicatorSet('rep-uid');

        container.appendChild(set);

        expect(findSetByUid('rep-uid', document)).toBe(set);
    });

    it('finds a bard set by UID', () => {
        const set = makeBardSet('bard-uid');

        container.appendChild(set);

        expect(findSetByUid('bard-uid', document)).toBe(set);
    });
});

// ---------------------------------------------------------------------------
// collectAncestorSets
// ---------------------------------------------------------------------------

describe('collectAncestorSets', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        container.remove();
    });

    it('returns empty array when set has no ancestor sets (1 level)', () => {
        const set = makeReplicatorSet('uid-a');

        container.appendChild(set);

        expect(collectAncestorSets(set)).toEqual([]);
    });

    it('returns single ancestor for 2 levels deep', () => {
        const outer = makeReplicatorSet('uid-outer');
        const inner = makeReplicatorSet('uid-inner');

        outer.appendChild(inner);
        container.appendChild(outer);

        expect(collectAncestorSets(inner)).toEqual([outer]);
    });

    it('returns outermost-first array for 3 levels deep', () => {
        const a = makeReplicatorSet('uid-a');
        const b = makeBardSet('uid-b');
        const c = makeReplicatorSet('uid-c');

        b.appendChild(c);
        a.appendChild(b);
        container.appendChild(a);

        expect(collectAncestorSets(c)).toEqual([a, b]);
    });
});

// ---------------------------------------------------------------------------
// expandSet
// ---------------------------------------------------------------------------

describe('expandSet', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        container.remove();
    });

    it('clicks the header toggle when set is collapsed', () => {
        const set = makeReplicatorSet('uid');

        set.setAttribute('data-collapsed', '');

        const header = addHeaderToggle(set, 'replicator-set-header');
        const clickSpy = vi.fn();

        header.addEventListener('click', clickSpy);
        container.appendChild(set);

        expandSet(set);

        expect(clickSpy).toHaveBeenCalledOnce();
    });

    it('does not click toggle when set is not collapsed', () => {
        const set = makeReplicatorSet('uid');
        const header = addHeaderToggle(set, 'replicator-set-header');
        const clickSpy = vi.fn();

        header.addEventListener('click', clickSpy);
        container.appendChild(set);

        expandSet(set);

        expect(clickSpy).not.toHaveBeenCalled();
    });

    it('also recognises bard-set-header as toggle', () => {
        const set = makeBardSet('uid');

        set.setAttribute('data-collapsed', '');

        const header = addHeaderToggle(set, 'bard-set-header');
        const clickSpy = vi.fn();

        header.addEventListener('click', clickSpy);
        container.appendChild(set);

        expandSet(set);

        expect(clickSpy).toHaveBeenCalledOnce();
    });
});

// ---------------------------------------------------------------------------
// highlightSet
// ---------------------------------------------------------------------------

describe('highlightSet', () => {
    it('adds the highlight class immediately', () => {
        vi.useFakeTimers();

        const set = makeReplicatorSet('uid');

        highlightSet(set, 2000);

        expect(set.classList.contains('sve-highlight')).toBe(true);

        vi.useRealTimers();
    });

    it('removes the highlight class after the duration', () => {
        vi.useFakeTimers();

        const set = makeReplicatorSet('uid');

        highlightSet(set, 2000);
        vi.advanceTimersByTime(2000);

        expect(set.classList.contains('sve-highlight')).toBe(false);

        vi.useRealTimers();
    });

    it('keeps the highlight class before the duration elapses', () => {
        vi.useFakeTimers();

        const set = makeReplicatorSet('uid');

        highlightSet(set, 2000);
        vi.advanceTimersByTime(1999);

        expect(set.classList.contains('sve-highlight')).toBe(true);

        vi.useRealTimers();
    });
});

// ---------------------------------------------------------------------------
// handleFocus
// ---------------------------------------------------------------------------

describe('handleFocus', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        container.remove();
    });

    it('is a no-op for unknown UID', () => {
        expect(() => handleFocus('nonexistent-uid', document)).not.toThrow();
    });

    it('expands and highlights a known collapsed set', () => {
        vi.useFakeTimers();

        const set = makeReplicatorSet('focus-uid');

        set.setAttribute('data-collapsed', '');

        const header = addHeaderToggle(set, 'replicator-set-header');
        const clickSpy = vi.fn();

        header.addEventListener('click', clickSpy);
        set.scrollIntoView = vi.fn();
        container.appendChild(set);

        handleFocus('focus-uid', document);

        expect(clickSpy).toHaveBeenCalledOnce();
        expect(set.classList.contains('sve-highlight')).toBe(true);

        vi.useRealTimers();
    });

    it('expands all ancestor sets before the target set', () => {
        vi.useFakeTimers();

        const outer = makeReplicatorSet('outer-uid');

        outer.setAttribute('data-collapsed', '');

        const outerHeader = addHeaderToggle(outer, 'replicator-set-header');
        const outerClickSpy = vi.fn();

        outerHeader.addEventListener('click', outerClickSpy);

        const inner = makeReplicatorSet('inner-uid');

        inner.setAttribute('data-collapsed', '');

        const innerHeader = addHeaderToggle(inner, 'replicator-set-header');
        const innerClickSpy = vi.fn();

        innerHeader.addEventListener('click', innerClickSpy);
        inner.scrollIntoView = vi.fn();

        outer.appendChild(inner);
        container.appendChild(outer);

        handleFocus('inner-uid', document);

        expect(outerClickSpy).toHaveBeenCalledOnce();
        expect(innerClickSpy).toHaveBeenCalledOnce();

        vi.useRealTimers();
    });
});

// ---------------------------------------------------------------------------
// handleHover
// ---------------------------------------------------------------------------

describe('handleHover', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        container.remove();
    });

    it('sets data-sve-hover on the matching set', () => {
        const set = makeReplicatorSet('hover-uid');

        container.appendChild(set);
        handleHover('hover-uid', document);

        expect(set.hasAttribute('data-sve-hover')).toBe(true);
    });

    it('is a no-op for unknown UID', () => {
        expect(() => handleHover('nonexistent', document)).not.toThrow();
    });

    it('clears data-sve-hover from a previously hovered set', () => {
        const first = makeReplicatorSet('first-uid');
        const second = makeReplicatorSet('second-uid');

        container.appendChild(first);
        container.appendChild(second);

        handleHover('first-uid', document);
        expect(first.hasAttribute('data-sve-hover')).toBe(true);

        handleHover('second-uid', document);
        expect(first.hasAttribute('data-sve-hover')).toBe(false);
        expect(second.hasAttribute('data-sve-hover')).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// createMessageListener
// ---------------------------------------------------------------------------

describe('createMessageListener', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
    });

    afterEach(() => {
        container.remove();
    });

    it('ignores messages not from statamic-visual-editor', () => {
        const set = makeReplicatorSet('uid');

        set.scrollIntoView = vi.fn();
        container.appendChild(set);

        const listener = createMessageListener(document);

        listener({ data: { source: 'other-source', type: 'click', uid: 'uid' } });

        expect(set.classList.contains('sve-highlight')).toBe(false);
    });

    it('ignores null data', () => {
        const listener = createMessageListener(document);

        expect(() => listener({ data: null })).not.toThrow();
    });

    it('ignores undefined data', () => {
        const listener = createMessageListener(document);

        expect(() => listener({ data: undefined })).not.toThrow();
    });

    it('routes click messages to handleFocus', () => {
        vi.useFakeTimers();

        const set = makeReplicatorSet('click-uid');

        set.scrollIntoView = vi.fn();
        container.appendChild(set);

        const listener = createMessageListener(document);

        listener({ data: { source: 'statamic-visual-editor', type: 'click', uid: 'click-uid' } });

        expect(set.classList.contains('sve-highlight')).toBe(true);

        vi.useRealTimers();
    });

    it('routes hover messages to handleHover', () => {
        const set = makeReplicatorSet('hover-uid');

        container.appendChild(set);

        const listener = createMessageListener(document);

        listener({ data: { source: 'statamic-visual-editor', type: 'hover', uid: 'hover-uid' } });

        expect(set.hasAttribute('data-sve-hover')).toBe(true);
    });

    it('ignores messages with an unrelated type', () => {
        const set = makeReplicatorSet('uid');

        set.scrollIntoView = vi.fn();
        container.appendChild(set);

        const listener = createMessageListener(document);

        listener({ data: { source: 'statamic-visual-editor', type: 'unknown', uid: 'uid' } });

        expect(set.classList.contains('sve-highlight')).toBe(false);
        expect(set.hasAttribute('data-sve-hover')).toBe(false);
    });
});
