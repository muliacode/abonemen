<?php

namespace Tests\Unit\Billing;

use App\Billing\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    private function assertThrowsInvalid(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Expected an InvalidArgumentException.');
    }

    public function test_a_decimal_price_becomes_integer_minor_units_with_its_currency(): void
    {
        $price = Money::parse('15.99', 'EUR');

        $this->assertSame(1599, $price->minor);
        $this->assertSame('EUR', $price->currency);
    }

    public function test_a_currency_without_minor_units_keeps_whole_amounts(): void
    {
        $this->assertSame(1500, Money::parse('1500', 'JPY')->minor);
    }

    public function test_a_currency_with_three_minor_digits_keeps_all_three(): void
    {
        $this->assertSame(1234, Money::parse('1.234', 'KWD')->minor);
        $this->assertSame(1200, Money::parse('1.2', 'KWD')->minor);
    }

    public function test_more_precision_than_the_currency_has_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::parse('15.999', 'EUR');
    }

    #[DataProvider('invalidAmounts')]
    public function test_an_amount_that_is_not_a_plain_non_negative_decimal_is_rejected(string $amount, string $currency): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::parse($amount, $currency);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'words' => ['abc', 'EUR'],
            'decimal comma' => ['1,5', 'EUR'],
            'negative' => ['-5.00', 'EUR'],
            'empty' => ['', 'EUR'],
            'trailing dot' => ['5.', 'EUR'],
            'unknown currency' => ['5.00', 'XXL'],
            'lowercase currency' => ['5.00', 'eur'],
        ];
    }

    public function test_an_amount_in_minor_units_keeps_its_value_and_currency(): void
    {
        $money = Money::ofMinor(1599, 'EUR');

        $this->assertSame(1599, $money->minor);
        $this->assertSame('EUR', $money->currency);
    }

    public function test_minor_units_cannot_be_negative_or_in_an_unknown_currency(): void
    {
        $this->assertThrowsInvalid(fn () => Money::ofMinor(-1, 'EUR'));
        $this->assertThrowsInvalid(fn () => Money::ofMinor(100, 'XXL'));
    }

    public function test_multiplying_by_a_fraction_rounds_half_a_minor_unit_up(): void
    {
        $this->assertSame(3, Money::ofMinor(5, 'EUR')->times(1, 2)->minor);
        $this->assertSame(2, Money::ofMinor(5, 'EUR')->times(1, 3)->minor);
        $this->assertSame(1, Money::ofMinor(4, 'EUR')->times(1, 3)->minor);
        $this->assertSame(1, Money::ofMinor(4, 'EUR')->times(1, 4)->minor);
    }

    public function test_multiplying_needs_a_non_negative_numerator_and_a_positive_denominator(): void
    {
        $this->assertThrowsInvalid(fn () => Money::ofMinor(100, 'EUR')->times(-1, 2));
        $this->assertThrowsInvalid(fn () => Money::ofMinor(100, 'EUR')->times(1, 0));
    }

    public function test_a_fraction_is_rejected_for_a_currency_without_minor_units(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::parse('1500.5', 'JPY');
    }
}
