import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { initWikiAiModal, initWikiSidebars } from './wiki';

describe('wiki sidebar', () => {
    beforeEach(() => {
        document.body.innerHTML = '<aside data-wiki-sidebar></aside><aside data-wiki-sidebar></aside>';
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('sets navigation role on wiki sidebars', () => {
        initWikiSidebars(document);

        document.querySelectorAll('[data-wiki-sidebar]').forEach((sidebar) => {
            expect(sidebar.getAttribute('role')).toBe('navigation');
        });
    });
});

describe('wiki ai modal', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="wikiAiAskModal">
                <input id="wiki-ai-modal-question" type="search" />
            </div>
        `;
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('focuses the question field when modal opens', () => {
        const modal = document.getElementById('wikiAiAskModal');
        const input = document.getElementById('wiki-ai-modal-question') as HTMLInputElement;
        const focus = vi.spyOn(input, 'focus');

        initWikiAiModal(document);
        modal?.dispatchEvent(new Event('shown.bs.modal'));

        expect(focus).toHaveBeenCalled();
    });
});
