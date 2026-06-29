<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestEngineTranslationProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_engine_layout_opts_out_of_browser_translation(): void
    {
        $student = User::factory()->student()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('engine.session', 'preview-rw'));

        $response->assertOk();
        $response->assertSee('<html lang="en" translate="no" class="notranslate">', false);
        $response->assertSee('<meta name="google" content="notranslate">', false);
        $response->assertSee('<body translate="no" class="notranslate">', false);
        $response->assertSee('<header translate="no" class="notranslate">', false);
        $response->assertSee('<main translate="no" class="notranslate">', false);
        $response->assertSee('<footer translate="no" class="notranslate">', false);
    }
}
