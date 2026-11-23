import { createBundleLogger } from './logger';

declare global {
  interface Window {
    __nowoScriptBanners?: Record<string, true>;
  }
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
  const seen = (window.__nowoScriptBanners ??= {});
  if (seen[name] === true) {
    return;
  }
  seen[name] = true;
  createBundleLogger(name, { buildTime }).scriptLoaded();
}
