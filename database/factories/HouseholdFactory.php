<?php

namespace Database\Factories;

use App\Actions\Households\AddUserToHousehold;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'main_currency' => 'EUR',
            'timezone' => 'Europe/Amsterdam',
        ];
    }

    /**
     * Add the user to the household with the given role, through a new member.
     */
    public function withUser(User $user, HouseholdRole $role = HouseholdRole::Owner): static
    {
        return $this->afterCreating(function (Household $household) use ($user, $role) {
            app(AddUserToHousehold::class)->handle($household, $user, $role);

            if ($user->current_household_id === null) {
                $user->switchHousehold($household);
            }
        });
    }
}
