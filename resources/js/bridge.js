// Bridge script — injected into the Live Preview iframe.
// Only activates when running inside an iframe (window.self !== window.top).

const ACTIVE_ATTR = 'data-sid-active';
const HOVER_ATTR = 'data-sid-hover';
const INNER_ATTR = 'data-sid-inner';
const SID_ATTR = 'data-sid';
const STYLES_ID = '__sve-bridge-styles';
const MOUSE_ACTIVE_CLASS = 'sve-mouse-active';
const HOVER_CLEAR_DELAY = 1500;

/**
 * Copies --focus-outline-width and --focus-outline-color from the CP (parent)
 * document into the preview iframe's documentElement so both ends share the
 * same outline token values. Falls back to safe defaults when the CP is
 * inaccessible (cross-origin guard) or the variables are not defined.
 */
export function injectCpVariables(doc, win) {
  let outlineWidth = '2px';
  let focusColor = 'currentColor';
  let hoverColor = '#9CA3AF';

  try {
    const cpStyle = getComputedStyle(win.top.document.documentElement);
    outlineWidth = cpStyle.getPropertyValue('--focus-outline-width').trim() || outlineWidth;
    focusColor = cpStyle.getPropertyValue('--focus-outline-color').trim() || focusColor;
    hoverColor = cpStyle.getPropertyValue('--theme-color-gray-400').trim() || hoverColor;
  } catch {
    // cross-origin or CP not accessible — use defaults
  }

  doc.documentElement.style.setProperty('--sve-outline-width', outlineWidth);
  doc.documentElement.style.setProperty('--sve-focus-color', focusColor);
  doc.documentElement.style.setProperty('--sve-hover-color', hoverColor);
}

export function injectStyles(doc) {
  if (doc.getElementById(STYLES_ID)) {
    return;
  }

  const style = doc.createElement('style');

  style.id = STYLES_ID;
  style.textContent = `
        [data-sid] {
            cursor: pointer;
            outline-width: var(--sve-outline-width, 2px);
            outline-style: dashed;
            outline-color: transparent;
            outline-offset: 2px;
            transition: outline-color 0.15s ease;
        }
        .${MOUSE_ACTIVE_CLASS} [data-sid] {
            outline-color: var(--sve-hover-color, #9CA3AF);
        }
        [data-sid-inner],
        [data-sid-hover] {
            outline-width: var(--sve-outline-width, 2px) !important;
            outline-style: dashed !important;
            outline-color: var(--sve-focus-color, currentColor) !important;
            outline-offset: 2px;
        }
        [data-sid-active] {
            outline-width: var(--sve-outline-width, 2px) !important;
            outline-style: solid !important;
            outline-color: var(--sve-focus-color, currentColor) !important;
            outline-offset: 2px;
        }
        [data-sid][data-sid-label] {
            position: relative;
        }
        [data-sid][data-sid-label]::after {
            content: attr(data-sid-label);
            position: absolute;
            top: 0;
            left: 0;
            background: var(--sve-focus-color, currentColor);
            color: #fff;
            font-size: 10px;
            font-family: sans-serif;
            padding: 1px 6px;
            border-radius: 0 0 4px 0;
            pointer-events: none;
            z-index: 9999;
            white-space: nowrap;
        }
    `;

  doc.head.appendChild(style);
}



/**
 * Returns the nearest preceding sibling that is (or contains) a non-text
 * [data-sid] element. Handles cases where data-sid lives on a descendant
 * element rather than the sibling itself (e.g. video IFRAME inside a wrapper
 * div that has no data-sid of its own).
 */
function findPrecedingSetSibling(el) {
  let prev = el.previousElementSibling;

  while (prev) {
    if (prev.hasAttribute(SID_ATTR) && prev.getAttribute('data-sid-label') !== 'text') {
      return prev;
    }

    // data-sid might live on a descendant inside an un-annotated wrapper (e.g. video)
    const inner = prev.querySelector(`[${SID_ATTR}]:not([data-sid-label="text"])`);

    if (inner) {
      return inner;
    }

    prev = prev.previousElementSibling;
  }

  return null;
}

/**
 * Given the article-set uid and an afterSetUid (the UID of the preceding set,
 * or null for the first text group), returns the matching text element in doc.
 */
export function findTextAfterSetUid(uid, afterSetUid, doc) {
  if (afterSetUid === null) {
    return doc.querySelector(`[${SID_ATTR}="${uid}"][data-sid-label="text"]`);
  }

  const setEl = doc.querySelector(`[${SID_ATTR}="${afterSetUid}"]`);

  if (!setEl) {
    return null;
  }

  // If setEl is not a direct sibling of text elements (e.g. the data-sid lives
  // on a deeply-nested element like an IFRAME inside a wrapper div), bubble up
  // to the level where there are next siblings.
  let scope = setEl;

  while (scope.parentElement && !scope.parentElement.hasAttribute(SID_ATTR) && !scope.nextElementSibling) {
    scope = scope.parentElement;
  }

  let next = scope.nextElementSibling;

  while (next) {
    if (next.hasAttribute(SID_ATTR) && next.getAttribute('data-sid-label') === 'text') {
      return next;
    }

    next = next.nextElementSibling;
  }

  return null;
}

/**
 * On every mouse movement: shows dashed outlines on all [data-sid] elements
 * and marks the innermost hovered one with a solid outline.
 * Both effects clear after HOVER_CLEAR_DELAY ms of no movement.
 */
export function createMouseMoveHandler(win) {
  let clearTimer = null;

  return function handleMouseMove(event) {
    win.document.documentElement.classList.add(MOUSE_ACTIVE_CLASS);

    // Track innermost [data-sid] for solid outline
    const current = win.document.querySelector(`[${INNER_ATTR}]`);
    const target = event.target.closest(`[${SID_ATTR}]`);

    if (current !== target) {
      if (current) {
        current.removeAttribute(INNER_ATTR);
      }

      if (target) {
        target.setAttribute(INNER_ATTR, '');
      }
    }

    if (clearTimer) {
      clearTimeout(clearTimer);
    }

    clearTimer = setTimeout(() => {
      win.document.documentElement.classList.remove(MOUSE_ACTIVE_CLASS);
      win.document.querySelectorAll(`[${INNER_ATTR}]`).forEach((el) => {
        el.removeAttribute(INNER_ATTR);
      });
    }, HOVER_CLEAR_DELAY);
  };
}

export function createClickHandler(win) {
  return function handleClick(event) {
    const target = event.target.closest(`[${SID_ATTR}]`);

    if (!target) {
      return;
    }

    event.preventDefault();

    win.document.querySelectorAll(`[${ACTIVE_ATTR}]`).forEach((el) => {
      el.removeAttribute(ACTIVE_ATTR);
    });

    target.setAttribute(ACTIVE_ATTR, '');

    const message = {
      source: 'statamic-visual-editor',
      type: 'click',
      uid: target.getAttribute(SID_ATTR),
    };

    if (target.getAttribute('data-sid-label') === 'text') {
      const prevSet = findPrecedingSetSibling(target);

      message.afterSetUid = prevSet ? prevSet.getAttribute(SID_ATTR) : null;
    }

    win.top.postMessage(message, '*');
  };
}

export function createHoverHandler(win) {
  let lastHoveredUid = null;

  return function handleHover(event) {
    const target = event.target.closest(`[${SID_ATTR}]`);
    const uid = target ? target.getAttribute(SID_ATTR) : null;

    // Deduplicate: skip when still over the same element (or still off any element).
    if (uid === lastHoveredUid) {
      return;
    }

    lastHoveredUid = uid;

    if (!uid) {
      // Mouse left all annotated elements — tell the CP to clear its hover state.
      win.top.postMessage({ source: 'statamic-visual-editor', type: 'hover', uid: null }, '*');

      return;
    }

    const message = {
      source: 'statamic-visual-editor',
      type: 'hover',
      uid,
    };

    if (target.getAttribute('data-sid-label') === 'text') {
      const prevSet = findPrecedingSetSibling(target);

      message.afterSetUid = prevSet ? prevSet.getAttribute(SID_ATTR) : null;
    }

    win.top.postMessage(message, '*');
  };
}

export function createMessageReceiver(win) {
  return function handleMessage(event) {
    const { data } = event;

    if (!data || data.source !== 'statamic-visual-editor') {
      return;
    }

    if (data.type === 'hover') {
      win.document.querySelectorAll(`[${HOVER_ATTR}]`).forEach((el) => {
        el.removeAttribute(HOVER_ATTR);
      });

      if (data.uid) {
        const el =
          'afterSetUid' in data
            ? findTextAfterSetUid(data.uid, data.afterSetUid, win.document)
            : win.document.querySelector(`[${SID_ATTR}="${data.uid}"]`);

        if (el) {
          el.setAttribute(HOVER_ATTR, '');
        }
      }

      return;
    }

    if (data.type === 'focus') {
      win.document.querySelectorAll(`[${ACTIVE_ATTR}]`).forEach((el) => {
        el.removeAttribute(ACTIVE_ATTR);
      });

      if (data.uid) {
        const el =
          'afterSetUid' in data
            ? findTextAfterSetUid(data.uid, data.afterSetUid, win.document)
            : win.document.querySelector(`[${SID_ATTR}="${data.uid}"]`);

        if (el) {
          el.setAttribute(ACTIVE_ATTR, '');
          el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    }
  };
}

export function initBridge(win = window) {
  if (win.self === win.top) {
    return;
  }

  injectStyles(win.document);
  injectCpVariables(win.document, win);
  win.document.addEventListener('click', createClickHandler(win), true);
  win.document.addEventListener('mousemove', createMouseMoveHandler(win), true);
  win.document.addEventListener('mouseover', createHoverHandler(win), true);
  win.addEventListener('message', createMessageReceiver(win));
}

initBridge();
