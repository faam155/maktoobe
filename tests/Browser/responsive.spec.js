import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

async function signIn(page) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('admin');
    await page.getByLabel('Password').fill('AdminBrowserPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app$/);
}

async function assertViewport(page, path) {
    const response = await page.goto(path);
    expect(response?.status(), path).toBeLessThan(400);
    await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), path).toBe(true);
}

test.describe.configure({ mode: 'serial' });

for (const viewport of [
    { name: 'large desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow mobile', width: 360, height: 800 },
]) {
    test(`${viewport.name}: primary portal and administration screens stay within the viewport`, async ({ page }) => {
        test.setTimeout(240_000);
        fixture('reset');
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(viewport);
        if (viewport.width <= 639) {
            await page.goto('/login');
            expect(await page.getByLabel('Email or username').evaluate(element => parseFloat(getComputedStyle(element).fontSize))).toBeGreaterThanOrEqual(16);
        }
        await signIn(page);

        for (const path of [
            '/app', '/app/prompts', '/app/prompts/browser-writing-assistant', '/app/my-prompts',
            '/app/assistant', '/app/events', '/app/calendar', '/app/notifications', '/account/security',
            '/admin', '/admin/users', '/admin/roles', '/admin/permissions', '/admin/prompt-categories',
            '/admin/prompts', '/admin/events', '/admin/calendar', '/admin/brand-guidelines',
            '/admin/analytics', '/admin/audit-logs',
        ]) {
            await assertViewport(page, path);
        }

        if (viewport.width < 1024) {
            await page.goto('/admin/users');
            const menu = page.locator('[data-responsive-menu]');
            await expect(menu).toBeVisible();
            await menu.locator('summary').click();
            expect(await menu.evaluate(element => element.hasAttribute('open'))).toBe(true);
            const bounds = await menu.locator(':scope > nav').boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(viewport.width);
            await page.keyboard.press('Escape');
            expect(await menu.evaluate(element => element.hasAttribute('open'))).toBe(false);
        }

        if (viewport.width <= 639) {
            await page.goto('/app/calendar');
            await expect(page.locator('.calendar-grid')).toBeHidden();
            await expect(page.locator('.calendar-agenda')).toBeVisible();
        }

        await page.goto('/admin/users');
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await assertViewport(page, '/admin/users');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(errors).toEqual([]);
    });
}

test('mobile overlays, tables and AI composer remain usable', async ({ page }) => {
    test.setTimeout(120_000);
    fixture('reset');
    await page.setViewportSize({ width: 390, height: 844 });
    await signIn(page);

    await page.goto('/admin/users');
    const tableCell = page.locator('.admin-table tbody td').first();
    const cellStyle = await tableCell.evaluate(element => getComputedStyle(element));
    expect(cellStyle.display).toBe('grid');

    await page.goto('/app/notifications');
    await page.locator('.notification-panel summary').click();
    const notificationBounds = await page.locator('.notification-dropdown').boundingBox();
    expect(notificationBounds.x).toBeGreaterThanOrEqual(0);
    expect(notificationBounds.x + notificationBounds.width).toBeLessThanOrEqual(390);

    await page.goto('/app/assistant/new');
    const composer = page.locator('.ai-compose');
    await expect(composer).toBeVisible();
    await composer.scrollIntoViewIfNeeded();
    const composerBounds = await composer.boundingBox();
    expect(composerBounds.y + composerBounds.height).toBeLessThanOrEqual(845);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
