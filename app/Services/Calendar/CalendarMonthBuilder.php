<?php

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\ManualEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarMonthBuilder
{
    /** @return Collection<int, mixed> */
    public function events(Calendar $calendar): Collection
    {
        $imported = $calendar->sources()
            ->where('enabled', true)
            ->with(['events' => fn ($query) => $query->orderBy('starts_at')])
            ->get()
            ->flatMap(fn ($source) => $source->events->map(function ($event) use ($source) {
                $event->setRelation('source', $source);
                $event->setAttribute('display_color', $source->color);

                return $event;
            }));

        $manual = $calendar->manualEvents()->get()->flatMap(
            fn (ManualEvent $event) => $this->manualOccurrences($event, $calendar->timezone)
        );

        return $imported->concat($manual)->sort($this->compareEvents(...))->values();
    }

    /** @param Collection<int, mixed> $events
     * @return Collection<int, CarbonImmutable>
     */
    public function populatedMonths(Calendar $calendar, Collection $events): Collection
    {
        return $events->flatMap(function ($event) use ($calendar) {
            $start = CarbonImmutable::instance($event->starts_at)->setTimezone($calendar->timezone)->startOfMonth();
            $lastMoment = CarbonImmutable::instance($event->ends_at)->setTimezone($calendar->timezone)->subSecond();
            $end = ($lastMoment->lt($start) ? $start : $lastMoment)->startOfMonth();
            $months = [];

            for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
                $months[] = $month;
            }

            return $months;
        })->unique(fn (CarbonImmutable $month) => $month->format('Y-m'))
            ->sort()
            ->values();
    }

    /** @param Collection<int, mixed> $events
     * @return array{month: CarbonImmutable, days: Collection<int, CarbonImmutable>, eventsByDay: Collection<string, Collection<int, mixed>>, weekdays: array<int, string>}
     */
    public function build(Calendar $calendar, CarbonImmutable $month, Collection $events): array
    {
        $gridStarts = $calendar->week_starts_on === 1
            ? $month->startOfMonth()->startOfWeek(CarbonImmutable::MONDAY)
            : $month->startOfMonth()->startOfWeek(CarbonImmutable::SUNDAY);
        $leadingDays = $gridStarts->diffInDays($month->startOfMonth());
        $cellCount = (int) (ceil(($leadingDays + $month->daysInMonth) / 7) * 7);
        $days = collect(range(0, $cellCount - 1))->map(fn (int $day) => $gridStarts->addDays($day));
        $eventsByDay = $days->mapWithKeys(function (CarbonImmutable $day) use ($events) {
            $start = $day->startOfDay()->utc();
            $end = $day->addDay()->startOfDay()->utc();

            return [$day->format('Y-m-d') => $events
                ->filter(fn ($event) => $event->starts_at->lt($end) && $event->ends_at->gt($start))
                ->sort($this->compareEvents(...))
                ->values()];
        });

        return [
            'month' => $month,
            'days' => $days,
            'eventsByDay' => $eventsByDay,
            'weekdays' => $calendar->week_starts_on === 1
                ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        ];
    }

    private function compareEvents(mixed $left, mixed $right): int
    {
        if ($left->all_day !== $right->all_day) {
            return $left->all_day ? -1 : 1;
        }

        return $left->starts_at->getTimestamp() <=> $right->starts_at->getTimestamp()
            ?: strcasecmp($left->title, $right->title);
    }

    /** @return Collection<int, ManualEvent> */
    private function manualOccurrences(ManualEvent $event, string $timezone): Collection
    {
        $event->setAttribute('display_color', $event->color);

        if (! $event->recurrence_type || ! $event->recurrence_until) {
            return collect([$event]);
        }

        $start = CarbonImmutable::instance($event->starts_at)->setTimezone($timezone);
        $until = CarbonImmutable::parse($event->recurrence_until->format('Y-m-d'), $timezone)->endOfDay();
        $duration = $event->starts_at->diffInSeconds($event->ends_at);
        $dates = match ($event->recurrence_type) {
            'daily' => $this->dailyDates($start, $until, $event->recurrence_interval),
            'weekly' => $this->weeklyDates($start, $until, $event->recurrence_interval, $event->recurrence_weekdays ?? []),
            'monthly' => $this->monthlyDates($start, $until, $event->recurrence_interval),
            'yearly' => $this->yearlyDates($start, $until, $event->recurrence_interval),
            default => collect([$start]),
        };

        return $dates->map(function (CarbonImmutable $occursAt) use ($event, $duration) {
            $occurrence = clone $event;
            $occurrence->setAttribute('starts_at', $occursAt->utc());
            $occurrence->setAttribute('ends_at', $occursAt->addSeconds($duration)->utc());
            $occurrence->setAttribute('display_color', $event->color);
            $occurrence->setAttribute('occurrence_key', $occursAt->utc()->format('Ymd\THis\Z'));

            return $occurrence;
        });
    }

    /** @return Collection<int, CarbonImmutable> */
    private function dailyDates(CarbonImmutable $start, CarbonImmutable $until, int $interval): Collection
    {
        $dates = [];

        for ($date = $start; $date->lte($until); $date = $date->addDays($interval)) {
            $dates[] = $date;
        }

        return collect($dates);
    }

    /** @param array<int, int> $weekdays
     * @return Collection<int, CarbonImmutable>
     */
    private function weeklyDates(CarbonImmutable $start, CarbonImmutable $until, int $interval, array $weekdays): Collection
    {
        $dates = [];
        $firstWeek = $start->startOfWeek(CarbonImmutable::SUNDAY);

        for ($date = $start->startOfDay(); $date->lte($until); $date = $date->addDay()) {
            $weeks = intdiv((int) $firstWeek->diffInDays($date->startOfWeek(CarbonImmutable::SUNDAY)), 7);

            if ($weeks % $interval === 0 && in_array($date->dayOfWeek, $weekdays, true)) {
                $dates[] = $date->setTimeFrom($start);
            }
        }

        return collect($dates);
    }

    /** @return Collection<int, CarbonImmutable> */
    private function monthlyDates(CarbonImmutable $start, CarbonImmutable $until, int $interval): Collection
    {
        $dates = [];

        for ($month = $start->startOfMonth(); $month->lte($until); $month = $month->addMonths($interval)) {
            if ($start->day <= $month->daysInMonth) {
                $dates[] = $month->day($start->day)->setTimeFrom($start);
            }
        }

        return collect($dates);
    }

    /** @return Collection<int, CarbonImmutable> */
    private function yearlyDates(CarbonImmutable $start, CarbonImmutable $until, int $interval): Collection
    {
        $dates = [];

        for ($year = $start->year; $year <= $until->year; $year += $interval) {
            if ($start->month !== 2 || $start->day !== 29 || CarbonImmutable::create($year, 1, 1)->isLeapYear()) {
                $dates[] = $start->year($year);
            }
        }

        return collect($dates)->filter(fn (CarbonImmutable $date) => $date->lte($until))->values();
    }
}
