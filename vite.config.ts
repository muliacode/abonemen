import { fileURLToPath } from 'node:url';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

// Vitest only needs Vue. The Laravel plugin refuses to start a dev server in
// CI, and Wayfinder would run artisan for every test run.
const vitest = process.env.VITEST !== undefined;

export default defineConfig({
    plugins: lazyPlugins(() => [
        ...(vitest
            ? []
            : [
                  laravel({
                      input: ['resources/css/app.css', 'resources/js/app.ts'],
                      refresh: true,
                  }),
                  inertia(),
                  wayfinder({
                      formVariants: true,
                  }),
              ]),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ]),
    // The Laravel plugin sets this alias for the app; tests need it too.
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.ts'],
        setupFiles: ['resources/js/tests/setup.ts'],
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'docs/**',
            '*.md',
            'composer.json',
            'resources/views/mail/*',
        ],
    },
});
