<?php

namespace Tests\Support\Crypto;

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasEncryptedFields;
use App\Support\Crypto\Casts\Encrypted;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A household model with encrypted fields, shaped like the real ones will be
 * (ULID key, household_id, key_version), for testing the Encrypted cast before
 * the first real model exists.
 *
 * @property string $id
 * @property string $household_id
 * @property int $key_version
 * @property string|null $title
 * @property string|null $amount
 */
class EncryptedNote extends Model
{
    use BelongsToHousehold;
    use HasEncryptedFields;
    use HasUlids;

    protected $table = 'encrypted_notes';

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('encrypted_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('household_id', 26)->index();
            $table->unsignedInteger('key_version');
            $table->text('title')->nullable();
            $table->text('amount')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => Encrypted::class,
            'amount' => Encrypted::class,
            'key_version' => 'integer',
        ];
    }
}
