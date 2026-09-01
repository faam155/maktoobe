import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

async function signIn(page, login, password) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(login);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app$/);
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => fixture('reset'));

const viewports = [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
];

for (const size of viewports) {
    test(`${size.name}: user dashboard navigation is responsive in English and Arabic`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page, 'member', 'BrowserPassword123');
        await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
        await expect(page.getByText('Welcome back, Browser Member')).toBeVisible();
        await expect(page.locator('a[href$="/admin"]')).toHaveCount(0);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        if (size.width <= 1023) {
            await page.locator('.portal-menu summary').click();
            await expect(page.locator('.portal-menu nav')).toBeVisible();
            await page.locator('.portal-menu summary').click();
        } else {
            await expect(page.locator('.portal-sidebar')).toBeVisible();
        }
        await page.locator('#portal-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('heading', { level: 1, name: 'لوحة المعلومات' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('admin dashboard uses real metrics and permission-aware navigation at desktop, tablet and mobile widths', async ({ page }) => {
    fixture('reset');
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await signIn(page, 'admin', 'AdminBrowserPassword123');
    for (const size of [viewports[0], viewports[2], viewports[3]]) {
        await page.setViewportSize(size);
        await page.goto('/admin');
        await expect(page.getByRole('heading', { level: 1, name: 'Administration overview' })).toBeVisible();
        await expect(page.getByText('Total users')).toBeVisible();
        await expect(page.getByText('System Settings').first()).toBeAttached();
        await expect(page.getByText('Not available').first()).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        if (size.width <= 1023) {
            await page.locator('.admin-menu summary').click();
            await expect(page.locator('.admin-menu nav')).toBeVisible();
        }
    }
    expect(errors).toEqual([]);
});
