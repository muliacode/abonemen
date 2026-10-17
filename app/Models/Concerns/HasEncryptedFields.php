<?php

namespace App\Models\Concerns;

use App\Models\Scopes\HouseholdScope;
use App\Support\Crypto\Casts\Encrypted;
use App\Support\Crypto\DataKeyRing;
use Illuminate\Database\Eloquent\Model;

/**
 * For models with Encrypted fields: keeps them out of toArray() and toJson(),
 * so a decrypted value only reaches a page, a log or an API response when code
 * asks for it by name (SEC-11). A new row whose encrypted fields are all null
 * still gets the household's newest key version, which the cast sets otherwise.
 *
 * @mixin Model
 */
trait HasEncryptedFields
{
    protected static function bootHasEncryptedFields(): void
    {
        static::creating(function (Model $model): void {
            // The bound household too, like the cast: BelongsToHousehold may
            // only fill household_id after this hook, depending on trait order.
            $householdId = $model->getAttribute('household_id')
                ?? (app()->bound(HouseholdScope::CONTAINER_KEY) ? app(HouseholdScope::CONTAINER_KEY) : null);

            if ($model->getAttribute('key_version') === null && is_scalar($householdId)) {
                $model->setAttribute('key_version', app(DataKeyRing::class)->household((string) $householdId)->version);
            }
        });

        static::saving(fn (Model $model) => Encrypted::assertStillBound($model));
        static::saved(fn (Model $model) => Encrypted::forgetContexts($model));
    }

    public function initializeHasEncryptedFields(): void
    {
        $encrypted = array_keys(array_filter(
            $this->getCasts(),
            fn (string $cast): bool => $cast === Encrypted::class,
        ));

        $this->makeHidden($encrypted);
    }
}
