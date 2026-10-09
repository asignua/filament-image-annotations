<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Forms\Components;

use Asignua\FilamentImageAnnotations\Concerns\InteractsWithImage;
use Asignua\FilamentImageAnnotations\Support\ShapeSanitizer;
use Filament\Forms\Components\Field;

/**
 * The editor: the image with a toolbar to draw on it. The state is the annotation layer
 * `{v: 1, items: [...]}`; whatever arrives from the browser is run through the ShapeSanitizer on save.
 */
class ImageAnnotator extends Field
{
    use InteractsWithImage;

    protected string $view = 'filament-image-annotations::forms.annotator';

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(static fn (mixed $state): array => ShapeSanitizer::sanitize($state));
    }

    public function getImageUrl(): ?string
    {
        $url = $this->getImageSource()?->url();

        return filled($url) ? $url : null;
    }
}
