<?php

namespace App\Services\Calendar;

use App\Models\Calendar;
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

        $manual = $calendar->manualEvents()->get()->each(
            fn ($event) => $event->setAttribute('display_color', $event->color)
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
}
