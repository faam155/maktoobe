import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const reset = () => { for (const action of ['reset','calendar']) execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000}); };
async function login(page, member=false) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(member?'member':'admin');
    await page.getByLabel('Password').fill(member?'BrowserPassword123':'AdminBrowserPassword123');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
}
async function workspace(page) {
    await page.goto('/admin/events');
    await page.getByRole('link',{name:/Calendar Public Forum/}).click();
    await page.getByRole('navigation',{name:'Event workspace'}).getByRole('link',{name:'Communications',exact:true}).click();
}
test.describe.configure({mode:'serial'});
test.setTimeout(90000);
for (const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
    test(`${size.width}px: bilingual communications, revisions and AI suggestions`,async({page},testInfo)=>{
        reset(); await page.setViewportSize(size);
        const errors=[]; page.on('pageerror',e=>errors.push(e.message)); page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
        await login(page); await workspace(page);
        await page.getByLabel('Subject / title',{exact:true}).fill('Event invitation');
        await page.getByRole('textbox',{name:'Content',exact:true}).fill('Please join our event. مرحبًا بكم');
        await page.getByRole('button',{name:'Save content',exact:true}).click();
        await expect(page.locator('.communication-history')).toContainText('Version 1');
        await page.getByRole('button',{name:'Copy saved content'}).click();
        await expect(page.locator('.communication-copy [role=status]')).toHaveText('Copied.');
        await page.getByRole('combobox',{name:'AI action',exact:true}).selectOption('improve');
        await page.getByRole('button',{name:'Generate with AI',exact:true}).click();
        await expect(page.locator('.communication-suggestion .communication-content')).toContainText('local verification response');
        await page.getByRole('button',{name:'Apply as a new draft'}).click();
        await expect(page.locator('.communication-history')).toContainText('Version 2');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('communications-en.png'),fullPage:true});
        await page.locator('.communication-slots>div').first().getByRole('link',{name:'Arabic',exact:true}).click();
        await expect(page.getByRole('textbox',{name:'Content',exact:true})).toHaveAttribute('dir','rtl');
        await page.getByRole('combobox',{name:'AI action',exact:true}).selectOption('translate');
        await page.getByRole('button',{name:'Generate with AI',exact:true}).click();
        await page.getByRole('button',{name:'Apply as a new draft'}).click();
        await page.getByLabel('Subject / title',{exact:true}).fill('دعوة للمشاركة');
        await page.getByRole('textbox',{name:'Content',exact:true}).fill('ندعوكم للمشاركة في الفعالية.');
        await page.getByRole('button',{name:'Save content',exact:true}).click();
        await page.locator('#admin-locale').selectOption('ar'); await page.getByRole('button',{name:'Change language'}).click();
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('communications-ar.png'),fullPage:true});
        await page.goto(page.url().replace('/admin/events/','/app/events/'));
        await expect(page.locator('[data-communication-editor] textarea')).toHaveValue('ندعوكم للمشاركة في الفعالية.');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('communications-portal-ar.png'),fullPage:true});
        expect(errors).toEqual([]);
    });
}
test('validation, archive and viewer permissions',async({page})=>{
    reset(); await login(page); await workspace(page);
    await page.getByRole('combobox',{name:'Status',exact:true}).selectOption('ready');
    await page.getByRole('button',{name:'Save content',exact:true}).click();
    await expect(page.getByRole('alert')).toBeVisible();
    await page.getByRole('textbox',{name:'Content',exact:true}).fill('Saved event content');
    await page.getByRole('button',{name:'Save content',exact:true}).click();
    const portal=page.url().replace('/admin/events/','/app/events/');
    await page.locator('.communication-archive summary').click();
    await page.locator('.communication-archive input[name=confirm]').check();
    await page.locator('.communication-archive button').click();
    await expect(page.locator('.communication-editor')).toContainText('Archived.');
    await expect(page.locator('.communication-ai')).toHaveCount(0);
    await page.getByRole('button',{name:'Sign out',exact:true}).click(); await login(page,true);
    await page.goto(portal);
    await expect(page.locator('[data-communication-editor]')).toHaveCount(0);
    await expect(page.locator('.communication-editor')).not.toContainText('Saved event content');
});
