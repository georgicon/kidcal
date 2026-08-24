<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\ManualEvent;
use App\Models\User;
use App\Services\Calendar\CalendarMonthBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_remove_an_ad_hoc_event(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)->post(route('manual-events.store', $calendar), [
            'title' => 'Volunteer orientation',
            'location' => 'Community Hall',
            'date' => '2026-09-12',
            'start_time' => '09:30',
            'end_time' => '11:00',
            'all_day' => '0',
            'color' => '#0f766e',
        ])->assertRedirect(route('calendars.edit', $calendar));

        $event = ManualEvent::sole();
        $this->assertSame('2026-09-12 13:30', $event->starts_at->utc()->format('Y-m-d H:i'));

        $this->put(route('manual-events.update', [$calendar, $event]), [
            'title' => 'Updated orientation',
            'location' => 'Main Library',
            'date' => '2026-09-13',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'all_day' => '0',
            'color' => '#c2410c',
        ])->assertRedirect(route('calendars.edit', $calendar));

        $this->assertSame('Updated orientation', $event->refresh()->title);
        $this->get(route('calendars.edit', $calendar))->assertOk()->assertSee('Updated orientation');

        $this->delete(route('manual-events.destroy', [$calendar, $event]))
            ->assertRedirect(route('calendars.edit', $calendar));
        $this->assertDatabaseCount('manual_events', 0);
    }

    public function test_non_owner_cannot_manage_ad_hoc_events(): void
    {
        $calendar = Calendar::factory()->create();
        $event = $calendar->manualEvents()->create($this->manualEventAttributes());
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->delete(route('manual-events.destroy', [$calendar, $event]))
            ->assertForbidden();
        $this->assertNotNull($event->fresh());
    }

    public function test_feed_and_ad_hoc_events_are_sorted_together_by_time(): void
    {
        $calendar = Calendar::factory()->create(['timezone' => 'UTC']);
        $source = $calendar->sources()->create([
            'name' => 'Imported',
            'url' => 'https://8.8.8.8/feed.ics',
            'normalized_url' => 'https://8.8.8.8/feed.ics',
            'color' => '#2563eb',
        ]);
        $source->events()->create([
            'uid' => 'later',
            'occurrence_key' => hash('sha256', 'later'),
            'title' => 'Later feed event',
            'location' => 'Arena',
            'starts_at' => CarbonImmutable::parse('2026-09-12 11:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-12 12:00', 'UTC'),
        ]);
        $calendar->manualEvents()->create([
            ...$this->manualEventAttributes(),
            'title' => 'Early manual event',
            'starts_at' => CarbonImmutable::parse('2026-09-12 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-12 10:00', 'UTC'),
        ]);

        $this->get(route('calendars.show', [$calendar, 'month' => '2026-09']))
            ->assertOk()
            ->assertSeeInOrder(['Early manual event', 'Later feed event']);

        $builder = app(CalendarMonthBuilder::class);
        $events = $builder->events($calendar);
        $pages = collect([$builder->build($calendar, CarbonImmutable::parse('2026-09-01'), $events)]);
        $html = view('calendars.pdf', compact('calendar', 'pages'))->render();
        $this->assertStringContainsString('9:00 am - Community Hall', $html);
        $this->assertStringContainsString('Early manual event', $html);
        $this->assertTrue(strpos($html, 'Early manual event') < strpos($html, 'Later feed event'));
    }

    /** @return array<string, mixed> */
    private function manualEventAttributes(): array
    {
        return [
            'title' => 'Manual event',
            'location' => 'Community Hall',
            'starts_at' => CarbonImmutable::parse('2026-09-12 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-12 10:00', 'UTC'),
            'all_day' => false,
            'color' => '#0f766e',
        ];
    }
}
