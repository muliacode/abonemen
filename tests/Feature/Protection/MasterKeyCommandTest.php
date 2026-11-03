<?php

namespace Tests\Feature\Protection;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MasterKeyCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/abonemen-env-'.bin2hex(random_bytes(4));
        mkdir($this->directory);

        $this->app->useEnvironmentPath($this->directory);
        $this->app->loadEnvironmentFrom('.env');
    }

    protected function tearDown(): void
    {
        @unlink($this->directory.'/.env');
        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_write_saves_a_new_key_in_an_empty_setting(): void
    {
        $this->writeEnv("APP_NAME=Abonemen\nABONEMEN_MASTER_KEY=\nAPP_DEBUG=true\n");

        $this->assertSame(0, Artisan::call('master-key:generate', ['--write' => true]));

        $env = $this->readEnv();

        $this->assertMatchesRegularExpression('/^ABONEMEN_MASTER_KEY=base64:[A-Za-z0-9+\/]{43}=$/m', $env);
        $this->assertStringContainsString("APP_NAME=Abonemen\n", $env);
        $this->assertStringContainsString("APP_DEBUG=true\n", $env);
        $this->assertSame(1, substr_count($env, 'ABONEMEN_MASTER_KEY='));
    }

    public function test_write_adds_the_setting_when_it_is_missing(): void
    {
        $this->writeEnv("APP_NAME=Abonemen\n");

        $this->assertSame(0, Artisan::call('master-key:generate', ['--write' => true]));

        $this->assertMatchesRegularExpression('/^ABONEMEN_MASTER_KEY=base64:/m', $this->readEnv());
    }

    public function test_write_does_not_say_what_the_key_is(): void
    {
        $this->writeEnv("ABONEMEN_MASTER_KEY=\n");

        Artisan::call('master-key:generate', ['--write' => true]);

        $this->assertStringNotContainsString('base64:', Artisan::output());
    }

    public function test_write_leaves_a_key_that_is_already_set_alone_so_setup_can_be_run_again(): void
    {
        $this->writeEnv("ABONEMEN_MASTER_KEY=base64:existing\n");

        $this->assertSame(0, Artisan::call('master-key:generate', ['--write' => true]));

        $this->assertSame("ABONEMEN_MASTER_KEY=base64:existing\n", $this->readEnv());

        $output = Artisan::output();

        $this->assertStringContainsString('left unchanged', $output);
        $this->assertStringContainsString('--force', $output);
    }

    public function test_write_with_force_replaces_the_key(): void
    {
        $this->writeEnv("ABONEMEN_MASTER_KEY=base64:existing\n");

        $this->assertSame(0, Artisan::call('master-key:generate', ['--write' => true, '--force' => true]));

        $this->assertStringNotContainsString('base64:existing', $this->readEnv());
        $this->assertMatchesRegularExpression('/^ABONEMEN_MASTER_KEY=base64:[A-Za-z0-9+\/]{43}=$/m', $this->readEnv());
    }

    public function test_write_fails_clearly_without_an_env_file(): void
    {
        $this->assertSame(1, Artisan::call('master-key:generate', ['--write' => true]));
        $this->assertStringContainsString('.env', Artisan::output());
    }

    private function writeEnv(string $contents): void
    {
        file_put_contents($this->directory.'/.env', $contents);
    }

    private function readEnv(): string
    {
        return file_get_contents($this->directory.'/.env');
    }
}
