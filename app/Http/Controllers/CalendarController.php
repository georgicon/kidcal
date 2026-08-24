<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Services\Calendar\CalendarMonthBuilder;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        return view('calendars.index', [
            'calendars' => $request->user()->calendars()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('calendars.create', [
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        $calendar = $request->user()->calendars()->create($validated);

        return to_route('calendars.edit', $calendar)
            ->with('status', 'Calendar created. Your public page is ready to share.');
    }

    public function edit(Calendar $calendar): View
    {
        Gate::authorize('update', $calendar);
        $calendar->load([
            'sources' => fn ($query) => $query->withCount('events')->latest(),
            'manualEvents' => fn ($query) => $query->orderBy('starts_at'),
        ]);

        return view('calendars.edit', [
            'calendar' => $calendar,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('update', $calendar);
        $calendar->update($request->validate($this->rules()));

        return to_route('calendars.edit', $calendar)
            ->with('status', 'Calendar settings saved.');
    }

    public function show(Request $request, Calendar $calendar, CalendarMonthBuilder $builder): View|RedirectResponse
    {
        $events = $builder->events($calendar);
        $months = $builder->populatedMonths($calendar, $events);
        $requested = $this->requestedMonth($request, $calendar);
        $month = $months->first(fn (CarbonImmutable $candidate) => $candidate->format('Y-m') === $requested?->format('Y-m'));

        if (! $month && $months->isNotEmpty()) {
            $now = CarbonImmutable::now($calendar->timezone)->startOfMonth();
            $month = $months->first(fn (CarbonImmutable $candidate) => $candidate->gte($now)) ?? $months->last();

            if ($request->filled('month')) {
                return to_route('calendars.show', [$calendar, 'month' => $month->format('Y-m')]);
            }
        }

        $month ??= CarbonImmutable::now($calendar->timezone)->startOfMonth();
        $monthData = $builder->build($calendar, $month, $events);
        $monthIndex = $months->search(fn (CarbonImmutable $candidate) => $candidate->format('Y-m') === $month->format('Y-m'));

        return view('calendars.show', [
            'calendar' => $calendar,
            ...$monthData,
            'previousMonth' => is_int($monthIndex) && $monthIndex > 0 ? $months[$monthIndex - 1] : null,
            'nextMonth' => is_int($monthIndex) && $monthIndex < $months->count() - 1 ? $months[$monthIndex + 1] : null,
            'hasEvents' => $months->isNotEmpty(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone:all'],
            'week_starts_on' => ['required', 'integer', Rule::in([0, 1])],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'calendar';
        $slug = $base;
        $suffix = 2;

        while (Calendar::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function requestedMonth(Request $request, Calendar $calendar): ?CarbonImmutable
    {
        $value = $request->string('month')->toString();

        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m', $value, $calendar->timezone)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }
}
