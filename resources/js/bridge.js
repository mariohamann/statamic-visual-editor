// Bridge script — injected into the Live Preview iframe.
// Only activates when running inside an iframe (window.self !== window.top).

const ACTIVE_ATTR = 'data-sid-active';
const HOVER_ATTR = 'data-sid-hover';
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
            outline: 2px dashed transparent;
            outline-offset: 2px;
            transition: outline-color 0.15s ease;
        }
        [data-sid]:hover,
        [data-sid-hover] {
            outline-color: rgba(99, 102, 241, 0.6);
        }
        [data-sid-active] {
            outline: 2px solid rgb(99, 102, 241) !important;
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
        const el = win.document.querySelector(`[${SID_ATTR}="${data.uid}"]`);

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
        const el = win.document.querySelector(`[${SID_ATTR}="${data.uid}"]`);

        if (el) {
          el.setAttribute(ACTIVE_ATTR, '');
          el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
  win.document.addEventListener('click', createClickHandler(win), true);
  win.document.addEventListener('mouseover', createHoverHandler(win), true);
  win.addEventListener('message', createMessageReceiver(win));
}

initBridge();
