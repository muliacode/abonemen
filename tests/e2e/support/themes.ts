import type { Page } from '@playwright/test';

/**
 * A way of looking at the app that every page must pass in (A11Y-06, A11Y-08).
 */
export interface Theme {
    name: string;
    /** Value of `data-theme` on `<html>`, as in docs/design/design-system/tokens.css. */
    dataTheme: 'light' | 'dark' | 'contrast';
    colorScheme: 'light' | 'dark';
    contrast: 'no-preference' | 'more';
    forcedColors: 'none' | 'active';
}

export const themes: Theme[] = [
    {
        name: 'light',
        dataTheme: 'light',
        colorScheme: 'light',
        contrast: 'no-preference',
        forcedColors: 'none',
    },
    {
        name: 'dark',
        dataTheme: 'dark',
        colorScheme: 'dark',
        contrast: 'no-preference',
        forcedColors: 'none',
    },
    {
        name: 'high contrast',
        dataTheme: 'contrast',
        colorScheme: 'light',
        contrast: 'more',
        forcedColors: 'none',
    },
    {
        // Windows contrast themes are mostly dark, such as "Night sky".
        name: 'forced colours',
        dataTheme: 'light',
        colorScheme: 'dark',
        contrast: 'no-preference',
        forcedColors: 'active',
    },
];

/**
 * Viewports every page is checked at. 320 CSS pixels is the reflow width of
 * WCAG 1.4.10, the same as 1280 pixels at 400% zoom (A11Y-07).
 */
export const viewports = [
    { name: '320 px', width: 320, height: 640 },
    { name: 'desktop', width: 1280, height: 800 },
] as const;

/**
 * Emulates the theme's media features and sets `data-theme` before any page
 * script runs. Call it before `page.goto`.
 */
export async function applyTheme(page: Page, theme: Theme): Promise<void> {
    await page.emulateMedia({
        colorScheme: theme.colorScheme,
        contrast: theme.contrast,
        forcedColors: theme.forcedColors,
        reducedMotion: 'reduce',
    });

    await page.addInitScript((dataTheme) => {
        document.documentElement.dataset.theme = dataTheme;
    }, theme.dataTheme);
}

/**
 * Whether the browser really applies the emulated forced colours. Not every
 * engine supports the emulation; the caller skips the check when it does not.
 */
export async function forcedColorsApplied(page: Page): Promise<boolean> {
    return page.evaluate(
        () => window.matchMedia('(forced-colors: active)').matches,
    );
}
