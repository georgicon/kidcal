<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Services\Calendar\CalendarMonthBuilder;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CalendarPdfController extends Controller
{
    public function __invoke(Calendar $calendar, CalendarMonthBuilder $builder): Response
    {
        $events = $builder->events($calendar);
        $months = $builder->populatedMonths($calendar, $events);
        abort_if($months->isEmpty(), 404, 'This calendar has no events to export.');

        $pages = $months->map(fn ($month) => $builder->build($calendar, $month, $events));
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->setPaper('letter', 'landscape');
        $pdf->loadHtml(view('calendars.pdf', compact('calendar', 'pages'))->render());
        $pdf->render();

        $filename = (Str::slug($calendar->name) ?: 'calendar').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
