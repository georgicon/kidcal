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

    public function test_ad_hoc_event_can_be_autosaved_as_json(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create(['timezone' => 'UTC']);
        $event = $calendar->manualEvents()->create($this->manualEventAttributes());

        $this->actingAs($user)->putJson(route('manual-events.update', [$calendar, $event]), [
            'title' => 'Autosaved practice',
            'location' => 'Arena 2',
            'date' => '2026-09-14',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'all_day' => '0',
            'color' => '#173f35',
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertSame('Autosaved practice', $event->refresh()->title);
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

    public function test_weekly_event_can_repeat_on_multiple_weekdays(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)->post(route('manual-events.store', $calendar), [
            'title' => 'Swim practice',
            'location' => 'Pool',
            'date' => '2026-09-07',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'all_day' => '0',
            'color' => '#3157d5',
            'recurrence_type' => 'weekly',
            'recurrence_interval' => '1',
            'recurrence_weekdays' => ['1', '3'],
            'recurrence_until' => '2026-09-16',
        ])->assertRedirect(route('calendars.edit', $calendar));

        $event = ManualEvent::sole();
        $this->assertSame([1, 3], $event->recurrence_weekdays);

        $occurrences = app(CalendarMonthBuilder::class)->events($calendar)
            ->map(fn ($occurrence) => $occurrence->starts_at->setTimezone($calendar->timezone)->format('Y-m-d H:i'));

        $this->assertSame([
            '2026-09-07 18:00',
            '2026-09-09 18:00',
            '2026-09-14 18:00',
            '2026-09-16 18:00',
        ], $occurrences->all());
    }

    public function test_daily_monthly_and_yearly_intervals_expand_to_occurrences(): void
    {
        $calendar = Calendar::factory()->create(['timezone' => 'UTC']);

        $daily = $calendar->manualEvents()->create([
            ...$this->manualEventAttributes(),
            'starts_at' => CarbonImmutable::parse('2026-01-01 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-01-01 10:00', 'UTC'),
            'recurrence_type' => 'daily',
            'recurrence_interval' => 2,
            'recurrence_until' => '2026-01-05',
        ]);
        $monthly = $calendar->manualEvents()->create([
            ...$this->manualEventAttributes(),
            'starts_at' => CarbonImmutable::parse('2026-01-31 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-01-31 10:00', 'UTC'),
            'recurrence_type' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_until' => '2026-03-31',
        ]);
        $yearly = $calendar->manualEvents()->create([
            ...$this->manualEventAttributes(),
            'starts_at' => CarbonImmutable::parse('2024-02-29 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2024-02-29 10:00', 'UTC'),
            'recurrence_type' => 'yearly',
            'recurrence_interval' => 1,
            'recurrence_until' => '2028-03-01',
        ]);

        $occurrences = app(CalendarMonthBuilder::class)->events($calendar)->groupBy('id');

        $this->assertSame(['2026-01-01', '2026-01-03', '2026-01-05'], $occurrences[$daily->id]->pluck('starts_at')->map->format('Y-m-d')->all());
        $this->assertSame(['2026-01-31', '2026-03-31'], $occurrences[$monthly->id]->pluck('starts_at')->map->format('Y-m-d')->all());
        $this->assertSame(['2024-02-29', '2028-02-29'], $occurrences[$yearly->id]->pluck('starts_at')->map->format('Y-m-d')->all());
    }

    public function test_recurring_event_requires_an_end_date_and_weekly_days(): void
    {
        $calendar = Calendar::factory()->create();

        $this->actingAs($calendar->user)->post(route('manual-events.store', $calendar), [
            'title' => 'Practice',
            'date' => '2026-09-07',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'all_day' => '0',
            'color' => '#3157d5',
            'recurrence_type' => 'weekly',
            'recurrence_interval' => '1',
        ])->assertInvalid(['recurrence_weekdays', 'recurrence_until']);
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
