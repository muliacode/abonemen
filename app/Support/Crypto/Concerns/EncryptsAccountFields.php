<?php

namespace App\Support\Crypto\Concerns;

use Illuminate\Database\Eloquent\Model;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
use ParagonIE\CipherSweet\Transformation\Lowercase;
use ReflectionClass;
use Spatie\LaravelCipherSweet\Concerns\UsesCipherSweet;

/**
 * Encrypts account-level fields under the global data key with
 * spatie/laravel-ciphersweet, so models declare their fields instead of
 * touching CipherSweet themselves.
 *
 * The model lists its fields in accountEncryptedFields(): column => blind
 * index name, or null for no index. Each field's associated data is the row's
 * ULID, so the model must use HasUlids. Unlike the household cast, the whole
 * row is decrypted when it is loaded; accounts are few.
 *
 * @mixin Model
 */
trait EncryptsAccountFields
{
    use UsesCipherSweet;

    /** Bits kept from each blind index; matches are confirmed by decrypting. */
    public const BLIND_INDEX_BITS = 32;

    /**
     * @return array<string, string|null>
     */
    abstract public static function accountEncryptedFields(): array;

    /**
     * Spatie encrypts on "saving", but HasUlids only sets the key on
     * "creating", which comes later. Without this, a new row's fields would be
     * bound to an empty id. Booted before UsesCipherSweet, so it runs first.
     */
    protected static function bootEncryptsAccountFields(): void
    {
        static::saving(function (Model $model): void {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), $model->newUniqueId());
            }
        });
    }

    public static function configureCipherSweet(EncryptedRow $encryptedRow): void
    {
        $model = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        foreach (static::accountEncryptedFields() as $column => $index) {
            $encryptedRow->addOptionalTextField($column, $model->getKeyName());

            if ($index !== null) {
                // The fast (keyed BLAKE2b) hash: the index key is derived from
                // the global data key, and whoever holds that can decrypt the
                // field itself, so a slow hash would only cost memory and time
                // on every sign-in.
                $encryptedRow->addBlindIndex(
                    $column,
                    new BlindIndex($index, [new Lowercase], self::BLIND_INDEX_BITS, true),
                );
            }
        }
    }
}
