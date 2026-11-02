<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateHousehold
{
    public function __construct(private AddUserToHousehold $addUserToHousehold)
    {
        //
    }

    /**
     * Create a new household and make the user its first owner, with a member carrying their name.
     */
    public function handle(User $user, string $name, string $mainCurrency, string $timezone): Household
    {
        return DB::transaction(function () use ($user, $name, $mainCurrency, $timezone) {
            $household = Household::create([
                'name' => $name,
                'main_currency' => $mainCurrency,
                'timezone' => $timezone,
            ]);

            $this->addUserToHousehold->handle($household, $user, HouseholdRole::Owner);

            $user->switchHousehold($household);

            return $household;
        });
    }
}
