import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const reset = () => execFileSync('php', ['scripts/browser-fixtures.php', 'reset'], { encoding: 'utf8', timeout: 60000 });
async function login(page, member = false) {
    await page.goto('/login');
    await page.getByLabel('Email or username').fill(member ? 'member' : 'admin');
    await page.getByLabel('Password').fill(member ? 'BrowserPassword123' : 'AdminBrowserPassword123');
    await page.getByRole('button', {name: 'Sign in', exact: true}).click();
}
async function create(page, title = 'Browser Event') {
    await page.goto('/admin/events/create');
    await page.getByLabel('Title', {exact:true}).fill(title);
    await page.getByLabel('Description', {exact:true}).fill('A bilingual event overview for browser verification.');
    await page.getByLabel('Starts', {exact:true}).fill('2027-01-10T09:00');
    await page.getByLabel('Ends', {exact:true}).fill('2027-01-10T12:00');
    await page.getByLabel('Timezone', {exact:true}).fill('Asia/Muscat');
    await page.getByRole('combobox', {name:'Status',exact:true}).selectOption('planned');
    await page.getByLabel('Visibility', {exact:false}).selectOption('all_users');
    await page.getByRole('button', {name:'Save event',exact:true}).click();
    await expect(page.getByRole('status')).toHaveText('The event was created.');
}
test.describe.configure({mode:'serial'});
for (const size of [
    {name:'desktop',width:1440,height:900}, {name:'laptop',width:1280,height:800},
    {name:'tablet',width:768,height:1024}, {name:'mobile',width:390,height:844},
    {name:'narrow',width:360,height:800},
]) {
    test(`${size.name}: event forms and workspace support English and Arabic`, async ({page}, testInfo) => {
        reset();
        const errors=[];
        page.on('pageerror', e=>errors.push(e.message));
        page.on('console', m=>{if(m.type()==='error')errors.push(m.text());});
        await page.setViewportSize(size);
        await login(page);
        await create(page);
        await expect(page.getByRole('navigation',{name:'Event workspace'})).toBeVisible();
        await page.screenshot({path:testInfo.outputPath('event-overview-en.png'),fullPage:true});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.locator('#admin-locale').selectOption('ar');
        await page.getByRole('button',{name:'Change language'}).click();
        await expect(page.locator('html')).toHaveAttribute('dir','rtl');
        await expect(page.getByRole('navigation',{name:'مساحة عمل الفعالية'})).toBeVisible();
        await page.getByRole('link',{name:'تعديل الفعالية',exact:true}).click();
        await expect(page.locator('input[name=title]')).toHaveValue('Browser Event');
        await page.screenshot({path:testInfo.outputPath('event-form-ar.png'),fullPage:true});
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.goto('/app/events?period=all');
        await page.getByRole('link', {name:/Browser Event/}).click();
        await expect(page.getByRole('navigation',{name:'مساحة عمل الفعالية'})).toBeVisible();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
        await page.screenshot({path:testInfo.outputPath('event-portal-ar.png'),fullPage:true});
        expect(errors).toEqual([]);
    });
}
test('event lifecycle and audience are enforced through the UI', async ({page})=>{
    reset(); await login(page); await create(page,'Public Event Verification');
    await page.getByRole('combobox',{name:'Change status',exact:true}).selectOption('confirmed');
    await page.getByRole('button',{name:'Change status',exact:true}).click();
    await expect(page.getByRole('status')).toHaveText('The event status was updated.');
    await page.getByRole('link',{name:'Edit event',exact:true}).click();
    await page.getByLabel('Visibility',{exact:false}).selectOption('selected_users');
    await page.getByRole('button',{name:'Save event',exact:true}).click();
    await expect(page.getByRole('alert')).toContainText('Select at least one user');
    await page.getByLabel('Browser Member',{exact:true}).check();
    await page.getByRole('button',{name:'Save event',exact:true}).click();
    await expect(page.getByRole('status')).toHaveText('The event was updated.');
    const eventUrl = page.url().replace('/admin/events/','/app/events/');
    await page.getByRole('button',{name:'Sign out',exact:true}).click();
    await login(page,true); await page.goto(eventUrl);
    await expect(page.locator('main')).toContainText('Public Event Verification');
    await page.goto('/admin/events');
    await expect(page.locator('body')).toContainText('403');
});
