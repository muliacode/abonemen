<?php

namespace App\Models;

use App\Casts\ProtectedAttribute;
use App\Concerns\HasWaitingProtectedValues;
use App\Contracts\HoldsWaitingProtectedValues;
use App\Enums\HouseholdRole;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $main_currency
 * @property string $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Member> $members
 */
#[Fillable(['name', 'main_currency', 'timezone'])]
#[Hidden(['data_key'])]
class Household extends Model implements HoldsWaitingProtectedValues
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory, HasWaitingProtectedValues;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Household $household) {
            $household->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => ProtectedAttribute::class,
        ];
    }

    /**
     * Get the people in this household.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Hold every other change to this household's Members back until the current transaction ends.
     *
     * Rules about the Members as a whole (a unique label, always an Owner) are checked in memory, so changes must
     * not interleave. Call it first inside a transaction.
     */
    public function lockMembers(): void
    {
        static::whereKey($this->getKey())->lockForUpdate()->first();
    }

    /**
     * Whether a User other than the given Member's holds the Owner role here.
     */
    public function hasOwnerOtherThan(Member $member): bool
    {
        return $this->members()
            ->whereNotNull('user_id')
            ->whereKeyNot($member->getKey())
            ->with('user')
            ->get()
            ->contains(fn (Member $other) => $other->user->householdRole($this) === HouseholdRole::Owner);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
