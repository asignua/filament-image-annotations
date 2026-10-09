<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations;

use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Asignua\FilamentImageAnnotations\Support\AnnotationStore;
use Asignua\FilamentImageAnnotations\Support\ImageSources;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImageAnnotationsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-image-annotations';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-image-annotations.php and are read as
        // `__('filament-image-annotations::filament-image-annotations.<key>')`. Publish tag: `filament-image-annotations-translations`.
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasMigration('create_image_annotations_table');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ImageSources::class, function (): ImageSources {
            $sources = new ImageSources;

            if (class_exists(Media::class)) {
                $sources->register(Media::class, fn (Media $media): MediaImageSource => new MediaImageSource($media));
            }

            return $sources;
        });

        // A singleton so the pro package can rebind it.
        $this->app->singleton(AnnotationStore::class);
    }

    public function packageBooted(): void
    {
        if (!class_exists(Media::class)) {
            return;
        }

        // No orphans: the layer goes together with its media item (also a custom media model).
        $models = array_unique([Media::class, (string) config('media-library.media_model', Media::class)]);

        foreach ($models as $model) {
            Event::listen('eloquent.deleted: '.$model, function (Media $media): void {
                $this->app->make(AnnotationStore::class)->forget(new MediaImageSource($media));
            });
        }
    }
}
