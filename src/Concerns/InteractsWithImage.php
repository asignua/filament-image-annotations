<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Concerns;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Support\ImageSources;
use Closure;

/**
 * `->image(...)` for the Filament entry points (field, action, entry): what to annotate.
 */
trait InteractsWithImage
{
    protected mixed $image = null;

    /**
     * An ImageSource, a Spatie Media (or any class registered in ImageSources), or a Closure returning one
     * (it may take the usual `$record` argument). A Closure returning null means "no image here".
     *
     * @param Closure|ImageSource|object|null $image
     */
    public function image(mixed $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageSource(): ?ImageSource
    {
        $value = $this->getImageValue();

        return $value === null ? null : app(ImageSources::class)->resolve($value);
    }

    protected function getImageValue(): mixed
    {
        return $this->evaluate($this->image);
    }
}
