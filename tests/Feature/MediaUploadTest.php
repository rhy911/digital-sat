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

        // Verify URL matches the Laravel-served media URL pattern
        $this->assertMatchesRegularExpression('/^\/media\/[a-zA-Z0-9]{20}\.png$/', $responseData['url']);
        $this->assertStringContainsString('![](', $responseData['markdown']);
        $this->assertSame('![]('.$responseData['url'].')', $responseData['markdown']);

        // Verify file was stored on disk
        $filename = basename($responseData['url']);
        Storage::disk('public')->assertExists('media/' . $filename);

        $showResponse = $this->get($responseData['url']);
        $showResponse->assertOk();
        $cacheControl = $showResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('immutable', $cacheControl);
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

    public function test_media_show_returns_not_found_for_missing_file(): void
    {
        Storage::fake('public');

        $this->get('/media/abcdefghijklmnopqrst.png')->assertNotFound();
    }

    public function test_media_show_rejects_invalid_filename(): void
    {
        Storage::fake('public');

        $this->get('/media/not-a-valid-file.png')->assertNotFound();
        $this->get('/media/../secret.png')->assertNotFound();
    }

    public function test_legacy_storage_media_markdown_is_normalized(): void
    {
        $markdown = implode(' ', [
            '![](https://dsat.bkse.vn/storage/media/abcdefghijklmnopqrst.jpg)',
            '![](/storage/media/ABCDEFGHIJKLMNOPQRST.png)',
            '![](https://example.com/storage/media/abcdefghijklmnopqrst.jpg)',
        ]);

        $normalized = \App\Support\QuestionMediaUrl::normalizeMarkdown($markdown);

        $this->assertStringContainsString('![](/media/abcdefghijklmnopqrst.jpg)', $normalized);
        $this->assertStringContainsString('![](/media/ABCDEFGHIJKLMNOPQRST.png)', $normalized);
        $this->assertStringContainsString('![](https://example.com/storage/media/abcdefghijklmnopqrst.jpg)', $normalized);
    }
}
