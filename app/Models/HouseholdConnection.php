<?php

namespace App\Models;

use App\Enums\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
