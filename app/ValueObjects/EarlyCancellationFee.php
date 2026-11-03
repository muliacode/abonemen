<?php

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * What cancelling inside a Commitment is expected to cost.
 */
final readonly class EarlyCancellationFee
{
    /**
     * @param  int  $hundredthsOfPercent  A share of the remaining payments, 10000 being 100%.
     */
    private function __construct(
        private ?Money $fixed,
        private int $hundredthsOfPercent,
    ) {
        //
    }

    public static function none(): self
    {
        return new self(null, 0);
    }

    public static function fixed(Money $amount): self
    {
        return new self($amount, 0);
    }

    /**
     * A share of the payments still to be made inside the Commitment, from 0 to 100 percent
     * with up to two decimals, such as 50 or "12.5". Pass decimals as a string to avoid float rounding.
     */
    public static function percentageOfRemainingPayments(int|string $percent): self
    {
        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', (string) $percent, $matches)) {
            throw new InvalidArgumentException("\"{$percent}\" is not a percentage with at most two decimals.");
        }

        $hundredths = (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');

        if ($hundredths > 10000) {
            throw new InvalidArgumentException('A percentage fee is between 0 and 100.');
        }

        return new self(null, $hundredths);
    }

    /**
     * Work out what cancelling costs, given the price of one payment and how many remain.
     */
    public function cost(Money $payment, int $paymentsRemaining): Money
    {
        if ($paymentsRemaining < 0) {
            throw new InvalidArgumentException('The number of remaining payments cannot be negative.');
        }

        return $this->fixed ?? $payment->times($paymentsRemaining * $this->hundredthsOfPercent, 10000);
    }
}
