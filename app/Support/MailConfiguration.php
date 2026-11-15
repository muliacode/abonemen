<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Decides which mail settings the app sends with: the ones an Instance Admin saved in the app, or else
 * the ones from .env.
 */
class MailConfiguration
{
    /** @var array<string, mixed>|null What .env set up, kept so that the admin can go back to it. */
    private ?array $environment = null;

    public function __construct(private PlatformSettings $settings)
    {
        //
    }

    /**
     * Use the saved settings, if there are any. Called when the app starts and after the admin changes them.
     *
     * Starting never fails because of this: with no database yet, or no table, there is nothing saved and
     * .env applies, which is what a fresh install needs for its first migration.
     */
    public function apply(): void
    {
        $this->environment ??= config('mail');

        try {
            $saved = $this->settings->mail();
        } catch (Throwable) {
            $saved = null;
        }

        config(['mail' => $this->environment]);

        if ($saved !== null) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.scheme' => $saved['scheme'],
                'mail.mailers.smtp.host' => $saved['host'],
                'mail.mailers.smtp.port' => $saved['port'],
                'mail.mailers.smtp.username' => $saved['username'],
                'mail.mailers.smtp.password' => $saved['password'],
                'mail.from.address' => $saved['from_address'],
                'mail.from.name' => $saved['from_name'],
            ]);
        }

        Mail::forgetMailers();
    }

    /**
     * Where the settings in use come from: "saved" in the app, "env" for a real mailer in .env, or
     * "undelivered" when the mailer only writes emails to a log or a test array and nobody receives them.
     */
    public function source(): string
    {
        if ($this->savedSettingsExist()) {
            return 'saved';
        }

        return in_array(config('mail.default'), ['log', 'array', null], true) ? 'undelivered' : 'env';
    }

    private function savedSettingsExist(): bool
    {
        try {
            return $this->settings->mail() !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
