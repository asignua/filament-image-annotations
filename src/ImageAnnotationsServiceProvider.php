<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ImageAnnotationsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-image-annotations';

    public function configurePackage(Package $package): void
    {
        // Translations live in resources/lang/<locale>/filament-image-annotations.php and are read as
        // `__('filament-image-annotations::filament-image-annotations.<key>')`. Publish tag: `filament-image-annotations-translations`.
        $package->name(static::$name)
            ->hasTranslations()
            ->hasViews();

        // Add a config file only when the plugin really has options: create config/filament-image-annotations.php and
        // chain `->hasConfigFile()` here (publish tag `filament-image-annotations-config`). Prefer fluent setters on the Plugin.
    }
}
