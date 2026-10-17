<?php

namespace App\Support\Crypto\Concerns;

use App\Support\Crypto\DecryptedValues;
use App\Support\Crypto\Transformations\CaseFolded;
use Illuminate\Database\Eloquent\Model;
use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\EncryptedRow;
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
    use UsesCipherSweet {
        decryptRow as decryptRowWithCipherSweet;
    }

    /** Bits kept from each blind index; matches are confirmed by decrypting. */
    public const BLIND_INDEX_BITS = 32;

    /**
     * @return array<string, string|null>
     */
    abstract public static function accountEncryptedFields(): array;

    /**
     * Spatie encrypts on "saving", but HasUlids only sets the key on
     * "creating", which comes later. Without this, a new row's fields would be
     * bound to an empty id. Booted before UsesCipherSweet, so it runs first;
     * a model that has to check its plaintext before saving, such as for a
     * unique email, does so in beforeEncrypting().
     */
    protected static function bootEncryptsAccountFields(): void
    {
        static::saving(function (Model $model): void {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), $model->newUniqueId());
            }

            // The last moment the fields still hold plaintext.
            if (method_exists($model, 'beforeEncrypting')) {
                $model->beforeEncrypting();
            }
        });
    }

    /**
     * Decrypts the row, and records the plaintext so the log redaction can
     * scrub it (SEC-11).
     */
    public function decryptRow(): void
    {
        $this->decryptRowWithCipherSweet();

        foreach (array_keys(static::accountEncryptedFields()) as $column) {
            $value = $this->getAttributes()[$column] ?? null;
            app(DecryptedValues::class)->add(is_string($value) ? $value : null);
        }
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
                    new BlindIndex($index, [new CaseFolded], self::BLIND_INDEX_BITS, true),
                );
            }
        }
    }
}
