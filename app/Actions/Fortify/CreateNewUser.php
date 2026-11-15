<?php

namespace App\Actions\Fortify;

use App\Actions\Households\CreateStarterHousehold;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private PlatformSettings $settings, private CreateStarterHousehold $createStarterHousehold)
    {
        //
    }

    /**
     * Validate and create a newly registered user, with a household of their own.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        abort_unless($this->settings->registrationIsOpen(), 403);

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'locale' => app()->getLocale(),
            ]);

            $this->createStarterHousehold->handle($user);

            return $user;
        });
    }
}
