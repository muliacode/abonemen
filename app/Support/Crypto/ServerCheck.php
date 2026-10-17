<?php

namespace App\Support\Crypto;

use Illuminate\Support\Facades\Http;
use PDO;
use Throwable;
use ZipArchive;

/**
 * SPIKE (#15): checks a server for what Abonemen needs. It lives in
 * Support\Crypto because it exercises libsodium, which the architecture tests
 * only allow here.
 *
 * Each check returns: name, status (ok, warn or fail), detail.
 */
final class ServerCheck
{
    private const REQUIRED_EXTENSIONS = [
        'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'intl', 'mbstring',
        'openssl', 'pdo_sqlite', 'session', 'sodium', 'tokenizer', 'xml', 'zip',
    ];

    private const RECOMMENDED_EXTENSIONS = ['bcmath', 'Zend OPcache', 'pcntl'];

    /**
     * @return list<array{name: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    public function run(bool $network = true): array
    {
        $checks = [
            $this->phpVersion(),
            ...$this->extensions(),
            $this->argon2id(),
            $this->sodium(),
            $this->aesZip(),
            $this->sqlite(),
            ...$this->writable(),
            $this->limits(),
            $this->disabledFunctions(),
        ];

        if ($network) {
            $checks[] = $this->outboundHttps();
        }

        return $checks;
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function phpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');

        return $this->result('PHP version', $ok ? 'ok' : 'fail', PHP_VERSION.' ('.PHP_SAPI.', '.PHP_OS_FAMILY.')');
    }

    /**
     * @return list<array{name: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    private function extensions(): array
    {
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, fn (string $e): bool => ! extension_loaded($e)));
        $missingRecommended = array_values(array_filter(self::RECOMMENDED_EXTENSIONS, fn (string $e): bool => ! extension_loaded($e)));

        return [
            $this->result('Required extensions', $missing === [] ? 'ok' : 'fail', $missing === [] ? 'all present' : 'missing: '.implode(', ', $missing)),
            $this->result('Recommended extensions', $missingRecommended === [] ? 'ok' : 'warn', $missingRecommended === [] ? 'all present' : 'missing: '.implode(', ', $missingRecommended)),
        ];
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function argon2id(): array
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            return $this->result('Argon2id passwords', 'warn', 'not compiled in; Abonemen falls back to bcrypt');
        }

        $hash = password_hash('spike', PASSWORD_ARGON2ID);

        return $this->result('Argon2id passwords', password_verify('spike', $hash) ? 'ok' : 'fail', substr($hash, 0, 30).'…');
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function sodium(): array
    {
        if (! extension_loaded('sodium')) {
            return $this->result('libsodium', 'fail', 'sodium extension missing');
        }

        try {
            $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $box = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt('spike', 'aad', $nonce, $key);
            $ok = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($box, 'aad', $nonce, $key) === 'spike';
            $pair = sodium_crypto_box_keypair();
            $sealed = sodium_crypto_box_seal('spike', sodium_crypto_box_publickey($pair));
            $ok = $ok && sodium_crypto_box_seal_open($sealed, $pair) === 'spike';

            return $this->result('libsodium', $ok ? 'ok' : 'fail', 'XChaCha20-Poly1305 and sealed boxes, libsodium '.SODIUM_LIBRARY_VERSION);
        } catch (Throwable $e) {
            return $this->result('libsodium', 'fail', $e->getMessage());
        }
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function aesZip(): array
    {
        if (! class_exists(ZipArchive::class) || ! defined('ZipArchive::EM_AES_256')) {
            return $this->result('AES-256 zip (backups)', 'fail', 'zip extension without encryption support');
        }

        $path = tempnam(sys_get_temp_dir(), 'abonemen-zip-');
        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('database.sqlite', 'spike');
            $zip->setEncryptionName('database.sqlite', ZipArchive::EM_AES_256, 'password');
            $zip->close();

            $zip->open($path);
            $zip->setPassword('password');
            $ok = $zip->getFromName('database.sqlite') === 'spike';
            $zip->close();

            return $this->result('AES-256 zip (backups)', $ok ? 'ok' : 'fail', 'libzip '.ZipArchive::LIBZIP_VERSION);
        } catch (Throwable $e) {
            return $this->result('AES-256 zip (backups)', 'fail', $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function sqlite(): array
    {
        $path = storage_path('framework/spike-check.sqlite');
        try {
            $pdo = new PDO("sqlite:{$path}", options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $scalar = fn (string $sql): string => (string) $pdo->query($sql)->fetchColumn(); // @phpstan-ignore method.nonObject (ERRMODE_EXCEPTION)
            $version = $scalar('select sqlite_version()');
            $mode = $scalar('PRAGMA journal_mode = WAL');
            $pdo->exec('create table if not exists t (v text)');
            $pdo->exec("insert into t values ('x')");
            $json = $scalar("select json_extract('{\"a\":1}', '$.a')");
            unset($scalar, $pdo);

            $ok = version_compare($version, '3.35.0', '>=') && strtolower($mode) === 'wal' && $json === '1';

            return $this->result('SQLite', $ok ? 'ok' : 'fail', "SQLite {$version}, journal mode {$mode}, JSON ".($json === '1' ? 'yes' : 'no'));
        } catch (Throwable $e) {
            return $this->result('SQLite', 'fail', $e->getMessage());
        } finally {
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($path.$suffix);
            }
        }
    }

    /**
     * @return list<array{name: string, status: 'ok'|'warn'|'fail', detail: string}>
     */
    private function writable(): array
    {
        return array_map(
            fn (string $dir): array => $this->result("Writable: {$dir}", is_writable(base_path($dir)) ? 'ok' : 'fail', base_path($dir)),
            ['storage', 'bootstrap/cache', 'database'],
        );
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function limits(): array
    {
        $memory = (string) ini_get('memory_limit');
        $time = (int) ini_get('max_execution_time');
        $bytes = $this->bytes($memory);
        $ok = ($bytes < 0 || $bytes >= 128 * 1024 * 1024) && ($time === 0 || $time >= 30);

        return $this->result('Limits', $ok ? 'ok' : 'warn', "memory_limit {$memory}, max_execution_time {$time}s, OPcache ".(function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off'));
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function disabledFunctions(): array
    {
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $needed = array_values(array_intersect($disabled, ['proc_open', 'escapeshellarg', 'set_time_limit', 'ignore_user_abort', 'symlink']));

        return $this->result('Disabled functions', $needed === [] ? 'ok' : 'warn', $needed === [] ? (count($disabled).' disabled, none that Abonemen uses') : 'disabled and used by Laravel: '.implode(', ', $needed));
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function outboundHttps(): array
    {
        try {
            $response = Http::timeout(5)->get('https://api.frankfurter.dev/v2/currencies');

            return $this->result('Outbound HTTPS (exchange rates)', $response->successful() ? 'ok' : 'warn', 'api.frankfurter.dev answered '.$response->status());
        } catch (Throwable $e) {
            return $this->result('Outbound HTTPS (exchange rates)', 'warn', 'blocked or unreachable: '.$e->getMessage());
        }
    }

    private function bytes(string $value): int
    {
        if ($value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @param  'ok'|'warn'|'fail'  $status
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function result(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }
}
