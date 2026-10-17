<?php

namespace App\Providers\Auth;

use App\Actions\Accounts\FindUserByEmail;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use LogicException;

/**
 * Laravel's Eloquent user provider, except that it finds users by email
 * through the blind index: the email column holds ciphertext, so the stock
 * provider's `where('email', …)` would never match.
 */
class EncryptedEmailUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (! array_key_exists('email', $credentials)) {
            return parent::retrieveByCredentials($credentials);
        }

        $email = $credentials['email'];
        if (! is_string($email)) {
            throw new LogicException('Sign-in by email needs one email address as a string.');
        }

        $user = app(FindUserByEmail::class)($email);
        if ($user === null) {
            return null;
        }

        // Any other credential (never the password) must match as well.
        foreach ($credentials as $key => $value) {
            if ($key === 'email' || str_contains($key, 'password')) {
                continue;
            }
            if (! is_scalar($value) || $user->getAttribute($key) != $value) {
                return null;
            }
        }

        return $user;
    }
}
