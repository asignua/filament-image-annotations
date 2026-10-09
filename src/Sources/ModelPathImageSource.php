<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Sources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An image that is a file path in a column of a model (FileUpload without a media library).
 */
class ModelPathImageSource extends AbstractImageSource
{
    /** @var (Closure(Model, string, ?string): string)|null */
    protected static ?Closure $urlResolver = null;

    public function __construct(
        public readonly Model $record,
        public readonly string $path,
        public readonly ?string $disk = null,
    ) {}

    /**
     * @param (Closure(Model, string, ?string): string)|null $resolver
     */
    public static function urlUsing(?Closure $resolver): void
    {
        static::$urlResolver = $resolver;
    }

    public function annotatable(): Model
    {
        return $this->record;
    }

    public function key(): ?string
    {
        return $this->path;
    }

    public function url(): string
    {
        if (static::$urlResolver !== null) {
            return (string) (static::$urlResolver)($this->record, $this->path, $this->disk);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    protected function owner(): ?Model
    {
        return $this->record;
    }
}
