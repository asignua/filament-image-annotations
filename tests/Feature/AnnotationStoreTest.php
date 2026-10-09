<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Events\AnnotationDeleted;
use Asignua\FilamentImageAnnotations\Events\AnnotationSaved;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Asignua\FilamentImageAnnotations\Sources\ModelPathImageSource;
use Asignua\FilamentImageAnnotations\Support\AnnotationStore;
use Asignua\FilamentImageAnnotations\Support\ImageSources;
use Asignua\FilamentImageAnnotations\Support\Layer;
use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\Note;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class AnnotationStoreTest extends TestCase
{
    private AnnotationStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = app(AnnotationStore::class);
    }

    /**
     * @return array{v: int, items: list<array<string, mixed>>}
     */
    private function layer(string $text = 'Look here'): array
    {
        return ['v' => 1, 'items' => [
            ['id' => 'r1', 'type' => 'rect', 'pts' => [[0.1, 0.1], [0.4, 0.4]], 'color' => 'red', 'width' => 4],
            ['id' => 't1', 'type' => 'text', 'pts' => [[0.5, 0.5]], 'color' => 'blue', 'width' => 2, 'text' => $text],
        ]];
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function media(?int $ownerId = null, string $name = 'a.png'): Media
    {
        // A post is guarded by its policy (author only); a note has none.
        $post = $ownerId === null ? Note::create() : Post::create(['user_id' => $ownerId]);

        return $post->addMediaFromString('png-bytes')->usingFileName($name)->toMediaCollection();
    }

    public function test_save_creates_a_row_and_sets_the_last_editor(): void
    {
        $user = $this->user();
        $source = new MediaImageSource($this->media($user->id));

        $row = $this->store->save($source, $user, $this->layer());

        $this->assertTrue($row->exists);
        $this->assertNotEmpty($row->ulid);
        $this->assertSame($user->id, $row->user_id);
        $this->assertNull($row->key);
        $this->assertSame(2, count($row->shapes['items'] ?? []));
        $this->assertSame(1, ImageAnnotation::count());
    }

    public function test_saving_again_updates_the_single_row_and_the_last_editor(): void
    {
        $first = $this->user();
        $second = $this->user();
        $source = new MediaImageSource($this->media());

        $this->store->save($source, $first, $this->layer('one'));
        $row = $this->store->save($source, $second, $this->layer('two'));

        $this->assertSame(1, ImageAnnotation::count());
        $this->assertSame($second->id, $row->user_id);
        $this->assertSame('two', $this->store->shapes($source, $first)['items'][1]['text']);
    }

    public function test_shapes_are_sanitised_on_save(): void
    {
        $source = new MediaImageSource($this->media());

        $this->store->save($source, $this->user(), ['items' => [
            ['type' => 'rect', 'pts' => [[-1, 2], [0.5, 0.5]], 'color' => 'evil'],
            ['type' => 'nope', 'pts' => []],
        ]]);

        $shapes = $this->store->shapes($source, null);

        $this->assertCount(1, $shapes['items']);
        $this->assertSame([[0.0, 1.0], [0.5, 0.5]], $shapes['items'][0]['pts']);
        $this->assertSame('#ef4444', $shapes['items'][0]['color']);
    }

    public function test_different_keys_of_one_model_are_separate_rows(): void
    {
        $user = $this->user();
        $note = Note::create();
        $one = new ModelPathImageSource($note, 'img/one.png');
        $two = new ModelPathImageSource($note, 'img/two.png');

        $this->store->save($one, $user, $this->layer('one'));
        $this->store->save($two, $user, $this->layer('two'));
        $this->store->save($one, $user, $this->layer('one again'));

        $this->assertSame(2, ImageAnnotation::count());
        $this->assertSame('one again', $this->store->shapes($one, $user)['items'][1]['text']);
        $this->assertSame('two', $this->store->shapes($two, $user)['items'][1]['text']);
        $this->assertSame('img/one.png', $this->store->find($one, $user)?->key);
    }

    public function test_a_null_key_does_not_match_a_path_row_of_the_same_owner(): void
    {
        $user = $this->user();
        $note = Note::create();

        $this->store->save(new ModelPathImageSource($note, 'img/one.png'), $user, $this->layer());

        $this->assertNull($this->store->find(new ModelPathImageSource($note, 'img/other.png'), $user));
    }

    public function test_an_empty_layer_deletes_the_row_and_returns_an_unsaved_model(): void
    {
        $user = $this->user();
        $source = new MediaImageSource($this->media());
        $this->store->save($source, $user, $this->layer());

        $row = $this->store->save($source, $user, ['v' => 1, 'items' => []]);

        $this->assertFalse($row->exists);
        $this->assertSame(0, ImageAnnotation::count());
        $this->assertSame(['v' => 1, 'items' => []], $this->store->shapes($source, $user));
    }

    public function test_an_empty_layer_with_no_row_stores_nothing(): void
    {
        $row = $this->store->save(new MediaImageSource($this->media()), $this->user(), null);

        $this->assertFalse($row->exists);
        $this->assertSame(0, ImageAnnotation::count());
    }

    public function test_a_guest_cannot_save(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->store->save(new MediaImageSource($this->media()), null, $this->layer());
    }

    public function test_a_policy_decides_for_media_through_its_model(): void
    {
        $author = $this->user();
        $stranger = $this->user();
        $source = new MediaImageSource($this->media($author->id));

        $this->assertTrue($source->canAnnotate($author));
        $this->assertTrue($source->canView($author));
        $this->assertFalse($source->canAnnotate($stranger));
        $this->assertFalse($source->canView($stranger));

        $this->expectException(AuthorizationException::class);
        $this->store->save($source, $stranger, $this->layer());
    }

    public function test_without_a_policy_any_signed_in_user_may_but_a_guest_may_not(): void
    {
        $source = new ModelPathImageSource(Note::create(), 'a.png');

        $this->assertTrue($source->canView($this->user()));
        $this->assertTrue($source->canAnnotate($this->user()));
        $this->assertFalse($source->canView(null));
        $this->assertFalse($source->canAnnotate(null));
    }

    public function test_the_global_callback_overrides_the_policy(): void
    {
        $author = $this->user();
        $stranger = $this->user();
        $source = new MediaImageSource($this->media($author->id));
        $calls = [];

        ImageAnnotations::authorizeUsing(function ($user, ImageSource $s, string $ability) use (&$calls, $stranger, $source): bool {
            $calls[] = $ability;
            $this->assertSame($source, $s);

            return $user?->is($stranger) === true;
        });

        $this->assertTrue($source->canAnnotate($stranger));
        $this->assertTrue($source->canView($stranger));
        $this->assertFalse($source->canAnnotate($author));
        $this->assertFalse($source->canView(null));
        $this->assertSame(['annotate', 'view', 'annotate', 'view'], $calls);

        $this->store->save($source, $stranger, $this->layer());
        $this->assertSame(1, ImageAnnotation::count());
    }

    public function test_raw_json_is_stored_unescaped(): void
    {
        $source = new MediaImageSource($this->media());
        $this->store->save($source, $this->user(), $this->layer('Дивись сюди / тут'));

        $raw = (string) DB::table('image_annotations')->value('shapes');

        $this->assertStringContainsString('Дивись сюди / тут', $raw);
        $this->assertStringNotContainsString('\u', $raw);
    }

    public function test_an_escaped_row_still_reads(): void
    {
        $source = new MediaImageSource($media = $this->media());
        $this->store->save($source, $this->user(), $this->layer('x'));
        DB::table('image_annotations')->update(['shapes' => json_encode($this->layer('Привіт'))]);

        $this->assertSame('Привіт', $this->store->shapes($source, null)['items'][1]['text']);
        $this->assertNotNull($media);
    }

    public function test_events_are_fired(): void
    {
        Event::fake([AnnotationSaved::class, AnnotationDeleted::class]);
        $user = $this->user();
        $source = new MediaImageSource($this->media());

        $this->store->save($source, $user, $this->layer());
        Event::assertDispatched(AnnotationSaved::class, fn (AnnotationSaved $e): bool => $e->source === $source && $e->user?->is($user) === true && $e->annotation->exists);

        $this->store->save($source, $user, null);
        Event::assertDispatched(AnnotationDeleted::class, fn (AnnotationDeleted $e): bool => $e->source === $source);
    }

    public function test_forget_removes_the_layer(): void
    {
        $source = new MediaImageSource($this->media());
        $this->store->save($source, $this->user(), $this->layer());

        $this->store->forget($source);

        $this->assertSame(0, ImageAnnotation::count());
    }

    public function test_deleting_a_media_item_removes_its_rows(): void
    {
        $user = $this->user();
        $kept = $this->media(null, 'kept.png');
        $gone = $this->media(null, 'gone.png');
        $this->store->save(new MediaImageSource($kept), $user, $this->layer());
        $this->store->save(new MediaImageSource($gone), $user, $this->layer());

        $gone->delete();

        $this->assertSame(1, ImageAnnotation::count());
        $this->assertSame($kept->getKey(), ImageAnnotation::first()?->annotatable_id);
    }

    public function test_deleting_a_model_with_the_trait_removes_its_rows(): void
    {
        $user = $this->user();
        $note = Note::create();
        $other = Note::create();
        $this->store->save(new ModelPathImageSource($note, 'a.png'), $user, $this->layer());
        $this->store->save(new ModelPathImageSource($note, 'b.png'), $user, $this->layer());
        $this->store->save(new ModelPathImageSource($other, 'a.png'), $user, $this->layer());

        $note->forceDelete();

        $this->assertSame(1, ImageAnnotation::count());
    }

    public function test_a_soft_deleted_model_keeps_its_rows(): void
    {
        $note = Note::create();
        $this->store->save(new ModelPathImageSource($note, 'a.png'), $this->user(), $this->layer());

        $note->delete();

        $this->assertSame(1, ImageAnnotation::count());
    }

    public function test_the_registry_resolves_media_sources_and_custom_classes(): void
    {
        $registry = app(ImageSources::class);
        $media = $this->media();

        $this->assertInstanceOf(MediaImageSource::class, $registry->resolve($media));

        $source = new ModelPathImageSource(Note::create(), 'a.png');
        $this->assertSame($source, $registry->resolve($source));

        $registry->register(Note::class, fn (Note $note): ImageSource => new ModelPathImageSource($note, 'default.png'));
        $this->assertSame('default.png', $registry->resolve(Note::create())->key());
    }

    public function test_the_registry_rejects_unknown_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ImageSources::class)->resolve('a string');
    }

    public function test_for_returns_the_layer_only_to_those_who_may_view(): void
    {
        $author = $this->user();
        $stranger = $this->user();
        $media = $this->media($author->id);
        $this->store->save(new MediaImageSource($media), $author, $this->layer());

        $this->actingAs($author);
        $this->assertCount(2, ImageAnnotations::for($media)['items'] ?? []);

        $this->actingAs($stranger);
        $this->assertNull(ImageAnnotations::for($media));
    }

    public function test_for_is_null_when_nothing_is_drawn(): void
    {
        $this->actingAs($this->user());

        $this->assertNull(ImageAnnotations::for($this->media()));
    }

    public function test_source_urls_and_resolvers(): void
    {
        $media = $this->media();
        $this->assertStringContainsString('a.png', (new MediaImageSource($media))->url());

        MediaImageSource::urlUsing(fn (Media $m): string => 'https://cdn.test/'.$m->file_name);
        $this->assertSame('https://cdn.test/a.png', (new MediaImageSource($media))->url());
        MediaImageSource::urlUsing(null);

        $source = new ModelPathImageSource(Note::create(), 'img/x.png', 'public');
        $this->assertStringEndsWith('img/x.png', $source->url());

        ModelPathImageSource::urlUsing(fn ($record, string $path, ?string $disk): string => "https://cdn.test/{$disk}/{$path}");
        $this->assertSame('https://cdn.test/public/img/x.png', $source->url());
        ModelPathImageSource::urlUsing(null);
    }

    public function test_the_free_store_has_one_default_layer_editable_for_who_may_annotate(): void
    {
        $author = User::factory()->create();
        $post = Post::create(['user_id' => $author->id]);
        $media = $post->addMedia(\Illuminate\Http\UploadedFile::fake()->image('a.jpg', 40, 30))->toMediaCollection('photos');
        $source = new MediaImageSource($media);

        $empty = $this->store->layers($source, $author);

        $this->assertCount(1, $empty);
        $this->assertSame(Layer::DEFAULT_KEY, $empty[0]->key);
        $this->assertNull($empty[0]->label);
        $this->assertTrue($empty[0]->editable);
        $this->assertSame([], $empty[0]->items);

        $this->store->save($source, $author, $this->layer());

        $layers = $this->store->layers($source, $author);

        $this->assertCount(1, $layers);
        $this->assertSame($this->store->shapes($source, $author)['items'], $layers[0]->items);

        $this->assertFalse($this->store->layers($source, User::factory()->create())[0]->editable);
    }
}
