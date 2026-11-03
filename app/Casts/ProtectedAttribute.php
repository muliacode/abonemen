<?php

namespace App\Casts;

use App\Concerns\HasProtectedAttributes;
use App\Models\Household;
use App\Protection\BlindIndex;
use App\Protection\KeyRing;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
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
 * Data that must be found again by exact match, such as an email, also names a
 * column for its blind index, which is kept in step with the value:
 *
 *     'email' => ProtectedAttribute::class.':platform,email_index',
 *
 * Because the stored value is ciphertext, never sort, filter or sum a protected
 * column in SQL. Load the Household's rows and do it in memory.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class ProtectedAttribute implements CastsAttributes, ComparesCastableAttributes
{
    /** Marks a value that is waiting for its Household to be known before it can be sealed. */
    private const WAITING = 'waiting-to-be-protected:';

    public function __construct(
        private string $scope = 'household',
        private ?string $indexColumn = null,
    ) {
        //
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (self::isWaiting($value)) {
            return self::waitingValue($value);
        }

        return $this->keyRing()->open($value, $this->keyFor($model));
    }

    /**
     * @return string|array<string, string|null>|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string|array|null
    {
        if ($value === null) {
            return null;
        }

        // A new Household has no data key yet: make it now and store it with the value.
        if ($this->scope !== 'platform' && $model instanceof Household && empty($attributes['data_key'])) {
            [$householdKey, $wrapped] = $this->keyRing()->newHouseholdKey();

            return [
                $key => $this->keyRing()->seal((string) $value, $householdKey),
                'data_key' => $wrapped,
            ];
        }

        if ($this->mustWaitForHousehold($model)) {
            return self::WAITING.$value;
        }

        $sealed = $this->keyRing()->seal((string) $value, $this->keyFor($model));

        return $this->indexColumn === null
            ? $sealed
            : [$key => $sealed, $this->indexColumn => BlindIndex::of((string) $value)];
    }

    /**
     * Determine if a stored value is still waiting to be sealed.
     */
    public static function isWaiting(mixed $stored): bool
    {
        return is_string($stored) && str_starts_with($stored, self::WAITING);
    }

    /**
     * Get the plain value of a stored value that is still waiting to be sealed.
     */
    public static function waitingValue(string $stored): string
    {
        return substr($stored, strlen(self::WAITING));
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
     * A model that belongs to a Household may be filled before its household_id is set.
     */
    private function mustWaitForHousehold(Model $model): bool
    {
        if ($this->scope === 'platform' || $model instanceof Household || $model->getAttribute('household_id') !== null) {
            return false;
        }

        if (! in_array(HasProtectedAttributes::class, class_uses_recursive($model), true)) {
            throw new LogicException(class_basename($model).' must use HasProtectedAttributes to hold protected attributes.');
        }

        return true;
    }

    private function keyFor(Model $model): string
    {
        if ($this->scope === 'platform') {
            return $this->keyRing()->platformKey();
        }

        if ($model instanceof Household) {
            return $this->keyRing()->householdKey((string) $model->getAttributes()['data_key']);
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
