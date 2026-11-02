<?php

namespace App\Rules;

use Illuminate\Validation\Rule;
use Symfony\Component\Intl\Currencies;

class HouseholdSettingRules
{
    /**
     * Get the validation rules for a household's name.
     *
     * @return array<int, mixed>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules for a household's main currency, an ISO 4217 code.
     *
     * @return array<int, mixed>
     */
    public static function mainCurrency(): array
    {
        return ['required', 'string', Rule::in(array_keys(Currencies::getNames()))];
    }

    /**
     * Get the validation rules for a household's time zone.
     *
     * @return array<int, mixed>
     */
    public static function timezone(): array
    {
        return ['required', 'string', 'timezone:all'];
    }
}
