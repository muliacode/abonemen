<?php

namespace App\ValueObjects;

use App\Enums\CycleUnit;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * How often a Subscription is paid: every N days, weeks, months or years.
 *
 * Payment dates are calendar dates. Any time of day or time zone on a date
 * that is passed in is ignored, and the dates that come back are midnight UTC.
 */
final readonly class Cycle
{
    public function __construct(
        public int $count,
        public CycleUnit $unit,
    ) {
        if ($count < 1) {
            throw new InvalidArgumentException('A cycle repeats at least every 1 unit.');
        }
    }

    /**
     * Get the date of the given payment, counting the start date as payment 0.
     *
     * Every date is worked out from the start date, never from the previous
     * payment, so a clamped month end does not drift the later dates.
     */
    public function dateOf(DateTimeImmutable $start, int $occurrence): DateTimeImmutable
    {
        if ($occurrence < 0) {
            throw new InvalidArgumentException('There is no payment before the start date.');
        }

        $start = self::calendarDate($start);
        $steps = $occurrence * $this->count;

        return match ($this->unit) {
            CycleUnit::Day => $start->modify("+{$steps} days"),
            CycleUnit::Week => $start->modify('+'.($steps * 7).' days'),
            CycleUnit::Month => $this->addMonths($start, $steps),
            CycleUnit::Year => $this->addMonths($start, $steps * 12),
        };
    }

    /**
     * Spread the price of one payment over the cycle to a cost per month.
     *
     * A year has 12 months, 52 weeks and 365 days.
     */
    public function monthlyEquivalent(Money $price): Money
    {
        return match ($this->unit) {
            CycleUnit::Day => $price->times(365, 12 * $this->count),
            CycleUnit::Week => $price->times(52, 12 * $this->count),
            CycleUnit::Month => $price->times(1, $this->count),
            CycleUnit::Year => $price->times(1, 12 * $this->count),
        };
    }

    /**
     * Get the first payment that falls on or after the given date.
     */
    public function firstOnOrAfter(DateTimeImmutable $start, DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->dateOf($start, $this->firstOccurrenceOnOrAfter(self::calendarDate($start), self::calendarDate($date)));
    }

    /**
     * Count the payments dated from the first date up to, but not including, the last.
     *
     * For a Commitment this is the number of payments left inside it: from the date
     * the Subscription ends up to the commitment end date, whose own payment starts
     * the next term.
     */
    public function paymentsInRange(DateTimeImmutable $start, DateTimeImmutable $from, DateTimeImmutable $until): int
    {
        [$start, $from, $until] = [self::calendarDate($start), self::calendarDate($from), self::calendarDate($until)];

        $payments = 0;

        for ($occurrence = $this->firstOccurrenceOnOrAfter($start, $from); $this->dateOf($start, $occurrence) < $until; $occurrence++) {
            $payments++;
        }

        return $payments;
    }

    /**
     * Find which payment, counting the start date as 0, is the first on or after the date.
     */
    private function firstOccurrenceOnOrAfter(DateTimeImmutable $start, DateTimeImmutable $date): int
    {
        if ($date <= $start) {
            return 0;
        }

        $occurrence = $this->estimateOccurrenceNear($start, $date);

        while ($this->dateOf($start, $occurrence) < $date) {
            $occurrence++;
        }

        while ($occurrence > 0 && $this->dateOf($start, $occurrence - 1) >= $date) {
            $occurrence--;
        }

        return $occurrence;
    }

    /**
     * Guess how many payments lie between the start and the date, close enough to search from.
     */
    private function estimateOccurrenceNear(DateTimeImmutable $start, DateTimeImmutable $date): int
    {
        $days = $start->diff($date)->days;

        $months = ((int) $date->format('Y') - (int) $start->format('Y')) * 12
            + (int) $date->format('n') - (int) $start->format('n');

        return max(0, match ($this->unit) {
            CycleUnit::Day => intdiv($days, $this->count),
            CycleUnit::Week => intdiv($days, 7 * $this->count),
            CycleUnit::Month => intdiv($months, $this->count),
            CycleUnit::Year => intdiv($months, 12 * $this->count),
        });
    }

    /**
     * Move a date by whole months, keeping its day of the month or the last day if the month is shorter.
     */
    private function addMonths(DateTimeImmutable $start, int $months): DateTimeImmutable
    {
        $total = (int) $start->format('Y') * 12 + ((int) $start->format('n') - 1) + $months;
        $year = intdiv($total, 12);
        $month = $total % 12 + 1;

        $day = min((int) $start->format('j'), (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t'));

        return $start->setDate($year, $month, $day);
    }

    /**
     * Keep only the calendar date of a moment, as midnight UTC.
     */
    private static function calendarDate(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->format('Y-m-d'), new DateTimeZone('UTC'));
    }
}
