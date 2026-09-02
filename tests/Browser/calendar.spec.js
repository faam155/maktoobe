import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

test.describe.configure({mode:'serial'});
for (const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
    test(`calendar ${size.width}px: views, filters, audience and RTL`, async ({page}, testInfo) => {
        for (const action of ['reset','calendar']) execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000});
        const errors=[];
        page.on('pageerror',e=>errors.push(e.message));
        page.on('console',m=>{if(m.type()==='error') errors.push(m.text());});
        await page.setViewportSize(size);
        await page.goto('/login');
        await page.getByLabel('Email or username').fill('member');
        await page.getByLabel('Password').fill('BrowserPassword123');
        await page.getByRole('button',{name:'Sign in',exact:true}).click();
        await page.goto('/app/calendar?date=2027-01-15');
        await expect(page.locator('body')).not.toContainText('Calendar Private Secret');
        const cards = page.locator(size.width<768?'.calendar-agenda-event':'.calendar-event');
        await expect(cards.first()).toContainText('Calendar Public Forum');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('calendar-en.png'),fullPage:true});
        await page.getByRole('link',{name:'Week',exact:true}).click();
        await expect(page.locator('.calendar-cell')).toHaveCount(7);
        await page.getByRole('link',{name:'Agenda',exact:true}).click();
        await expect(page.locator('.calendar-agenda')).toBeVisible();
        await page.locator('select[name=status]').selectOption('cancelled');
        await page.getByRole('button',{name:'Apply filters'}).click();
        await expect(page.locator('.calendar-empty')).toBeVisible();
        await page.getByRole('link',{name:'Clear filters'}).click();
        await page.locator('input[name=from]').fill('2027-02-01');
        await page.locator('input[name=to]').fill('2027-02-02');
        await page.getByRole('button',{name:'Apply filters'}).click();
        await expect(page.locator('.calendar-empty')).toBeVisible();
        await page.goto('/app/calendar?date=2027-01-15');
        await page.locator('select[name=locale]').selectOption('ar');
        await page.getByRole('button',{name:'Change language'}).click();
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('calendar-ar.png'),fullPage:true});
        await cards.first().click();
        await expect(page.getByRole('navigation',{name:'مساحة عمل الفعالية'})).toBeVisible();
        expect(errors).toEqual([]);
    });
}

test('administrator calendar includes private events and validates date ranges', async ({page}) => {
    for (const action of ['reset','calendar']) execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000});
    await page.goto('/login');
    await page.getByLabel('Email or username').fill('admin');
    await page.getByLabel('Password').fill('AdminBrowserPassword123');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await page.goto('/admin/calendar?date=2027-01-15');
    await page.locator('select[name=visibility]').selectOption('private');
    await page.getByRole('button',{name:'Apply filters'}).click();
    await expect(page.locator('.calendar-event').first()).toContainText('Calendar Private Secret');
    await expect(page.locator('body')).not.toContainText('Calendar Public Forum');
    await page.locator('input[name=from]').fill('2027-01-01');
    await page.locator('input[name=to]').fill('2027-12-31');
    await page.getByRole('button',{name:'Apply filters'}).click();
    await expect(page.getByRole('alert')).toContainText('at most 62 days');
});
