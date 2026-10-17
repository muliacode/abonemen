<?php

namespace App\Support\Crypto\Casts;

use App\Models\Scopes\HouseholdScope;
use App\Support\Crypto\HouseholdCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Encrypts one household field (`'name' => Encrypted::class`).
 *
 * Values are decrypted lazily, only when the attribute is read. Encrypting
 * needs the row's ULID and household before insert: the cast generates the
 * ULID if it is missing and takes the household from the attribute or, for a
 * new row, from the bound current household.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class Encrypted implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return app(HouseholdCipher::class)->decrypt(
            (string) $value,
            (string) $attributes['household_id'],
            (string) $attributes[$model->getKeyName()],
            $model->getTable(),
            $key,
        );
    }

    /**
     * Returns the ciphertext and, when they were missing, the generated ULID and
     * the current household. They must be returned rather than set on the model:
     * Eloquent merges the result into a copy of the attributes taken before this
     * method runs, so anything set on the model here would be lost.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $keyName = $model->getKeyName();
        $extra = [];

        $rowId = $attributes[$keyName] ?? null;
        if ($rowId === null) {
            $rowId = $extra[$keyName] = $model->newUniqueId();
        }

        $householdId = $attributes['household_id'] ?? null;
        if ($householdId === null && app()->bound(HouseholdScope::CONTAINER_KEY)) {
            $householdId = $extra['household_id'] = (string) app(HouseholdScope::CONTAINER_KEY);
        }
        if ($householdId === null) {
            throw new RuntimeException("Set household_id before the encrypted field {$key}.");
        }

        return [
            ...$extra,
            $key => app(HouseholdCipher::class)->encrypt(
                (string) $value,
                (string) $householdId,
                (string) $rowId,
                $model->getTable(),
                $key,
            ),
        ];
    }
}
