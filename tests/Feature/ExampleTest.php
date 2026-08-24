<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_kidcal_landing_page_is_displayed_without_starter_branding(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('KidCal')
            ->assertSee('Less schedule juggling')
            ->assertDontSee('Laravel')
            ->assertDontSee('Repository')
            ->assertDontSee('Documentation');
    }
}
