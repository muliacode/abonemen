// ESLint runs only the Vue accessibility rules. General linting and
// formatting stay with Vite+ (oxlint and oxfmt, `npm run check`).
import pluginVueA11y from 'eslint-plugin-vuejs-accessibility';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';

export default [
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'resources/js/actions/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
    },
    ...pluginVueA11y.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
            globals: globals.browser,
        },
        linterOptions: {
            reportUnusedDisableDirectives: 'error',
        },
        rules: {
            // A label needs a control by nesting or by for/id, not both.
            'vuejs-accessibility/label-has-for': [
                'error',
                { required: { some: ['nesting', 'id'] } },
            ],
        },
    },
];
