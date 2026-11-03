<?php

namespace App\Casts;

use App\Contracts\HoldsWaitingProtectedValues;
use App\Models\Household;
use App\Protection\BlindIndex;
use App\Protection\KeyRing;
use App\Protection\ProtectionException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Marks a model attribute as protected: stored encrypted, read decrypted.
 *
 * Declare it in a model's casts. The default protects the value with the key of
 * the Household the model belongs to (a Household itself, or any model with a
 * household_id). Add ":platform" for data that is not part of one Household,
 * such as a sign-in email:
 *
 *     'name' => ProtectedAttribute::class,
 *     'email' => ProtectedAttribute::class.':platform',
 *
 * Values are strings unless a third argument names another type, "int" or "json"
 * (leave the second empty when there is no blind index). Amounts and structured
 * details are protected the same way:
 *
 *     'price_minor' => ProtectedAttribute::class.':household,,int',
 *     'details' => ProtectedAttribute::class.':household,,json',
 *
 * Data that must be found again by exact match, such as an email, also names a
 * column for its blind index, which is kept in step with the value:
 *
 *     'email' => ProtectedAttribute::class.':platform,email_index',
 *
 * Because the stored value is ciphertext, never sort, filter or sum a protected
 * column in SQL. Load the Household's rows and do it in memory.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
final class ProtectedAttribute implements CastsAttributes, ComparesCastableAttributes
{
    public const HOUSEHOLD = 'household';

    public const PLATFORM = 'platform';

    public function __construct(
        private string $scope = self::HOUSEHOLD,
        private ?string $indexColumn = null,
        private string $type = 'string',
    ) {
        $this->indexColumn = $indexColumn === '' ? null : $indexColumn;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            $waiting = $model instanceof HoldsWaitingProtectedValues ? $model->waitingProtectedValue($key) : null;

            return $waiting === null ? null : $this->decode($waiting);
        }

        return $this->decode($this->keyRing()->open($value, $this->keyFor($model)));
    }

    /**
     * @return string|array<string, string|null>|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|array|null
    {
        if ($value === null) {
            return null;
        }

        $value = $this->encode($value);

        // A new Household has no data key yet: make it now and store it with the value.
        if ($this->scope !== self::PLATFORM && $model instanceof Household && empty($attributes['data_key'])) {
            [$householdKey, $wrapped] = $this->keyRing()->newHouseholdKey();

            return [
                $key => $this->keyRing()->seal($value, $householdKey),
                'data_key' => $wrapped,
            ];
        }

        if ($this->householdNotKnownYet($model)) {
            if (! $model instanceof HoldsWaitingProtectedValues) {
                throw new LogicException(class_basename($model).' must implement HoldsWaitingProtectedValues (use HasProtectedAttributes) to hold protected attributes.');
            }

            $model->holdWaitingProtectedValue($key, $value);

            return null;
        }

        $sealed = $this->keyRing()->seal($value, $this->keyFor($model));

        return $this->indexColumn === null
            ? $sealed
            : [$key => $sealed, $this->indexColumn => BlindIndex::of($value)];
    }

    /**
     * Two stored values are the same when they hold the same plaintext, however they were encrypted.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        if ($firstValue === $secondValue) {
            return true;
        }

        if ($firstValue === null || $secondValue === null) {
            return false;
        }

        return $this->get($model, $key, $firstValue, []) === $this->get($model, $key, $secondValue, []);
    }

    /**
     * Turn a value into the text that gets sealed.
     */
    private function encode(mixed $value): string
    {
        return match ($this->type) {
            'int' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))
                ? (string) $value
                : throw new InvalidArgumentException('A protected integer must be a whole number.'),
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    /**
     * Turn the text that was sealed back into a value.
     */
    private function decode(string $text): mixed
    {
        return match ($this->type) {
            'int' => (int) $text,
            'json' => json_decode($text, true, flags: JSON_THROW_ON_ERROR),
            default => $text,
        };
    }

    /**
     * A model that belongs to a Household may be filled before its household_id is set.
     */
    private function householdNotKnownYet(Model $model): bool
    {
        return $this->scope !== self::PLATFORM
            && ! $model instanceof Household
            && $model->getAttribute('household_id') === null;
    }

    private function keyFor(Model $model): string
    {
        if ($this->scope === self::PLATFORM) {
            return $this->keyRing()->platformKey();
        }

        if ($model instanceof Household) {
            $wrapped = $model->getAttributes()['data_key'] ?? null;

            if (! is_string($wrapped)) {
                throw ProtectionException::householdKeyNotLoaded();
            }

            return $this->keyRing()->householdKey($wrapped);
        }

        $householdId = $model->getAttribute('household_id');

        if ($householdId === null) {
            throw new LogicException(class_basename($model).' needs a household_id to protect its data with the Household key.');
        }

        return $this->keyRing()->householdKeyById((int) $householdId);
    }

    private function keyRing(): KeyRing
    {
        return app(KeyRing::class);
    }
}
