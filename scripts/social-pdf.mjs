import { chromium } from 'playwright';
import { readFile } from 'node:fs/promises';
const [input,output]=process.argv.slice(2);
const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||undefined});
try {
    const context=await browser.newContext({javaScriptEnabled:false});
    await context.route('**/*',route=>route.abort());
    const page=await context.newPage();
    await page.setContent(await readFile(input,'utf8'),{waitUntil:'load'});
    await page.pdf({path:output,format:'A4',printBackground:true,displayHeaderFooter:true,headerTemplate:'<div></div>',footerTemplate:'<div style="width:100%;text-align:center;font-size:9px;color:#61665f"><span class="pageNumber"></span> / <span class="totalPages"></span></div>',margin:{top:'16mm',bottom:'18mm',left:'14mm',right:'14mm'}});
} finally { await browser.close(); }
