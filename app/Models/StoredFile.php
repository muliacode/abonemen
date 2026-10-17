<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An encrypted upload, such as a logo. App\Support\Crypto\FileVault writes and
 * reads the file; this row holds what is needed to find and serve it.
 *
 * @property string $id
 * @property string $household_id
 * @property string $mime
 * @property int $size
 * @property string $path
 * @property int $key_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['household_id', 'mime', 'size', 'path', 'key_version'])]
#[Hidden(['path'])]
class StoredFile extends Model
{
    use BelongsToHousehold;
    use HasUlids;

    protected $table = 'files';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'key_version' => 'integer',
        ];
    }
}
