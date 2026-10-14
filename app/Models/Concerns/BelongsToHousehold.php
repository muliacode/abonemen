<?php

namespace App\Models\Concerns;

use App\Models\Scopes\HouseholdScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Required on every model with a household_id column (enforced by tests/Arch).
 *
 * @mixin Model
 */
trait BelongsToHousehold
{
    protected static function bootBelongsToHousehold(): void
    {
        static::addGlobalScope(new HouseholdScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('household_id') === null && app()->bound(HouseholdScope::CONTAINER_KEY)) {
                $model->setAttribute('household_id', app(HouseholdScope::CONTAINER_KEY));
            }
        });
    }
}
