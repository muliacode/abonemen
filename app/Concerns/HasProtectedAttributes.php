<?php

namespace App\Concerns;

use App\Contracts\HoldsWaitingProtectedValues;
use Illuminate\Database\Eloquent\Model;

/**
 * Lets a model hold protected attributes declared with the ProtectedAttribute cast.
 *
 * The model must also implement HoldsWaitingProtectedValues.
 *
 * A value set before its Household is known, for example by
 * $household->members()->create([...]) which fills the model before it sets
 * household_id, is kept in memory only and sealed just before the model is saved.
 * Until then the stored attribute stays null, so a save that skips model events
 * fails on the empty column instead of writing the value readable.
 */
trait HasProtectedAttributes
{
    /** @var array<string, string> Values waiting for their Household, by attribute. */
    private array $waitingProtectedValues = [];

    protected static function bootHasProtectedAttributes(): void
    {
        static::saving(function (Model $model) {
            if (! $model instanceof HoldsWaitingProtectedValues) {
                return;
            }

            foreach ($model->takeWaitingProtectedValues() as $attribute => $value) {
                $model->setAttribute($attribute, $value);
            }
        });
    }

    /**
     * Keep a value in memory until the model knows its Household.
     */
    public function holdWaitingProtectedValue(string $attribute, string $value): void
    {
        $this->waitingProtectedValues[$attribute] = $value;
    }

    /**
     * Get a value that is still waiting for its Household, if any.
     */
    public function waitingProtectedValue(string $attribute): ?string
    {
        return $this->waitingProtectedValues[$attribute] ?? null;
    }

    /**
     * Hand over and forget the values that are waiting to be sealed.
     *
     * @return array<string, string>
     */
    public function takeWaitingProtectedValues(): array
    {
        $waiting = $this->waitingProtectedValues;
        $this->waitingProtectedValues = [];

        return $waiting;
    }
}
