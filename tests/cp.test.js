import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  findSetByUid,
  collectAncestorSets,
  expandSet,
  isSetCollapsed,
  highlightSet,
  focusBardSet,
  handleFocus,
  handleHover,
  createMessageListener,
  sendToPreview,
  initCp,
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
  // Mirrors actual Statamic 6 Bard set DOM:
  // [data-node-view-wrapper] > div[contenteditable] > header > button
  //                                                 > div (content, v-show)
  const wrapper = document.createElement('div');

  wrapper.setAttribute('data-node-view-wrapper', '');

  const inner = document.createElement('div');

  inner.setAttribute('contenteditable', 'false');

  const header = document.createElement('header');
  const btn = document.createElement('button');

  btn.type = 'button';
  header.appendChild(btn);
  inner.appendChild(header);

  const contentDiv = document.createElement('div');

  inner.appendChild(contentDiv);
  wrapper.appendChild(inner);

  if (uid) {
    const input = document.createElement('input');

    input.type = 'hidden';
    input.value = uid;
    input.setAttribute('data-visual-id', uid);
    contentDiv.appendChild(input);
  }

  return wrapper;
}

function addHeaderToggle(setEl) {
  const header = document.createElement('header');
  const button = document.createElement('button');

  button.type = 'button';
  header.appendChild(button);
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
// isSetCollapsed
// ---------------------------------------------------------------------------

describe('isSetCollapsed', () => {
  it('returns true for replicator set with data-collapsed="true"', () => {
    const set = makeReplicatorSet('uid');

    set.dataset.collapsed = 'true';

    expect(isSetCollapsed(set)).toBe(true);
  });

  it('returns false for replicator set with data-collapsed="false"', () => {
    const set = makeReplicatorSet('uid');

    set.dataset.collapsed = 'false';

    expect(isSetCollapsed(set)).toBe(false);
  });

  it('returns false for replicator set with no data-collapsed attribute', () => {
    const set = makeReplicatorSet('uid');

    expect(isSetCollapsed(set)).toBe(false);
  });

  it('returns true for bard set when content div has display:none (v-show collapsed)', () => {
    const set = makeBardSet('uid');
    const inner = set.querySelector('[contenteditable="false"]');

    inner.lastElementChild.style.display = 'none';

    expect(isSetCollapsed(set)).toBe(true);
  });

  it('returns false for bard set when content div is visible', () => {
    const set = makeBardSet('uid');

    expect(isSetCollapsed(set)).toBe(false);
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

  it('clicks the header toggle when replicator set is collapsed', () => {
    const set = makeReplicatorSet('uid');

    set.dataset.collapsed = 'true';

    const header = addHeaderToggle(set);
    const clickSpy = vi.fn();

    header.addEventListener('click', clickSpy);
    container.appendChild(set);

    expandSet(set);

    expect(clickSpy).toHaveBeenCalledOnce();
  });

  it('does not click toggle when replicator set is not collapsed', () => {
    const set = makeReplicatorSet('uid');
    const header = addHeaderToggle(set);
    const clickSpy = vi.fn();

    header.addEventListener('click', clickSpy);
    container.appendChild(set);

    expandSet(set);

    expect(clickSpy).not.toHaveBeenCalled();
  });

  it('clicks the header toggle when bard set is collapsed (v-show content div hidden)', () => {
    const set = makeBardSet('uid');
    const inner = set.querySelector('[contenteditable="false"]');

    inner.lastElementChild.style.display = 'none';

    const header = set.querySelector('header');
    const clickSpy = vi.fn();

    header.addEventListener('click', clickSpy);
    container.appendChild(set);

    expandSet(set);

    expect(clickSpy).toHaveBeenCalledOnce();
  });

  it('does not click toggle when bard set is not collapsed', () => {
    const set = makeBardSet('uid');
    const header = set.querySelector('header');
    const clickSpy = vi.fn();

    header.addEventListener('click', clickSpy);
    container.appendChild(set);

    expandSet(set);

    expect(clickSpy).not.toHaveBeenCalled();
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
// focusBardSet
// ---------------------------------------------------------------------------

describe('focusBardSet', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('adds ProseMirror-selectednode immediately', () => {
    vi.useFakeTimers();

    const set = makeBardSet('uid');

    container.appendChild(set);
    focusBardSet(set, 2000);

    expect(set.classList.contains('ProseMirror-selectednode')).toBe(true);

    vi.useRealTimers();
  });

  it('removes ProseMirror-selectednode after the duration', () => {
    vi.useFakeTimers();

    const set = makeBardSet('uid');

    container.appendChild(set);
    focusBardSet(set, 2000);
    vi.advanceTimersByTime(2000);

    expect(set.classList.contains('ProseMirror-selectednode')).toBe(false);

    vi.useRealTimers();
  });

  it('keeps ProseMirror-selectednode before the duration elapses', () => {
    vi.useFakeTimers();

    const set = makeBardSet('uid');

    container.appendChild(set);
    focusBardSet(set, 2000);
    vi.advanceTimersByTime(1999);

    expect(set.classList.contains('ProseMirror-selectednode')).toBe(true);

    vi.useRealTimers();
  });

  it('does not throw when no contenteditable ancestor is present', () => {
    const set = makeBardSet('uid');

    container.appendChild(set);

    expect(() => focusBardSet(set)).not.toThrow();
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

  it('sets data-sve-active on the focused set', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('focus-uid');

    set.scrollIntoView = vi.fn();
    container.appendChild(set);

    handleFocus('focus-uid', document);

    expect(set.hasAttribute('data-sve-active')).toBe(true);

    vi.useRealTimers();
  });

  it('clears data-sve-active from the previous set when focusing a new one', () => {
    vi.useFakeTimers();

    const first = makeReplicatorSet('first-uid');

    first.scrollIntoView = vi.fn();
    container.appendChild(first);

    const second = makeReplicatorSet('second-uid');

    second.scrollIntoView = vi.fn();
    container.appendChild(second);

    handleFocus('first-uid', document);
    expect(first.hasAttribute('data-sve-active')).toBe(true);

    handleFocus('second-uid', document);
    expect(first.hasAttribute('data-sve-active')).toBe(false);
    expect(second.hasAttribute('data-sve-active')).toBe(true);

    vi.useRealTimers();
  });

  it('expands and highlights a known collapsed set', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('focus-uid');

    set.dataset.collapsed = 'true';

    const header = addHeaderToggle(set);
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

    outer.dataset.collapsed = 'true';

    const outerHeader = addHeaderToggle(outer);
    const outerClickSpy = vi.fn();

    outerHeader.addEventListener('click', outerClickSpy);

    const inner = makeReplicatorSet('inner-uid');

    inner.dataset.collapsed = 'true';

    const innerHeader = addHeaderToggle(inner);
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

  it('uses focusBardSet (ProseMirror-selectednode) for bard sets', () => {
    vi.useFakeTimers();

    const set = makeBardSet('bard-focus-uid');

    set.scrollIntoView = vi.fn();
    container.appendChild(set);

    handleFocus('bard-focus-uid', document);

    expect(set.classList.contains('ProseMirror-selectednode')).toBe(true);
    expect(set.classList.contains('sve-highlight')).toBe(false);

    vi.useRealTimers();
  });

  it('uses highlightSet (sve-highlight) for replicator sets', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('rep-focus-uid');

    set.scrollIntoView = vi.fn();
    container.appendChild(set);

    handleFocus('rep-focus-uid', document);

    expect(set.classList.contains('sve-highlight')).toBe(true);
    expect(set.classList.contains('ProseMirror-selectednode')).toBe(false);

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

  it('skips applying hover outline to the currently active set', () => {
    const set = makeReplicatorSet('active-uid');

    set.setAttribute('data-sve-active', '');
    container.appendChild(set);

    handleHover('active-uid', document);

    expect(set.hasAttribute('data-sve-hover')).toBe(false);
  });

  it('clears stale hover even when new hover targets the active element', () => {
    const active = makeReplicatorSet('active-uid');

    active.setAttribute('data-sve-active', '');

    const stale = makeReplicatorSet('stale-uid');

    stale.setAttribute('data-sve-hover', '');
    container.appendChild(active);
    container.appendChild(stale);

    handleHover('active-uid', document);

    // Stale hover must be cleared even though the target is skipped.
    expect(stale.hasAttribute('data-sve-hover')).toBe(false);
    expect(active.hasAttribute('data-sve-hover')).toBe(false);
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

// ---------------------------------------------------------------------------
// sendToPreview
// ---------------------------------------------------------------------------

describe('sendToPreview', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('posts a message to the iframe contentWindow', () => {
    const postMessage = vi.fn();
    const iframe = document.createElement('iframe');

    iframe.id = 'live-preview-iframe';
    Object.defineProperty(iframe, 'contentWindow', { get: () => ({ postMessage }) });
    container.appendChild(iframe);

    const fakeWin = { document };

    sendToPreview({ source: 'statamic-visual-editor', type: 'hover', uid: 'uid-1' }, fakeWin);

    expect(postMessage).toHaveBeenCalledWith(
      { source: 'statamic-visual-editor', type: 'hover', uid: 'uid-1' },
      '*',
    );
  });

  it('is a no-op when no iframe is present', () => {
    const fakeWin = { document };

    expect(() => {
      sendToPreview({ source: 'statamic-visual-editor', type: 'hover', uid: 'uid-1' }, fakeWin);
    }).not.toThrow();
  });
});

// ---------------------------------------------------------------------------
// initCp — CP→iframe listeners
// ---------------------------------------------------------------------------

describe('initCp CP→iframe listeners', () => {
  let container;
  let postMessage;
  let iframe;
  let fakeWin;
  let messageListeners;
  let cleanup;

  beforeEach(() => {
    postMessage = vi.fn();
    container = document.createElement('div');
    document.body.appendChild(container);

    iframe = document.createElement('iframe');
    iframe.id = 'live-preview-iframe';
    Object.defineProperty(iframe, 'contentWindow', { get: () => ({ postMessage }) });
    container.appendChild(iframe);

    messageListeners = [];

    fakeWin = {
      document,
      addEventListener: (type, handler) => {
        messageListeners.push({ type, handler });
      },
    };

    cleanup = initCp(fakeWin);
  });

  afterEach(() => {
    cleanup?.();
    document.getElementById('__sve-cp-styles')?.remove();
    container.remove();
  });

  it('sends hover message to iframe when mouse enters a set', () => {
    const set = makeReplicatorSet('hover-uid');

    container.appendChild(set);

    set.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));

    expect(postMessage).toHaveBeenCalledWith(
      { source: 'statamic-visual-editor', type: 'hover', uid: 'hover-uid' },
      '*',
    );
  });

  it('sends hover with null when mouse moves outside all sets', () => {
    const set = makeReplicatorSet('hover-uid');

    container.appendChild(set);

    // Enter set
    set.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    postMessage.mockClear();

    // Leave to a non-set element
    container.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));

    expect(postMessage).toHaveBeenCalledWith(
      { source: 'statamic-visual-editor', type: 'hover', uid: null },
      '*',
    );
  });

  it('does not send duplicate hover messages for the same set', () => {
    const set = makeReplicatorSet('hover-uid');

    container.appendChild(set);

    set.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    set.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));

    const hoverCalls = postMessage.mock.calls.filter((c) => c[0]?.type === 'hover' && c[0]?.uid !== null);

    expect(hoverCalls.length).toBe(1);
  });

  it('sends focus message to iframe when clicking anywhere inside a set', () => {
    const set = makeReplicatorSet('click-uid');
    const inner = document.createElement('div');

    set.appendChild(inner);
    container.appendChild(set);

    inner.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    expect(postMessage).toHaveBeenCalledWith(
      { source: 'statamic-visual-editor', type: 'focus', uid: 'click-uid' },
      '*',
    );
  });

  it('sends focus for innermost set when nested sets are clicked', () => {
    const outer = makeReplicatorSet('outer-uid');
    const inner = makeReplicatorSet('inner-uid');

    outer.appendChild(inner);
    container.appendChild(outer);

    inner.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    const focusCalls = postMessage.mock.calls.filter((c) => c[0]?.type === 'focus');

    expect(focusCalls.length).toBe(1);
    expect(focusCalls[0][0].uid).toBe('inner-uid');
  });

  it('does not send focus message when clicking outside any set', () => {
    const other = document.createElement('div');

    container.appendChild(other);

    other.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    const focusCalls = postMessage.mock.calls.filter((c) => c[0]?.type === 'focus');

    expect(focusCalls.length).toBe(0);
  });
});

// ---------------------------------------------------------------------------
// afterSetUid — Bard text group bidirectional targeting
// ---------------------------------------------------------------------------

describe('handleFocus with afterSetUid scrolls inside Bard editor', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('scrolls to the element after the specified Bard set node', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('article-uid');

    set.scrollIntoView = vi.fn();

    const editor = document.createElement('div');

    editor.setAttribute('contenteditable', 'true');

    const para1 = document.createElement('p');
    const nodeWrapper = document.createElement('div');

    nodeWrapper.setAttribute('data-node-view-wrapper', '');

    const hiddenInput = document.createElement('input');

    hiddenInput.setAttribute('type', 'hidden');
    hiddenInput.setAttribute('data-visual-id', 'bard-set-uid');
    nodeWrapper.appendChild(hiddenInput);

    const para2 = document.createElement('p');

    para2.scrollIntoView = vi.fn();
    editor.appendChild(para1);
    editor.appendChild(nodeWrapper);
    editor.appendChild(para2);
    set.appendChild(editor);
    container.appendChild(set);

    handleFocus('article-uid', document, 'bard-set-uid');

    vi.advanceTimersByTime(300);

    expect(para2.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });

    vi.useRealTimers();
  });

  it('scrolls the editor to the top when afterSetUid is null (first text group)', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('article-uid');

    set.scrollIntoView = vi.fn();

    const editor = document.createElement('div');

    editor.setAttribute('contenteditable', 'true');
    editor.scrollIntoView = vi.fn();
    set.appendChild(editor);
    container.appendChild(set);

    handleFocus('article-uid', document, null);

    vi.advanceTimersByTime(300);

    expect(editor.scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });

    vi.useRealTimers();
  });

  it('does not attempt Bard scroll when afterSetUid is undefined', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('article-uid');

    set.scrollIntoView = vi.fn();

    const editor = document.createElement('div');

    editor.setAttribute('contenteditable', 'true');
    editor.scrollIntoView = vi.fn();
    set.appendChild(editor);
    container.appendChild(set);

    handleFocus('article-uid', document);

    vi.advanceTimersByTime(300);

    expect(editor.scrollIntoView).not.toHaveBeenCalled();

    vi.useRealTimers();
  });

  it('does not scroll the outer set when afterSetUid is provided (avoids double scroll)', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('article-uid');

    set.scrollIntoView = vi.fn();

    const editor = document.createElement('div');

    editor.setAttribute('contenteditable', 'true');

    const nodeWrapper = document.createElement('div');

    nodeWrapper.setAttribute('data-node-view-wrapper', '');

    const hiddenInput = document.createElement('input');

    hiddenInput.setAttribute('type', 'hidden');
    hiddenInput.setAttribute('data-visual-id', 'bard-set-uid');
    hiddenInput.value = 'bard-set-uid';
    nodeWrapper.appendChild(hiddenInput);

    const para = document.createElement('p');

    para.scrollIntoView = vi.fn();
    editor.appendChild(nodeWrapper);
    editor.appendChild(para);
    set.appendChild(editor);
    container.appendChild(set);

    handleFocus('article-uid', document, 'bard-set-uid');

    // The outer set must NOT be scrolled — only the text target gets scrolled later.
    expect(set.scrollIntoView).not.toHaveBeenCalled();

    vi.useRealTimers();
  });

  it('does not scroll the outer set when afterSetUid is null (first text group)', () => {
    vi.useFakeTimers();

    const set = makeReplicatorSet('article-uid');

    set.scrollIntoView = vi.fn();

    const editor = document.createElement('div');

    editor.setAttribute('contenteditable', 'true');
    editor.scrollIntoView = vi.fn();
    set.appendChild(editor);
    container.appendChild(set);

    handleFocus('article-uid', document, null);

    // Outer set scroll is suppressed; editor scroll happens via scrollBardToTextAfterSet.
    expect(set.scrollIntoView).not.toHaveBeenCalled();

    vi.useRealTimers();
  });
});

describe('createMessageListener passes afterSetUid to handleFocus', () => {
  it('forwards afterSetUid from click message', () => {
    const doc = document.implementation.createHTMLDocument();
    const listener = createMessageListener(doc);

    // minimal set in the doc
    const set = doc.createElement('div');

    set.setAttribute('data-replicator-set', '');

    const input = doc.createElement('input');

    input.setAttribute('data-visual-id', 'article-uid');
    input.value = 'article-uid';
    set.appendChild(input);
    set.scrollIntoView = vi.fn();
    doc.body.appendChild(set);

    // afterSetUid = null means text before first set
    listener({ data: { source: 'statamic-visual-editor', type: 'click', uid: 'article-uid', afterSetUid: null } });

    // handleFocus was called — set should be highlighted
    expect(set.classList.contains('sve-highlight')).toBe(true);
  });
});


