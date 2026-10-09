{{--
    One toggle chip per layer (label, item count, accent dot). Shared by the editor (underlays) and the viewer.

    $list (string)  Alpine expression of the layers array: [{key, label, color, items}]
    $style (string) extra CSS for the container

    The component must spread layerToggles() from render.js (isShown / toggleLayer).
--}}
@php
    use Illuminate\Support\Js;

    $defaultLabel = __('filament-image-annotations::filament-image-annotations.layers.default');
@endphp

<div
    role="group"
    aria-label="{{ __('filament-image-annotations::filament-image-annotations.layers.label') }}"
    data-layer-chips
    style="display:flex;flex-wrap:wrap;gap:0.375rem;line-height:1.25rem;{{ $style ?? '' }}"
>
    <template x-for="layer in {{ $list }}" x-bind:key="layer.key">
        <button
            type="button"
            x-on:click="toggleLayer(layer.key)"
            x-bind:aria-pressed="isShown(layer.key).toString()"
            x-bind:style="{ opacity: isShown(layer.key) ? 1 : 0.5 }"
            style="display:inline-flex;align-items:center;gap:0.375rem;padding:0.125rem 0.625rem;border:1px solid rgba(128,128,128,.4);border-radius:9999px;background:rgba(17,24,39,.75);color:#fff;font-size:0.75rem;cursor:pointer"
        >
            <span
                aria-hidden="true"
                x-bind:style="{ background: layer.color || '#9ca3af' }"
                style="display:inline-block;width:0.625rem;height:0.625rem;border-radius:9999px;border:1px solid rgba(255,255,255,.7)"
            ></span>
            <span x-text="layer.label || {{ Js::from($defaultLabel) }}"></span>
            <span x-text="layer.items.length" style="min-width:1.25rem;padding:0 0.25rem;border-radius:9999px;background:#fff;color:#111827;text-align:center;font-weight:600"></span>
        </button>
    </template>
</div>
