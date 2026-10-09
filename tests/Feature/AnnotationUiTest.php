<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\Actions\AnnotateImageAction;
use Asignua\FilamentImageAnnotations\Forms\Components\ImageAnnotator;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\ImageAnnotationsServiceProvider;
use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Filament\Resources\Posts\Pages\ViewPost;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class AnnotationUiTest extends TestCase
{
    private User $author;

    private Post $post;

    private Media $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->author = User::factory()->create();
        $this->post = Post::create(['user_id' => $this->author->id]);
        $this->media = $this->post->addMedia(UploadedFile::fake()->image('door.jpg', 400, 300))->toMediaCollection('photos');
    }

    /**
     * @return array<string, mixed>
     */
    private function layer(string $text = 'Check this'): array
    {
        return [
            'v' => 1,
            'items' => [
                ['id' => 'a1', 'type' => 'arrow', 'pts' => [[0.1, 0.1], [0.6, 0.5]], 'color' => 'red', 'width' => 4],
                ['id' => 't1', 'type' => 'text', 'pts' => [[0.2, 0.7]], 'color' => 'blue', 'width' => 4, 'text' => $text],
            ],
        ];
    }

    private function store(string $text = 'Check this'): void
    {
        ImageAnnotations::store()->save(new MediaImageSource($this->media), $this->author, $this->layer($text));
    }

    private function annotate(): TestAction
    {
        return TestAction::make(AnnotateImageAction::getDefaultName())->table($this->post);
    }

    public function test_the_assets_are_registered(): void
    {
        $this->assertNotEmpty(FilamentAsset::getAlpineComponentSrc(ImageAnnotationsServiceProvider::ANNOTATOR, ImageAnnotationsServiceProvider::PACKAGE));
        $this->assertNotEmpty(FilamentAsset::getAlpineComponentSrc(ImageAnnotationsServiceProvider::VIEWER, ImageAnnotationsServiceProvider::PACKAGE));
    }

    public function test_the_action_mounts_with_the_stored_shapes_prefilled(): void
    {
        $this->actingAs($this->author);
        $this->store();

        Livewire::test(ListPosts::class)
            ->mountAction($this->annotate())
            ->assertActionDataSet(fn (array $data): bool => ($data['annotations']['items'][1]['text'] ?? null) === 'Check this');
    }

    public function test_the_action_saves_sanitized_shapes(): void
    {
        $this->actingAs($this->author);

        Livewire::test(ListPosts::class)
            ->callAction($this->annotate(), ['annotations' => [
                'v' => 1,
                'items' => [
                    ['id' => 'ok', 'type' => 'rect', 'pts' => [[-3, 0.2], [0.5, 9]], 'color' => 'nope', 'width' => 999],
                    ['id' => 'bad', 'type' => 'script', 'pts' => [[0, 0], [1, 1]]],
                ],
            ]])
            ->assertHasNoActionErrors();

        $row = ImageAnnotation::query()->sole();
        $items = $row->shapes['items'];

        $this->assertCount(1, $items);
        $this->assertSame('rect', $items[0]['type']);
        $this->assertEquals([0.0, 0.2], $items[0]['pts'][0]);
        $this->assertEquals([0.5, 1.0], $items[0]['pts'][1]);
        $this->assertSame($this->author->id, $row->user_id);
    }

    public function test_the_action_is_hidden_for_a_user_who_may_not_update_the_record(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ListPosts::class)->assertActionHidden($this->annotate());
    }

    public function test_the_action_is_hidden_for_a_record_without_an_image(): void
    {
        $this->actingAs($this->author);
        $empty = Post::create(['user_id' => $this->author->id]);

        Livewire::test(ListPosts::class)->assertActionHidden(TestAction::make('annotateImage')->table($empty));
    }

    public function test_the_view_page_renders_the_image_and_the_layer(): void
    {
        $this->actingAs($this->author);
        $this->store();

        $html = Livewire::test(ViewPost::class, ['record' => $this->post->getKey()])->html();

        $this->assertStringContainsString($this->media->getUrl(), $html);
        $this->assertStringContainsString('data-annotations="', $html);
        $this->assertStringContainsString('Check this', $html);
        $this->assertStringContainsString('imageAnnotationsViewer', $html);
    }

    public function test_user_text_is_escaped_in_the_data_attribute(): void
    {
        $this->actingAs($this->author);
        $this->store('<script>alert(1)</script>');

        $html = Livewire::test(ViewPost::class, ['record' => $this->post->getKey()])->html();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)', $html);
    }

    public function test_the_entry_without_a_layer_is_the_plain_picture(): void
    {
        $this->actingAs($this->author);

        $html = Livewire::test(ViewPost::class, ['record' => $this->post->getKey()])->html();

        $this->assertStringContainsString($this->media->getUrl(), $html);
        $this->assertStringNotContainsString('data-annotations', $html);
    }

    public function test_the_blade_component_shows_the_layer_to_a_user_who_may_view(): void
    {
        $this->actingAs($this->author);
        $this->store();

        $html = Blade::render('<x-image-annotations::image :image="$media" alt="Door" />', ['media' => $this->media]);

        $this->assertStringContainsString('data-annotations="', $html);
        $this->assertStringContainsString('alt="Door"', $html);
    }

    public function test_the_blade_component_shows_a_plain_image_without_permission(): void
    {
        $this->store();

        $this->actingAs(User::factory()->create());

        $html = Blade::render('<x-image-annotations::image :image="$media" alt="Door" />', ['media' => $this->media]);

        $this->assertStringContainsString($this->media->getUrl(), $html);
        $this->assertStringNotContainsString('data-annotations', $html);
        $this->assertStringNotContainsString('Check this', $html);
    }

    public function test_the_blade_component_shows_a_plain_image_to_a_guest(): void
    {
        $this->store();

        $html = Blade::render('<x-image-annotations::image :image="$media" />', ['media' => $this->media]);

        $this->assertStringNotContainsString('data-annotations', $html);
    }

    public function test_the_field_resolves_its_image_url(): void
    {
        $field = ImageAnnotator::make('annotations')->image(new MediaImageSource($this->media));

        $this->assertSame($this->media->getUrl(), $field->getImageUrl());
        $this->assertNull(ImageAnnotator::make('x')->getImageUrl());
    }
}
