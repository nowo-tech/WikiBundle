/**
 * announceScript(): one "script loaded" line per script name and page, even when the script is evaluated twice.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { announceScript } from './script-banner';

describe('announceScript', () => {
    let log: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        delete window.__nowoScriptBanners;
        log = vi.spyOn(console, 'log').mockImplementation(() => undefined);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        delete window.__nowoScriptBanners;
    });

    it('logs the script name and the build id', () => {
        announceScript('wiki', 'abc123def456');

        expect(log).toHaveBeenCalledTimes(1);
        expect(String(log.mock.calls[0]?.[0])).toContain('[wiki] script loaded, build: ');
        expect(String(log.mock.calls[0]?.[0])).toContain('abc123def456');
    });

    it('logs without a build id when none is injected', () => {
        announceScript('wiki');

        expect(String(log.mock.calls[0]?.[0])).toContain('[wiki] script loaded');
        expect(String(log.mock.calls[0]?.[0])).not.toContain('build: ');
    });

    it('logs a script loaded twice only once, and each script name on its own', () => {
        announceScript('wiki');
        announceScript('wiki');
        announceScript('wiki/other');

        expect(log).toHaveBeenCalledTimes(2);
        expect(String(log.mock.calls[1]?.[0])).toContain('[wiki/other] script loaded');
    });

    it('never throws when page HTML clobbers the registry global, and still announces once', () => {
        document.body.innerHTML = '<img id="__nowoScriptBanners" name="wiki"><img id="__nowoScriptBanners" name="wiki">';
        Object.defineProperty(window, '__nowoScriptBanners', { get: () => document.getElementsByName('wiki'), set: () => { throw new TypeError('read-only'); }, configurable: true });
        try {
            expect(() => announceScript('wiki')).not.toThrow();
            expect(() => announceScript('wiki')).not.toThrow();
            expect(log).toHaveBeenCalledTimes(1);
        } finally {
            delete (window as { __nowoScriptBanners?: unknown }).__nowoScriptBanners;
            document.body.innerHTML = '';
        }
    });

    it('falls back to the module-scope set when the global swallows the assignment without throwing', () => {
        Object.defineProperty(window, '__nowoScriptBanners', { get: () => [], set: () => undefined, configurable: true });
        try {
            announceScript('wiki/swallowed');
            announceScript('wiki/swallowed');

            expect(log).toHaveBeenCalledTimes(1);
        } finally {
            delete (window as { __nowoScriptBanners?: unknown }).__nowoScriptBanners;
        }
    });

    it('does nothing without a window', () => {
        vi.stubGlobal('window', undefined);
        try {
            announceScript('wiki');
        } finally {
            vi.unstubAllGlobals();
        }

        expect(log).not.toHaveBeenCalled();
    });
});

describe('wiki entry', () => {
    beforeEach(() => {
        delete window.__nowoScriptBanners;
        vi.resetModules();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        delete window.__nowoScriptBanners;
    });

    it('announces itself once, with the injected build id', async () => {
        vi.stubGlobal('__WIKI_BUILD_TIME__', 'abc123def456');
        const log = vi.spyOn(console, 'log').mockImplementation(() => undefined);

        await import('./wiki');
        vi.resetModules();
        await import('./wiki');

        const lines = log.mock.calls.filter((call) => String(call[0]).includes('script loaded'));
        expect(lines).toHaveLength(1);
        expect(String(lines[0]?.[0])).toContain('[wiki] script loaded, build: ');
        expect(String(lines[0]?.[0])).toContain('abc123def456');
    });
});
