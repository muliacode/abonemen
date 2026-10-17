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
use WeakMap;

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
    /** @var WeakMap<Model, array<string, FieldContext>>|null */
    private static ?WeakMap $contexts = null;

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

        // An existing row must have been loaded with what binds its fields:
        // guessing them would tie this field to another household or version
        // than the row's other fields, and they would no longer decrypt.
        if ($model->exists) {
            foreach ([$keyName, 'household_id', 'key_version'] as $required) {
                if (($attributes[$required] ?? null) === null) {
                    throw new RuntimeException("Load {$model->getTable()}.{$required} before setting the encrypted field {$key}.");
                }
            }
        }

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

        $context = $this->context($model, $key, $attributes);
        self::remember($model, $context);

        return [
            ...$extra,
            $key => app(HouseholdCipher::class)->encrypt((string) $value, $context),
        ];
    }

    /**
     * Throws when a field was encrypted for another row id, household or key
     * version than the model now has, for example when household_id was set
     * after the field. HasEncryptedFields calls this before every save.
     *
     * @throws RuntimeException
     */
    public static function assertStillBound(Model $model): void
    {
        $contexts = self::contexts()[$model] ?? [];

        foreach ($contexts as $column => $context) {
            $now = [
                (string) $model->getKey(),
                (string) $model->getAttribute('household_id'),
                (int) $model->getAttribute('key_version'),
            ];

            if ($now !== [$context->rowId, $context->householdId, $context->keyVersion]) {
                throw new RuntimeException("{$model->getTable()}.{$column} was encrypted for another row, household or key version than the row now has; set household_id before encrypted fields.");
            }
        }
    }

    public static function forgetContexts(Model $model): void
    {
        $contexts = self::contexts();
        unset($contexts[$model]);
    }

    private static function remember(Model $model, FieldContext $context): void
    {
        $contexts = self::contexts();
        $contexts[$model] = [...($contexts[$model] ?? []), $context->column => $context];
    }

    /**
     * What each field of each model in memory was encrypted for, without
     * keeping the models alive.
     *
     * @return WeakMap<Model, array<string, FieldContext>>
     */
    private static function contexts(): WeakMap
    {
        return self::$contexts ??= new WeakMap;
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
