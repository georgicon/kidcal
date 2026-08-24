<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\CalendarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_webcal_feed_and_import_recurring_events(): void
    {
        Http::fake([
            'https://8.8.8.8/events.ics' => Http::response($this->recurringCalendar(), 200, [
                'ETag' => '"feed-v1"',
                'Content-Type' => 'text/calendar',
            ]),
        ]);
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();

        $this->actingAs($user)->post(route('calendar-sources.store', $calendar), [
            'links' => 'webcal://8.8.8.8/events.ics',
            'color' => '#dc2626',
        ])->assertRedirect(route('calendars.edit', $calendar));

        $source = CalendarSource::sole();
        $this->assertSame('https://8.8.8.8/events.ics', $source->normalized_url);
        $this->assertSame('synced', $source->sync_status);
        $this->assertSame('"feed-v1"', $source->etag);
        $this->assertCount(3, $source->events);

        $this->get(route('calendars.show', [$calendar, 'month' => '2026-08']))
            ->assertOk()
            ->assertSee('Neighborhood Meeting');
    }

    public function test_failed_refresh_keeps_events_from_the_last_good_import(): void
    {
        Http::fakeSequence()
            ->push($this->recurringCalendar(), 200)
            ->push('Unavailable', 503);

        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();

        $this->actingAs($user)->post(route('calendar-sources.store', $calendar), [
            'links' => 'https://8.8.8.8/events.ics',
            'color' => '#2563eb',
        ]);

        $source = CalendarSource::sole();
        $this->post(route('calendar-sources.refresh', [$calendar, $source]));

        $this->assertSame('failed', $source->refresh()->sync_status);
        $this->assertCount(3, $source->events);
    }

    public function test_feed_import_follows_safe_redirects(): void
    {
        Http::fake([
            'https://8.8.8.8/calendar' => Http::response('', 302, ['Location' => '/feeds/events.ics']),
            'https://8.8.8.8/feeds/events.ics' => Http::response($this->recurringCalendar()),
        ]);
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();

        $this->actingAs($user)->post(route('calendar-sources.store', $calendar), [
            'links' => 'https://8.8.8.8/calendar',
            'color' => '#2563eb',
        ]);

        $source = CalendarSource::sole();
        $this->assertSame('synced', $source->sync_status);
        $this->assertCount(3, $source->events);
    }

    public function test_private_network_feed_urls_are_rejected(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();

        $this->actingAs($user)->post(route('calendar-sources.store', $calendar), [
            'links' => 'http://127.0.0.1/private.ics',
            'color' => '#2563eb',
        ])->assertInvalid(['links']);

        $this->assertDatabaseCount('calendar_sources', 0);
    }

    public function test_owner_can_paste_mixed_text_to_create_many_feeds_and_skip_duplicates(): void
    {
        Http::fake([
            'https://8.8.8.8/*' => Http::response($this->recurringCalendar()),
            'https://1.1.1.1/*' => Http::response($this->recurringCalendar()),
        ]);
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();
        $calendar->sources()->create([
            'name' => 'Existing feed',
            'url' => 'https://8.8.8.8/existing.ics',
            'normalized_url' => 'https://8.8.8.8/existing.ics',
            'color' => '#000000',
        ]);

        $this->actingAs($user)->post(route('calendar-sources.store', $calendar), [
            'links' => <<<'LINKS'
                Here are the calendars:
                https://8.8.8.8/existing.ics,
                webcal://1.1.1.1/team.ics
                https://8.8.8.8/new.ics
                LINKS,
            'color' => '#f97316',
        ])->assertRedirect(route('calendars.edit', $calendar));

        $this->assertCount(3, $calendar->sources()->get());
        $this->assertDatabaseHas('calendar_sources', [
            'calendar_id' => $calendar->id,
            'name' => '1.1.1.1',
            'normalized_url' => 'https://1.1.1.1/team.ics',
            'color' => '#f97316',
        ]);
        $this->assertDatabaseHas('calendar_sources', [
            'calendar_id' => $calendar->id,
            'name' => '8.8.8.8',
            'normalized_url' => 'https://8.8.8.8/new.ics',
        ]);
    }

    public function test_non_owner_cannot_manage_another_users_sources(): void
    {
        $calendar = Calendar::factory()->create();
        $source = $calendar->sources()->create([
            'name' => 'Private controls',
            'url' => 'https://8.8.8.8/events.ics',
            'normalized_url' => 'https://8.8.8.8/events.ics',
            'color' => '#2563eb',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->put(route('calendar-sources.update', [$calendar, $source]), [
            'name' => 'Changed',
            'color' => '#000000',
            'enabled' => '1',
        ])->assertForbidden();

        $this->delete(route('calendar-sources.destroy', [$calendar, $source]))->assertForbidden();
        $this->assertNotNull($source->fresh());
    }

    public function test_feed_settings_can_be_autosaved_as_json(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();
        $source = $calendar->sources()->create([
            'name' => 'Original feed',
            'url' => 'https://8.8.8.8/events.ics',
            'normalized_url' => 'https://8.8.8.8/events.ics',
            'color' => '#2563eb',
        ]);

        $this->actingAs($user)->putJson(route('calendar-sources.update', [$calendar, $source]), [
            'name' => 'Autosaved feed',
            'color' => '#f97316',
            'enabled' => '0',
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertSame('Autosaved feed', $source->refresh()->name);
        $this->assertFalse($source->enabled);
    }

    public function test_source_must_belong_to_the_calendar_in_the_route(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();
        $otherCalendar = Calendar::factory()->for($user)->create();
        $source = $otherCalendar->sources()->create([
            'name' => 'Other feed',
            'url' => 'https://8.8.8.8/events.ics',
            'normalized_url' => 'https://8.8.8.8/events.ics',
            'color' => '#2563eb',
        ]);

        $this->actingAs($user)->post(route('calendar-sources.refresh', [$calendar, $source]))
            ->assertNotFound();
    }

    private function recurringCalendar(): string
    {
        return <<<'ICAL'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//GatherCal Tests//EN
BEGIN:VEVENT
UID:meeting@example.test
DTSTART:20260825T140000Z
DTEND:20260825T150000Z
RRULE:FREQ=DAILY;COUNT=3
SUMMARY:Neighborhood Meeting
LOCATION:Town Hall
DESCRIPTION:Bring your ideas.
END:VEVENT
END:VCALENDAR
ICAL;
    }
}
