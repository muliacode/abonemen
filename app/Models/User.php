<?php

namespace App\Models;

use App\Casts\ProtectedAttribute;
use App\Concerns\HasHouseholds;
use App\Protection\BlindIndex;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $email_index
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_household_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Household|null $currentHousehold
 * @property-read Collection<int, Household> $households
 * @property-read Collection<int, Member> $members
 */
#[Fillable(['name', 'email', 'password', 'current_household_id'])]
#[Hidden(['email_index', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasHouseholds, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => ProtectedAttribute::class.':platform',
            'email' => ProtectedAttribute::class.':platform,email_index',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Find users whose email is exactly the given address, ignoring case.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereEmail(Builder $query, string $email): void
    {
        $query->where('email_index', BlindIndex::of($email));
    }

    /**
     * Get the identifier stored in password reset tokens: the blind index, never the readable email.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email_index;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
