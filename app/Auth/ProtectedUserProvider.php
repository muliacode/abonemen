<?php

namespace App\Auth;

use App\Protection\BlindIndex;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Finds users by email through the blind index, because the stored email is encrypted.
 */
class ProtectedUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (isset($credentials['email']) && is_string($credentials['email'])) {
            $credentials['email_index'] = BlindIndex::of($credentials['email']);

            unset($credentials['email']);
        }

        return parent::retrieveByCredentials($credentials);
    }
}
