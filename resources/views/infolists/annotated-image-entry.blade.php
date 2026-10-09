<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php($url = $getImageUrl())

    @if ($url)
        @include('filament-image-annotations::viewer', [
            'url' => $url,
            'items' => $getAnnotations(),
            'alt' => $getAlt(),
            'wrapperStyle' => 'border-radius:0.5rem;overflow:hidden',
        ])
    @else
        <p class="fi-in-placeholder">{{ $getPlaceholder() }}</p>
    @endif
</x-dynamic-component>
