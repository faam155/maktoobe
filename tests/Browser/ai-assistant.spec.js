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
    test(`${size.name}: assistant conversation is responsive in English and Arabic`, async ({ page }) => {
        fixture('reset');
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        await page.setViewportSize(size);
        await signIn(page);
        await page.goto('/app/assistant/new');
        await page.getByLabel('Message').fill('Draft a concise project update for leadership.');
        await page.getByRole('button', { name: 'Send message' }).click();
        await expect(page.getByText('This is a local verification response.')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await page.locator('#portal-locale').selectOption('ar');
        await page.getByRole('button', { name: 'Change language' }).click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByRole('button', { name: 'إرسال الرسالة' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}

test('authorized library prompt launches into the assistant with additional context', async ({ page }) => {
    fixture('reset');
    await signIn(page);
    await page.goto('/app/prompts/browser-writing-assistant');
    await page.getByRole('link', { name: 'Use with AI' }).click();
    await expect(page.getByText('Selected prompt: Browser Writing Assistant')).toBeVisible();
    await page.getByLabel('Additional context').fill('Use a calm tone and keep the result under 100 words.');
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByText('This is a local verification response.')).toBeVisible();
    await page.goto('/app/my-prompts?section=recent');
    await expect(page.getByText('Browser Writing Assistant')).toBeVisible();
});

test('conversation history can be searched, renamed, archived and restored', async ({ page }) => {
    fixture('reset');
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.setViewportSize({ width: 390, height: 844 });
    await signIn(page);
    await page.goto('/app/assistant/new');
    await page.getByLabel('Message').fill('Create a launch readiness checklist for our internal team.');
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByText('This is a local verification response.')).toBeVisible();
    await page.getByLabel('Conversation title').fill('Launch readiness notes');
    await page.getByRole('button', { name: 'Rename', exact: true }).click();
    await page.getByRole('button', { name: 'Archive', exact: true }).click();
    await expect(page).toHaveURL(/status=archived/);
    await expect(page.getByText('Launch readiness notes')).toBeVisible();
    await page.getByRole('link', { name: /Launch readiness notes/ }).click();
    await page.getByRole('button', { name: 'Restore', exact: true }).click();
    await page.getByLabel('Search conversations').fill('Launch readiness');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.getByText('Launch readiness notes')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(errors).toEqual([]);
});
