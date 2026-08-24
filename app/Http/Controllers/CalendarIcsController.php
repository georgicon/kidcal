<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Services\Calendar\CalendarMonthBuilder;
use App\Services\Calendar\ICalendarExporter;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CalendarIcsController extends Controller
{
    public function __invoke(Calendar $calendar, CalendarMonthBuilder $builder, ICalendarExporter $exporter): Response
    {
        $contents = $exporter->export($calendar, $builder->events($calendar));
        $filename = (Str::slug($calendar->name) ?: 'kidcal').'.ics';

        return response($contents, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
