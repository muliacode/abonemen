import { existsSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8100);
const baseURL = `http://127.0.0.1:${port}`;

// The end-to-end suite gets its own SQLite file, so it never touches the
// development database.
const database = resolve('database/e2e.sqlite');
if (!existsSync(database)) {
    writeFileSync(database, '');
}

// Lets a local run use a browser that is already installed.
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;

export default defineConfig({
    testDir: 'tests/e2e',
    outputDir: 'build/playwright/results',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: process.env.CI
        ? [
              ['github'],
              [
                  'html',
                  { outputFolder: 'build/playwright/report', open: 'never' },
              ],
          ]
        : [['list']],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                launchOptions: executablePath ? { executablePath } : {},
            },
        },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
    webServer: {
        // `artisan serve` drops most environment variables, so the PHP
        // built-in server runs Laravel's router script directly. That script
        // expects to start in public/.
        command: `php artisan migrate:fresh --force --no-interaction && cd public && php -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        url: `${baseURL}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 60_000,
        stdout: 'ignore',
        // Laravel logs to storage/logs/laravel.log; CI keeps it when a test fails.
        stderr: 'ignore',
        env: {
            APP_ENV: 'testing',
            APP_DEBUG: 'false',
            APP_URL: baseURL,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: database,
            PHP_CLI_SERVER_WORKERS: '4',
            LOG_CHANNEL: 'single',
            MAIL_MAILER: 'array',
            QUEUE_CONNECTION: 'sync',
        },
    },
});
