<?php

namespace Tests\Feature;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_a_calendar_without_signing_in(): void
    {
        $calendar = Calendar::factory()->create([
            'name' => 'Community Events',
            'description' => 'Everything happening nearby.',
        ]);

        $this->get(route('calendars.show', $calendar))
            ->assertOk()
            ->assertSee('Community Events')
            ->assertSee('Everything happening nearby.');
    }

    public function test_guest_cannot_access_calendar_management(): void
    {
        $calendar = Calendar::factory()->create();

        $this->get(route('calendars.create'))->assertRedirect(route('login'));
        $this->get(route('calendars.edit', $calendar))->assertRedirect(route('login'));
    }

    public function test_owner_can_view_calendar_management_screens(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('calendars.create'))
            ->assertOk()
            ->assertSee('Create a public calendar');

        $this->get(route('calendars.edit', $calendar))
            ->assertOk()
            ->assertSee($calendar->name);
    }

    public function test_owner_can_create_a_calendar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('calendars.store'), [
            'name' => 'Downtown Arts',
            'description' => 'Arts and culture events.',
            'timezone' => 'America/Toronto',
            'week_starts_on' => 1,
        ]);

        $calendar = Calendar::sole();

        $response->assertRedirect(route('calendars.edit', $calendar));
        $this->assertTrue($calendar->user->is($user));
        $this->assertSame('downtown-arts', $calendar->slug);
    }

    public function test_duplicate_names_receive_unique_public_slugs(): void
    {
        Calendar::factory()->create(['slug' => 'community-events']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('calendars.store'), [
            'name' => 'Community Events',
            'timezone' => 'UTC',
            'week_starts_on' => 0,
        ])->assertValid();

        $this->assertDatabaseHas('calendars', [
            'user_id' => $user->id,
            'slug' => 'community-events-2',
        ]);
    }

    public function test_owner_can_update_calendar_settings_without_changing_slug(): void
    {
        $user = User::factory()->create();
        $calendar = Calendar::factory()->for($user)->create(['slug' => 'stable-link']);

        $this->actingAs($user)->put(route('calendars.update', $calendar), [
            'name' => 'Renamed Calendar',
            'description' => 'Updated description.',
            'timezone' => 'Europe/London',
            'week_starts_on' => 1,
        ])->assertRedirect(route('calendars.edit', $calendar));

        $this->assertDatabaseHas('calendars', [
            'id' => $calendar->id,
            'name' => 'Renamed Calendar',
            'slug' => 'stable-link',
            'timezone' => 'Europe/London',
        ]);
    }

    public function test_non_owner_cannot_edit_or_update_a_calendar(): void
    {
        $calendar = Calendar::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('calendars.edit', $calendar))
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->put(route('calendars.update', $calendar), [
                'name' => 'Taken over',
                'timezone' => 'UTC',
                'week_starts_on' => 0,
            ])
            ->assertForbidden();
    }

    public function test_calendar_settings_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('calendars.store'), [
            'name' => '',
            'timezone' => 'Not/A_Timezone',
            'week_starts_on' => 2,
        ])->assertInvalid(['name', 'timezone', 'week_starts_on']);
    }
}
