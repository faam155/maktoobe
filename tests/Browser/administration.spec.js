import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

async function signIn(page, login = 'admin', password = 'AdminBrowserPassword123') {
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
    test(`${size.name}: admin users, roles and permissions are responsive in both directions`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page);
        for (const path of ['/admin', '/admin/users', '/admin/roles', '/admin/permissions']) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await page.goto('/admin/users');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('heading', { level: 1, name: 'المستخدمون' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('super administrator manages users, roles and protected access through real forms', async ({ page }) => {
    test.setTimeout(90_000);
    fixture('reset');
    await signIn(page);
    await page.goto('/admin/users/create');
    await page.getByLabel('Full name').fill('Managed Browser User');
    await page.getByLabel('Username').fill('managed.browser');
    await page.getByLabel('Email address').fill('managed.browser@example.test');
    await page.getByLabel(/Mobile number/).fill('+968 9444 4444');
    await page.getByLabel('Password', { exact: true }).fill('ManagedBrowserPassword123');
    await page.getByLabel('Confirm password').fill('ManagedBrowserPassword123');
    await page.getByLabel('Account status').selectOption('active');
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page.getByRole('status')).toContainText('created');

    await page.getByLabel('Event Manager').check();
    await page.getByRole('button', { name: 'Save role assignment' }).click();
    await expect(page.getByRole('status')).toContainText('role assignment');
    await page.getByLabel('Reason for this access change').fill('Browser verification disable');
    await page.getByRole('button', { name: 'Disable account' }).click();
    await expect(page.getByRole('status')).toContainText('status');

    await page.goto('/admin/users?search=managed.browser&status=disabled');
    await expect(page.getByText('Managed Browser User')).toBeVisible();

    await page.goto('/admin/roles/create');
    await page.getByLabel('Role name').fill('Browser Coordinator');
    await page.getByLabel('Manage events').check();
    await page.getByLabel('View reports').check();
    await page.getByRole('button', { name: 'Create role' }).click();
    await expect(page.getByRole('status')).toContainText('created');
    await expect(page.getByText('Manage events')).toBeVisible();

    await page.goto('/admin/roles');
    await page.getByRole('row').filter({ hasText: 'Super Administrator' }).getByRole('link', { name: 'View' }).click();
    await expect(page.getByText(/protected/i)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Edit role' })).toHaveCount(0);
});

test('standard users cannot enter administration by direct request', async ({ page }) => {
    fixture('reset');
    await signIn(page, 'member', 'BrowserPassword123');
    const response = await page.goto('/admin');
    expect(response.status()).toBe(403);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('403');
});
