<?php

namespace App\Models;

use App\Casts\ProtectedAttribute;
use App\Concerns\HasWaitingProtectedValues;
use App\Contracts\HoldsWaitingProtectedValues;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $household_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $nickname
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household $household
 * @property-read User|null $user
 */
#[Fillable(['household_id', 'user_id', 'name', 'nickname'])]
class Member extends Model implements HoldsWaitingProtectedValues
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasWaitingProtectedValues;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => ProtectedAttribute::class,
            'nickname' => ProtectedAttribute::class,
        ];
    }

    /**
     * Get what the Member is shown as: its nickname when it has one, otherwise its name.
     *
     * The label is unique within a Household.
     */
    public function label(): string
    {
        return $this->nickname ?? $this->name;
    }

    /**
     * Whether two labels are the same label: ignoring case and surrounding spaces, but not accents.
     */
    public static function sameLabel(string $first, string $second): bool
    {
        return mb_strtolower(trim($first)) === mb_strtolower(trim($second));
    }

    /**
     * Get the household this member belongs to.
     *
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the user who signs in as this member, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
