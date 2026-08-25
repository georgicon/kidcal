<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\ManualEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManualEventController extends Controller
{
    public function store(Request $request, Calendar $calendar): RedirectResponse
    {
        $calendar->manualEvents()->create($this->eventAttributes($request, $calendar));

        return to_route('calendars.edit', $calendar)->with('status', 'Event added.');
    }

    public function update(Request $request, Calendar $calendar, ManualEvent $event): JsonResponse|RedirectResponse
    {
        $this->authorizeEvent($calendar, $event);
        $event->update($this->eventAttributes($request, $calendar));

        if ($request->expectsJson()) {
            return response()->json(['saved' => true]);
        }

        return to_route('calendars.edit', $calendar)->with('status', 'Event updated.');
    }

    public function destroy(Calendar $calendar, ManualEvent $event): RedirectResponse
    {
        $this->authorizeEvent($calendar, $event);
        $event->delete();

        return to_route('calendars.edit', $calendar)->with('status', 'Event removed.');
    }

    /** @return array<string, mixed> */
    private function eventAttributes(Request $request, Calendar $calendar): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_if:all_day,0', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'all_day' => ['required', Rule::in(['0', '1', 0, 1])],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'recurrence_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_interval' => ['required_with:recurrence_type', 'integer', 'min:1', 'max:52'],
            'recurrence_weekdays' => ['required_if:recurrence_type,weekly', 'array'],
            'recurrence_weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'recurrence_until' => ['required_with:recurrence_type', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
        ]);

        $allDay = (bool) $validated['all_day'];
        $startsAt = CarbonImmutable::parse(
            $validated['date'].' '.($allDay ? '00:00' : $validated['start_time']),
            $calendar->timezone,
        );
        $endsAt = $allDay
            ? $startsAt->addDay()
            : CarbonImmutable::parse($validated['date'].' '.($validated['end_time'] ?: $validated['start_time']), $calendar->timezone);

        if (! $allDay && $endsAt->lte($startsAt)) {
            $endsAt = $startsAt->addHour();
        }

        return [
            'title' => $validated['title'],
            'location' => $validated['location'] ?? null,
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'all_day' => $allDay,
            'color' => $validated['color'],
            'recurrence_type' => $validated['recurrence_type'] ?? null,
            'recurrence_interval' => $validated['recurrence_interval'] ?? 1,
            'recurrence_weekdays' => ($validated['recurrence_type'] ?? null) === 'weekly'
                ? array_map(intval(...), array_values($validated['recurrence_weekdays']))
                : null,
            'recurrence_until' => $validated['recurrence_until'] ?? null,
        ];
    }

    private function authorizeEvent(Calendar $calendar, ManualEvent $event): void
    {
        abort_unless($event->calendar_id === $calendar->id, 404);
    }
}
