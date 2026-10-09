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
    $layer = ImageAnnotations::for($source);
@endphp

@include('filament-image-annotations::viewer', [
    'url' => $source->url(),
    'items' => $layer,
    'alt' => $alt,
    'imageAttributes' => $attributes,
])
