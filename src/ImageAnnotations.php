<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Support\AnnotationStore;
use Asignua\FilamentImageAnnotations\Support\ImageSources;
use Asignua\FilamentImageAnnotations\Support\Layer;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * Static entry point (not a Laravel facade).
 */
class ImageAnnotations
{
    /** @var (Closure(?Authenticatable, ImageSource, string): bool)|null */
    protected static ?Closure $authorizer = null;

    /** @var list<Closure(ImageSource, string): (Htmlable|string|null)> */
    protected static array $panels = [];

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

    /**
     * Adds a side panel next to the editor canvas and the viewer image (the pro package puts its pin threads there).
     * The callback gets the image and the context (`editor` or `viewer`) and returns HTML — a string or Htmlable —
     * or null for no panel. The root element of the HTML sits inside the `data-ia-scope` container that also holds the
     * canvas, so it can listen to the `image-annotations:*` events bubbling from it.
     *
     * @param Closure(ImageSource, string): (Htmlable|string|null) $renderer
     */
    public static function addPanel(Closure $renderer): void
    {
        static::$panels[] = $renderer;
    }

    public static function flushPanels(): void
    {
        static::$panels = [];
    }

    /**
     * The HTML of every registered panel for this image, concatenated; an empty string when there is none.
     */
    public static function panels(ImageSource $source, string $context): string
    {
        $html = '';

        foreach (static::$panels as $renderer) {
            $part = $renderer($source, $context);

            $html .= $part instanceof Htmlable ? $part->toHtml() : (string) $part;
        }

        return $html;
    }

    public static function store(): AnnotationStore
    {
        return app(AnnotationStore::class);
    }
}
