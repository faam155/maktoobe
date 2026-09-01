import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

async function signIn(page, login = 'member', password = 'BrowserPassword123') {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(login);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app$/);
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => fixture('reset'));

for (const size of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
]) {
    test(`${size.name}: prompt library and detail are responsive in English and Arabic`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page);
        await page.goto('/app/prompts');
        await expect(page.getByRole('heading', { level: 2, name: 'Prompt Library' })).toBeVisible();
        await expect(page.getByText('Browser Writing Assistant')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.getByText('Browser Writing Assistant').click();
        await expect(page.getByRole('button', { name: 'Copy prompt' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.locator('#portal-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('button', { name: 'نسخ الموجّه' })).toBeVisible();
        expect(errors).toEqual([]);
    });
}

test('administrator creates, publishes, duplicates, archives and previews a prompt', async ({ page }) => {
    fixture('reset');
    await signIn(page, 'admin', 'AdminBrowserPassword123');
    await page.goto('/admin/prompts/create');
    await page.getByLabel('Title').fill('Meeting Summary');
    await page.getByLabel('Description').fill('Summarize meeting notes and decisions.');
    await page.getByLabel('Prompt content').fill('Summarize these meeting notes into decisions, owners, deadlines, and open questions.');
    await page.getByLabel('Category').selectOption({ label: 'Writing' });
    await page.getByLabel('Tags').fill('Meetings, Reports');
    await page.getByLabel('Visibility').selectOption('all_users');
    await page.getByRole('button', { name: 'Save as draft' }).click();
    await expect(page.getByRole('status')).toContainText('created');
    await page.getByRole('button', { name: 'Publish' }).click();
    await expect(page.getByRole('status')).toContainText('status');
    await page.getByRole('button', { name: 'Duplicate' }).click();
    await expect(page.getByRole('status')).toContainText('private draft');
    await page.getByRole('button', { name: 'Archive' }).click();
    await expect(page.getByRole('status')).toContainText('status');
    await page.getByRole('link', { name: 'Preview' }).click();
    await expect(page.getByText('Administrative preview')).toBeVisible();
});

test('user searches, filters and copies an authorized prompt', async ({ page }) => {
    fixture('reset');
    await signIn(page);
    await page.goto('/app/prompts');
    await page.getByLabel('Search prompts').fill('rough notes');
    await page.getByLabel('Category').selectOption({ label: 'Writing' });
    await page.getByLabel('Tags').selectOption({ label: 'Writing' });
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.getByText('Browser Writing Assistant')).toBeVisible();
    await page.getByText('Browser Writing Assistant').click();
    await page.getByRole('button', { name: 'Copy prompt' }).click();
    await expect(page.getByRole('status')).toContainText('Prompt copied');
});
