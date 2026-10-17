<?php

use App\Support\Crypto\InvalidMasterKey;
use App\Support\Crypto\MasterKey;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\MasterKeyMissing;
use App\Support\Crypto\MasterKeyStatus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
 * SEC-01: the master key comes from ABONEMEN_MASTER_KEY_FILE (preferred) or
 * ABONEMEN_MASTER_KEY, the app serves nothing else without it, and the key
 * never shows up in logs, error pages or the configuration.
 */

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/abonemen-mk-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->dir);
    $this->key = MasterKey::generate();
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function useLoader(MasterKeyLoader $loader): void
{
    app()->instance(MasterKeyLoader::class, $loader);
}

describe('loading', function () {
    it('reads the key from the environment', function () {
        $loader = new MasterKeyLoader($this->key->toBase64(), null);

        expect($loader->status())->toBe(MasterKeyStatus::Ready)
            ->and($loader->key()->equals($this->key))->toBeTrue();
    });

    it('reads the key from a file, ignoring a trailing newline', function () {
        file_put_contents("{$this->dir}/master.key", $this->key->toBase64()."\n");

        $loader = new MasterKeyLoader(null, "{$this->dir}/master.key");

        expect($loader->key()->equals($this->key))->toBeTrue();
    });

    it('resolves a relative file path from the project root', function () {
        expect((new MasterKeyLoader(null, 'storage/master.key'))->filePath())->toBe(base_path('storage/master.key'));
    });

    it('treats empty values as not set', function () {
        $loader = new MasterKeyLoader('', '');

        expect($loader->status())->toBe(MasterKeyStatus::Missing)
            ->and(fn () => $loader->key())->toThrow(MasterKeyMissing::class);
    });

    it('refuses a key that is not 32 base64-encoded bytes', function (string $value) {
        $loader = new MasterKeyLoader($value, null);

        expect($loader->status())->toBe(MasterKeyStatus::Invalid)
            ->and(fn () => $loader->key())->toThrow(InvalidMasterKey::class);
    })->with([
        'too short' => [base64_encode(random_bytes(16))],
        'too long' => [base64_encode(random_bytes(33))],
        'not base64' => ['this is not base64 at all!'],
        'hex instead of base64' => [bin2hex(random_bytes(32))],
    ]);

    it('refuses a missing or unreadable key file', function () {
        $loader = new MasterKeyLoader(null, "{$this->dir}/absent.key");

        expect($loader->status())->toBe(MasterKeyStatus::Invalid)
            ->and($loader->problem())->toContain('does not exist or cannot be read');
    });

    it('refuses both forms at once, even when they hold the same key', function () {
        file_put_contents("{$this->dir}/master.key", $this->key->toBase64());

        $loader = new MasterKeyLoader($this->key->toBase64(), "{$this->dir}/master.key");

        expect($loader->status())->toBe(MasterKeyStatus::Invalid)
            ->and($loader->problem())->toContain('not both');
    });

    it('never puts the key or the file contents in an error message', function () {
        $secret = 'c2VjcmV0LWJ1dC10b28tc2hvcnQ=';
        file_put_contents("{$this->dir}/master.key", $secret);

        $fromFile = new MasterKeyLoader(null, "{$this->dir}/master.key");
        $fromEnvironment = new MasterKeyLoader($secret, null);

        expect($fromFile->problem())->not->toContain($secret)
            ->and($fromEnvironment->problem())->not->toContain($secret);
    });
});

describe('the key object', function () {
    it('hides its bytes from dumps and cannot be serialised', function () {
        $dump = print_r($this->key, true).var_export((array) $this->key, true);
        ob_start();
        var_dump($this->key);
        $dump .= ob_get_clean();

        expect($dump)->toContain('[redacted]')
            ->and(str_contains(print_r($this->key, true), $this->key->toBase64()))->toBeFalse()
            ->and(fn () => serialize($this->key))->toThrow(LogicException::class);
    });

    it('never enters the configuration, so config:cache cannot write it to disk', function () {
        $configured = (string) json_encode(config()->all());

        expect($configured)->not->toContain(base64_encode('test-master-key-not-for-prod-use'))
            ->and(app(MasterKeyLoader::class)->status())->toBe(MasterKeyStatus::Ready);
    });

    it('is removed from the server variables once loaded', function () {
        $_SERVER['ABONEMEN_MASTER_KEY'] = $_ENV['ABONEMEN_MASTER_KEY'] = $this->key->toBase64();
        app()->forgetInstance(MasterKeyLoader::class);

        expect(app(MasterKeyLoader::class)->key()->equals($this->key))->toBeTrue()
            ->and($_SERVER)->not->toHaveKey('ABONEMEN_MASTER_KEY')
            ->and($_ENV)->not->toHaveKey('ABONEMEN_MASTER_KEY');
    });
});

describe('without a usable key', function () {
    it('serves only a page that says the app is not set up', function () {
        useLoader(new MasterKeyLoader(null, null));

        $this->get('/')
            ->assertServiceUnavailable()
            ->assertSee('Abonemen is not set up yet')
            ->assertSee('ABONEMEN_MASTER_KEY_FILE')
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('says when the configured key cannot be used, and logs why', function () {
        Log::spy();
        useLoader(new MasterKeyLoader(null, "{$this->dir}/absent.key"));

        $this->get('/')
            ->assertServiceUnavailable()
            ->assertSee('The master key cannot be used');

        Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'absent.key'));
    });

    it('keeps the health check answering', function () {
        useLoader(new MasterKeyLoader(null, null));

        $this->get('/up')->assertOk();
    });
});

describe('logs and error pages', function () {
    beforeEach(function () {
        config()->set('logging.channels.master-key-test', ['driver' => 'single', 'path' => "{$this->dir}/laravel.log"]);
        config()->set('logging.default', 'master-key-test');
        Log::forgetChannel('master-key-test');

        useLoader(new MasterKeyLoader($this->key->toBase64(), null));

        // A request that uses the key and then fails, as a bug would.
        Route::middleware('web')->get('/_test/fails-after-using-the-key', function (MasterKeyLoader $loader) {
            $loader->key();

            throw new RuntimeException('Something went wrong after loading the key.');
        });
    });

    it('keeps the key out of the debug error page and the log', function () {
        config()->set('app.debug', true);

        $response = $this->get('/_test/fails-after-using-the-key')->assertInternalServerError();

        $page = (string) $response->getContent();
        $log = (string) file_get_contents("{$this->dir}/laravel.log");

        expect($page)->toContain('Something went wrong after loading the key.')
            ->and($log)->toContain('Something went wrong after loading the key.');

        foreach ([$this->key->toBase64(), substr($this->key->toBase64(), 0, 12), $this->key->bytes()] as $form) {
            expect($page)->not->toContain($form)
                ->and($log)->not->toContain($form);
        }
    });
});
