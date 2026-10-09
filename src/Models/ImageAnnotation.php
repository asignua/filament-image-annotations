<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Models;

use Asignua\FilamentImageAnnotations\Casts\UnescapedJson;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property int $id
 * @property string $ulid
 * @property string $annotatable_type
 * @property int $annotatable_id
 * @property string|null $key
 * @property int|null $user_id
 * @property array{v: int, items: list<array<string, mixed>>}|null $shapes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImageAnnotation extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('filament-image-annotations.table', 'image_annotations');
    }

    protected static function booted(): void
    {
        static::creating(function (self $annotation): void {
            if (!$annotation->ulid) {
                $annotation->ulid = (string) Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'shapes' => UnescapedJson::class,
            'user_id' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function annotatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Authenticatable&Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Authenticatable&Model> $model */
        $model = config('filament-image-annotations.user_model')
            ?? config('auth.providers.'.config('auth.guards.'.config('auth.defaults.guard').'.provider').'.model')
            ?? throw new RuntimeException('No user model: set filament-image-annotations.user_model.');

        return $this->belongsTo($model, 'user_id');
    }
}
