<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Concerns;

use Asignua\FilamentImageAnnotations\Models\ImageAnnotation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For host models whose image is a path in a column (ModelPathImageSource): removes the annotation rows
 * together with the record.
 *
 * @mixin Model
 */
trait HasImageAnnotations
{
    public static function bootHasImageAnnotations(): void
    {
        static::deleting(function (Model $model): void {
            // A soft-deleted record can come back; its annotations stay.
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->imageAnnotations()->delete();
        });
    }

    /**
     * @return MorphMany<ImageAnnotation, $this>
     */
    public function imageAnnotations(): MorphMany
    {
        return $this->morphMany(ImageAnnotation::class, 'annotatable');
    }
}
