<?php

namespace App\Support\Crypto\Transformations;

use Normalizer;
use ParagonIE\CipherSweet\Contract\TransformationInterface;

/**
 * Blind-index transformation for values compared without regard to case or
 * Unicode form, such as email addresses: trimmed, NFC-normalised and
 * lowercased with mbstring. CipherSweet's own Lowercase uses strtolower(),
 * which leaves non-ASCII letters alone, so "Ä@example.org" and
 * "ä@example.org" would get different indexes.
 *
 * fold() is the same normalisation for comparing decrypted candidates, so the
 * index and the comparison can never disagree.
 */
final class CaseFolded implements TransformationInterface
{
    public function __invoke(#[\SensitiveParameter] mixed $input): string
    {
        return self::fold(is_scalar($input) ? (string) $input : '');
    }

    public static function fold(#[\SensitiveParameter] string $value): string
    {
        $value = trim($value);
        $normalised = Normalizer::normalize($value, Normalizer::FORM_C);

        return mb_strtolower(is_string($normalised) ? $normalised : $value, 'UTF-8');
    }
}
