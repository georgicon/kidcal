<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarSource;
use App\Services\Calendar\CalendarMonthBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_only_moves_between_populated_months(): void
    {
        $this->travelTo('2026-08-15 12:00:00');
        [$calendar] = $this->calendarWithEvents();

        $august = $this->get(route('calendars.show', [$calendar, 'month' => '2026-08']));
        $august->assertOk()
            ->assertDontSee('>Previous<', false)
            ->assertSee('month=2026-10', false)
            ->assertDontSee('month=2026-07', false);

        $this->get(route('calendars.show', [$calendar, 'month' => '2026-09']))
            ->assertRedirect(route('calendars.show', [$calendar, 'month' => '2026-08']));

        $october = $this->get(route('calendars.show', [$calendar, 'month' => '2026-10']));
        $october->assertOk()
            ->assertSee('month=2026-08', false)
            ->assertDontSee('>Next<', false)
            ->assertDontSee('month=2026-11', false);
    }

    public function test_pdf_download_contains_only_populated_month_pages_and_event_details(): void
    {
        [$calendar, $source] = $this->calendarWithEvents();
        $builder = app(CalendarMonthBuilder::class);
        $events = $builder->events($calendar);
        $pages = $builder->populatedMonths($calendar, $events)
            ->map(fn ($month) => $builder->build($calendar, $month, $events));

        $html = view('calendars.pdf', compact('calendar', 'pages'))->render();
        $this->assertSame(2, substr_count($html, 'class="month-page'));
        $this->assertStringContainsString('August 2026', $html);
        $this->assertStringContainsString('October 2026', $html);
        $this->assertStringNotContainsString('September 2026', $html);
        $this->assertStringContainsString('Main Arena', $html);
        $this->assertStringContainsString($source->color, $html);

        $response = $this->get(route('calendars.pdf', $calendar));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(2, $this->pdfPageCount($response->getContent()));
    }

    public function test_a_crowded_month_never_overflows_to_another_pdf_page(): void
    {
        $calendar = Calendar::factory()->create(['timezone' => 'UTC']);

        foreach (range(1, 20) as $number) {
            $calendar->manualEvents()->create([
                'title' => "Long community event name number {$number}",
                'location' => "Community venue number {$number}",
                'starts_at' => CarbonImmutable::parse('2026-08-24 09:00', 'UTC')->addMinutes($number),
                'ends_at' => CarbonImmutable::parse('2026-08-24 10:00', 'UTC')->addMinutes($number),
                'all_day' => false,
                'color' => '#173f35',
            ]);
        }

        $response = $this->get(route('calendars.pdf', $calendar));
        $response->assertOk();
        $this->assertSame(1, $this->pdfPageCount($response->getContent()));

        $this->get(route('calendars.show', [$calendar, 'month' => '2026-08']))
            ->assertOk()
            ->assertSee('Long community event name number 1')
            ->assertSee('Long community event name number 20');
    }

    public function test_month_grid_does_not_include_an_unneeded_sixth_week(): void
    {
        $calendar = Calendar::factory()->create(['timezone' => 'UTC']);
        $calendar->manualEvents()->create([
            'title' => 'September event',
            'starts_at' => CarbonImmutable::parse('2026-09-19 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-19 10:00', 'UTC'),
            'all_day' => false,
            'color' => '#173f35',
        ]);

        $this->get(route('calendars.show', [$calendar, 'month' => '2026-09']))
            ->assertOk()
            ->assertSee('data-date="2026-10-03"', false)
            ->assertDontSee('data-date="2026-10-04"', false)
            ->assertDontSee('data-date="2026-10-10"', false);
    }

    /** @return array{Calendar, CalendarSource} */
    private function calendarWithEvents(): array
    {
        $calendar = Calendar::factory()->create(['timezone' => 'UTC']);
        $source = $calendar->sources()->create([
            'name' => 'Hockey',
            'url' => 'https://8.8.8.8/hockey.ics',
            'normalized_url' => 'https://8.8.8.8/hockey.ics',
            'color' => '#c2410c',
        ]);

        $source->events()->create([
            'uid' => 'august-game',
            'occurrence_key' => hash('sha256', 'august-game'),
            'title' => 'Community Championship Hockey Game',
            'location' => 'Main Arena',
            'starts_at' => CarbonImmutable::parse('2026-08-24 19:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-08-24 21:00:00', 'UTC'),
        ]);
        $source->events()->create([
            'uid' => 'october-game',
            'occurrence_key' => hash('sha256', 'october-game'),
            'title' => 'Fall Hockey Final',
            'location' => 'Main Arena',
            'starts_at' => CarbonImmutable::parse('2026-10-03 18:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-03 20:00:00', 'UTC'),
        ]);

        return [$calendar, $source];
    }

    private function pdfPageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page\b/', $pdf);
    }
}
