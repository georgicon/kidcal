<?php

namespace App\Http\Controllers;

use App\Jobs\SyncCalendarSource;
use App\Models\Calendar;
use App\Models\CalendarSource;
use App\Rules\PublicFeedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CalendarSourceController extends Controller
{
    public function store(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        $validated = $request->validate([
            'links' => ['required', 'string', 'max:100000'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $urls = $this->extractUrls($validated['links']);
        if ($urls === []) {
            throw ValidationException::withMessages([
                'links' => 'No http, https, or webcal links were found.',
            ]);
        }

        if (count($urls) > 50) {
            throw ValidationException::withMessages([
                'links' => 'Add no more than 50 feed links at a time.',
            ]);
        }

        foreach ($urls as $url) {
            $urlValidator = Validator::make(['url' => $url], ['url' => [new PublicFeedUrl]]);
            if ($urlValidator->fails()) {
                throw ValidationException::withMessages([
                    'links' => "This link is invalid or does not resolve to a public host: {$url}",
                ]);
            }
        }

        $normalizedUrls = collect($urls)
            ->mapWithKeys(fn (string $url) => [$url => preg_replace('/^webcal:\/\//i', 'https://', $url)]);
        $existing = $calendar->sources()->whereIn('normalized_url', $normalizedUrls->values())->pluck('normalized_url');
        $created = 0;

        foreach ($normalizedUrls as $url => $normalizedUrl) {
            if ($existing->contains($normalizedUrl)) {
                continue;
            }

            $source = $calendar->sources()->create([
                'name' => $this->generatedName($normalizedUrl, $calendar),
                'url' => $url,
                'normalized_url' => $normalizedUrl,
                'color' => $validated['color'],
            ]);
            SyncCalendarSource::dispatch($source->id);
            $existing->push($normalizedUrl);
            $created++;
        }

        $skipped = count($urls) - $created;
        $message = trans_choice('{1} One feed added.|[2,*] :count feeds added.', $created, ['count' => $created]);
        if ($skipped > 0) {
            $message .= ' '.trans_choice('{1} One duplicate was skipped.|[2,*] :count duplicates were skipped.', $skipped, ['count' => $skipped]);
        }

        return to_route('calendars.edit', $calendar)->with('status', $message);
    }

    public function update(Request $request, Calendar $calendar, CalendarSource $source): RedirectResponse
    {
        $this->authorizeSource($calendar, $source);

        $source->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'enabled' => ['required', Rule::in(['0', '1', 0, 1])],
        ]));

        return to_route('calendars.edit', $calendar)->with('status', 'Feed settings saved.');
    }

    public function refresh(Calendar $calendar, CalendarSource $source): RedirectResponse
    {
        $this->authorizeSource($calendar, $source);
        SyncCalendarSource::dispatch($source->id);

        return to_route('calendars.edit', $calendar)->with('status', 'Feed refresh queued.');
    }

    public function destroy(Calendar $calendar, CalendarSource $source): RedirectResponse
    {
        $this->authorizeSource($calendar, $source);
        $source->delete();

        return to_route('calendars.edit', $calendar)->with('status', 'Feed removed.');
    }

    private function authorizeSource(Calendar $calendar, CalendarSource $source): void
    {
        Gate::authorize('update', $calendar);
        abort_unless($source->calendar_id === $calendar->id, 404);
    }

    /** @return array<int, string> */
    private function extractUrls(string $input): array
    {
        preg_match_all('/(?:https?|webcal):\/\/[^\s<>"\']+/i', $input, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $url) => rtrim($url, ',.;)]}'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function generatedName(string $url, Calendar $calendar): string
    {
        $host = preg_replace('/^www\./i', '', (string) parse_url($url, PHP_URL_HOST));
        $base = Str::limit($host !== '' ? $host : 'Calendar feed', 90, '');
        $name = $base;
        $suffix = 2;

        while ($calendar->sources()->where('name', $name)->exists()) {
            $name = Str::limit($base, 94, '').' '.$suffix++;
        }

        return $name;
    }
}
