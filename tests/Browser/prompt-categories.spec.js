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

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => fixture('reset'));

for (const size of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
]) {
    test(`${size.name}: category administration is responsive in English and Arabic`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page);
        await page.goto('/admin/prompt-categories');
        await expect(page.getByRole('heading', { level: 1, name: 'Prompt Categories' })).toBeVisible();
        await expect(page.getByText('Writing', { exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('heading', { level: 1, name: 'فئات الموجّهات' })).toBeVisible();
        await expect(page.getByText('الكتابة', { exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('administrator creates, validates, edits, deactivates and reorders a bilingual category', async ({ page }) => {
    fixture('reset');
    await signIn(page);
    await page.goto('/admin/prompt-categories/create');
    await page.getByLabel('English name').fill('Customer Care');
    await page.getByLabel('Arabic name').fill('خدمة العملاء');
    await page.getByLabel('English description').fill('Customer communication prompts.');
    await page.getByLabel('Arabic description').fill('موجّهات التواصل مع العملاء.');
    await page.getByLabel('Icon identifier').fill('headphones');
    await page.getByRole('button', { name: 'Create category' }).click();
    await expect(page.getByRole('status')).toContainText('created');
    await expect(page.getByLabel('Slug')).toHaveValue('customer-care');
    await page.getByLabel('English name').fill('Customer Experience');
    await page.getByRole('button', { name: 'Save category' }).click();
    await expect(page.getByRole('status')).toContainText('updated');
    await page.goto('/admin/prompt-categories?search=Customer%20Experience');
    await expect(page.getByText('Customer Experience', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Deactivate' }).click();
    await expect(page.getByRole('status')).toContainText('status');
    await expect(page.getByRole('table').getByText('Inactive', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: /Move Customer Experience up/ }).click();
    await expect(page.getByRole('status')).toContainText('order');
});
