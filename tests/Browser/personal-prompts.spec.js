import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const fixture = (...args) => JSON.parse(execFileSync('php', ['scripts/browser-fixtures.php', ...args], { encoding: 'utf8' }));

async function signIn(page) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('member');
    await page.getByLabel('Password').fill('BrowserPassword123');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/app$/);
}

test.describe.configure({ mode: 'serial' });

for (const size of [
    { name: 'desktop', width: 1440, height: 900 },
    { name: 'laptop', width: 1280, height: 800 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'narrow-mobile', width: 360, height: 800 },
]) {
    test(`${size.name}: user manages a private prompt in English and Arabic`, async ({ page }) => {
        fixture('reset');
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page);
        await page.goto('/app/my-prompts/create');
        await page.getByLabel('Title', { exact: true }).fill('My Browser Brief');
        await page.getByLabel('Description').fill('A reusable private brief.');
        await page.getByLabel('Prompt content').fill('Create a concise private brief from these notes and preserve every factual detail.');
        await page.getByLabel('Category').selectOption({ label: 'Writing' });
        await page.getByLabel('Tags').fill('Private, Brief');
        await page.getByRole('button', { name: 'Save personal prompt' }).click();
        await expect(page.getByRole('heading', { level: 2, name: 'My Browser Brief' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.getByRole('link', { name: 'Edit prompt' }).click();
        await page.getByLabel('Title', { exact: true }).fill('My Updated Brief');
        await page.getByRole('button', { name: 'Save personal prompt' }).click();
        await expect(page.getByRole('heading', { level: 2, name: 'My Updated Brief' })).toBeVisible();
        await page.locator('#portal-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('user adds, filters and removes a favorite and sees recent use', async ({ page }) => {
    fixture('reset');
    await signIn(page);
    await page.goto('/app/prompts');
    await page.getByRole('button', { name: 'Add to favorites' }).click();
    await page.goto('/app/my-prompts?section=favorites');
    await expect(page.getByText('Browser Writing Assistant')).toBeVisible();
    await page.getByLabel('Search prompts').fill('rough notes');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.getByText('Browser Writing Assistant')).toBeVisible();
    await page.getByText('Browser Writing Assistant').click();
    await page.getByRole('button', { name: 'Copy prompt' }).click();
    await expect(page.getByRole('status')).toContainText('Prompt copied');
    await page.goto('/app/my-prompts?section=recent');
    await expect(page.getByText('Browser Writing Assistant')).toBeVisible();
    await page.goto('/app/my-prompts?section=favorites');
    await page.getByRole('button', { name: 'Remove favorite' }).click();
    await expect(page.getByText('Nothing here yet.')).toBeVisible();
});
