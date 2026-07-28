<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The actual phone/tablet-by-screen-size block runs client-side (inline
 * viewport check in layouts/test.blade.php + resources/js/test/screen-guard.js)
 * since screen size isn't knowable server-side. These tests only verify the
 * guard is wired into the rendered page and that the fallback page works —
 * the viewport threshold itself needs manual/browser verification.
 */
class EngineMobileBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_engine_page_includes_the_screen_size_guard()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('engine.session'));

        $response->assertOk();
        // Width and height are gated separately: a single min(w, h) threshold
        // locked out 1366x768 laptops, whose inner height is ~620 after chrome.
        $response->assertSee('SCREEN_MIN_WIDTH', false);
        $response->assertSee('SCREEN_MIN_HEIGHT', false);
        $response->assertSee('screenSizeGuard', false);
    }

    public function test_mobile_blocked_page_loads()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->get(route('engine.mobile-blocked'));

        $response->assertOk();
        $response->assertSee('screen is too small');
    }
}
