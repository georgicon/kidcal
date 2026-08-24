<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <style>
            @page { size: letter landscape; margin: 0.25in; }
            * { box-sizing: border-box; }
            body { margin: 0; color: #17201d; font-family: "DejaVu Sans", sans-serif; }
            .month-page { height: 7.7in; overflow: hidden; page-break-after: always; page-break-inside: avoid; }
            .month-page:last-child { page-break-after: avoid; }
            .month-header { height: 0.52in; padding: 0.02in 0.06in 0.05in; }
            .calendar-name { color: #95621d; font-size: 7pt; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
            h1 { margin: 2px 0 0; color: #173f35; font-family: Georgia, serif; font-size: 20pt; line-height: 1; }
            .timezone { float: right; margin-top: -18px; color: #59635f; font-size: 7pt; }
            table { width: 100%; height: 7.05in; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
            thead tr { height: 0.25in; }
            th { height: 0.25in; border: 0.5px solid #aeb8b3; background: #e8ede8; color: #31423b; font-size: 7pt; letter-spacing: 0.8px; text-transform: uppercase; }
            tbody tr { height: 1.08in; page-break-inside: avoid; }
            td { height: 1.08in; border: 0.5px solid #aeb8b3; padding: 0; vertical-align: top; overflow: hidden; }
            td.outside { background: #f4f5f3; color: #a0a7a3; }
            .day-content { height: 1.05in; padding: 3px; overflow: hidden; }
            table.weeks-5 tbody tr, table.weeks-5 td { height: 1.31in; }
            table.weeks-5 .day-content { height: 1.28in; }
            .day-number { margin-bottom: 2px; font-size: 7pt; font-weight: bold; }
            .event { margin-bottom: 2px; padding: 2px 3px; border-radius: 2px; color: #fff; font-size: 6.2pt; line-height: 1.15; overflow-wrap: anywhere; page-break-inside: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .event-time { font-weight: bold; opacity: 0.92; }
            .event-meta { margin-bottom: 1px; font-size: 5.5pt; opacity: 0.92; overflow-wrap: anywhere; }
            .event-title { display: block; font-weight: bold; overflow-wrap: anywhere; }
            .dense .event { margin-bottom: 1px; padding: 1px 2px; font-size: 5pt; line-height: 1.05; }
            .dense .event-meta { margin-bottom: 0; font-size: 4.6pt; }
            .very-dense .event { margin-bottom: 0.5px; padding: 1px; font-size: 4.2pt; line-height: 1; }
            .very-dense .event-meta { margin-bottom: 0; font-size: 4pt; }
        </style>
    </head>
    <body>
        @foreach ($pages as $page)
            @php
                $busiestDay = $page['eventsByDay']->max(fn ($events) => $events->count()) ?? 0;
                $density = $busiestDay > 6 ? 'very-dense' : ($busiestDay > 3 ? 'dense' : '');
                $eventLimit = $busiestDay > 8 ? 7 : null;
                $weekCount = $page['days']->count() / 7;
            @endphp
            <section class="month-page {{ $density }}">
                <header class="month-header">
                    <div class="calendar-name">{{ $calendar->name }}</div>
                    <h1>{{ $page['month']->format('F Y') }}</h1>
                    <div class="timezone">Times shown in {{ str_replace('_', ' ', $calendar->timezone) }}</div>
                </header>

                <table class="weeks-{{ $weekCount }}">
                    <thead>
                        <tr>
                            @foreach ($page['weekdays'] as $weekday)
                                <th>{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($page['days']->chunk(7) as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td class="{{ $day->month !== $page['month']->month ? 'outside' : '' }}"><div class="day-content">
                                        <div class="day-number">{{ $day->day }}</div>
                                        @if ($day->month === $page['month']->month)
                                            @php $dayEvents = $page['eventsByDay'][$day->format('Y-m-d')]; @endphp
                                            @foreach ($eventLimit ? $dayEvents->take($eventLimit) : $dayEvents as $event)
                                                <div class="event" style="background-color: {{ $event->display_color }};">
                                                    <div class="event-meta">
                                                        {{ $event->all_day ? 'All day' : $event->starts_at->setTimezone($calendar->timezone)->format('g:i a') }}@if ($event->location) - {{ Str::limit($event->location, 30) }}@endif
                                                    </div>
                                                    <span class="event-title">{{ Str::limit($event->title, 48) }}</span>
                                                </div>
                                            @endforeach
                                            @if ($eventLimit && $dayEvents->count() > $eventLimit)
                                                <div class="event-meta">+{{ $dayEvents->count() - $eventLimit }} more events</div>
                                            @endif
                                        @endif
                                    </div></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach
    </body>
</html>
