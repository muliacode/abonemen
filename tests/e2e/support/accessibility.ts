import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { pageRoutes, type AppRoute } from './routes';
import {
    applyTheme,
    forcedColorsApplied,
    themes,
    viewports,
    type Theme,
} from './themes';

type Viewport = (typeof viewports)[number];

/** The WCAG 2.2 A and AA rules, which is what Abonemen conforms to. */
const wcagTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/**
 * Runs axe on the current page and fails with a readable list of violations.
 */
export async function expectNoAxeViolations(page: Page): Promise<void> {
    const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();

    const summary = results.violations.map((violation) => ({
        rule: violation.id,
        impact: violation.impact,
        help: violation.help,
        targets: violation.nodes.map((node) => node.target.join(' ')),
    }));

    expect(summary, 'axe violations').toEqual([]);
}

/**
 * Fails when the page scrolls sideways (WCAG 1.4.10 Reflow).
 */
export async function expectNoHorizontalScroll(page: Page): Promise<void> {
    const overflow = await page.evaluate(
        () =>
            document.documentElement.scrollWidth -
            document.documentElement.clientWidth,
    );

    expect(overflow, 'horizontal overflow in CSS pixels').toBeLessThanOrEqual(
        0,
    );
}

export interface PageVisit {
    page: Page;
    route: AppRoute;
    theme: Theme;
    viewport: Viewport;
}

/**
 * Declares one test per page route, theme and viewport. The callback runs
 * after the page has loaded in that theme and viewport.
 */
export function forEveryPage(
    title: string,
    check: (visit: PageVisit) => Promise<void>,
): void {
    for (const route of pageRoutes()) {
        for (const theme of themes) {
            for (const viewport of viewports) {
                test(`${title}: ${route.name} (${route.path}), ${theme.name}, ${viewport.name}`, async ({
                    page,
                }) => {
                    await page.setViewportSize({
                        width: viewport.width,
                        height: viewport.height,
                    });
                    await applyTheme(page, theme);

                    const response = await page.goto(route.path);
                    test.skip(
                        !(response?.headers()['content-type'] ?? '').includes(
                            'text/html',
                        ),
                        'Not a page: the route does not return HTML.',
                    );
                    expect(response?.status(), 'HTTP status').toBeLessThan(400);
                    await page.waitForLoadState('networkidle');

                    if (theme.forcedColors === 'active') {
                        test.skip(
                            !(await forcedColorsApplied(page)),
                            'This browser does not emulate forced colours.',
                        );
                    }

                    await check({ page, route, theme, viewport });
                });
            }
        }
    }
}
