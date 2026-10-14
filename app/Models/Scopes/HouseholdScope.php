<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query to the current household, which the HTTP layer will bind
 * as `household.id`. With no household bound it matches nothing, so a missing
 * household can never widen a query.
 *
 * @implements Scope<Model>
 */
class HouseholdScope implements Scope
{
    public const CONTAINER_KEY = 'household.id';

    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('household_id'), app(self::CONTAINER_KEY));
    }
}
