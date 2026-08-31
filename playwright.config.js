import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        browserName: 'chromium',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || undefined },
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000 --no-reload',
        url: 'http://127.0.0.1:8000/up',
        reuseExistingServer: !process.env.CI,
        timeout: 30000,
    },
});
