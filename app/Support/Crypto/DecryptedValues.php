<?php

namespace App\Support\Crypto;

/**
 * Every plaintext decrypted during the current request or job, so the log
 * redaction can scrub them from messages and exceptions wherever they ended
 * up, under any key or none (SEC-11).
 *
 * It lives only in memory and is emptied when a queued job starts or succeeds.
 * Values shorter than three characters are not kept: scrubbing them would
 * mangle unrelated text, and they say little on their own.
 */
final class DecryptedValues
{
    private const MIN_LENGTH = 3;

    /** Bounds memory in a long-running worker; the oldest values go first. */
    private const MAX_VALUES = 5000;

    /**
     * Keyed by "v" plus the value, so a numeric value never becomes an integer
     * key, which array functions renumber.
     *
     * @var array<string, string>
     */
    private array $values = [];

    /** @var list<string>|null Longest first; rebuilt only after a change. */
    private ?array $sorted = null;

    public function add(#[\SensitiveParameter] ?string $value): void
    {
        if ($value === null || mb_strlen($value) < self::MIN_LENGTH) {
            return;
        }

        $key = 'v'.$value;
        if (isset($this->values[$key])) {
            return;
        }

        $this->values[$key] = $value;
        $this->sorted = null;

        if (count($this->values) > self::MAX_VALUES) {
            unset($this->values[array_key_first($this->values)]);
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

        if ($this->sorted === null) {
            $sorted = array_values($this->values);
            usort($sorted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $this->sorted = $sorted;
        }

        return str_replace($this->sorted, '[redacted]', $text);
    }

    public function forget(): void
    {
        $this->values = [];
        $this->sorted = null;
    }
}
