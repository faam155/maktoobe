import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8001',
        browserName: 'chromium',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined },
    },
    webServer: {
        // Keep automated traffic separate from the single-process Windows preview server.
        command: 'php scripts/browser-fixtures.php reset && php artisan serve --env=browser --host=127.0.0.1 --port=8001 --no-reload',
        url: 'http://127.0.0.1:8001/up',
        reuseExistingServer: false,
        timeout: 30000,
    },
});
