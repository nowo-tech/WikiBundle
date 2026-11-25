import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        // wiki-css-tokens.test.ts reads wiki.css as text (?raw); Vitest would otherwise stub every CSS import.
        css: { include: [/wiki\.css/] },
        include: ['src/Resources/assets/src/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'text-summary', 'html'],
            reportsDirectory: 'coverage-ts',
            include: ['src/Resources/assets/src/wiki.ts', 'src/Resources/assets/src/logger.ts', 'src/Resources/assets/src/script-banner.ts', 'src/Resources/assets/src/wiki-comments.ts', 'src/Resources/assets/src/wiki-comments-moderation.ts', 'src/Resources/assets/src/wiki-comments-list.ts', 'src/Resources/assets/src/wiki-layout.ts', 'src/Resources/assets/src/wiki-new-page.ts', 'src/Resources/assets/src/wiki-tree-menu.ts', 'src/Resources/assets/src/wiki-plain-tree.ts', 'src/Resources/assets/src/wiki-section-links.ts'],
            exclude: ['**/*.test.ts', '**/node_modules/**'],
            // REQ-TEST-003: fail the run (non-zero exit) when TS coverage drops below 100%.
            thresholds: {
                lines: 100,
                statements: 100,
                functions: 100,
                branches: 100,
            },
        },
    },
});
