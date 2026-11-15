<?php

namespace App\Support;

use App\Enums\Connection;
use App\Enums\Locale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Choices the Instance Admin makes for the whole instance, kept in the database so that they can change without a deploy.
 *
 * Read once per request. A setting that was never saved reads as its safe default: registration closed, the
 * second factor required.
 */
class PlatformSettings
{
    private const REGISTRATION = 'registration_open';

    private const SECOND_FACTOR = 'require_second_factor';

    private const LANGUAGE = 'default_language';

    private const CURRENCY = 'default_currency';

    private const TIMEZONE = 'default_timezone';

    private const MAIL = 'mail';

    private const MAIL_TESTED = 'mail_tested';

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    /**
     * Whether anyone can register. Closed until an Instance Admin opens it.
     */
    public function registrationIsOpen(): bool
    {
        return $this->get(self::REGISTRATION) === '1';
    }

    public function openRegistration(bool $open): void
    {
        $this->put(self::REGISTRATION, $open ? '1' : '0');
    }

    /**
     * Whether an Instance Admin needs a second factor to open their section. On until an admin turns it off.
     */
    public function requireSecondFactor(): bool
    {
        return $this->get(self::SECOND_FACTOR) !== '0';
    }

    public function setRequireSecondFactor(bool $required): void
    {
        $this->put(self::SECOND_FACTOR, $required ? '1' : '0');
    }

    /**
     * The language used when neither the User nor their browser picks one.
     */
    public function defaultLanguage(): Locale
    {
        return Locale::tryFrom((string) $this->get(self::LANGUAGE))
            ?? Locale::tryFrom((string) config('app.locale'))
            ?? Locale::English;
    }

    /**
     * The main currency of a new Household, an ISO 4217 code.
     */
    public function defaultCurrency(): string
    {
        return $this->get(self::CURRENCY) ?: 'EUR';
    }

    /**
     * The time zone of a new Household.
     */
    public function defaultTimezone(): string
    {
        return $this->get(self::TIMEZONE) ?: (string) config('app.timezone');
    }

    public function setDefaults(Locale $language, string $currency, string $timezone): void
    {
        $this->put(self::LANGUAGE, $language->value);
        $this->put(self::CURRENCY, $currency);
        $this->put(self::TIMEZONE, $timezone);
    }

    /**
     * Whether the Instance Admin has made a Connection available to Households. Off until they do.
     */
    public function connectionIsAvailable(Connection $connection): bool
    {
        return $this->get('connection.'.$connection->value) === '1';
    }

    public function setConnectionAvailable(Connection $connection, bool $available): void
    {
        $this->put('connection.'.$connection->value, $available ? '1' : '0');
    }

    /**
     * The SMTP settings saved in the app, or null when there are none and .env applies. The whole record is
     * protected with the platform key, so the password is not readable in the database.
     *
     * @return array{from_name: string, from_address: string, host: string, port: int, scheme: string, username: ?string, password: ?string}|null
     */
    public function mail(): ?array
    {
        $sealed = $this->get(self::MAIL);

        if ($sealed === null) {
            return null;
        }

        $keys = app(KeyRing::class);

        return json_decode($keys->open($sealed, $keys->platformKey()), true);
    }

    /**
     * @param  array<string, mixed>  $mail
     */
    public function saveMail(array $mail): void
    {
        $keys = app(KeyRing::class);

        $this->put(self::MAIL, $keys->seal(json_encode($mail, JSON_THROW_ON_ERROR), $keys->platformKey()));
    }

    public function clearMail(): void
    {
        $this->put(self::MAIL, null);
        $this->put(self::MAIL_TESTED, null);
    }

    /**
     * Remember that a test email went out with the settings as they are now.
     */
    public function markMailTested(): void
    {
        $this->put(self::MAIL_TESTED, $this->mailFingerprint());
    }

    /**
     * Whether a test email has gone out with exactly the settings saved now. Changing any of them undoes it.
     */
    public function mailTestSucceeded(): bool
    {
        $fingerprint = $this->mailFingerprint();

        return $fingerprint !== null && $this->get(self::MAIL_TESTED) === $fingerprint;
    }

    private function mailFingerprint(): ?string
    {
        $mail = $this->mail();

        return $mail === null ? null : hash_hmac('sha256', json_encode($mail, JSON_THROW_ON_ERROR), app(KeyRing::class)->blindIndexKey());
    }

    private function get(string $key): ?string
    {
        return $this->values()[$key] ?? null;
    }

    /**
     * Save a setting. The change is logged with who made it, never with the value, which may be a secret.
     */
    private function put(string $key, ?string $value): void
    {
        $this->values();

        DB::table('platform_settings')->upsert(
            [['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );

        $this->values[$key] = $value;

        Log::info('Instance setting changed.', ['setting' => $key, 'user_id' => Auth::id()]);
    }

    /**
     * @return array<string, string|null>
     */
    private function values(): array
    {
        // A fresh install reads settings before its first migration has run.
        return $this->values ??= Schema::hasTable('platform_settings')
            ? DB::table('platform_settings')->pluck('value', 'key')->all()
            : [];
    }
}
