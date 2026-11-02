<?php

namespace App\Billing;

use InvalidArgumentException;
use Symfony\Component\Intl\Currencies;

final readonly class Money
{
    private function __construct(
        public int $minor,
        public string $currency,
    ) {
        //
    }

    /**
     * Wrap an amount that is already in integer minor units.
     */
    public static function ofMinor(int $minor, string $currency): self
    {
        self::fractionDigits($currency);

        if ($minor < 0) {
            throw new InvalidArgumentException('An amount of money cannot be negative.');
        }

        return new self($minor, $currency);
    }

    /**
     * Multiply by a fraction, rounding half a minor unit up.
     */
    public function times(int $numerator, int $denominator): self
    {
        if ($numerator < 0 || $denominator < 1) {
            throw new InvalidArgumentException('Multiply by a non-negative numerator over a positive denominator.');
        }

        return new self(intdiv(2 * $this->minor * $numerator + $denominator, 2 * $denominator), $this->currency);
    }

    /**
     * Read a decimal amount such as "15.99" into integer minor units.
     *
     * The currency decides how many decimal places are allowed: 0 for JPY, 2 for EUR, 3 for KWD.
     */
    public static function parse(string $amount, string $currency): self
    {
        $digits = self::fractionDigits($currency);

        if (! preg_match('/^(\d+)(?:\.(\d+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("\"{$amount}\" is not a plain non-negative decimal amount.");
        }

        $whole = $matches[1];
        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > $digits) {
            throw new InvalidArgumentException("{$currency} has {$digits} decimal places, got \"{$amount}\".");
        }

        return new self((int) ($whole.str_pad($fraction, $digits, '0')), $currency);
    }

    /**
     * Get the number of minor-unit digits of an ISO 4217 currency.
     */
    private static function fractionDigits(string $currency): int
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency) || ! Currencies::exists($currency)) {
            throw new InvalidArgumentException("\"{$currency}\" is not an ISO 4217 currency code.");
        }

        return Currencies::getFractionDigits($currency);
    }
}
