<?php

namespace Tests\Feature;

use App\Models\Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_ical_feed_aggregates_enabled_sources_and_manual_events(): void
    {
        $calendar = Calendar::factory()->create([
            'name' => 'The Kids, Together',
            'slug' => 'kids-together',
            'timezone' => 'America/Toronto',
        ]);
        $enabled = $calendar->sources()->create([
            'name' => 'Soccer',
            'url' => 'https://8.8.8.8/soccer.ics',
            'normalized_url' => 'https://8.8.8.8/soccer.ics',
            'color' => '#ec4899',
            'enabled' => true,
        ]);
        $disabled = $calendar->sources()->create([
            'name' => 'Old schedule',
            'url' => 'https://8.8.4.4/old.ics',
            'normalized_url' => 'https://8.8.4.4/old.ics',
            'color' => '#111111',
            'enabled' => false,
        ]);

        $sourceEvent = $enabled->events()->create([
            'uid' => 'source-event',
            'occurrence_key' => hash('sha256', 'source-event'),
            'title' => 'Soccer; finals',
            'description' => "Bring water\nand cleats",
            'location' => 'Field 1, North',
            'starts_at' => CarbonImmutable::parse('2026-09-12 14:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-12 15:30:00', 'UTC'),
            'all_day' => false,
        ]);
        $disabled->events()->create([
            'uid' => 'disabled-event',
            'occurrence_key' => hash('sha256', 'disabled-event'),
            'title' => 'Do not export me',
            'starts_at' => CarbonImmutable::parse('2026-09-12 16:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-12 17:00:00', 'UTC'),
            'all_day' => false,
        ]);
        $manualEvent = $calendar->manualEvents()->create([
            'title' => 'Tournament day',
            'location' => 'Community centre',
            'starts_at' => CarbonImmutable::parse('2026-09-19 04:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-20 04:00:00', 'UTC'),
            'all_day' => true,
            'color' => '#3157d5',
        ]);

        $response = $this->get(route('calendars.ics', $calendar));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $ical = $response->getContent();
        $this->assertStringContainsString("BEGIN:VCALENDAR\r\n", $ical);
        $this->assertStringContainsString('X-WR-CALNAME:The Kids\, Together', $ical);
        $this->assertStringContainsString("UID:source-{$sourceEvent->id}@kids-together.kidcal", $ical);
        $this->assertStringContainsString('DTSTART:20260912T140000Z', $ical);
        $this->assertStringContainsString('SUMMARY:Soccer\; finals', $ical);
        $this->assertStringContainsString('LOCATION:Field 1\, North', $ical);
        $this->assertStringContainsString('DESCRIPTION:Bring water\nand cleats', $ical);
        $this->assertStringContainsString('COLOR:#EC4899', $ical);
        $this->assertStringContainsString("UID:manual-{$manualEvent->id}@kids-together.kidcal", $ical);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260919', $ical);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260920', $ical);
        $this->assertStringNotContainsString('Do not export me', $ical);
        $this->assertSame(2, substr_count($ical, 'BEGIN:VEVENT'));
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ical);
    }

    public function test_public_calendar_offers_webcal_subscription_link(): void
    {
        $calendar = Calendar::factory()->create();

        $this->get(route('calendars.show', $calendar))
            ->assertOk()
            ->assertSee('Subscribe in calendar app')
            ->assertSee(preg_replace('/^https?:/', 'webcal:', route('calendars.ics', $calendar)), false);
    }

    public function test_recurring_manual_occurrences_have_unique_ical_uids(): void
    {
        $calendar = Calendar::factory()->create(['slug' => 'practice', 'timezone' => 'UTC']);
        $event = $calendar->manualEvents()->create([
            'title' => 'Practice',
            'starts_at' => CarbonImmutable::parse('2026-09-01 18:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-01 19:00', 'UTC'),
            'all_day' => false,
            'color' => '#3157d5',
            'recurrence_type' => 'daily',
            'recurrence_interval' => 1,
            'recurrence_until' => '2026-09-02',
        ]);

        $ical = $this->get(route('calendars.ics', $calendar))->getContent();

        $this->assertStringContainsString("UID:manual-{$event->id}-20260901T180000Z@practice.kidcal", $ical);
        $this->assertStringContainsString("UID:manual-{$event->id}-20260902T180000Z@practice.kidcal", $ical);
        $this->assertSame(2, substr_count($ical, 'BEGIN:VEVENT'));
    }
}
