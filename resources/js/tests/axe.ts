import { configureAxe } from 'vitest-axe';

// jsdom does no layout or painting, so contrast is checked in the browser
// by the Playwright suite instead.
export const axe = configureAxe({
    rules: {
        'color-contrast': { enabled: false },
    },
});
