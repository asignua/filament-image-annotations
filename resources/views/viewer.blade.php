{{--
    Image + annotation overlay. Shared by the infolist entry and <x-image-annotations::image>.

    $url (string), $source (ImageSource|null, for the side panels), $layers (list<Layer>|null), $alt (string), $imageAttributes (ComponentAttributeBag), $wrapperStyle (string)

    The layers travel in `data-layers` as escaped JSON ([{key,label,color,items}]) and are parsed by the Alpine component;
    without JavaScript (or without a permission: $layers is empty) it is just the picture.
--}}
@php
    use Asignua\FilamentImageAnnotations\ImageAnnotationsServiceProvider;
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;
    use Illuminate\View\ComponentAttributeBag;

    $imageAttributes = $imageAttributes ?? new ComponentAttributeBag();
    $layers = array_values(array_filter($layers ?? [], static fn ($layer): bool => $layer->items !== []));
    $hasItems = $layers !== [];
    $panels = $hasItems && ($source ?? null) ? \Asignua\FilamentImageAnnotations\ImageAnnotations::panels($source, 'viewer') : '';
@endphp

@if ($hasItems)
    @if ($panels !== '')
        <div data-ia-scope style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:1rem">
    @endif
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc(ImageAnnotationsServiceProvider::VIEWER, ImageAnnotationsServiceProvider::PACKAGE) }}"
        x-data="imageAnnotationsViewer()"
        data-layers="{{ json_encode(array_map(static fn ($layer): array => $layer->toArray(), $layers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) }}"
        data-palette="{{ json_encode(config('filament-image-annotations.palette'), JSON_THROW_ON_ERROR) }}"
        style="position:relative;display:inline-block;max-width:100%;line-height:0;{{ $wrapperStyle ?? '' }}"
    >
        <img
            x-ref="image"
            src="{{ $url }}"
            alt="{{ $alt ?? '' }}"
            x-on:load="loaded()"
            {{ $imageAttributes->style(['display:block', 'max-width:100%', 'height:auto']) }}
        />
        <svg
            x-ref="svg"
            x-show="visible"
            aria-hidden="true"
            focusable="false"
            x-on:click="pinClick($event)"
            style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none"
        ></svg>
        <div x-show="visible && layers.length > 1" style="display:none;position:absolute;top:0.5rem;left:0.5rem;max-width:calc(100% - 9rem)">
            @include('filament-image-annotations::partials.layer-chips', ['list' => 'layers', 'style' => ''])
        </div>
        <button
            type="button"
            x-show="count > 0"
            x-on:click="toggle()"
            x-bind:aria-pressed="visible.toString()"
            title="{{ __('filament-image-annotations::filament-image-annotations.viewer.toggle') }}"
            style="display:none;position:absolute;top:0.5rem;right:0.5rem;white-space:nowrap;padding:0.25rem 0.625rem;border:0;border-radius:9999px;background:rgba(17,24,39,.75);color:#fff;font-size:0.75rem;line-height:1.25rem;cursor:pointer"
        >
            <span x-text="visible ? {{ Js::from(__('filament-image-annotations::filament-image-annotations.viewer.hide')) }} : {{ Js::from(__('filament-image-annotations::filament-image-annotations.viewer.show')) }}"></span>
            <span
                x-text="count"
                style="display:inline-block;margin-left:0.375rem;min-width:1.25rem;padding:0 0.25rem;border-radius:9999px;background:#fff;color:#111827;text-align:center;font-weight:600"
            ></span>
        </button>
    </div>
    @if ($panels !== '')
        {!! $panels !!}
        </div>
    @endif
@else
    <img src="{{ $url }}" alt="{{ $alt ?? '' }}" {{ $imageAttributes->style(['max-width:100%', 'height:auto']) }} />
@endif
