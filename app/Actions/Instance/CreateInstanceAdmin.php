<?php

namespace App\Actions\Instance;

use App\Actions\Households\CreateStarterHousehold;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateInstanceAdmin
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateStarterHousehold $createStarterHousehold)
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
     * Validate the details and make a verified User who can run the instance.
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

            $this->createStarterHousehold->handle($admin);

            return $admin;
        });
    }
}
