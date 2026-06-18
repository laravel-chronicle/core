<?php

declare(strict_types=1);

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\OutsideTimeWindowException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Opt-in policy that only permits entries recorded within the configured time window.
 */
final class TimeWindowPolicy extends AbstractPolicy
{
    protected readonly Carbon $startCarbon;

    protected readonly Carbon $endCarbon;

    /** @var string[] */
    protected readonly array $days;

    protected readonly string $timezone;

    protected readonly string $start;

    protected readonly string $end;

    public function __construct()
    {
        $start = Config::string('chronicle.policy.time_window.start', '00:00');

        $end = Config::string('chronicle.policy.time_window.end', '23:59:59');

        /** @var string[] $days */
        $days = Config::array('chronicle.policy.time_window.days', []);

        /** @var string|null $timezone */
        $timezone = Config::get('chronicle.policy.time_window.timezone');

        $this->start = $start;
        $this->end = $end;
        $this->days = $days;
        $appTimezone = Config::string('app.timezone', 'UTC');
        $this->timezone = $timezone ?? $appTimezone;

        $startCarbon = $this->parseTime($this->start, $this->timezone);
        $endCarbon = $this->parseTime($this->end, $this->timezone);

        if ($startCarbon === null || $endCarbon === null) {
            throw new InvalidArgumentException(
                'Chronicle TimeWindowPolicy: could not parse start or end time.'
            );
        }

        if ($startCarbon->gte($endCarbon)) {
            throw new InvalidArgumentException(
                'Chronicle TimeWindowPolicy: start time must be before end time. '.
                'Midnight-spanning windows are not supported.'
            );
        }

        $this->startCarbon = $startCarbon;
        $this->endCarbon = $endCarbon;
    }

    public function enforce(PendingEntry $entry): void
    {
        $now = Carbon::now($this->timezone);

        if (! empty($this->days)) {
            $dayNames = array_map('strtolower', $this->days);
            if (! in_array(strtolower($now->format('l')), $dayNames, true)) {
                throw OutsideTimeWindowException::outsideWindow($this->start, $this->end);
            }
        }

        $start = clone $this->startCarbon;
        $end = clone $this->endCarbon;
        /** @var Carbon $start */
        $start->setDate($now->year, $now->month, $now->day);
        /** @var Carbon $end */
        $end->setDate($now->year, $now->month, $now->day);

        if ($now->lt($start) || $now->gt($end)) {
            throw OutsideTimeWindowException::outsideWindow($this->start, $this->end);
        }
    }

    protected function parseTime(string $time, string $timezone): ?Carbon
    {
        if (str_contains($time, ':') && substr_count($time, ':') === 2) {
            $result = Carbon::createFromFormat('H:i:s', $time, $timezone);
        } else {
            $result = Carbon::createFromFormat('H:i', $time, $timezone);
        }

        return $result instanceof Carbon ? $result : null;
    }
}
