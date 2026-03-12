// Bridge script — injected into the Live Preview iframe.
// Only activates when running inside an iframe (window.self !== window.top).

const ACTIVE_ATTR = 'data-sid-active';
const SID_ATTR = 'data-sid';
const STYLES_ID = '__sve-bridge-styles';

export function injectStyles(doc) {
  if (doc.getElementById(STYLES_ID)) {
    return;
  }

  const style = doc.createElement('style');

  style.id = STYLES_ID;
  style.textContent = `
        [data-sid] {
            cursor: pointer;
            outline: 2px dashed rgba(99, 102, 241, 0.4);
            outline-offset: 2px;
        }
        [data-sid]:hover {
            outline: 2px solid rgba(99, 102, 241, 0.8);
        }
        [data-sid-active] {
            outline: 2px solid rgb(99, 102, 241) !important;
        }
        [data-sid][data-sid-label] {
            position: relative;
        }
        [data-sid][data-sid-label]::after {
            content: attr(data-sid-label);
            position: absolute;
            top: 0;
            left: 0;
            background: rgb(99, 102, 241);
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

    win.top.postMessage(
      {
        source: 'statamic-visual-editor',
        type: 'click',
        uid: target.getAttribute(SID_ATTR),
      },
      '*',
    );
  };
}

export function createHoverHandler(win) {
  return function handleHover(event) {
    const target = event.target.closest(`[${SID_ATTR}]`);

    if (!target) {
      return;
    }

    win.top.postMessage(
      {
        source: 'statamic-visual-editor',
        type: 'hover',
        uid: target.getAttribute(SID_ATTR),
      },
      '*',
    );
  };
}

export function initBridge(win = window) {
  if (win.self === win.top) {
    return;
  }

  injectStyles(win.document);
  win.document.addEventListener('click', createClickHandler(win), true);
  win.document.addEventListener('mouseover', createHoverHandler(win), true);
}

initBridge();
