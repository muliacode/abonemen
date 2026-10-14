<?php

namespace Tests\Arch\Support;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class HouseholdScopeAudit
{
    /**
     * The models, out of the given classes, that have a household_id column
     * but do not use the BelongsToHousehold trait.
     *
     * @param  list<class-string<Model>>  $models
     * @return list<class-string<Model>>
     */
    public static function unscoped(array $models): array
    {
        return array_values(array_filter(
            $models,
            fn (string $model): bool => Schema::hasColumn((new $model)->getTable(), 'household_id')
                && ! in_array(BelongsToHousehold::class, class_uses_recursive($model), true),
        ));
    }
}
