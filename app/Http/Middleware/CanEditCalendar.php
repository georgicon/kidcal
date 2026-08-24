<?php

namespace App\Http\Middleware;

use App\Models\Calendar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanEditCalendar
{
    public function handle(Request $request, Closure $next): Response
    {
        $calendar = $request->route('calendar');
        abort_unless($calendar instanceof Calendar, 404);

        $isOwner = $request->user()?->is($calendar->user) ?? false;
        $sessionHash = $request->session()->get("calendar_editor.{$calendar->id}");
        $hasSharedAccess = $calendar->editor_token_hash
            && is_string($sessionHash)
            && hash_equals($calendar->editor_token_hash, $sessionHash);

        abort_unless($isOwner || $hasSharedAccess, 403);

        return $next($request);
    }
}
