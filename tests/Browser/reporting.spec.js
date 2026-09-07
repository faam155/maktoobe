import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
const reset=()=>{for(const action of ['reset','calendar','reporting'])execFileSync('php',['scripts/browser-fixtures.php',action],{timeout:60000});};
async function login(page, member=false) {
 await page.goto('/login'); await page.getByLabel('Email or username').fill(member?'member':'admin');
 await page.getByLabel('Password').fill(member?'BrowserPassword123':'AdminBrowserPassword123');
 await page.getByRole('button',{name:'Sign in',exact:true}).click();
}
test.describe.configure({mode:'serial'});
for (const size of [{width:1440,height:900},{width:1280,height:800},{width:768,height:1024},{width:390,height:844},{width:360,height:800}]) {
 test(size.width+'px: analytics and audit filters support English and Arabic',async({page},testInfo)=>{
  reset(); await page.setViewportSize(size);
  const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
  await login(page); await page.goto('/admin/analytics');
  await expect(page.locator('[data-metric=total_users] strong')).toHaveText('2');
  await expect(page.locator('.analytics-rankings')).toContainText('Browser Writing Assistant');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:testInfo.outputPath('analytics-en.png'),fullPage:true});
  await page.locator('#admin-locale').selectOption('ar');await page.getByRole('button',{name:'Change language'}).click();
  await expect(page.locator('html')).toHaveAttribute('dir','rtl');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:testInfo.outputPath('analytics-ar.png'),fullPage:true});
  await page.goto('/admin/audit-logs');
  await expect(page.locator('.audit-entry')).toHaveCount(2);
  await expect(page.locator('.audit-list')).not.toContainText('must-not-appear');
  await page.locator('input[name=search]').fill('event.created');
  await page.locator('.report-filters button').click();
  await expect(page.locator('.audit-entry')).toHaveCount(1);
  await page.locator('.audit-entry summary').click();
  await expect(page.locator('.audit-entry pre')).toContainText('event_id');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.screenshot({path:testInfo.outputPath('audits-ar.png'),fullPage:true});
  expect(errors).toEqual([]);
 });
}
test('reporting validates custom dates and denies ordinary users',async({page})=>{
 reset();await login(page);await page.goto('/admin/analytics');
 await page.locator('select[name=period]').selectOption('custom');
 await page.locator('input[name=from]').fill('2026-09-04');await page.locator('input[name=to]').fill('2026-09-03');
 await page.locator('.report-filters button').click();await expect(page.locator('.admin-errors')).toBeVisible();
 await page.getByRole('button',{name:'Sign out',exact:true}).click();await login(page,true);
 expect((await page.request.get('/admin/analytics')).status()).toBe(403);
 expect((await page.request.get('/admin/audit-logs')).status()).toBe(403);
});
