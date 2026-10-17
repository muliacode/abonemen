// SPIKE (#16): switching language needs no full reload, and sticks.
import { expect, test } from '@playwright/test';

test.use({ locale: 'en-GB' });

test('switches language without a full page load', async ({ page }) => {
    await page.goto('/');
    await expect(
        page.getByText('A self-hostable, privacy-first subscription tracker.'),
    ).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    // A full reload would wipe this.
    await page.evaluate(() => {
        (window as unknown as { marker: string }).marker = 'still here';
    });

    await page.getByLabel('Language').selectOption('fr');

    await expect(
        page.getByText(
            'Un suivi d’abonnements auto-hébergeable qui protège votre vie privée.',
        ),
    ).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.getByLabel('Langue')).toHaveValue('fr');
    expect(
        await page.evaluate(
            () => (window as unknown as { marker?: string }).marker,
        ),
    ).toBe('still here');

    // French treats 0 as singular.
    await page.getByLabel('Abonnements').fill('0');
    await expect(page.getByTestId('empty-state')).toHaveText(
        'Aucun abonnement pour l’instant',
    );
    await expect(page.getByTestId('renewals')).toHaveText(
        '0 abonnement est renouvelé aujourd’hui',
    );
    await page.getByLabel('Abonnements').fill('2');
    await expect(page.getByTestId('renewals')).toHaveText(
        '2 abonnements sont renouvelés aujourd’hui',
    );

    // The choice is kept in the session.
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('lang', 'fr');
    await expect(page.getByLabel('Langue')).toHaveValue('fr');
});

test('follows the browser language on a first visit', async ({ browser }) => {
    const context = await browser.newContext({ locale: 'id-ID' });
    const page = await context.newPage();

    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('lang', 'id');
    await page.getByLabel('Langganan').fill('3');
    await expect(page.getByTestId('renewals')).toHaveText(
        '3 langganan diperpanjang hari ini',
    );

    await context.close();
});
