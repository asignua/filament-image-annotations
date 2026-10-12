<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Asignua\FilamentImageAnnotations\Concerns\HasImageAnnotations;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Guarded by PostPolicy: only its author may view and update it. Holds media and a path column.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $title
 * @property string|null $photo
 */
class Post extends Model implements HasMedia
{
    use HasImageAnnotations;
    use InteractsWithMedia;

    protected $guarded = [];
}
