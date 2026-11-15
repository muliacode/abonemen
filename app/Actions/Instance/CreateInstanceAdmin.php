<?php

namespace App\Actions\Instance;

use App\Actions\Households\CreateStarterHousehold;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Locale;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateInstanceAdmin
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateStarterHousehold $createStarterHousehold, private PlatformSettings $settings)
    {
        //
    }

    /**
     * Whether the instance still has nobody who can run it.
     */
    public static function isOpen(): bool
    {
        return ! User::where('is_instance_admin', true)->exists();
    }

    /**
     * Validate the details and make a verified User who can run the instance, with the choices they made for the
     * instance: whether its admin section needs a second factor (it does unless said otherwise) and whether anyone
     * can register (they cannot unless said otherwise). The defaults for new Households start as what the installer uses.
     *
     * The check and the insert share a transaction, so that two requests cannot both become the first admin.
     * Returns null when somebody else was first.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): ?User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            if (User::where('is_instance_admin', true)->lockForUpdate()->exists()) {
                return null;
            }

            $admin = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'locale' => app()->getLocale(),
            ]);

            $admin->forceFill(['is_instance_admin' => true, 'email_verified_at' => now()])->save();

            $this->settings->setRequireSecondFactor(filter_var($input['require_second_factor'] ?? true, FILTER_VALIDATE_BOOLEAN));
            $this->settings->openRegistration(filter_var($input['registration_open'] ?? false, FILTER_VALIDATE_BOOLEAN));
            $this->settings->setDefaults(
                Locale::tryFrom(app()->getLocale()) ?? Locale::English,
                'EUR',
                $this->validTimezone($input['timezone'] ?? null),
            );

            // After the defaults, so that the installer's own Household follows them.
            $this->createStarterHousehold->handle($admin);

            return $admin;
        });
    }

    /**
     * The time zone the installer's browser reported, or the server's when it is missing or not one that exists.
     */
    private function validTimezone(mixed $timezone): string
    {
        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) config('app.timezone');
    }
}
