<?php

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\EarlyCancellationFee;
use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_a_percentage_can_have_up_to_two_decimals(): void
    {
        // 5 payments of 59.99 is 299.95; 12.5% of it is 37.49375, which rounds to 37.49.
        $this->assertSame(3749, EarlyCancellationFee::percentageOfRemainingPayments('12.5')->cost(Money::parse('59.99', 'EUR'), 5)->minor);

        // 6 payments of 59.99 is 359.94; 33.33% of it is 119.968..., which rounds to 119.97.
        $this->assertSame(11997, EarlyCancellationFee::percentageOfRemainingPayments('33.33')->cost(Money::parse('59.99', 'EUR'), 6)->minor);
    }

    public function test_a_whole_percentage_gives_the_same_fee_as_an_int_or_a_string(): void
    {
        $payment = Money::parse('59.99', 'EUR');

        $this->assertSame(
            EarlyCancellationFee::percentageOfRemainingPayments(50)->cost($payment, 5)->minor,
            EarlyCancellationFee::percentageOfRemainingPayments('50.00')->cost($payment, 5)->minor,
        );
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

    public function test_zero_percent_costs_nothing(): void
    {
        $this->assertSame(0, EarlyCancellationFee::percentageOfRemainingPayments(0)->cost(Money::parse('59.99', 'EUR'), 6)->minor);
    }

    #[DataProvider('invalidPercentages')]
    public function test_a_percentage_must_be_between_zero_and_one_hundred_with_at_most_two_decimals(int|string $percent): void
    {
        $this->expectException(InvalidArgumentException::class);

        EarlyCancellationFee::percentageOfRemainingPayments($percent);
    }

    /**
     * @return array<string, array{0: int|string}>
     */
    public static function invalidPercentages(): array
    {
        return [
            'below zero' => [-1],
            'above one hundred' => [101],
            'just above one hundred' => ['100.01'],
            'three decimals' => ['12.345'],
            'negative string' => ['-5'],
            'words' => ['half'],
            'empty' => [''],
            'decimal comma' => ['12,5'],
            'trailing dot' => ['5.'],
            'leading dot' => ['.5'],
        ];
    }

    public function test_the_boundary_percentages_are_accepted(): void
    {
        $payment = Money::parse('10.00', 'EUR');

        $this->assertSame(0, EarlyCancellationFee::percentageOfRemainingPayments('0.00')->cost($payment, 3)->minor);
        $this->assertSame(3000, EarlyCancellationFee::percentageOfRemainingPayments('100')->cost($payment, 3)->minor);
        // 0.01% of 1000.00 is 0.10.
        $this->assertSame(10, EarlyCancellationFee::percentageOfRemainingPayments('0.01')->cost(Money::parse('1000.00', 'EUR'), 1)->minor);
    }

    public function test_the_number_of_remaining_payments_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EarlyCancellationFee::none()->cost(Money::parse('59.99', 'EUR'), -1);
    }
}
