<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarEditorLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_link_grants_anonymous_calendar_editing_access(): void
    {
        $owner = User::factory()->create();
        $calendar = Calendar::factory()->for($owner)->create();

        $response = $this->actingAs($owner)->post(route('calendar-editor.store', $calendar));
        $response->assertRedirect(route('calendars.edit', $calendar))->assertSessionHas('editor_link');
        $editorLink = session('editor_link');

        auth()->logout();
        session()->flush();

        $this->get($editorLink)->assertRedirect(route('calendars.edit', $calendar));
        $this->get(route('calendars.edit', $calendar))
            ->assertOk()
            ->assertSee('Shared editor')
            ->assertDontSee('Create editor link');

        $this->put(route('calendars.update', $calendar), [
            'name' => 'Edited by family',
            'description' => 'Updated through the private link.',
            'timezone' => 'UTC',
            'week_starts_on' => 0,
        ])->assertRedirect(route('calendars.edit', $calendar));

        $this->post(route('manual-events.store', $calendar), [
            'title' => 'Shared family event',
            'date' => '2026-09-12',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'all_day' => '0',
            'color' => '#173f35',
        ])->assertRedirect(route('calendars.edit', $calendar));

        $this->assertSame('Edited by family', $calendar->refresh()->name);
        $this->assertDatabaseHas('manual_events', [
            'calendar_id' => $calendar->id,
            'title' => 'Shared family event',
        ]);
    }

    public function test_rotating_link_invalidates_old_url_and_old_sessions(): void
    {
        $owner = User::factory()->create();
        $calendar = Calendar::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('calendar-editor.store', $calendar));
        $oldLink = session('editor_link');
        $oldHash = $calendar->refresh()->editor_token_hash;

        $this->post(route('calendar-editor.store', $calendar));
        $this->assertNotSame($oldHash, $calendar->refresh()->editor_token_hash);

        auth()->logout();
        session()->flush();
        $this->get($oldLink)->assertNotFound();

        $this->withSession(["calendar_editor.{$calendar->id}" => $oldHash])
            ->get(route('calendars.edit', $calendar))
            ->assertForbidden();
    }

    public function test_revoking_link_invalidates_shared_access(): void
    {
        $owner = User::factory()->create();
        $calendar = Calendar::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('calendar-editor.store', $calendar));
        $link = session('editor_link');
        $hash = $calendar->refresh()->editor_token_hash;
        $this->delete(route('calendar-editor.destroy', $calendar));

        $this->assertNull($calendar->refresh()->editor_token_hash);
        auth()->logout();
        session()->flush();
        $this->get($link)->assertNotFound();
        $this->withSession(["calendar_editor.{$calendar->id}" => $hash])
            ->get(route('calendars.edit', $calendar))
            ->assertForbidden();
    }

    public function test_non_owner_cannot_create_or_revoke_editor_links(): void
    {
        $calendar = Calendar::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->post(route('calendar-editor.store', $calendar))->assertForbidden();
        $this->delete(route('calendar-editor.destroy', $calendar))->assertForbidden();
    }
}
