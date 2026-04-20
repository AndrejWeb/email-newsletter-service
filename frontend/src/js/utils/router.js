const routes = {};
let currentCleanup = null;

export function registerRoute(hash, handler) {
  routes[hash] = handler;
}

export function navigate(hash) {
  window.location.hash = hash;
}

export function getCurrentRoute() {
  return window.location.hash.slice(1) || '/';
}

export function getRouteParams() {
  const hash = getCurrentRoute();
  const parts = hash.split('/').filter(Boolean);
  return parts;
}

export async function handleRoute() {
  if (typeof currentCleanup === 'function') {
    currentCleanup();
    currentCleanup = null;
  }

  const hash = getCurrentRoute();

  let handler = routes[hash];

  if (!handler) {
    const parts = hash.split('/').filter(Boolean);
    for (const [pattern, h] of Object.entries(routes)) {
      const patternParts = pattern.split('/').filter(Boolean);
      if (patternParts.length !== parts.length) continue;

      let match = true;
      const params = {};
      for (let i = 0; i < patternParts.length; i++) {
        if (patternParts[i].startsWith(':')) {
          params[patternParts[i].slice(1)] = parts[i];
        } else if (patternParts[i] !== parts[i]) {
          match = false;
          break;
        }
      }

      if (match) {
        handler = (container) => h(container, params);
        break;
      }
    }
  }

  if (!handler) {
    handler = routes['/'] || routes['/dashboard'];
  }

  if (handler) {
    const container = document.getElementById('app');
    const result = await handler(container);
    if (typeof result === 'function') {
      currentCleanup = result;
    }
  }
}

export function initRouter() {
  window.addEventListener('hashchange', handleRoute);
  handleRoute();
}
