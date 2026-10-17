import { expect } from '@playwright/test';
import {
    expectNoAxeViolations,
    expectNoHorizontalScroll,
    forEveryPage,
} from './support/accessibility';

// Every page, in every theme, at 320 px and on a desktop (A11Y-06 to A11Y-08).
forEveryPage('accessible', async ({ page, viewport }) => {
    await expectNoAxeViolations(page);

    if (viewport.width === 320) {
        await expectNoHorizontalScroll(page);
    }

    await expect(page.locator('h1')).toHaveCount(1);
});
