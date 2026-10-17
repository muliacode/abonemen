<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Crypto\Concerns\EncryptsAccountFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;

/**
 * An account. Email and name are encrypted under the global data key; the
 * email is found through its blind index (FindUserByEmail), never by a plain
 * query on the column.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'name', 'email'])]
class User extends Authenticatable implements CipherSweetEncrypted
{
    use EncryptsAccountFields;
    use HasUlids;
    use Notifiable;

    public const EMAIL_INDEX = 'email_index';

    /**
     * @return array<string, string|null>
     */
    public static function accountEncryptedFields(): array
    {
        return [
            'email' => self::EMAIL_INDEX,
            'name' => null,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
