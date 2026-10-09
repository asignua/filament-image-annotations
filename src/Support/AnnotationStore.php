<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Support;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Events\AnnotationDeleted;
use Asignua\FilamentImageAnnotations\Events\AnnotationSaved;
use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reads and writes the annotation layer of an image. Bound as a singleton: the pro package rebinds it
 * with per-author layers. v1 has ONE layer per image, edited by any authorised user; user_id is the last editor.
 */
class AnnotationStore
{
    /**
     * The layer shown to $user. The only place that decides which row that is — override it for per-user layers.
     */
    public function find(ImageSource $source, ?Authenticatable $user): ?ImageAnnotation
    {
        return $this->query($source)->first();
    }

    /**
     * @return array{v: int, items: list<array<string, mixed>>}
     */
    public function shapes(ImageSource $source, ?Authenticatable $user): array
    {
        return ShapeSanitizer::sanitize($this->find($source, $user)?->shapes);
    }

    /**
     * Every layer $user is shown: at most one in the free package (`default`, editable when they may annotate).
     * The pro package returns one per author — the user's own editable, the others read-only. Callers use the
     * editable one for editing and the rest as underlays.
     *
     * @return list<Layer>
     */
    public function layers(ImageSource $source, ?Authenticatable $user): array
    {
        return [new Layer(Layer::DEFAULT_KEY, null, null, $this->shapes($source, $user)['items'], $source->canAnnotate($user))];
    }

    /**
     * Stores the layer. An empty layer deletes the row and returns a fresh UNSAVED model (exists === false)
     * carrying the empty shapes, so callers can treat the result uniformly.
     *
     * @throws AuthorizationException
     */
    public function save(ImageSource $source, ?Authenticatable $user, mixed $shapes): ImageAnnotation
    {
        if (!$source->canAnnotate($user)) {
            throw new AuthorizationException(__('filament-image-annotations::filament-image-annotations.errors.unauthorized'));
        }

        $clean = ShapeSanitizer::sanitize($shapes);

        $annotation = $this->find($source, $user);

        if ($clean['items'] === []) {
            if ($annotation !== null) {
                $annotation->delete();
                event(new AnnotationDeleted($annotation, $source, $user));
            }

            return $this->blank($source, $clean);
        }

        $annotation ??= $this->blank($source, $clean);
        $annotation->shapes = $clean;
        $annotation->user_id = $user?->getAuthIdentifier();
        $annotation->save();

        event(new AnnotationSaved($annotation, $source, $user));

        return $annotation;
    }

    /**
     * Removes the layer without an authorisation check (the image itself went away).
     */
    public function forget(ImageSource $source): void
    {
        foreach ($this->query($source)->get() as $annotation) {
            $annotation->delete();
            event(new AnnotationDeleted($annotation, $source, null));
        }
    }

    /**
     * @return Builder<ImageAnnotation>
     */
    protected function query(ImageSource $source): Builder
    {
        $owner = $source->annotatable();
        $key = $source->key();

        return ImageAnnotation::query()
            ->where('annotatable_type', $owner->getMorphClass())
            ->where('annotatable_id', $owner->getKey())
            ->when(
                $key === null,
                fn (Builder $query) => $query->whereNull('key'),
                fn (Builder $query) => $query->where('key', $key),
            )
            ->orderBy('id');
    }

    /**
     * @param array{v: int, items: list<array<string, mixed>>} $shapes
     */
    protected function blank(ImageSource $source, array $shapes): ImageAnnotation
    {
        $owner = $source->annotatable();

        $annotation = new ImageAnnotation;
        $annotation->annotatable_type = $owner->getMorphClass();
        $annotation->annotatable_id = $owner->getKey();
        $annotation->key = $source->key();
        $annotation->shapes = $shapes;

        return $annotation;
    }
}
