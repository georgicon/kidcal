<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CalendarEditorLinkController extends Controller
{
    public function store(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('update', $calendar);

        $token = Str::random(64);
        $hash = hash('sha256', $token);
        $calendar->update(['editor_token_hash' => $hash]);
        $request->session()->put("calendar_editor.{$calendar->id}", $hash);

        return to_route('calendars.edit', $calendar)->with([
            'status' => 'A new shared editor link was created. Any previous link is now invalid.',
            'editor_link' => route('calendar-editor.access', [$calendar, $token]),
        ]);
    }

    public function destroy(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('update', $calendar);
        $calendar->update(['editor_token_hash' => null]);
        $request->session()->forget("calendar_editor.{$calendar->id}");

        return to_route('calendars.edit', $calendar)->with('status', 'Shared editing access revoked.');
    }

    public function access(Request $request, Calendar $calendar, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);
        abort_unless($calendar->editor_token_hash && hash_equals($calendar->editor_token_hash, $hash), 404);

        $request->session()->put("calendar_editor.{$calendar->id}", $hash);

        return to_route('calendars.edit', $calendar);
    }
}
