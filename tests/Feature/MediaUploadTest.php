<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test successful media upload.
     */
    public function test_media_upload_success(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test_image.png', 100, 100);

        $response = $this->actingAs($this->user)
            ->postJson(route('home-dashboard.media.upload'), [
                'image' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('url', $responseData);
        $this->assertArrayHasKey('markdown', $responseData);

        // Verify URL matches the expected public asset URL pattern
        $this->assertStringContainsString('/storage/media/', $responseData['url']);
        $this->assertStringContainsString('![](', $responseData['markdown']);
        $this->assertStringContainsString('/storage/media/', $responseData['markdown']);

        // Verify file was stored on disk
        $filename = basename($responseData['url']);
        Storage::disk('public')->assertExists('media/' . $filename);
    }

    /**
     * Test media upload validation fails for non-image.
     */
    public function test_media_upload_validation_fails_for_non_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson(route('home-dashboard.media.upload'), [
                'image' => $file,
            ]);

        $response->assertStatus(422); // Validation error
    }
}
