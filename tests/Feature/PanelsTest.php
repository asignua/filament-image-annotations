<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class PanelsTest extends TestCase
{
    protected function tearDown(): void
    {
        ImageAnnotations::flushPanels();

        parent::tearDown();
    }

    public function test_a_registered_panel_is_rendered_next_to_the_viewer_with_a_scope(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $media = Post::create(['user_id' => $user->id])->addMedia(UploadedFile::fake()->image('a.jpg', 100, 80))->toMediaCollection('photos');
        $source = new MediaImageSource($media);
        $this->actingAs($user);
        ImageAnnotations::store()->save($source, $user, ['v' => 1, 'items' => [['id' => 'a', 'type' => 'rect', 'pts' => [[0.1, 0.1], [0.5, 0.5]]]]]);

        ImageAnnotations::addPanel(static fn (ImageSource $s, string $context): string => '<aside data-test-panel="'.$context.'"></aside>');

        $html = Blade::render('<x-image-annotations::image :image="$media" />', ['media' => $media]);

        $this->assertStringContainsString('data-ia-scope', $html);
        $this->assertStringContainsString('data-test-panel="viewer"', $html);
        $this->assertStringContainsString('min-width:min(24rem,100%)', $html);

        ImageAnnotations::flushPanels();

        $this->assertStringNotContainsString('min-width:min(24rem', Blade::render('<x-image-annotations::image :image="$media" />', ['media' => $media]));
        $this->assertStringNotContainsString('data-ia-scope', Blade::render('<x-image-annotations::image :image="$media" />', ['media' => $media]));
    }
}
