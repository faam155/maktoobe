import { test, expect } from '@playwright/test';

const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
];

for (const viewport of viewports) {
    for (const locale of ['en', 'ar']) {
        test(`${viewport.name}: ${locale} layout, language and navigation`, async ({ page }) => {
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
            page.on('response', (response) => { if (response.status() >= 400) errors.push(`${response.status()} ${response.url()}`); });
            await page.setViewportSize(viewport);
            await page.goto('/');
            await expect(page.locator('html')).toHaveAttribute('lang', 'en');

            if (locale === 'ar') {
                await page.getByRole('combobox', { name: 'Display language', exact: true }).selectOption('ar');
                await page.getByRole('button', { name: 'Save preference', exact: true }).click();
                await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
                await expect(page.getByRole('status')).toHaveText('تم حفظ تفضيل اللغة.');
                await page.reload();
            }

            await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            await expect(page.locator('.language-link')).toHaveAccessibleName(locale === 'ar' ? 'لغة العرض' : 'Display language');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            await expect(page.locator('#locale')).toHaveValue(locale);

            if (viewport.width < 1024) {
                const menu = page.getByRole('button', { name: locale === 'ar' ? 'فتح قائمة التنقل' : 'Open navigation', exact: true });
                await menu.click();
                const dialog = page.getByRole('dialog');
                await expect(dialog).toBeVisible();
                await page.keyboard.press('Escape');
                await expect(dialog).not.toBeVisible();
                await expect(menu).toBeFocused();
                await menu.click();
                await dialog.getByRole('link', { name: locale === 'ar' ? 'التفضيلات' : 'Preferences', exact: true }).click();
                await expect(dialog).not.toBeVisible();
                await expect(page).toHaveURL(/#preferences$/);
                await expect(page.locator('#locale')).toBeInViewport();
            } else {
                await page.locator('.desktop-sidebar').getByRole('link', { name: locale === 'ar' ? 'التفضيلات' : 'Preferences', exact: true }).click();
                await expect(page.locator('#locale')).toBeInViewport();
            }

            // Capture from the top so sticky navigation is represented in its initial position.
            await page.goto('/');
            await expect(page.locator('html')).toHaveAttribute('lang', locale);
            await page.screenshot({ path: `test-results/${viewport.name}-${locale}.png`, fullPage: true });
            expect(errors).toEqual([]);
        });
    }
}

test('server rejects invalid locale input and missing CSRF without exposing a feature', async ({ page, request }) => {
    await page.goto('/');
    await page.getByRole('combobox', { name: 'Display language', exact: true }).selectOption('ar');
    // Deliberately tamper with this test-only form to verify server-side validation.
    await page.locator('#locale').evaluate((select) => {
        const option = new Option('Invalid test locale', 'fr');
        select.add(option);
        select.value = 'fr';
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await page.getByRole('button', { name: 'Save preference', exact: true }).click();
    await expect(page.getByRole('alert')).toHaveText('Please choose English or Arabic.');
    await expect(page.locator('#locale')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');

    const updateUri = await page.locator('script[data-update-uri]').getAttribute('data-update-uri');
    expect(updateUri).toBeTruthy();
    const csrfResponse = await request.post(updateUri, { data: {}, headers: { 'Sec-Fetch-Site': 'cross-site' } });
    expect(csrfResponse.status()).toBe(419);

    for (const path of ['/admin', '/app', '/register', '/.env', '/composer.json']) {
        const response = await request.get(path);
        expect(response.status()).toBe(404);
    }
});

test('keyboard skip link and reduced motion remain usable', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('main')).toBeFocused();
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior)).toBe('auto');
});
