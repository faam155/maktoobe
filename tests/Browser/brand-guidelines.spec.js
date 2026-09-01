import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

async function signIn(page, username = 'admin', password = 'AdminBrowserPassword123') {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(username);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
}

test.describe.configure({ mode: 'serial' });

for (const size of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
]) {
    test(`${size.name}: brand guideline management is responsive in English and Arabic`, async ({ page }) => {
        fixture('reset');
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page);
        await page.goto('/admin/brand-guidelines');
        await expect(page.getByRole('heading', { name: 'Brand Guidelines' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('heading', { name: 'إرشادات الهوية' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('administrator uploads, versions, activates and uses a text guideline with AI', async ({ page }) => {
    fixture('reset');
    await signIn(page);
    await page.goto('/admin/brand-guidelines/create');
    await page.getByLabel('Title').fill('Browser Brand Voice');
    await page.getByLabel('Version').fill('1.0');
    await page.getByLabel('Description').fill('Browser verification guideline.');
    await page.getByLabel('Document file').setInputFiles({ name: 'browser-brand.txt', mimeType: 'text/plain', buffer: Buffer.from('Use a calm, direct and inclusive voice.') });
    await page.getByRole('button', { name: 'Upload document' }).click();
    await expect(page.getByText('1.0', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Activate' }).click();
    await expect(page.getByText('Active', { exact: true })).toBeVisible();
    await page.getByLabel('Version').fill('2.0');
    await page.getByLabel('Document file').setInputFiles({ name: 'browser-brand-v2.txt', mimeType: 'text/plain', buffer: Buffer.from('Use a warm, concise and accessible voice.') });
    await page.getByRole('button', { name: 'Upload document' }).click();
    await expect(page.getByText('2.0', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Sign out' }).click();
    await signIn(page, 'member', 'BrowserPassword123');
    await page.goto('/app/assistant/new');
    await page.getByLabel('Message').fill('Write a short welcome message.');
    await page.getByLabel('Use Brand Guidelines').check();
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByText('This is a local verification response.')).toBeVisible();
});
