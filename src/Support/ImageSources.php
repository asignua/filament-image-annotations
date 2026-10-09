<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Support;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Closure;
use InvalidArgumentException;

/**
 * Turns "an image" as the host names it (a Media, a custom object) into an ImageSource.
 * Other packages register their own class here.
 */
class ImageSources
{
    /** @var array<class-string, Closure(mixed): ImageSource> */
    protected array $factories = [];

    /**
     * @param class-string                $class
     * @param Closure(mixed): ImageSource $factory
     */
    public function register(string $class, Closure $factory): void
    {
        $this->factories[$class] = $factory;
    }

    public function resolve(mixed $value): ImageSource
    {
        if ($value instanceof ImageSource) {
            return $value;
        }

        if (is_object($value)) {
            foreach ($this->factories as $class => $factory) {
                if ($value instanceof $class) {
                    return $factory($value);
                }
            }
        }

        throw new InvalidArgumentException('Cannot annotate '.(is_object($value) ? $value::class : get_debug_type($value)).': no image source is registered for it.');
    }
}
