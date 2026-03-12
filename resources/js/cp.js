// Control Panel script — handles postMessage routing from the Live Preview iframe.

export const SELECTORS = {
  visualIdInput: '[data-visual-id]',
  anySet: '[data-replicator-set], [data-bard-set]',
  headerToggle: '.replicator-set-header, .bard-set-header',
};

const HIGHLIGHT_CLASS = 'sve-highlight';
const HIGHLIGHT_DURATION = 2000;

export function findSetByUid(uid, doc = document) {
  const inputs = doc.querySelectorAll(SELECTORS.visualIdInput);

  for (const input of inputs) {
    if (input.value === uid) {
      return input.closest(SELECTORS.anySet);
    }
  }

  return null;
}

export function collectAncestorSets(setEl) {
  const ancestors = [];
  let current = setEl.parentElement;

  while (current) {
    const ancestor = current.closest(SELECTORS.anySet);

    if (!ancestor) {
      break;
    }

    ancestors.unshift(ancestor);
    current = ancestor.parentElement;
  }

  return ancestors;
}

export function expandSet(setEl) {
  if (!setEl.hasAttribute('data-collapsed')) {
    return;
  }

  const toggle = setEl.querySelector(SELECTORS.headerToggle);

  if (toggle) {
    toggle.click();
  }
}

export function highlightSet(setEl, duration = HIGHLIGHT_DURATION) {
  setEl.classList.add(HIGHLIGHT_CLASS);
  setTimeout(() => {
    setEl.classList.remove(HIGHLIGHT_CLASS);
  }, duration);
}

export function handleFocus(uid, doc = document) {
  const setEl = findSetByUid(uid, doc);

  if (!setEl) {
    return;
  }

  const ancestors = collectAncestorSets(setEl);

  [...ancestors, setEl].forEach(expandSet);
  setEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  highlightSet(setEl);
}

export function handleHover(uid, doc = document) {
  doc.querySelectorAll('[data-sve-hover]').forEach((el) => {
    el.removeAttribute('data-sve-hover');
  });

  const setEl = findSetByUid(uid, doc);

  if (!setEl) {
    return;
  }

  setEl.setAttribute('data-sve-hover', '');
}

export function createMessageListener(doc = document) {
  return function handleMessage(event) {
    const { data } = event;

    if (!data || data.source !== 'statamic-visual-editor') {
      return;
    }

    if (data.type === 'click') {
      handleFocus(data.uid, doc);
    } else if (data.type === 'hover') {
      handleHover(data.uid, doc);
    }
  };
}

export function initCp(win = window) {
  const listener = createMessageListener(win.document);

  win.addEventListener('message', listener);
}
