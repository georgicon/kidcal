<?php

namespace App\Services\Calendar;

use App\Models\Calendar;
use App\Models\ManualEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ICalendarExporter
{
    /** @param Collection<int, mixed> $events */
    public function export(Calendar $calendar, Collection $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//KidCal//Aggregate Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($calendar->name),
            'X-WR-TIMEZONE:'.$this->escape($calendar->timezone),
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];

        foreach ($events as $event) {
            array_push($lines, ...$this->eventLines($calendar, $event));
        }

        $lines[] = 'END:VCALENDAR';

        return collect($lines)->map($this->fold(...))->implode("\r\n")."\r\n";
    }

    /** @return array<int, string> */
    private function eventLines(Calendar $calendar, mixed $event): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$this->uid($calendar, $event),
            'DTSTAMP:'.$this->utc($event->updated_at ?? now()),
        ];

        if ($event->all_day) {
            $lines[] = 'DTSTART;VALUE=DATE:'.$event->starts_at->setTimezone($calendar->timezone)->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$event->ends_at->setTimezone($calendar->timezone)->format('Ymd');
        } else {
            $lines[] = 'DTSTART:'.$this->utc($event->starts_at);
            $lines[] = 'DTEND:'.$this->utc($event->ends_at);
        }

        $lines[] = 'SUMMARY:'.$this->escape($event->title);

        if ($event->location) {
            $lines[] = 'LOCATION:'.$this->escape($event->location);
        }

        if ($event->description ?? null) {
            $lines[] = 'DESCRIPTION:'.$this->escape($event->description);
        }

        if ($event->display_color ?? null) {
            $lines[] = 'COLOR:'.strtoupper($event->display_color);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function uid(Calendar $calendar, mixed $event): string
    {
        $type = $event instanceof ManualEvent ? 'manual' : 'source';
        $occurrence = $event instanceof ManualEvent ? ($event->occurrence_key ?? null) : null;
        $suffix = $occurrence ? "-{$occurrence}" : '';

        return "{$type}-{$event->getKey()}{$suffix}@{$calendar->slug}.kidcal";
    }

    private function utc(CarbonInterface $date): string
    {
        return $date->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\r", "\n", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    private function fold(string $line): string
    {
        $segments = [];
        $segment = '';
        $limit = 75;

        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            if (strlen($segment.$character) > $limit) {
                $segments[] = $segment;
                $segment = $character;
                $limit = 74;
            } else {
                $segment .= $character;
            }
        }

        $segments[] = $segment;

        return implode("\r\n ", $segments);
    }
}
