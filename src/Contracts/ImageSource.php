<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * An image that can carry annotations: where its row hangs, how to show it and who may see/draw on it.
 */
interface ImageSource
{
    /**
     * The row owner for the morph.
     */
    public function annotatable(): Model;

    /**
     * Null for an image that is a model of its own (Media); the file path for a path in a model column.
     */
    public function key(): ?string;

    /**
     * Displayable URL of the original image.
     */
    public function url(): string;

    public function canView(?Authenticatable $user): bool;

    public function canAnnotate(?Authenticatable $user): bool;
}
