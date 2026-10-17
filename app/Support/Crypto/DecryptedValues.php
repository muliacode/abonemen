<?php

namespace App\Support\Crypto;

/**
 * Every plaintext decrypted during the current request or job, so the log
 * redaction can scrub them from messages and exceptions wherever they ended
 * up, under any key or none (SEC-11).
 *
 * It lives only in memory and is emptied before and after every queued job.
 * Values shorter than three characters are not kept: scrubbing them would
 * mangle unrelated text, and they say little on their own.
 */
final class DecryptedValues
{
    private const MIN_LENGTH = 3;

    /** Bounds memory in a long-running worker; the oldest values go first. */
    private const MAX_VALUES = 5000;

    /** @var array<string, true> */
    private array $values = [];

    public function add(#[\SensitiveParameter] ?string $value): void
    {
        if ($value === null || mb_strlen($value) < self::MIN_LENGTH) {
            return;
        }

        unset($this->values[$value]);
        $this->values[$value] = true;

        if (count($this->values) > self::MAX_VALUES) {
            array_shift($this->values);
        }
    }

    /**
     * Replaces every known plaintext in the text, longest first, so a value
     * that contains another is scrubbed whole.
     */
    public function scrub(string $text): string
    {
        if ($this->values === [] || $text === '') {
            return $text;
        }

        $values = array_map('strval', array_keys($this->values));
        usort($values, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($values, '[redacted]', $text);
    }

    public function forget(): void
    {
        $this->values = [];
    }
}
