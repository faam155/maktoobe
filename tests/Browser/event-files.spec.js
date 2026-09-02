import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const reset = () => { for (const action of ['reset','calendar']) execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000}); };
async function login(page, member=false) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(member?'member':'admin');
    await page.getByLabel('Password').fill(member?'BrowserPassword123':'AdminBrowserPassword123');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
}
async function workspace(page, privateEvent=false) {
    await page.goto('/admin/events');
    await page.getByRole('link',{name:privateEvent?/Calendar Private Secret/:/Calendar Public Forum/}).click();
    await page.getByRole('link',{name:'Photos',exact:true}).click();
}
async function photo(page) {
    const base64 = await page.evaluate(()=>{
        const canvas=document.createElement('canvas'); canvas.width=480; canvas.height=240;
        const ctx=canvas.getContext('2d'); ctx.fillStyle='#dce5d7'; ctx.fillRect(0,0,480,240);
        ctx.fillStyle='#49654c'; ctx.fillRect(32,48,416,144); ctx.fillStyle='white'; ctx.font='24px sans-serif'; ctx.fillText('Event photo fixture',110,130);
        return canvas.toDataURL('image/png').split(',')[1];
    });
    return {name:'event-photo.png',mimeType:'image/png',buffer:Buffer.from(base64,'base64')};
}
async function loadGallery(page) {
    for (const image of await page.locator('.event-file-preview img').all()) {
        await image.scrollIntoViewIfNeeded();
        await expect.poll(()=>image.evaluate(img=>img.naturalWidth)).toBe(480);
    }
}
test.describe.configure({mode:'serial'});
for (const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
    test(`${size.width}px: photo upload gallery and editing in English and Arabic`, async ({page},testInfo)=>{
        reset(); await page.setViewportSize(size);
        const errors=[]; page.on('pageerror',e=>errors.push(e.message)); page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
        await login(page); await workspace(page);
        await expect(page.locator('.event-file-empty')).toBeVisible();
        const upload=page.locator('[data-event-upload]');
        const fixture=await photo(page);
        await upload.locator('input[type=file]').setInputFiles([fixture,{...fixture,name:'second-photo.png'}]);
        await upload.getByLabel('Caption',{exact:true}).fill('Event setup and team workspace');
        await upload.getByRole('button',{name:'Upload files',exact:true}).click();
        await expect(page.locator('.event-file-card')).toHaveCount(2);
        const image=page.locator('.event-file-preview img').first();
        await expect.poll(()=>image.evaluate(img=>img.naturalWidth)).toBe(480);
        await loadGallery(page);
        await page.screenshot({path:testInfo.outputPath('files-en.png'),fullPage:true});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        const downloadPromise=page.waitForEvent('download');
        await page.locator('.event-file-card').first().getByRole('link',{name:'Download',exact:true}).click();
        expect((await downloadPromise).suggestedFilename()).toBe('event-photo.png');
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button',{name:'Change language'}).click();
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        let card=page.locator('.event-file-card').first();
        await card.locator('summary').filter({hasText:'تعديل التفاصيل'}).click();
        await card.locator('textarea').fill('صور تجهيز الفعالية');
        await card.locator('input[name=display_order]').fill('5');
        await card.getByRole('button',{name:'حفظ التفاصيل'}).click();
        await expect(page.locator('.event-file-card').last()).toContainText('صور تجهيز الفعالية');
        await loadGallery(page);
        await page.screenshot({path:testInfo.outputPath('files-ar.png'),fullPage:true});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        card=page.locator('.event-file-card').first();
        await card.locator('summary').filter({hasText:'حذف الملف'}).click();
        await card.locator('input[name=confirm]').check();
        await card.getByRole('button',{name:'حذف الملف',exact:true}).click();
        await expect(page.locator('.event-file-card')).toHaveCount(1);
        await page.goto(page.url().replace('/admin/events/','/app/events/'));
        await expect(page.locator('.event-file-card')).toHaveCount(1);
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await loadGallery(page);
        await page.screenshot({path:testInfo.outputPath('files-portal-ar.png'),fullPage:true});
        expect(errors).toEqual([]);
    });
}

test('file validation, document categories and private downloads are enforced', async ({page})=>{
    reset(); await login(page); await workspace(page,true);
    const upload=page.locator('[data-event-upload]');
    await upload.locator('input[type=file]').setInputFiles({name:'not-a-photo.png',mimeType:'image/png',buffer:Buffer.from('Not a PNG')});
    await upload.getByRole('button',{name:'Upload files',exact:true}).click();
    await expect(upload.locator('[role=status]')).toContainText('invalid');
    await expect(page.locator('.event-file-card')).toHaveCount(0);
    await upload.locator('select[name=category]').selectOption('reports');
    await upload.locator('input[type=file]').setInputFiles({name:'report.txt',mimeType:'text/plain',buffer:Buffer.from('Event report notes for internal review.')});
    await upload.getByRole('button',{name:'Upload files',exact:true}).click();
    await expect(page.locator('.event-file-card')).toHaveCount(1);
    const download=await page.getByRole('link',{name:'Download',exact:true}).getAttribute('href');
    const portalDownload=download.replace('/admin/events/','/app/events/');
    await page.getByRole('button',{name:'Sign out',exact:true}).click();
    await login(page,true);
    expect((await page.request.get(portalDownload)).status()).toBe(403);
    await page.goto('/app/events?period=all');
    await page.getByRole('link',{name:/Calendar Public Forum/}).click();
    await page.getByRole('link',{name:'Documents',exact:true}).click();
    await expect(page.locator('[data-event-upload]')).toHaveCount(0);
    await expect(page.locator('.event-file-empty')).toBeVisible();
});
