<?php

namespace App\Billing;

use InvalidArgumentException;

/**
 * What cancelling inside a Commitment is expected to cost.
 */
final readonly class EarlyCancellationFee
{
    private function __construct(
        private ?Money $fixed,
        private int $percent,
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
     * A share of the payments still to be made inside the Commitment, from 0 to 100 percent.
     */
    public static function percentageOfRemainingPayments(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('A percentage fee is between 0 and 100.');
        }

        return new self(null, $percent);
    }

    /**
     * Work out what cancelling costs, given the price of one payment and how many remain.
     */
    public function cost(Money $payment, int $paymentsRemaining): Money
    {
        if ($paymentsRemaining < 0) {
            throw new InvalidArgumentException('The number of remaining payments cannot be negative.');
        }

        return $this->fixed ?? $payment->times($paymentsRemaining * $this->percent, 100);
    }
}
