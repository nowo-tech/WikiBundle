import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['src/Resources/assets/src/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'text-summary', 'html'],
            reportsDirectory: 'coverage-ts',
            include: ['src/Resources/assets/src/wiki.ts', 'src/Resources/assets/src/wiki-comments.ts', 'src/Resources/assets/src/wiki-comments-moderation.ts', 'src/Resources/assets/src/wiki-code-blocks.ts', 'src/Resources/assets/src/wiki-layout.ts'],
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
