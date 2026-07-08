/**
 * Wiki sidebar toggle for narrow viewports.
 */
export function initWikiSidebars(root: ParentNode = document): void {
    root.querySelectorAll('[data-wiki-sidebar]').forEach((sidebar) => {
        sidebar.setAttribute('role', 'navigation');
    });
}

/** Focus question field when the AI ask modal opens. */
export function initWikiAiModal(root: ParentNode = document): void {
    const modal = root.querySelector('#wikiAiAskModal');
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    modal.addEventListener('shown.bs.modal', () => {
        const input = modal.querySelector<HTMLInputElement>('#wiki-ai-modal-question');
        input?.focus();
    });
}

document.documentElement.classList.add('wiki-ready');
initWikiSidebars();
initWikiAiModal();
