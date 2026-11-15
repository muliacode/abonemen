<?php

namespace App\Models;

use App\Enums\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Owner's consent to one Connection for a Household, with what they were shown when they gave it.
 *
 * @property int $id
 * @property int $household_id
 * @property Connection $connection
 * @property int|null $enabled_by_user_id
 * @property string $confirmed_destination
 * @property int $confirmed_version
 * @property Carbon $enabled_at
 */
#[Fillable(['household_id', 'connection', 'enabled_by_user_id', 'confirmed_destination', 'confirmed_version', 'enabled_at'])]
class HouseholdConnection extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection' => Connection::class,
            'enabled_at' => 'datetime',
        ];
    }

    /**
     * The consents that still match what is disclosed now. With a destination they must also match where it goes;
     * without one (mail does not deliver) only the disclosure version is compared.
     *
     * @param  Builder<HouseholdConnection>  $query
     */
    public function scopeWithCurrentConsent(Builder $query, Connection $connection, ?string $destination): void
    {
        $query->where('connection', $connection)
            ->where('confirmed_version', $connection->disclosureVersion())
            ->when($destination !== null, fn (Builder $query) => $query->where('confirmed_destination', $destination));
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
