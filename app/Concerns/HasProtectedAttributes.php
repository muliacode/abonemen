<?php

namespace App\Concerns;

use App\Casts\ProtectedAttribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Lets a model hold protected attributes declared with the ProtectedAttribute cast.
 *
 * A value set before its Household is known, for example by
 * $household->members()->create([...]) which fills the model before it sets
 * household_id, waits in memory and is sealed just before the model is saved.
 */
trait HasProtectedAttributes
{
    protected static function bootHasProtectedAttributes(): void
    {
        static::saving(function (Model $model) {
            foreach ($model->getCasts() as $attribute => $cast) {
                if (! str_starts_with($cast, ProtectedAttribute::class)) {
                    continue;
                }

                $stored = $model->getAttributes()[$attribute] ?? null;

                if (ProtectedAttribute::isWaiting($stored)) {
                    $model->setAttribute($attribute, ProtectedAttribute::waitingValue($stored));
                }
            }
        });
    }
}
