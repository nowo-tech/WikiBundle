/**
 * Wiki page tree: brings the current page (`aria-current="page"`) into view inside each scrollable tree
 * (`[data-wiki-tree]`), so long trees open at the page being read. No-op without a current page.
 *
 * @param root - Document subtree to scan for page trees.
 */
export function initWikiTrees(root: ParentNode = document): void {
    root.querySelectorAll('[data-wiki-tree]').forEach((tree) => {
        const current = tree.querySelector('[aria-current="page"]');
        if (current instanceof HTMLElement && typeof current.scrollIntoView === 'function') {
            current.scrollIntoView({ block: 'nearest' });
        }
    });
}

document.documentElement.classList.add('wiki-ready');
initWikiTrees();
