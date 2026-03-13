import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { initBridge, injectStyles, createClickHandler, createHoverHandler, createMessageReceiver } from '../resources/js/bridge.js';

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

  it('attaches click, mouseover, and message listeners when inside an iframe', () => {
    const win = makeFakeWin();
    const spy = vi.spyOn(document, 'addEventListener');

    initBridge(win);

    const docTypes = spy.mock.calls.map((call) => call[0]);

    expect(docTypes).toContain('click');
    expect(docTypes).toContain('mouseover');
    expect(win.addEventListener).toHaveBeenCalledWith('message', expect.any(Function));

    spy.mockRestore();
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
