<?php

namespace App\Protection;

/**
 * A keyed fingerprint of a value, so it can be found again by exact match without storing it readable.
 *
 * The same value always gives the same index, and without the master key an index reveals nothing
 * about the value. It can only answer "is it exactly this?", never "does it start with that?".
 */
final class BlindIndex
{
    /**
     * Get the index for a value, ignoring case and surrounding spaces.
     */
    public static function of(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), app(KeyRing::class)->blindIndexKey());
    }
}
