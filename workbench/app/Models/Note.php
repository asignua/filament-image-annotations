<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Asignua\FilamentImageAnnotations\Concerns\HasImageAnnotations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * No policy: the default rule lets every signed-in user in. Soft-deletable.
 *
 * @property int $id
 * @property string|null $photo
 * @property string|null $image
 */
class Note extends Model implements HasMedia
{
    use HasImageAnnotations;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $guarded = [];
}
