<?php

namespace App\Actions\Households;

use App\Models\Household;
use App\Models\User;
use App\Support\PlatformSettings;

class CreateStarterHousehold
{
    public function __construct(private CreateHousehold $createHousehold, private PlatformSettings $settings)
    {
        //
    }

    /**
     * Give a User their first Household, named after them and set up the way the Instance Admin chose for new
     * Households, so that they land somewhere they can start.
     * Whoever signs up on their own, or installs the instance, gets one; a User an admin creates makes their own.
     */
    public function handle(User $user): Household
    {
        return $this->createHousehold->handle(
            $user,
            __(":name's household", ['name' => $user->name]),
            $this->settings->defaultCurrency(),
            $this->settings->defaultTimezone(),
        );
    }
}
