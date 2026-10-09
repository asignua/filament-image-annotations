<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\Forms\AnnotationsPanel;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Asignua\FilamentImageAnnotations\Sources\ModelPathImageSource;
use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Notes\Pages\CreateNote;
use Workbench\App\Filament\Resources\Notes\Pages\EditNote;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Models\Note;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class AnnotatedUploadTest extends TestCase
{
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->author = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function layer(): array
    {
        return ['v' => 1, 'items' => [['id' => 'a1', 'type' => 'arrow', 'pts' => [[0.1, 0.1], [0.6, 0.5]], 'color' => 'red', 'width' => 4]]];
    }

    private function note(): Note
    {
        Storage::disk('public')->putFileAs('notes', UploadedFile::fake()->image('a.png', 40, 30), 'a.png');

        return Note::create(['image' => 'notes/a.png']);
    }

    private function annotate(string $key): TestAction
    {
        return TestAction::make(AnnotationsPanel::ACTION)->schemaComponent('image_annotations', schema: 'form')->arguments(['key' => $key]);
    }

    private function postAnnotate(string $key): TestAction
    {
        return TestAction::make(AnnotationsPanel::ACTION)->schemaComponent('photos_annotations', schema: 'form')->arguments(['key' => $key]);
    }

    private function panelOf(mixed $test): AnnotationsPanel
    {
        foreach ($test->instance()->getSchema('form')->getFlatComponents() as $component) {
            if ($component instanceof AnnotationsPanel) {
                return $component;
            }
        }

        $this->fail('No annotations panel on the page.');
    }

    /**
     * @return array<string, string>
     */
    private function stateOf(mixed $test, string $field): array
    {
        return (array) $test->get('data.'.$field);
    }

    public function test_the_panel_lists_only_the_stored_images_of_the_upload(): void
    {
        $this->actingAs($this->author);
        $post = Post::create(['user_id' => $this->author->id]);
        $post->addMedia(UploadedFile::fake()->image('door.jpg', 400, 300))->toMediaCollection('photos');
        $post->addMedia(UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf'))->toMediaCollection('photos');

        $test = Livewire::test(EditPost::class, ['record' => $post->getKey()]);
        $panel = $this->panelOf($test);
        $this->assertInstanceOf(AnnotationsPanel::class, $panel);

        $rows = $panel->getRows();

        $this->assertCount(1, $rows);
        $this->assertSame('door', $rows[0]['name']);
        $this->assertTrue($rows[0]['stored']);
        $this->assertTrue($rows[0]['can']);
    }

    public function test_the_button_is_disabled_for_an_upload_that_is_not_stored_yet(): void
    {
        $this->actingAs($this->author);

        $test = Livewire::test(CreateNote::class)
            ->fillForm(['image' => UploadedFile::fake()->image('new.png', 40, 30)]);

        $panel = $this->panelOf($test);
        $rows = $panel->getRows();

        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['stored']);
        $test->assertSee(__('filament-image-annotations::filament-image-annotations.upload.save_first'));
        $this->assertNull($panel->sourceFor($rows[0]['key']));
    }

    public function test_the_action_saves_the_layer_of_a_media_item(): void
    {
        $this->actingAs($this->author);
        $post = Post::create(['user_id' => $this->author->id]);
        $media = $post->addMedia(UploadedFile::fake()->image('door.jpg', 400, 300))->toMediaCollection('photos');

        $test = Livewire::test(EditPost::class, ['record' => $post->getKey()]);
        $key = (string) array_key_first($this->stateOf($test, 'photos'));

        $test->callAction($this->postAnnotate($key), ['annotations' => $this->layer()])->assertHasNoActionErrors();

        $this->assertCount(1, ImageAnnotations::store()->shapes(new MediaImageSource($media), $this->author)['items']);
    }

    public function test_the_button_of_a_stored_file_is_enabled(): void
    {
        $this->actingAs($this->author);
        $post = Post::create(['user_id' => $this->author->id]);
        $post->addMedia(UploadedFile::fake()->image('door.jpg', 400, 300))->toMediaCollection('photos');

        $test = Livewire::test(EditPost::class, ['record' => $post->getKey()]);
        $key = (string) array_key_first($this->stateOf($test, 'photos'));

        $test->assertActionVisible($this->postAnnotate($key))->assertActionEnabled($this->postAnnotate($key));
        // assertActionEnabled() builds its own action; the RENDERED per-file button is what broke (a cloned action
        // whose closures still saw the argument-less original).
        $this->assertStringNotContainsString('fi-ac-link-action fi-link fi-disabled', $test->html());
    }

    public function test_the_action_saves_the_layer_of_a_path_and_the_count_shows(): void
    {
        $this->actingAs($this->author);
        $note = $this->note();

        $test = Livewire::test(EditNote::class, ['record' => $note->getKey()]);
        $key = (string) array_key_first($this->stateOf($test, 'image'));

        $test->callAction($this->annotate($key), ['annotations' => $this->layer()])->assertHasNoActionErrors();

        $row = ImageAnnotation::query()->sole();
        $this->assertSame('notes/a.png', $row->key);
        $this->assertSame($note->getKey(), $row->annotatable_id);
        $test->assertSee(__('filament-image-annotations::filament-image-annotations.upload.shapes', ['count' => 1]));
    }

    public function test_removing_a_file_forgets_its_annotations_on_save(): void
    {
        $this->actingAs($this->author);
        $note = $this->note();
        ImageAnnotations::store()->save(new ModelPathImageSource($note, 'notes/a.png', 'public'), $this->author, $this->layer());

        $this->assertSame(1, ImageAnnotation::query()->count());

        Livewire::test(EditNote::class, ['record' => $note->getKey()])
            ->fillForm(['image' => null])
            ->call('save');

        $this->assertSame(0, ImageAnnotation::query()->count());
    }

    public function test_keeping_the_file_keeps_its_annotations_on_save(): void
    {
        $this->actingAs($this->author);
        $note = $this->note();
        ImageAnnotations::store()->save(new ModelPathImageSource($note, 'notes/a.png', 'public'), $this->author, $this->layer());

        Livewire::test(EditNote::class, ['record' => $note->getKey()])->call('save');

        $this->assertSame(1, ImageAnnotation::query()->count());
    }

    public function test_an_unauthorized_user_gets_no_button(): void
    {
        $post = Post::create(['user_id' => $this->author->id]);
        $post->addMedia(UploadedFile::fake()->image('door.jpg', 400, 300))->toMediaCollection('photos');

        // The page is open to the author; annotating is denied by the override.
        $this->actingAs($this->author);
        ImageAnnotations::authorizeUsing(static fn ($user, $source, string $ability): bool => $ability !== 'annotate');

        $test = Livewire::test(EditPost::class, ['record' => $post->getKey()]);
        $panel = $this->panelOf($test);

        $this->assertFalse($panel->getRows()[0]['can'] ?? false);
        $test->assertActionHidden($this->postAnnotate((string) array_key_first($this->stateOf($test, 'photos'))));
    }

    public function test_a_forged_key_resolves_to_no_source(): void
    {
        $this->actingAs($this->author);
        $note = $this->note();

        $test = Livewire::test(EditNote::class, ['record' => $note->getKey()]);
        $panel = $this->panelOf($test);

        $this->assertNull($panel->sourceFor('nope'));
        $this->assertNull($panel->sourceFor(null));
    }
}
