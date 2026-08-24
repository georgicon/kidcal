<?php

use App\Jobs\SyncCalendarSource;
use App\Models\CalendarSource;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('calendars:sync', function () {
    CalendarSource::query()->where('enabled', true)->eachById(
        fn (CalendarSource $source) => SyncCalendarSource::dispatch($source->id)
    );
})->purpose('Queue a refresh for every enabled calendar source');

Schedule::command('calendars:sync')->hourly()->withoutOverlapping();
