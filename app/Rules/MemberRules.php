<?php

namespace App\Rules;

use App\Enums\HouseholdRole;
use Illuminate\Validation\Rule;

class MemberRules
{
    /**
     * Get the validation rules for a Member's name.
     *
     * @return array<int, mixed>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules for a Member's nickname, which may be left out.
     *
     * @return array<int, mixed>
     */
    public static function nickname(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules for the role of a Member who can sign in.
     *
     * @return array<int, mixed>
     */
    public static function role(): array
    {
        return ['required', Rule::enum(HouseholdRole::class)];
    }
}
