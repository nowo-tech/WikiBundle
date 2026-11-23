import { createBundleLogger } from './logger';

declare global {
  interface Window {
    __nowoScriptBanners?: Record<string, true>;
  }
}

const announcedHere = new Set<string>();

function isPlainObject(value: unknown): value is Record<string, true> {
  return Object.prototype.toString.call(value) === '[object Object]';
}

/**
 * Records `name` and tells whether it was announced before. The page-wide registry `window.__nowoScriptBanners` lets a
 * script that is evaluated twice announce once, but page HTML can clobber that global (DOM named access, e.g. two
 * elements with `id="__nowoScriptBanners"`), so it is only used when it is a plain object, every access is wrapped in
 * `try/catch`, and the module-scope set is the fallback. A clobbered global can never throw here.
 */
function alreadyAnnounced(name: string): boolean {
  try {
    if (!isPlainObject(window.__nowoScriptBanners)) {
      window.__nowoScriptBanners = {};
    }
    const seen = window.__nowoScriptBanners;
    if (isPlainObject(seen)) {
      if (seen[name] === true) {
        return true;
      }
      seen[name] = true;

      return false;
    }
  } catch {
    // Clobbered or read-only global: fall back to the module-scope set.
  }
  const known = announcedHere.has(name);
  announcedHere.add(name);

  return known;
}

/**
 * Logs the house "script loaded" line for one shipped script, at most once per page: a script that the host (or a
 * template) loads twice announces itself once. The name is the script's own, e.g. `ui-kit/dialog`.
 *
 * @param name - Logger name in kebab-case, `<bundle>` or `<bundle>/<script>`.
 * @param buildTime - Build id injected by Vite `define` (undefined when not injected, e.g. under test).
 */
export function announceScript(name: string, buildTime?: string): void {
  if (typeof window === 'undefined') {
    return;
  }
  if (alreadyAnnounced(name)) {
    return;
  }
  createBundleLogger(name, { buildTime }).scriptLoaded();
}
