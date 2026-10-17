<?php

namespace App\Support\Crypto\Casts;

use App\Models\Scopes\HouseholdScope;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\DecryptedValues;
use App\Support\Crypto\FieldContext;
use App\Support\Crypto\HouseholdCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Encrypts one household field: `'name' => Encrypted::class`.
 *
 * The model needs ULID keys, a household_id and a key_version column. Values
 * are decrypted only when the attribute is read. Encrypting needs the row's
 * ULID, household and key version before insert, so for a new row the cast
 * makes the ULID, takes the bound current household and the household's newest
 * key version, and returns them with the ciphertext. An existing row keeps its
 * key version, so all of its fields stay under one key until the rotation job
 * re-encrypts them (#140).
 *
 * @implements CastsAttributes<string|null, string|int|float|null>
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

        $plaintext = app(HouseholdCipher::class)->decrypt((string) $value, $this->context($model, $key, $attributes));
        app(DecryptedValues::class)->add($plaintext);

        return $plaintext;
    }

    /**
     * Returns the ciphertext and, when they were missing, the ULID, household
     * and key version. They must be returned rather than set on the model:
     * Eloquent merges the result into a copy of the attributes taken before this
     * method runs, so anything set on the model here would be lost.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $extra = [];
        $keyName = $model->getKeyName();

        if (($attributes[$keyName] ?? null) === null) {
            $attributes[$keyName] = $extra[$keyName] = $model->newUniqueId();
        }

        if (($attributes['household_id'] ?? null) === null) {
            if (! app()->bound(HouseholdScope::CONTAINER_KEY)) {
                throw new RuntimeException("Set household_id before the encrypted field {$model->getTable()}.{$key}.");
            }
            $attributes['household_id'] = $extra['household_id'] = (string) app(HouseholdScope::CONTAINER_KEY);
        }

        if (($attributes['key_version'] ?? null) === null) {
            $attributes['key_version'] = $extra['key_version'] = app(DataKeyRing::class)->household((string) $attributes['household_id'])->version;
        }

        // A value just set is as sensitive as one just read.
        app(DecryptedValues::class)->add((string) $value);

        return [
            ...$extra,
            $key => app(HouseholdCipher::class)->encrypt((string) $value, $this->context($model, $key, $attributes)),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function context(Model $model, string $key, array $attributes): FieldContext
    {
        $rowId = $attributes[$model->getKeyName()] ?? null;
        $householdId = $attributes['household_id'] ?? null;
        $version = $attributes['key_version'] ?? null;

        if (! is_scalar($rowId) || ! is_scalar($householdId) || ! is_numeric($version)) {
            throw new RuntimeException("{$model->getTable()}.{$key} needs the row's id, household_id and key_version.");
        }

        return new FieldContext((string) $householdId, (int) $version, (string) $rowId, $model->getTable(), $key);
    }
}
