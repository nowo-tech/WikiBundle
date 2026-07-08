import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['src/Resources/assets/src/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'text-summary', 'html'],
            reportsDirectory: 'coverage-ts',
            include: ['src/Resources/assets/src/wiki.ts'],
            exclude: ['**/*.test.ts', '**/node_modules/**'],
        },
    },
});
