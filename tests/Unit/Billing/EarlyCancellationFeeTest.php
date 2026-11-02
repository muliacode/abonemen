<?php

namespace Tests\Unit\Billing;

use App\Billing\EarlyCancellationFee;
use App\Billing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EarlyCancellationFeeTest extends TestCase
{
    public function test_no_fee_costs_nothing_in_the_currency_of_the_payments(): void
    {
        $cost = EarlyCancellationFee::none()->cost(Money::parse('59.99', 'EUR'), 6);

        $this->assertSame(0, $cost->minor);
        $this->assertSame('EUR', $cost->currency);
    }

    public function test_a_fixed_fee_costs_the_same_however_many_payments_remain(): void
    {
        $fee = EarlyCancellationFee::fixed(Money::parse('50.00', 'EUR'));

        $this->assertSame(5000, $fee->cost(Money::parse('59.99', 'EUR'), 11)->minor);
        $this->assertSame(5000, $fee->cost(Money::parse('59.99', 'EUR'), 1)->minor);
    }

    public function test_a_percentage_fee_is_that_share_of_the_remaining_payments(): void
    {
        // 6 payments of 59.99 is 359.94; 100% of it is 359.94.
        $this->assertSame(35994, EarlyCancellationFee::percentageOfRemainingPayments(100)->cost(Money::parse('59.99', 'EUR'), 6)->minor);

        // 5 payments of 59.99 is 299.95; half of it is 149.975, which rounds up to 149.98.
        $this->assertSame(14998, EarlyCancellationFee::percentageOfRemainingPayments(50)->cost(Money::parse('59.99', 'EUR'), 5)->minor);
    }

    public function test_a_percentage_fee_works_in_a_currency_without_minor_units(): void
    {
        // 3 payments of 5000 yen is 15000 yen; half of it is 7500.
        $cost = EarlyCancellationFee::percentageOfRemainingPayments(50)->cost(Money::parse('5000', 'JPY'), 3);

        $this->assertSame(7500, $cost->minor);
        $this->assertSame('JPY', $cost->currency);
    }

    public function test_a_percentage_fee_with_no_payments_remaining_costs_nothing(): void
    {
        $this->assertSame(0, EarlyCancellationFee::percentageOfRemainingPayments(100)->cost(Money::parse('59.99', 'EUR'), 0)->minor);
    }

    public function test_a_percentage_must_be_between_zero_and_one_hundred(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EarlyCancellationFee::percentageOfRemainingPayments(101);
    }

    public function test_the_number_of_remaining_payments_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EarlyCancellationFee::none()->cost(Money::parse('59.99', 'EUR'), -1);
    }
}
