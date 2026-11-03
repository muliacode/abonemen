<?php

namespace App\Contracts;

/**
 * A model that can keep a protected value in memory until it knows which Household's key seals it.
 *
 * Use the HasProtectedAttributes trait to implement it.
 */
interface HoldsWaitingProtectedValues
{
    public function holdWaitingProtectedValue(string $attribute, string $value): void;

    public function waitingProtectedValue(string $attribute): ?string;

    /**
     * @return array<string, string>
     */
    public function takeWaitingProtectedValues(): array;
}
