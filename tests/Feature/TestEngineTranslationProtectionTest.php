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
        $content = $response->getContent();
        $response->assertSee('<meta name="google" content="notranslate">', false);

        foreach (['html', 'body', 'header', 'main', 'footer'] as $tag) {
            $this->assertMatchesRegularExpression("/<{$tag}[^>]*translate=\"no\"/i", $content);
            $this->assertMatchesRegularExpression("/<{$tag}[^>]*class=\"[^\"]*notranslate/i", $content);
        }
    }
}
