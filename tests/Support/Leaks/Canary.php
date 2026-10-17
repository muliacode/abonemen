<?php

namespace Tests\Support\Leaks;

/**
 * A known value written to a sensitive field, so a scan can prove it never
 * reaches the disk in a readable form.
 */
final readonly class Canary
{
    /**
     * @param  string  $field  The field it was written to, such as "users.email".
     * @param  array<string, string>  $forms  Encodings to search for, by name.
     */
    private function __construct(
        public string $field,
        public string $value,
        public array $forms,
    ) {}

    /**
     * A text canary. Besides the value itself, the scan looks for the forms a
     * value takes when it is serialised, escaped or normalised on its way to
     * disk.
     */
    public static function text(string $field, string $value): self
    {
        $json = substr((string) json_encode($value), 1, -1);

        return new self($field, $value, array_filter([
            'raw' => $value,
            'lowercase' => mb_strtolower($value),
            'uppercase' => mb_strtoupper($value),
            'JSON-escaped' => $json,
            'URL-encoded' => rawurlencode($value),
            'form-encoded' => urlencode($value),
            'HTML-escaped' => htmlspecialchars($value, ENT_QUOTES),
            'base64' => base64_encode($value),
            'UTF-16' => mb_convert_encoding($value, 'UTF-16LE', 'UTF-8'),
        ], fn (string $form, string $name): bool => $name === 'raw' || $form !== $value, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * An amount canary, given in minor units. The scan looks for it with a
     * decimal point, with a decimal comma and as an integer of minor units.
     * Pick an unusual amount of at least seven digits, so random bytes do
     * not match it.
     */
    public static function amount(string $field, int $minorUnits): self
    {
        $major = intdiv($minorUnits, 100);
        $minor = str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);

        return new self($field, "{$major}.{$minor}", [
            'decimal point' => "{$major}.{$minor}",
            'decimal comma' => "{$major},{$minor}",
            'minor units' => (string) $minorUnits,
        ]);
    }
}
