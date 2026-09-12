import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
const fixture=()=>{for(const action of ['reset','social'])execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000});};
async function login(page){await page.goto('/login');await page.getByLabel('Email or username').fill('admin');await page.getByLabel('Password').fill('AdminBrowserPassword123');await page.getByRole('button',{name:'Sign in',exact:true}).click();}
const query='?period=custom&from=2026-09-01&to=2026-09-30';
test.describe.configure({mode:'serial'});
for(const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
test(size.width+'px social analytics in English and Arabic',async({page},info)=>{
    fixture();await page.setViewportSize(size);const errors=[];
    page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
    await login(page);await page.goto('/app/social-media/linkedin');await page.getByRole('link',{name:'Maktoob · Demo'}).click();
    const base=page.url().split('/dashboard')[0];await page.goto(base+'/dashboard'+query);
    await expect(page.locator('[data-social-metric=impressions] strong')).not.toHaveText('—');
    await expect(page.locator('.social-post').first()).toBeVisible();
    if(size.width>=1280) expect((await page.locator('.social-kpis').boundingBox()).y+(await page.locator('.social-kpis').boundingBox()).height).toBeLessThan(size.height);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath('dashboard-en.png'),fullPage:true});
    await page.screenshot({path:info.outputPath('viewport-en.png')});
    await page.locator('#portal-locale').selectOption('ar');await page.getByRole('button',{name:'Change language'}).click();
    await expect(page.locator('html')).toHaveAttribute('dir','rtl');
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    await page.screenshot({path:info.outputPath('dashboard-ar.png'),fullPage:true});
    await page.screenshot({path:info.outputPath('viewport-ar.png')});
    for(const path of ['/posts','/posts/create','/catalog/campaign','/catalog/target','/catalog/audience','/metrics','/imports','/settings','/reports','/sync']) {
        const response=await page.goto(base+path+query);expect(response.status()).toBe(200);
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),path).toBe(true);
        expect(await page.locator('main').innerText()).not.toMatch(/social\.[a-z_]+/);
    }
    expect(errors).toEqual([]);
});
}
test('post editing, filtering, CSV confirmation, exports and event integration',async({page},info)=>{
    test.setTimeout(120000);
    fixture();await login(page);await page.goto('/app/social-media/linkedin');await page.getByRole('link',{name:'Maktoob · Demo'}).click();
    const base=page.url().split('/dashboard')[0];
    await page.goto(base+'/posts'+query);await page.locator('.social-post h3 a').first().click();await page.getByRole('link',{name:'Edit',exact:true}).click();
    await page.getByLabel('Post title',{exact:true}).fill('Revised corporate update');await page.getByRole('button',{name:'Save',exact:true}).click();
    await expect(page.locator('main')).toContainText('Revised corporate update');
    await page.goto(base+'/dashboard'+query);await page.locator('.social-filter-panel summary').click();await page.locator('select[name=language]').selectOption('ar');await page.getByRole('button',{name:'Apply filters'}).click();
    await expect(page.locator('[data-social-metric=followers] strong')).toHaveText('—');
    await page.goto(base+'/imports');await page.locator('input[type=file]').setInputFiles({name:'metrics.csv',mimeType:'text/csv',buffer:Buffer.from('subject,post_id,starts_on,ends_on,impressions,reactions,comments,shares,clicks,followers,new_followers\naccount,,2026-10-01,2026-10-01,1000,30,5,5,10,26000,20\n')});
    await page.getByRole('button',{name:'Preview import',exact:true}).click();await expect(page.locator('main')).toContainText('Import preview is ready');
    await page.getByRole('button',{name:'Confirm import',exact:true}).click();await expect(page.locator('main')).toContainText('Confirmed');
    await page.goto(base+'/reports'+query);
    for(const format of ['Excel','PDF']) {
        const download=page.waitForEvent('download');await page.getByRole('link',{name:'Export '+format,exact:true}).first().click();
        const file=await download;expect(await file.failure()).toBeNull();await file.saveAs(info.outputPath('report.'+(format==='PDF'?'pdf':'xlsx')));
    }
    await page.goto('/app/events?period=all');await page.getByRole('link').filter({has:page.getByRole('heading',{name:'OPES 2026',exact:true})}).click();
    await expect(page.locator('main')).toContainText('Event social media performance');
    await page.goto(base+'/reports'+query);
    await page.locator('#portal-locale').selectOption('ar');await page.getByRole('button',{name:'Change language'}).click();
    const arabicDownload=page.waitForEvent('download');await page.getByRole('link',{name:'تصدير PDF',exact:true}).first().click();
    const arabicFile=await arabicDownload;expect(await arabicFile.failure()).toBeNull();await arabicFile.saveAs(info.outputPath('report-ar.pdf'));
});
