<?php

namespace App\Actions\Accounts;

use App\Models\User;

/**
 * Finds the account with an email address. Emails are encrypted, so the lookup
 * goes through a 32-bit blind index; it can match a few other accounts by
 * chance, so each candidate is decrypted and compared, ignoring case and
 * Unicode form the same way the index does.
 */
final class FindUserByEmail
{
    public function __invoke(string $email): ?User
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $matches = User::query()
            ->whereBlind('email', User::EMAIL_INDEX, $email)
            ->get()
            ->filter(fn (User $user): bool => User::sameEmail($user->email, $email));

        // The User model keeps emails unique; should two accounts ever share
        // one, sign in neither rather than whichever comes first.
        return $matches->count() === 1 ? $matches->first() : null;
    }
}
