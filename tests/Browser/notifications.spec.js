import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const reset = () => { for (const action of ['reset','calendar','notifications']) execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000}); };
async function login(page, admin=false) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(admin?'admin':'member');
    await page.getByLabel('Password').fill(admin?'AdminBrowserPassword123':'BrowserPassword123');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
}
test.describe.configure({mode:'serial'});
for (const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
    test(size.width+'px: notification panel and inbox work in English and Arabic',async({page},testInfo)=>{
        reset(); await page.setViewportSize(size);
        const errors=[]; page.on('pageerror',e=>errors.push(e.message)); page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
        await login(page);
        await page.locator('.notification-panel summary').click();
        await expect(page.locator('.notification-dropdown')).toBeVisible();
        await expect(page.locator('.notification-dropdown')).not.toContainText('Calendar Private Secret');
        await page.locator('.notification-panel summary').click();
        await expect(page.locator('.notification-dropdown')).not.toBeVisible();
        await page.locator('.notification-panel summary').press('Enter');
        await expect(page.locator('.notification-dropdown')).toBeVisible();
        await page.locator('.notification-dropdown a').click();
        await expect(page.locator('.notification-item')).toHaveCount(2);
        await expect(page.locator('.notification-count')).toHaveText('2');
        await page.locator('.notification-item').first().getByRole('button',{name:'Mark as read',exact:true}).click();
        await expect(page.locator('.notification-count')).toHaveText('1');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('notifications-en.png'),fullPage:true});
        await page.locator('#portal-locale').selectOption('ar');
        await page.getByRole('button',{name:'Change language'}).click();
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        await expect(page.locator('.notification-list')).toContainText('صيانة مجدولة');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('notifications-ar.png'),fullPage:true});
        await page.locator('.notification-heading button').click();
        await expect(page.locator('.notification-count')).toHaveText('0');
        await page.locator('.notification-item').first().locator('form').last().locator('button').click();
        await expect(page.locator('.notification-item')).toHaveCount(1);
        expect(errors).toEqual([]);
    });
}
test('administrative system notices and event links open authorized workspace',async({page})=>{
    reset(); await login(page,true); await page.goto('/app/notifications');
    await page.locator('.notification-system summary').click();
    const form=page.locator('.notification-system form');
    await form.locator('[name=title_en]').fill('Service notice');
    await form.locator('[name=body_en]').fill('Please review your event calendar.');
    await form.locator('[name=title_ar]').fill('إشعار الخدمة');
    await form.locator('[name=body_ar]').fill('يرجى مراجعة تقويم الفعاليات.');
    await form.locator('[name=confirm]').check();
    await form.locator('[name=target_user_id]').fill('999999999');
    await form.locator('button').click();
    await expect(page.locator('.admin-errors')).toBeVisible();
    await page.locator('.notification-system summary').click();
    await form.locator('[name=target_user_id]').fill('');
    await form.locator('[name=confirm]').check();
    await form.locator('button').click();
    await expect(page.locator('.notification-list')).toContainText('Service notice');
    await page.locator('.notification-item').filter({hasText:'Calendar Public Forum'}).getByRole('button',{name:'Open',exact:true}).click();
    await expect(page).toHaveURL(/\/app\/events\//);
    await expect(page.locator('#portal-main').getByRole('heading',{name:'Calendar Public Forum',exact:true})).toBeVisible();
});
