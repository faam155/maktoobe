import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const reset = () => { for (const action of ['reset','calendar']) execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000}); };
const pdf = (name) => ({name,mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF')});
async function login(page, member=false) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(member?'member':'admin');
    await page.getByLabel('Password').fill(member?'BrowserPassword123':'AdminBrowserPassword123');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
}
async function workspace(page, privateEvent=false) {
    await page.goto('/admin/events');
    await page.getByRole('link',{name:privateEvent?/Calendar Private Secret/:/Calendar Public Forum/}).click();
    await page.getByRole('navigation',{name:'Event workspace'}).getByRole('link',{name:'Reports',exact:true}).click();
}
async function upload(page, type, title, name, replacing=false) {
    const section=page.locator(`#${type}`);
    if(replacing) await section.locator('.report-upload summary').click();
    const form=section.locator('[data-event-upload]');
    await form.getByLabel('Report title',{exact:true}).fill(title);
    await form.getByLabel('Description / version notes').fill('Review notes — ملاحظات المراجعة');
    await form.locator('input[type=file]').setInputFiles(pdf(name));
    await form.locator('button.admin-button').click();
    await expect(section.locator('.report-current')).toContainText(title);
}
test.describe.configure({mode:'serial'});
for (const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
    test(`${size.width}px: reports preserve versions in bilingual admin and portal layouts`,async({page},testInfo)=>{
        reset(); await page.setViewportSize(size);
        const errors=[]; page.on('pageerror',e=>errors.push(e.message)); page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
        await login(page); await workspace(page);
        await expect(page.locator('.report-empty')).toHaveCount(2);
        await upload(page,'PRE_EVENT','Preparation report','pre-v1.pdf');
        await upload(page,'PRE_EVENT','Revised preparation','pre-v2.pdf',true);
        await upload(page,'POST_EVENT','Outcome report','post-v1.pdf');
        await expect(page.locator('#PRE_EVENT .report-version')).toHaveCount(2);
        await expect(page.locator('#PRE_EVENT .report-current')).toContainText('Version 2');
        const downloadPromise=page.waitForEvent('download');
        await page.locator('#PRE_EVENT .report-version').last().getByRole('link',{name:'Download report'}).click();
        expect((await downloadPromise).suggestedFilename()).toBe('pre-v1.pdf');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('reports-en.png'),fullPage:true});
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button',{name:'Change language'}).click();
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        await expect(page.locator('#PRE_EVENT h2')).toHaveText('تقرير ما قبل الفعالية');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('reports-ar.png'),fullPage:true});
        await page.goto(page.url().replace('/admin/events/','/app/events/'));
        await expect(page.locator('#PRE_EVENT .report-version')).toHaveCount(2);
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('reports-portal-ar.png'),fullPage:true});
        const post=page.locator('#POST_EVENT');
        await post.locator('.report-delete summary').click();
        await post.locator('input[name=confirm]').check();
        await post.getByRole('button',{name:'حذف التقرير',exact:true}).click();
        await expect(post.locator('.report-empty')).toBeVisible();
        await expect(page.locator('#PRE_EVENT .report-version')).toHaveCount(2);
        expect(errors).toEqual([]);
    });
}
test('report validation and private download enforcement',async({page})=>{
    reset(); await login(page); await workspace(page,true);
    const form=page.locator('#PRE_EVENT [data-event-upload]');
    await form.getByLabel('Report title').fill('Private report');
    await form.locator('input[type=file]').setInputFiles({name:'fake.xlsx',mimeType:'application/zip',buffer:Buffer.from('not an xlsx')});
    await form.locator('button.admin-button').click();
    await expect(form.locator('[role=status]')).toContainText('invalid');
    await upload(page,'PRE_EVENT','Private report','private.pdf');
    const url=(await page.locator('#PRE_EVENT .report-current a').getAttribute('href')).replace('/admin/events/','/app/events/');
    await page.getByRole('button',{name:'Sign out',exact:true}).click();
    await login(page,true);
    expect((await page.request.get(url)).status()).toBe(403);
    await page.goto('/app/events?period=all');
    await page.getByRole('link',{name:/Calendar Public Forum/}).click();
    await page.getByRole('navigation',{name:'Event workspace'}).getByRole('link',{name:'Reports',exact:true}).click();
    await expect(page.locator('[data-event-upload]')).toHaveCount(0);
    await expect(page.locator('.report-empty')).toHaveCount(2);
});
