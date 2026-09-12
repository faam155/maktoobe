import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const reset = () => execFileSync('php', ['scripts/browser-fixtures.php', 'reset'], { timeout: 60000 });
const routes = [
    '/app', '/app/prompts', '/app/my-prompts', '/app/assistant', '/app/events', '/app/calendar', '/app/notifications', '/account/security',
    '/admin', '/admin/users', '/admin/roles', '/admin/permissions', '/admin/prompt-categories', '/admin/prompts', '/admin/events', '/admin/calendar', '/admin/brand-guidelines', '/admin/analytics', '/admin/audit-logs',
];

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('admin');
    await page.getByLabel('Password').fill('AdminBrowserPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
}

async function auditRoutes(page, locale, direction) {
    for (const path of routes) {
        await page.goto(path);
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await expect(page.locator('html')).toHaveAttribute('dir', direction);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        // Permission identifiers such as linkedin.dashboard.view are intentionally displayed.
        await expect(page.locator('body')).not.toContainText(/(?<!\.)(?:foundation|validation|dashboard|admin|prompts|events|calendar|reporting)\.[a-z_]+/);
    }
}

test.describe.configure({ mode: 'serial' });

for (const size of [{ width: 1440, height: 900 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
    test(`${size.width}px: primary modules remain complete in English LTR and Arabic RTL`, async ({ page, context }, testInfo) => {
        reset();
        await page.setViewportSize(size);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });

        await login(page);
        await auditRoutes(page, 'en', 'ltr');
        await page.goto('/admin');
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await auditRoutes(page, 'ar', 'rtl');
        await page.goto('/app');
        await page.screenshot({ path: testInfo.outputPath('portal-ar.png'), fullPage: true });

        await context.clearCookies();
        await login(page);
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(errors).toEqual([]);
    });
}
