import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => fixture('reset'));

for (const size of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
]) {
    test(`${size.name}: authentication forms remain readable in English and Arabic`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        for (const path of ['/login', '/register', '/forgot-password', '/otp']) {
            await page.goto(path);
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        }
        await page.locator('#auth-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await page.goto('/register');
        await expect(page.getByRole('heading', { level: 1, name: 'إنشاء حساب' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('password login, validation, logout, reset, registration, verification and OTP work end to end', async ({ page }) => {
    test.setTimeout(120_000);
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('member');
    await page.getByLabel('Password').fill('wrong-password');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('credentials');

    await page.getByLabel('Password').fill('BrowserPassword123');
    await page.getByLabel('Remember me').check();
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app$/);
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/\/$/);

    await page.goto('/forgot-password');
    await page.getByLabel('Email address').fill('member@example.test');
    await page.getByRole('button', { name: 'Send reset link' }).click();
    await expect(page.getByRole('status')).toBeVisible();
    const reset = fixture('inbox', 'member@example.test').at(-1);
    await page.goto(reset.url);
    await page.getByLabel('Password', { exact: true }).fill('BrowserPassword456');
    await page.getByLabel('Confirm password').fill('BrowserPassword456');
    await page.getByRole('button', { name: 'Choose a new password' }).click();
    await expect(page).toHaveURL(/\/login$/);

    const email = 'new.member@example.test';
    await page.goto('/register');
    await page.getByLabel('Full name').fill('New Browser Member');
    await page.getByLabel('Username').fill('new.member');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel(/Mobile number/).fill('+968 9222 2222');
    await page.getByLabel('Password', { exact: true }).fill('BrowserPassword123');
    await page.getByLabel('Confirm password').fill('BrowserPassword123');
    await page.getByRole('button', { name: 'Create account' }).click();
    await expect(page).toHaveURL(/\/account\/pending$/);
    fixture('activate', email);
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('new.member');
    await page.getByLabel('Password').fill('BrowserPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/email\/verify$/);
    const verify = fixture('inbox', email).find(message => message.url.includes('/email/verify/'));
    await page.goto(verify.url);
    await expect(page).toHaveURL(/\/app$/);
    await page.getByRole('button', { name: 'Sign out' }).click();

    await page.goto('/otp');
    await page.getByLabel('Mobile number').fill('+968 9111 1111');
    await page.getByRole('button', { name: 'Send verification code' }).click();
    await expect(page).toHaveURL(/\/otp\/verify$/);
    const otp = fixture('inbox', '+96891111111').at(-1);
    await page.getByLabel('Verification code').fill(otp.code);
    await page.getByRole('button', { name: 'Verify code' }).click();
    await expect(page).toHaveURL(/\/app$/);
});

test('disabled accounts receive the same login failure and cannot use an existing session', async ({ page }) => {
    fixture('reset');
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('member');
    await page.getByLabel('Password').fill('BrowserPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app$/);
    fixture('disable', 'member@example.test');
    await page.reload();
    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('Email or username').fill('member');
    await page.getByLabel('Password').fill('BrowserPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('credentials');
});
