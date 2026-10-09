<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Events;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Illuminate\Contracts\Auth\Authenticatable;

class AnnotationSaved
{
    public function __construct(
        public readonly ImageAnnotation $annotation,
        public readonly ImageSource $source,
        public readonly ?Authenticatable $user,
    ) {}
}
