<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Sources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A Spatie media item. Needs spatie/laravel-medialibrary; the registry only registers it when it is installed.
 */
class MediaImageSource extends AbstractImageSource
{
    /** @var (Closure(Media): string)|null */
    protected static ?Closure $urlResolver = null;

    public function __construct(public readonly Media $media) {}

    /**
     * @param (Closure(Media): string)|null $resolver
     */
    public static function urlUsing(?Closure $resolver): void
    {
        static::$urlResolver = $resolver;
    }

    public function annotatable(): Model
    {
        return $this->media;
    }

    public function key(): ?string
    {
        return null;
    }

    public function url(): string
    {
        return static::$urlResolver !== null ? (string) (static::$urlResolver)($this->media) : $this->media->getUrl();
    }

    protected function owner(): ?Model
    {
        return $this->media->model;
    }
}
