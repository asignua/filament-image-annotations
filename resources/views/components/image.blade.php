@props([
    'image',
    'alt' => '',
])

@php
    use Asignua\FilamentImageAnnotations\ImageAnnotations;
    use Asignua\FilamentImageAnnotations\Support\ImageSources;

    // `image`: a Media, an ImageSource, or anything an ImageSources factory knows. ImageAnnotations::for()
    // is already permission-aware: a viewer who may not see the layer gets the plain picture.
    $source = app(ImageSources::class)->resolve($image);
    $layers = ImageAnnotations::layersFor($source);
@endphp

@include('filament-image-annotations::viewer', [
    'source' => $source,
    'url' => $source->url(),
    'layers' => $layers,
    'alt' => $alt,
    'imageAttributes' => $attributes,
])
