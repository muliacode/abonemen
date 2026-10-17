<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A data key wrapped by the master key and sealed to the recovery key: the
 * global data key (GDK) or one version of a household data key (HDK).
 *
 * Only App\Support\Crypto reads and writes these rows; it never stores the
 * unwrapped key. There is no household_id column on purpose: data keys are
 * looked up across households, outside the household scope.
 *
 * @property string $id
 * @property string $owner_type
 * @property string $owner_id
 * @property int $version
 * @property string $wrapped_mk
 * @property string|null $wrapped_rk
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['owner_type', 'owner_id', 'version', 'wrapped_mk', 'wrapped_rk', 'status'])]
#[Hidden(['wrapped_mk', 'wrapped_rk'])]
class DataKey extends Model
{
    use HasUlids;

    public const OWNER_GLOBAL = 'global';

    public const OWNER_HOUSEHOLD = 'household';

    /** owner_id of the global data key, which belongs to the instance. */
    public const INSTANCE = 'instance';

    public const STATUS_ACTIVE = 'active';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }
}
