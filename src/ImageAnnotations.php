<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Support\AnnotationStore;
use Asignua\FilamentImageAnnotations\Support\ImageSources;
use Asignua\FilamentImageAnnotations\Support\Layer;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Static entry point (not a Laravel facade).
 */
class ImageAnnotations
{
    /** @var (Closure(?Authenticatable, ImageSource, string): bool)|null */
    protected static ?Closure $authorizer = null;

    /**
     * Replaces the default rule (the owner's policy) for every source. The ability is 'view' or 'annotate'.
     *
     * @param (Closure(?Authenticatable, ImageSource, string): bool)|null $callback
     */
    public static function authorizeUsing(?Closure $callback): void
    {
        static::$authorizer = $callback;
    }

    /**
     * @return (Closure(?Authenticatable, ImageSource, string): bool)|null
     */
    public static function authorizer(): ?Closure
    {
        return static::$authorizer;
    }

    /**
     * The layer to display for the signed-in user; null when they may not see the image or there is nothing drawn.
     *
     * @return array{v: int, items: list<array<string, mixed>>}|null
     */
    public static function for(mixed $image): ?array
    {
        $source = app(ImageSources::class)->resolve($image);
        $user = Auth::user();

        if (!$source->canView($user)) {
            return null;
        }

        $shapes = static::store()->shapes($source, $user);

        return $shapes['items'] === [] ? null : $shapes;
    }

    /**
     * The non-empty layers to display for the signed-in user; empty when they may not see the image.
     *
     * @return list<Layer>
     */
    public static function layersFor(mixed $image): array
    {
        $source = app(ImageSources::class)->resolve($image);
        $user = Auth::user();

        if (!$source->canView($user)) {
            return [];
        }

        return array_values(array_filter(static::store()->layers($source, $user), static fn (Layer $layer): bool => $layer->items !== []));
    }

    public static function store(): AnnotationStore
    {
        return app(AnnotationStore::class);
    }
}
