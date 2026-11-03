<?php

namespace Tests\Unit\ValueObjects;

use App\Enums\CycleUnit;
use App\ValueObjects\Cycle;
use App\ValueObjects\Money;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CycleTest extends TestCase
{
    public function test_a_monthly_cycle_from_the_31st_lands_on_the_last_day_of_shorter_months(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);
        $start = new DateTimeImmutable('2026-01-31');

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'],
            $this->dates($cycle, $start, 5),
        );
    }

    public function test_a_yearly_cycle_from_29_february_lands_on_28_february_in_years_without_one(): void
    {
        $cycle = new Cycle(1, CycleUnit::Year);
        $start = new DateTimeImmutable('2028-02-29');

        $this->assertSame(
            ['2028-02-29', '2029-02-28', '2030-02-28', '2031-02-28', '2032-02-29'],
            $this->dates($cycle, $start, 5),
        );
    }

    public function test_a_daily_cycle_steps_over_month_and_year_ends(): void
    {
        $cycle = new Cycle(1, CycleUnit::Day);

        $this->assertSame(
            ['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02'],
            $this->dates($cycle, new DateTimeImmutable('2026-12-30'), 4),
        );
    }

    public function test_a_cycle_every_ten_days_counts_ten_days_each_time(): void
    {
        $cycle = new Cycle(10, CycleUnit::Day);

        $this->assertSame(
            ['2026-02-20', '2026-03-02', '2026-03-12'],
            $this->dates($cycle, new DateTimeImmutable('2026-02-20'), 3),
        );
    }

    public function test_a_cycle_every_two_weeks_counts_fourteen_days_each_time(): void
    {
        $cycle = new Cycle(2, CycleUnit::Week);

        $this->assertSame(
            ['2026-01-01', '2026-01-15', '2026-01-29', '2026-02-12'],
            $this->dates($cycle, new DateTimeImmutable('2026-01-01'), 4),
        );
    }

    public function test_a_cycle_every_three_months_keeps_the_start_day_across_years(): void
    {
        $cycle = new Cycle(3, CycleUnit::Month);

        $this->assertSame(
            ['2026-11-30', '2027-02-28', '2027-05-30', '2027-08-30', '2027-11-30'],
            $this->dates($cycle, new DateTimeImmutable('2026-11-30'), 5),
        );
    }

    public function test_a_monthly_cycle_from_the_31st_keeps_february_29th_in_a_leap_year(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);

        $this->assertSame(
            ['2028-01-31', '2028-02-29', '2028-03-31'],
            $this->dates($cycle, new DateTimeImmutable('2028-01-31'), 3),
        );
    }

    public function test_a_cycle_every_two_years_keeps_the_start_day(): void
    {
        $cycle = new Cycle(2, CycleUnit::Year);

        $this->assertSame(
            ['2026-06-15', '2028-06-15', '2030-06-15'],
            $this->dates($cycle, new DateTimeImmutable('2026-06-15'), 3),
        );
    }

    public function test_the_first_payment_on_or_after_a_date_is_the_start_when_the_date_is_earlier(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);

        $this->assertSame('2026-01-31', $this->firstOnOrAfter($cycle, '2026-01-31', '2025-12-01'));
    }

    public function test_the_first_payment_on_or_after_a_date_is_that_date_when_a_payment_falls_on_it(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);

        $this->assertSame('2026-02-28', $this->firstOnOrAfter($cycle, '2026-01-31', '2026-02-28'));
    }

    public function test_the_first_payment_on_or_after_a_date_is_the_next_one_when_none_falls_on_it(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);

        $this->assertSame('2026-03-31', $this->firstOnOrAfter($cycle, '2026-01-31', '2026-03-01'));
    }

    public function test_the_first_payment_on_or_after_a_date_works_years_later(): void
    {
        // 2026-01-01 is a Thursday, and 2030-01-01 is a Tuesday: the next Thursday is the 3rd.
        $this->assertSame('2030-01-03', $this->firstOnOrAfter(new Cycle(1, CycleUnit::Week), '2026-01-01', '2030-01-01'));
        $this->assertSame('2030-02-28', $this->firstOnOrAfter(new Cycle(1, CycleUnit::Month), '2026-01-31', '2030-02-01'));
        $this->assertSame('2031-06-15', $this->firstOnOrAfter(new Cycle(1, CycleUnit::Year), '2026-06-15', '2030-06-16'));
    }

    #[DataProvider('monthlyEquivalents')]
    public function test_the_monthly_equivalent_spreads_the_price_over_the_cycle(int $count, CycleUnit $unit, string $price, string $currency, int $expectedMinor): void
    {
        $equivalent = (new Cycle($count, $unit))->monthlyEquivalent(Money::parse($price, $currency));

        $this->assertSame($expectedMinor, $equivalent->minor);
        $this->assertSame($currency, $equivalent->currency);
    }

    /**
     * Expected values are worked out by hand from: monthly = price / N, yearly = price / 12N,
     * weekly = price x 52 / 12N, daily = price x 365 / 12N, rounded half up to a minor unit.
     *
     * @return array<string, array{0: int, 1: CycleUnit, 2: string, 3: string, 4: int}>
     */
    public static function monthlyEquivalents(): array
    {
        return [
            'monthly stays the same' => [1, CycleUnit::Month, '9.99', 'EUR', 999],
            'every 3 months is a third' => [3, CycleUnit::Month, '30.00', 'EUR', 1000],
            'yearly is a twelfth' => [1, CycleUnit::Year, '120.00', 'EUR', 1000],
            'yearly rounds down below half' => [1, CycleUnit::Year, '100.00', 'EUR', 833],
            'every 2 years is a twenty-fourth' => [2, CycleUnit::Year, '240.00', 'EUR', 1000],
            'weekly is 52/12 of the price' => [1, CycleUnit::Week, '10.00', 'EUR', 4333],
            'every 2 weeks is half of that' => [2, CycleUnit::Week, '20.00', 'EUR', 4333],
            'daily is 365/12 of the price' => [1, CycleUnit::Day, '1.00', 'EUR', 3042],
            'every 10 days' => [10, CycleUnit::Day, '10.00', 'EUR', 3042],
            'whole-unit currency' => [1, CycleUnit::Year, '12000', 'JPY', 1000],
            'whole-unit currency weekly' => [1, CycleUnit::Week, '1000', 'JPY', 4333],
            'three-digit currency' => [1, CycleUnit::Year, '12.000', 'KWD', 1000],
        ];
    }

    public function test_the_monthly_equivalent_rounds_half_a_minor_unit_up(): void
    {
        // 0.30 EUR a year is 0.025 a month: exactly half of a cent more than 0.02.
        $this->assertSame(3, (new Cycle(1, CycleUnit::Year))->monthlyEquivalent(Money::parse('0.30', 'EUR'))->minor);
    }

    public function test_payments_in_range_counts_from_the_first_date_up_to_but_not_including_the_last(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);
        $start = new DateTimeImmutable('2026-01-15');

        // 15 Jun, 15 Jul, 15 Aug, 15 Sep, 15 Oct, 15 Nov: the 15 Dec payment starts the next term.
        $this->assertSame(6, $cycle->paymentsInRange($start, new DateTimeImmutable('2026-06-15'), new DateTimeImmutable('2026-12-15')));

        // One day later the 15 Jun payment is already behind us.
        $this->assertSame(5, $cycle->paymentsInRange($start, new DateTimeImmutable('2026-06-16'), new DateTimeImmutable('2026-12-15')));
    }

    public function test_payments_in_range_is_zero_when_the_range_is_empty_or_reversed(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);
        $start = new DateTimeImmutable('2026-01-15');

        $this->assertSame(0, $cycle->paymentsInRange($start, new DateTimeImmutable('2026-12-15'), new DateTimeImmutable('2026-12-15')));
        $this->assertSame(0, $cycle->paymentsInRange($start, new DateTimeImmutable('2026-12-15'), new DateTimeImmutable('2026-06-15')));
    }

    public function test_payments_in_range_does_not_count_payments_before_the_start_date(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);
        $start = new DateTimeImmutable('2026-03-15');

        // 15 Mar and 15 Apr are the only payments before 1 May; nothing exists before the start.
        $this->assertSame(2, $cycle->paymentsInRange($start, new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-05-01')));
    }

    public function test_dates_are_calendar_dates_whatever_the_time_of_day(): void
    {
        $cycle = new Cycle(1, CycleUnit::Month);

        // 29 Feb at noon still counts as 29 Feb, the day the 31 Jan cycle lands on.
        $this->assertSame('2024-02-29', $this->firstOnOrAfter($cycle, '2024-01-31 00:00', '2024-02-29 12:00'));
        $this->assertSame('2024-02-29', $this->firstOnOrAfter($cycle, '2024-01-31 23:45', '2024-02-29 00:00'));
    }

    public function test_dates_are_calendar_dates_whatever_the_time_zone(): void
    {
        $cycle = new Cycle(1, CycleUnit::Day);
        $amsterdam = new DateTimeZone('Europe/Amsterdam');

        // Amsterdam moves its clocks forward at 02:00 on 31 March 2024, so 02:30 that day does not exist.
        $this->assertSame('2024-03-31', $cycle->dateOf(new DateTimeImmutable('2024-03-30 02:30', $amsterdam), 1)->format('Y-m-d'));

        // 1 June in Amsterdam at 00:30 is still 31 May in UTC, but the calendar date is 1 June.
        $this->assertSame(
            3,
            (new Cycle(1, CycleUnit::Month))->paymentsInRange(
                new DateTimeImmutable('2024-03-01 00:30', $amsterdam),
                new DateTimeImmutable('2024-03-01', new DateTimeZone('UTC')),
                new DateTimeImmutable('2024-06-01 00:30', $amsterdam),
            ),
        );
    }

    public function test_the_first_payment_on_or_after_a_date_works_for_daily_cycles_every_n_days(): void
    {
        $cycle = new Cycle(10, CycleUnit::Day);

        // Payments: 20 Feb, 2 Mar, 12 Mar, 22 Mar.
        $this->assertSame('2026-03-12', $this->firstOnOrAfter($cycle, '2026-02-20', '2026-03-03'));
        $this->assertSame('2026-03-12', $this->firstOnOrAfter($cycle, '2026-02-20', '2026-03-12'));
        $this->assertSame('2026-03-22', $this->firstOnOrAfter($cycle, '2026-02-20', '2026-03-13'));
    }

    public function test_payments_in_range_counts_every_n_days_and_every_n_months(): void
    {
        // 20 Feb, 2 Mar, 12 Mar, 22 Mar fall in [20 Feb, 1 Apr).
        $this->assertSame(4, (new Cycle(10, CycleUnit::Day))->paymentsInRange(
            new DateTimeImmutable('2026-02-20'),
            new DateTimeImmutable('2026-02-20'),
            new DateTimeImmutable('2026-04-01'),
        ));

        // Every 3 months from 30 Nov: 28 Feb 2027, 30 May 2027, 30 Aug 2027 are in (1 Jan, 1 Sep 2027).
        $this->assertSame(3, (new Cycle(3, CycleUnit::Month))->paymentsInRange(
            new DateTimeImmutable('2026-11-30'),
            new DateTimeImmutable('2027-01-01'),
            new DateTimeImmutable('2027-09-01'),
        ));
    }

    public function test_a_cycle_must_repeat_at_least_every_one_unit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cycle(0, CycleUnit::Month);
    }

    public function test_a_payment_before_the_start_date_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Cycle(1, CycleUnit::Month))->dateOf(new DateTimeImmutable('2026-01-31'), -1);
    }

    private function firstOnOrAfter(Cycle $cycle, string $start, string $date): string
    {
        return $cycle->firstOnOrAfter(new DateTimeImmutable($start), new DateTimeImmutable($date))->format('Y-m-d');
    }

    /**
     * @return list<string>
     */
    private function dates(Cycle $cycle, DateTimeImmutable $start, int $count): array
    {
        return array_map(
            fn (int $occurrence) => $cycle->dateOf($start, $occurrence)->format('Y-m-d'),
            range(0, $count - 1),
        );
    }
}
