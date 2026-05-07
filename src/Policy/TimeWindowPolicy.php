<?php

namespace Chronicle\Policy;

use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\OutsideTimeWindowException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TimeWindowPolicy extends AbstractPolicy
{
    private readonly Carbon $startCarbon;

    private readonly Carbon $endCarbon;

    /** @var string[] */
    private readonly array $days;

    private readonly string $timezone;

    private readonly string $start;

    private readonly string $end;

    public function __construct()
    {
        /** @var string $start */
        $start = config('chronicle.policy.time_window.start', '00:00');

        /** @var string $end */
        $end = config('chronicle.policy.time_window.end', '23:59:59');

        /** @var string[] $days */
        $days = config('chronicle.policy.time_window.days', []);

        /** @var string|null $timezone */
        $timezone = config('chronicle.policy.time_window.timezone');

        $this->start = $start;
        $this->end = $end;
        $this->days = $days;
        /** @var string $appTimezone */
        $appTimezone = config('app.timezone', 'UTC');
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

    private function parseTime(string $time, string $timezone): ?Carbon
    {
        if (str_contains($time, ':') && substr_count($time, ':') === 2) {
            $result = Carbon::createFromFormat('H:i:s', $time, $timezone);
        } else {
            $result = Carbon::createFromFormat('H:i', $time, $timezone);
        }

        return $result instanceof Carbon ? $result : null;
    }
}
