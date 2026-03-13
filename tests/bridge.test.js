import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { initBridge, injectStyles, createClickHandler, createHoverHandler, createMessageReceiver, findTextAfterSetUid, injectCpVariables, createMouseMoveHandler } from '../resources/js/bridge.js';

const STYLES_ID = '__sve-bridge-styles';

function makeFakeWin(doc = document) {
  const top = { postMessage: vi.fn() };

  return { self: {}, top, document: doc, addEventListener: vi.fn() };
}

function makeTopWin(doc = document) {
  const self = {};

  return { self, top: self, document: doc };
}

// ---------------------------------------------------------------------------
// injectStyles
// ---------------------------------------------------------------------------

describe('injectStyles', () => {
  afterEach(() => {
    document.getElementById(STYLES_ID)?.remove();
  });

  it('injects a style tag into the document head', () => {
    injectStyles(document);

    expect(document.getElementById(STYLES_ID)).not.toBeNull();
  });

  it('does not inject duplicate style tags', () => {
    injectStyles(document);
    injectStyles(document);

    expect(document.querySelectorAll(`#${STYLES_ID}`).length).toBe(1);
  });

  it('style content includes [data-sid] selector', () => {
    injectStyles(document);

    const content = document.getElementById(STYLES_ID).textContent;

    expect(content).toContain('[data-sid]');
  });

  it('style content includes [data-sid-active] selector', () => {
    injectStyles(document);

    const content = document.getElementById(STYLES_ID).textContent;

    expect(content).toContain('[data-sid-active]');
  });

  it('style content includes ::after label tooltip', () => {
    injectStyles(document);

    const content = document.getElementById(STYLES_ID).textContent;

    expect(content).toContain('::after');
    expect(content).toContain('attr(data-sid-label)');
  });
});

// ---------------------------------------------------------------------------
// initBridge
// ---------------------------------------------------------------------------

describe('initBridge', () => {
  afterEach(() => {
    document.getElementById(STYLES_ID)?.remove();
  });

  it('is a no-op when self === top (not inside an iframe)', () => {
    const win = makeTopWin();
    const spy = vi.spyOn(document, 'addEventListener');

    initBridge(win);

    expect(spy).not.toHaveBeenCalled();
    expect(document.getElementById(STYLES_ID)).toBeNull();

    spy.mockRestore();
  });

  it('injects styles when running inside an iframe', () => {
    const win = makeFakeWin();

    initBridge(win);

    expect(document.getElementById(STYLES_ID)).not.toBeNull();
  });

  it('attaches click, mousemove, mouseover, and message listeners when inside an iframe', () => {
    const win = makeFakeWin();
    const spy = vi.spyOn(document, 'addEventListener');

    initBridge(win);

    const docTypes = spy.mock.calls.map((call) => call[0]);

    expect(docTypes).toContain('click');
    expect(docTypes).toContain('mousemove');
    expect(docTypes).toContain('mouseover');
    expect(win.addEventListener).toHaveBeenCalledWith('message', expect.any(Function));

    spy.mockRestore();
  });
});

// ---------------------------------------------------------------------------
// injectCpVariables
// ---------------------------------------------------------------------------

describe('injectCpVariables', () => {
  afterEach(() => {
    document.documentElement.style.removeProperty('--sve-outline-width');
    document.documentElement.style.removeProperty('--sve-focus-color');
    document.documentElement.style.removeProperty('--sve-hover-color');
  });

  it('sets fallback values when CP is inaccessible (cross-origin)', () => {
    const win = {
      top: {
        get document() {
          throw new Error('cross-origin');
        },
      },
    };

    injectCpVariables(document, win);

    expect(document.documentElement.style.getPropertyValue('--sve-outline-width')).toBe('2px');
    expect(document.documentElement.style.getPropertyValue('--sve-focus-color')).toBe('currentColor');
    expect(document.documentElement.style.getPropertyValue('--sve-hover-color')).toBe('#9CA3AF');
  });

  it('sets fallback values when CP variables are not defined', () => {
    const win = { top: { document: { documentElement: document.createElement('div') } } };

    injectCpVariables(document, win);

    expect(document.documentElement.style.getPropertyValue('--sve-outline-width')).toBe('2px');
    expect(document.documentElement.style.getPropertyValue('--sve-focus-color')).toBe('currentColor');
    expect(document.documentElement.style.getPropertyValue('--sve-hover-color')).toBe('#9CA3AF');
  });

  it('copies all three variables from CP when defined', () => {
    const cpRoot = document.createElement('div');

    cpRoot.style.setProperty('--focus-outline-width', '3px');
    cpRoot.style.setProperty('--focus-outline-color', 'oklch(0.5 0.2 250)');
    cpRoot.style.setProperty('--theme-color-gray-400', '#aaa');

    const win = { top: { document: { documentElement: cpRoot } } };

    injectCpVariables(document, win);

    expect(document.documentElement.style.getPropertyValue('--sve-outline-width')).toBe('3px');
    expect(document.documentElement.style.getPropertyValue('--sve-focus-color')).toBe('oklch(0.5 0.2 250)');
    expect(document.documentElement.style.getPropertyValue('--sve-hover-color')).toBe('#aaa');
  });
});

// ---------------------------------------------------------------------------
// createMouseMoveHandler
// ---------------------------------------------------------------------------

describe('createMouseMoveHandler', () => {
  let win;
  let container;

  beforeEach(() => {
    win = { self: {}, top: {}, document };
    container = document.createElement('div');
    document.body.appendChild(container);
    vi.useFakeTimers();
  });

  afterEach(() => {
    container.remove();
    document.documentElement.classList.remove('sve-mouse-active');
    document.querySelectorAll('[data-sid-inner]').forEach((el) => el.removeAttribute('data-sid-inner'));
    vi.useRealTimers();
  });

  it('adds sve-mouse-active class on mouse movement', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    container.appendChild(el);

    const handler = createMouseMoveHandler(win);

    document.addEventListener('mousemove', handler, true);
    el.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    document.removeEventListener('mousemove', handler, true);

    expect(document.documentElement.classList.contains('sve-mouse-active')).toBe(true);
  });

  it('sets data-sid-inner on the innermost hovered [data-sid] element', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    container.appendChild(el);

    const handler = createMouseMoveHandler(win);

    document.addEventListener('mousemove', handler, true);
    el.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    document.removeEventListener('mousemove', handler, true);

    expect(el.hasAttribute('data-sid-inner')).toBe(true);
  });

  it('moves data-sid-inner to the new innermost element on subsequent movements', () => {
    const el1 = document.createElement('div');

    el1.setAttribute('data-sid', 'uid-1');

    const el2 = document.createElement('div');

    el2.setAttribute('data-sid', 'uid-2');
    container.appendChild(el1);
    container.appendChild(el2);

    const handler = createMouseMoveHandler(win);

    document.addEventListener('mousemove', handler, true);
    el1.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    expect(el1.hasAttribute('data-sid-inner')).toBe(true);

    el2.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    expect(el1.hasAttribute('data-sid-inner')).toBe(false);
    expect(el2.hasAttribute('data-sid-inner')).toBe(true);

    document.removeEventListener('mousemove', handler, true);
  });

  it('clears sve-mouse-active class and data-sid-inner after 1500ms of no movement', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    container.appendChild(el);

    const handler = createMouseMoveHandler(win);

    document.addEventListener('mousemove', handler, true);
    el.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    document.removeEventListener('mousemove', handler, true);

    expect(document.documentElement.classList.contains('sve-mouse-active')).toBe(true);

    vi.advanceTimersByTime(1500);

    expect(document.documentElement.classList.contains('sve-mouse-active')).toBe(false);
    expect(el.hasAttribute('data-sid-inner')).toBe(false);
  });

  it('resets the clear timer on each movement', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    container.appendChild(el);

    const handler = createMouseMoveHandler(win);

    document.addEventListener('mousemove', handler, true);
    el.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    vi.advanceTimersByTime(1000);
    // Move again before timeout fires
    el.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    vi.advanceTimersByTime(1000);
    // Still active — timer was reset
    expect(document.documentElement.classList.contains('sve-mouse-active')).toBe(true);

    vi.advanceTimersByTime(500);
    // Now the full 1500ms has passed since last movement
    expect(document.documentElement.classList.contains('sve-mouse-active')).toBe(false);

    document.removeEventListener('mousemove', handler, true);
  });

  it('does not set data-sid-inner when moving over a non-annotated element', () => {
    const el = document.createElement('div');

    container.appendChild(el);

    const handler = createMouseMoveHandler(win);

    document.addEventListener('mousemove', handler, true);
    el.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
    document.removeEventListener('mousemove', handler, true);

    expect(document.querySelector('[data-sid-inner]')).toBeNull();
  });
});

// ---------------------------------------------------------------------------
// createClickHandler
// ---------------------------------------------------------------------------

describe('createClickHandler', () => {
  let win;
  let container;

  beforeEach(() => {
    win = makeFakeWin();
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('sends a click message with the correct UID', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'test-uuid');
    container.appendChild(el);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      { source: 'statamic-visual-editor', type: 'click', uid: 'test-uuid' },
      '*',
    );
  });

  it('child click bubbles to closest [data-sid] ancestor', () => {
    const parent = document.createElement('div');

    parent.setAttribute('data-sid', 'parent-uid');

    const child = document.createElement('span');

    parent.appendChild(child);
    container.appendChild(parent);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    child.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({ uid: 'parent-uid' }),
      '*',
    );
  });

  it('ignores clicks on non-annotated elements', () => {
    const el = document.createElement('div');

    container.appendChild(el);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    expect(win.top.postMessage).not.toHaveBeenCalled();
  });

  it('prevents default when clicking an annotated element', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid');
    container.appendChild(el);

    const handler = createClickHandler(win);
    const event = new MouseEvent('click', { bubbles: true, cancelable: true });
    const preventSpy = vi.spyOn(event, 'preventDefault');

    document.addEventListener('click', handler, true);
    el.dispatchEvent(event);
    document.removeEventListener('click', handler, true);

    expect(preventSpy).toHaveBeenCalled();
  });

  it('ensures only one element has data-sid-active at a time', () => {
    const el1 = document.createElement('div');

    el1.setAttribute('data-sid', 'uid-1');

    const el2 = document.createElement('div');

    el2.setAttribute('data-sid', 'uid-2');
    container.appendChild(el1);
    container.appendChild(el2);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);

    el1.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(el1.hasAttribute('data-sid-active')).toBe(true);
    expect(el2.hasAttribute('data-sid-active')).toBe(false);

    el2.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    expect(el1.hasAttribute('data-sid-active')).toBe(false);
    expect(el2.hasAttribute('data-sid-active')).toBe(true);

    document.removeEventListener('click', handler, true);
  });
});

// ---------------------------------------------------------------------------
// createHoverHandler
// ---------------------------------------------------------------------------

describe('createHoverHandler', () => {
  let win;
  let container;

  beforeEach(() => {
    win = makeFakeWin();
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('sends a hover message with the correct UID', () => {
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'hover-uid');
    container.appendChild(el);

    const handler = createHoverHandler(win);

    document.addEventListener('mouseover', handler, true);
    el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    document.removeEventListener('mouseover', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      { source: 'statamic-visual-editor', type: 'hover', uid: 'hover-uid' },
      '*',
    );
  });

  it('child hover bubbles to closest [data-sid] ancestor', () => {
    const parent = document.createElement('div');

    parent.setAttribute('data-sid', 'hover-parent-uid');

    const child = document.createElement('span');

    parent.appendChild(child);
    container.appendChild(parent);

    const handler = createHoverHandler(win);

    document.addEventListener('mouseover', handler, true);
    child.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    document.removeEventListener('mouseover', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({ uid: 'hover-parent-uid' }),
      '*',
    );
  });

  it('ignores hover on non-annotated elements', () => {
    const el = document.createElement('div');

    container.appendChild(el);

    const handler = createHoverHandler(win);

    document.addEventListener('mouseover', handler, true);
    el.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
    document.removeEventListener('mouseover', handler, true);

    expect(win.top.postMessage).not.toHaveBeenCalled();
  });
});

// ---------------------------------------------------------------------------
// createMessageReceiver
// ---------------------------------------------------------------------------

describe('createMessageReceiver', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  function makeFakeWinWithDoc() {
    return { self: {}, top: {}, document };
  }

  it('ignores messages with wrong source', () => {
    const win = makeFakeWinWithDoc();
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    container.appendChild(el);

    const handler = createMessageReceiver(win);

    handler({ data: { source: 'other', type: 'hover', uid: 'uid-1' } });

    expect(el.hasAttribute('data-sid-hover')).toBe(false);
  });

  it('sets data-sid-hover on the matching element for hover type', () => {
    const win = makeFakeWinWithDoc();
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    container.appendChild(el);

    const handler = createMessageReceiver(win);

    handler({ data: { source: 'statamic-visual-editor', type: 'hover', uid: 'uid-1' } });

    expect(el.hasAttribute('data-sid-hover')).toBe(true);
  });

  it('clears previous data-sid-hover when hovering a new element', () => {
    const win = makeFakeWinWithDoc();
    const el1 = document.createElement('div');

    el1.setAttribute('data-sid', 'uid-1');
    el1.setAttribute('data-sid-hover', '');

    const el2 = document.createElement('div');

    el2.setAttribute('data-sid', 'uid-2');
    container.appendChild(el1);
    container.appendChild(el2);

    const handler = createMessageReceiver(win);

    handler({ data: { source: 'statamic-visual-editor', type: 'hover', uid: 'uid-2' } });

    expect(el1.hasAttribute('data-sid-hover')).toBe(false);
    expect(el2.hasAttribute('data-sid-hover')).toBe(true);
  });

  it('clears all data-sid-hover when uid is null', () => {
    const win = makeFakeWinWithDoc();
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    el.setAttribute('data-sid-hover', '');
    container.appendChild(el);

    const handler = createMessageReceiver(win);

    handler({ data: { source: 'statamic-visual-editor', type: 'hover', uid: null } });

    expect(el.hasAttribute('data-sid-hover')).toBe(false);
  });

  it('sets data-sid-active on the matching element for focus type', () => {
    const win = makeFakeWinWithDoc();
    const el = document.createElement('div');

    el.setAttribute('data-sid', 'uid-1');
    el.scrollIntoView = vi.fn();
    container.appendChild(el);

    const handler = createMessageReceiver(win);

    handler({ data: { source: 'statamic-visual-editor', type: 'focus', uid: 'uid-1' } });

    expect(el.hasAttribute('data-sid-active')).toBe(true);
  });

  it('clears previous data-sid-active when focusing a new element', () => {
    const win = makeFakeWinWithDoc();
    const el1 = document.createElement('div');

    el1.setAttribute('data-sid', 'uid-1');
    el1.setAttribute('data-sid-active', '');

    const el2 = document.createElement('div');

    el2.setAttribute('data-sid', 'uid-2');
    el2.scrollIntoView = vi.fn();
    container.appendChild(el1);
    container.appendChild(el2);

    const handler = createMessageReceiver(win);

    handler({ data: { source: 'statamic-visual-editor', type: 'focus', uid: 'uid-2' } });

    expect(el1.hasAttribute('data-sid-active')).toBe(false);
    expect(el2.hasAttribute('data-sid-active')).toBe(true);
  });
});

// ---------------------------------------------------------------------------
// afterSetUid — text group targeting
// ---------------------------------------------------------------------------

describe('findTextAfterSetUid', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('returns the first text element when afterSetUid is null', () => {
    const text = document.createElement('div');

    text.setAttribute('data-sid', 'parent-uid');
    text.setAttribute('data-sid-label', 'text');
    container.appendChild(text);

    expect(findTextAfterSetUid('parent-uid', null, document)).toBe(text);
  });

  it('returns the text element that follows the given set element', () => {
    const setEl = document.createElement('figure');

    setEl.setAttribute('data-sid', 'set-uid');
    setEl.setAttribute('data-sid-label', 'pull_quote');

    const text = document.createElement('div');

    text.setAttribute('data-sid', 'parent-uid');
    text.setAttribute('data-sid-label', 'text');

    container.appendChild(setEl);
    container.appendChild(text);

    expect(findTextAfterSetUid('parent-uid', 'set-uid', document)).toBe(text);
  });

  it('bubbles up through wrapper divs when data-sid is on a nested element', () => {
    // video: <div class="wrapper"><iframe data-sid="video-uid" /></div>
    const wrapper = document.createElement('div');
    const iframe = document.createElement('iframe');

    iframe.setAttribute('data-sid', 'video-uid');
    iframe.setAttribute('data-sid-label', 'video');
    wrapper.appendChild(iframe);

    const text = document.createElement('div');

    text.setAttribute('data-sid', 'parent-uid');
    text.setAttribute('data-sid-label', 'text');

    container.appendChild(wrapper);
    container.appendChild(text);

    expect(findTextAfterSetUid('parent-uid', 'video-uid', document)).toBe(text);
  });

  it('returns null when afterSetUid does not exist in the document', () => {
    expect(findTextAfterSetUid('parent-uid', 'nonexistent', document)).toBeNull();
  });
});

describe('createClickHandler with text element', () => {
  let win;
  let container;

  beforeEach(() => {
    win = makeFakeWin();
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('includes afterSetUid of the preceding set when clicking a text element', () => {
    const setEl = document.createElement('figure');

    setEl.setAttribute('data-sid', 'set-uid');
    setEl.setAttribute('data-sid-label', 'pull_quote');

    const textEl = document.createElement('div');

    textEl.setAttribute('data-sid', 'parent-uid');
    textEl.setAttribute('data-sid-label', 'text');

    container.appendChild(setEl);
    container.appendChild(textEl);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    textEl.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({ uid: 'parent-uid', afterSetUid: 'set-uid' }),
      '*',
    );
  });

  it('finds preceding set even when data-sid is on a nested element inside a wrapper', () => {
    // video: <div><iframe data-sid="video-uid"></div>
    const wrapper = document.createElement('div');
    const iframe = document.createElement('iframe');

    iframe.setAttribute('data-sid', 'video-uid');
    iframe.setAttribute('data-sid-label', 'video');
    wrapper.appendChild(iframe);

    const textEl = document.createElement('div');

    textEl.setAttribute('data-sid', 'parent-uid');
    textEl.setAttribute('data-sid-label', 'text');

    container.appendChild(wrapper);
    container.appendChild(textEl);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    textEl.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({ uid: 'parent-uid', afterSetUid: 'video-uid' }),
      '*',
    );
  });

  it('includes afterSetUid: null when text element has no preceding set', () => {
    const textEl = document.createElement('div');

    textEl.setAttribute('data-sid', 'parent-uid');
    textEl.setAttribute('data-sid-label', 'text');
    container.appendChild(textEl);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    textEl.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    expect(win.top.postMessage).toHaveBeenCalledWith(
      expect.objectContaining({ uid: 'parent-uid', afterSetUid: null }),
      '*',
    );
  });

  it('does not include afterSetUid for non-text elements', () => {
    const el = document.createElement('figure');

    el.setAttribute('data-sid', 'set-uid');
    el.setAttribute('data-sid-label', 'pull_quote');
    container.appendChild(el);

    const handler = createClickHandler(win);

    document.addEventListener('click', handler, true);
    el.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    document.removeEventListener('click', handler, true);

    const call = win.top.postMessage.mock.calls[0][0];

    expect('afterSetUid' in call).toBe(false);
  });
});

describe('createMessageReceiver with afterSetUid', () => {
  let container;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
  });

  afterEach(() => {
    container.remove();
  });

  it('hovers the correct text element when afterSetUid is provided', () => {
    const win = { self: {}, top: {}, document };

    const setEl = document.createElement('figure');

    setEl.setAttribute('data-sid', 'set-uid');
    setEl.setAttribute('data-sid-label', 'pull_quote');

    const textEl = document.createElement('div');

    textEl.setAttribute('data-sid', 'parent-uid');
    textEl.setAttribute('data-sid-label', 'text');

    container.appendChild(setEl);
    container.appendChild(textEl);

    const handler = createMessageReceiver(win);

    handler({
      data: { source: 'statamic-visual-editor', type: 'hover', uid: 'parent-uid', afterSetUid: 'set-uid' },
    });

    expect(textEl.hasAttribute('data-sid-hover')).toBe(true);
  });

  it('focuses the correct text element when afterSetUid is provided', () => {
    const win = { self: {}, top: {}, document };

    const setEl = document.createElement('figure');

    setEl.setAttribute('data-sid', 'set-uid');
    setEl.setAttribute('data-sid-label', 'pull_quote');

    const textEl = document.createElement('div');

    textEl.setAttribute('data-sid', 'parent-uid');
    textEl.setAttribute('data-sid-label', 'text');
    textEl.scrollIntoView = vi.fn();

    container.appendChild(setEl);
    container.appendChild(textEl);

    const handler = createMessageReceiver(win);

    handler({
      data: { source: 'statamic-visual-editor', type: 'focus', uid: 'parent-uid', afterSetUid: 'set-uid' },
    });

    expect(textEl.hasAttribute('data-sid-active')).toBe(true);
    expect(textEl.scrollIntoView).toHaveBeenCalled();
  });
});
