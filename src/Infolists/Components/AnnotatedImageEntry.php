<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Infolists\Components;

use Asignua\FilamentImageAnnotations\Concerns\InteractsWithImage;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Support\Layer;
use Closure;
use Filament\Infolists\Components\Entry;

/**
 * Read-only view of an image with its annotation layer and a show/hide toggle. Without a layer (or without
 * permission to see it) it is the plain picture. Without `->image()` the entry's own state is the image.
 */
class AnnotatedImageEntry extends Entry
{
    use InteractsWithImage;

    protected string $view = 'filament-image-annotations::infolists.annotated-image-entry';

    protected string|Closure|null $alt = null;

    public function alt(string|Closure|null $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getAlt(): string
    {
        return (string) $this->evaluate($this->alt);
    }

    public function getImageUrl(): ?string
    {
        $url = $this->getImageSource()?->url();

        return filled($url) ? $url : null;
    }

    /**
     * @return list<Layer>
     */
    public function getLayers(): array
    {
        $source = $this->getImageSource();

        return $source === null ? [] : ImageAnnotations::layersFor($source);
    }

    protected function getImageValue(): mixed
    {
        return $this->image === null ? $this->getState() : $this->evaluate($this->image);
    }
}
