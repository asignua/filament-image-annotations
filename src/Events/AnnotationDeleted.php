<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Events;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Fired after a row is removed (the layer was cleared, or the image went away). The model is already deleted.
 */
class AnnotationDeleted
{
    public function __construct(
        public readonly ImageAnnotation $annotation,
        public readonly ImageSource $source,
        public readonly ?Authenticatable $user,
    ) {}
}
