<?php

namespace Tests\Support\Crypto;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasEncryptedFields;
use App\Support\Crypto\Casts\Encrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * EncryptedNote's table and fields, with HasEncryptedFields booted before
 * BelongsToHousehold.
 *
 * @property string $id
 * @property string $household_id
 * @property int $key_version
 */
class EncryptedNoteTraitsReversed extends Model
{
    use BelongsToHousehold;
    use HasEncryptedFields;
    use HasUlids;

    protected $table = 'encrypted_notes';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => Encrypted::class,
            'key_version' => 'integer',
        ];
    }
}
