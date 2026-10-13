@php
    use Asignua\FilamentImageAnnotations\ImageAnnotationsServiceProvider;
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;

    $statePath = $getStatePath();
    $imageUrl = $getImageUrl();
    $palette = config('filament-image-annotations.palette');
    $widths = array_values(config('filament-image-annotations.widths'));
    $limits = config('filament-image-annotations.limits');
    $t = fn (string $key, array $replace = []): string => __('filament-image-annotations::filament-image-annotations.'.$key, $replace);

    $panels = ($source = $getImageSource()) ? \Asignua\FilamentImageAnnotations\ImageAnnotations::panels($source, 'editor') : '';

    $underlays = array_map(static fn ($layer): array => $layer->toArray(), $getUnderlays());

    // Tool => [label key, shortcut, svg markup (24x24, stroke = currentColor)].
    $allTools = [
        'select' => ['V', '<path d="M5 3l14 7-6 2-2 6z"/>'],
        'arrow' => ['A', '<path d="M5 19L19 5M19 5h-8M19 5v8"/>'],
        'rect' => ['R', '<rect x="4" y="6" width="16" height="12" rx="1"/>'],
        'ellipse' => ['E', '<ellipse cx="12" cy="12" rx="9" ry="6"/>'],
        'line' => ['L', '<path d="M5 19L19 5"/>'],
        'pen' => ['P', '<path d="M4 20l1-4L16 5l3 3L8 19z"/>'],
        'highlight' => ['H', '<path d="M4 21h16M8 17l8-8 3 3-8 8H8z"/>'],
        'text' => ['T', '<path d="M5 6h14M12 6v13M9 19h6"/>'],
        'pin' => ['N', '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M10 10l2-2"/>'],
    ];
    $enabledTools = $getTools();
    $tools = array_intersect_key($allTools, array_flip($enabledTools));

    // Frames that read on a light and a dark panel alike.
    $frame = 'color-mix(in srgb, currentColor 18%, transparent)';

    $buttonStyle = 'display:inline-flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;border-radius:0.5rem;border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit;cursor:pointer;padding:0';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if ($imageUrl)
        {{-- The scope of the image-annotations:* events: the editor and, when a pro panel is registered, that panel. --}}
        <div data-ia-scope @if ($panels !== '') style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:1rem" @endif>
        <div
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc(ImageAnnotationsServiceProvider::ANNOTATOR, ImageAnnotationsServiceProvider::PACKAGE) }}"
            x-data="imageAnnotator({
                state: $wire.$entangle({{ Js::from($statePath) }}),
                palette: {{ Js::from($palette) }},
                widths: {{ Js::from($widths) }},
                limits: {{ Js::from($limits) }},
                labels: {{ Js::from(['limit' => $t('limit', ['count' => $limits['shapes']])]) }},
                tools: {{ Js::from($enabledTools) }},
                underlays: {{ Js::from($underlays) }},
                layer: {{ Js::from($getLayerKey()) }},
            })"
            x-ref="root"
            x-on:keydown="keys($event)"
            x-on:keydown.window="windowKeys($event)"
            tabindex="-1"
            wire:ignore
            wire:key="{{ $getLivewireKey() }}.annotator"
            style="display:flex;flex-direction:column;gap:0.75rem;outline:none;{{ $panels !== '' ? 'flex:1 1 24rem;min-width:0' : '' }}"
        >
            <div
                role="toolbar"
                aria-label="{{ $t('toolbar.label') }}"
                style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem 1.25rem;padding:0.5rem;border:1px solid {{ $frame }};border-radius:0.75rem"
            >
                <div role="group" aria-label="{{ $t('toolbar.tools') }}" style="display:flex;flex-wrap:wrap;gap:0.25rem">
                    @foreach ($tools as $name => [$shortcut, $icon])
                        <button
                            type="button"
                            title="{{ $t('tools.'.$name) }} ({{ $shortcut }})"
                            aria-label="{{ $t('tools.'.$name) }} ({{ $shortcut }})"
                            aria-keyshortcuts="{{ $shortcut }}"
                            x-bind:aria-pressed="(tool === '{{ $name }}').toString()"
                            @if ($name !== 'select')
                                x-bind:disabled="atLimit"
                            @endif
                            x-on:click="setTool('{{ $name }}')"
                            x-bind:style="tool === '{{ $name }}' ? { background: 'var(--primary-600, #2563eb)', color: '#fff', borderColor: 'transparent' } : {}"
                            style="{{ $buttonStyle }}"
                        >
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                        </button>
                    @endforeach
                </div>

                <div role="radiogroup" aria-label="{{ $t('toolbar.colors') }}" style="display:flex;flex-wrap:wrap;gap:0.375rem">
                    @foreach ($palette as $name => $hex)
                        @php($colorLabel = \Illuminate\Support\Facades\Lang::has('filament-image-annotations::filament-image-annotations.colors.'.$name) ? $t('colors.'.$name) : $name)
                        <button
                            type="button"
                            role="radio"
                            title="{{ $colorLabel }}"
                            aria-label="{{ $colorLabel }}"
                            x-bind:aria-checked="(color === '{{ $name }}').toString()"
                            x-on:click="setColor('{{ $name }}')"
                            x-bind:style="color === '{{ $name }}' ? { boxShadow: '0 0 0 2px var(--primary-600, #2563eb)' } : {}"
                            style="width:1.5rem;height:1.5rem;border-radius:9999px;border:1px solid rgba(128,128,128,.55);background:{{ $hex }};cursor:pointer;padding:0;margin:0.125rem"
                        ></button>
                    @endforeach
                </div>

                <div role="radiogroup" aria-label="{{ $t('toolbar.widths') }}" style="display:flex;gap:0.25rem">
                    @foreach ($widths as $index => $width)
                        @php($widthLabel = count($widths) === 3 ? $t('widths.'.['thin', 'medium', 'thick'][$index]) : $width.' px')
                        <button
                            type="button"
                            role="radio"
                            title="{{ $widthLabel }}"
                            aria-label="{{ $widthLabel }}"
                            x-bind:aria-checked="(width === {{ (int) $width }}).toString()"
                            x-on:click="setWidth({{ (int) $width }})"
                            x-bind:style="width === {{ (int) $width }} ? { background: 'var(--primary-600, #2563eb)', color: '#fff', borderColor: 'transparent' } : {}"
                            style="{{ $buttonStyle }}"
                        >
                            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 12h16" stroke="currentColor" stroke-width="{{ max(1, min(8, (int) $width)) }}" stroke-linecap="round"/></svg>
                        </button>
                    @endforeach
                </div>

                <div role="group" aria-label="{{ $t('toolbar.actions') }}" style="display:flex;flex-wrap:wrap;gap:0.25rem;align-items:center">
                    <button type="button" title="{{ $t('toolbar.undo') }} (Ctrl+Z)" aria-label="{{ $t('toolbar.undo') }}" x-bind:disabled="! canUndo" x-on:click="undo()" style="{{ $buttonStyle }}" x-bind:style="{ opacity: canUndo ? 1 : 0.4 }">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-3"/></svg>
                    </button>
                    <button type="button" title="{{ $t('toolbar.redo') }} (Ctrl+Shift+Z)" aria-label="{{ $t('toolbar.redo') }}" x-bind:disabled="! canRedo" x-on:click="redo()" style="{{ $buttonStyle }}" x-bind:style="{ opacity: canRedo ? 1 : 0.4 }">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 14l5-5-5-5M20 9H10a6 6 0 000 12h3"/></svg>
                    </button>
                    <button type="button" title="{{ $t('toolbar.delete') }} (Del)" aria-label="{{ $t('toolbar.delete') }}" x-bind:disabled="! selectedId" x-on:click="deleteSelected()" style="{{ $buttonStyle }}" x-bind:style="{ opacity: selectedId ? 1 : 0.4 }">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12M9 7V4h6v3M8 7l1 13h6l1-13"/></svg>
                    </button>
                    <button
                        type="button"
                        x-bind:disabled="count === 0"
                        x-on:click="clear()"
                        x-bind:style="Object.assign({ opacity: count === 0 ? 0.4 : 1 }, confirmClear ? { background: '#dc2626', color: '#fff', borderColor: 'transparent' } : {})"
                        style="display:inline-flex;align-items:center;height:2.25rem;padding:0 0.75rem;border-radius:0.5rem;border:1px solid rgba(128,128,128,.35);background:transparent;color:inherit;cursor:pointer;font-size:0.875rem"
                    >
                        <span x-text="confirmClear ? {{ Js::from($t('toolbar.clear_confirm')) }} : {{ Js::from($t('toolbar.clear')) }}"></span>
                    </button>
                </div>
            </div>

            @if ($underlays !== [])
                @include('filament-image-annotations::partials.layer-chips', ['list' => 'underlays', 'style' => ''])
            @endif

            <p x-show="hint" x-cloak x-text="hint" role="status" class="fi-fo-field-wrp-hint"></p>

            {{-- The stage: a tinted, framed area, so a light screenshot does not melt into a light modal. --}}
            <div style="display:flex;justify-content:center;overflow:auto;padding:1rem;border:1px solid {{ $frame }};border-radius:0.75rem;background:color-mix(in srgb, currentColor 7%, transparent)">
                <div
                    x-ref="surface"
                    x-on:pointerdown="start($event)"
                    x-on:pointermove="move($event)"
                    x-on:pointerup="end($event)"
                    x-on:pointercancel="end($event)"
                    x-on:dblclick="edit($event)"
                    x-bind:style="surfaceStyle"
                    style="position:relative;display:inline-block;max-width:100%;touch-action:none;user-select:none;line-height:0"
                >
                    <img
                        x-ref="image"
                        src="{{ $imageUrl }}"
                        alt=""
                        draggable="false"
                        x-on:load="imageLoaded()"
                        style="display:block;max-width:100%;max-height:70vh;border-radius:0.25rem;box-shadow:0 0 0 1px rgba(0,0,0,.18),0 6px 20px rgba(0,0,0,.18)"
                    />
                    <svg x-ref="under" aria-hidden="true" focusable="false" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none"></svg>
                    <svg x-ref="svg" aria-hidden="true" focusable="false" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none"></svg>

                    <template x-if="editor">
                        <textarea
                            x-ref="editor"
                            x-model="editor.text"
                            x-on:keydown="textKey($event)"
                            x-on:blur="commitText()"
                            x-bind:style="editorStyle"
                            rows="1"
                            maxlength="{{ (int) $limits['text'] }}"
                            placeholder="{{ $t('text_placeholder') }}"
                            aria-label="{{ $t('text_placeholder') }}"
                            style="position:absolute;min-width:9em;max-width:90%;margin:0;padding:0.125rem 0.25rem;border:1px dashed #3b82f6;border-radius:0.25rem;font-weight:600;line-height:1.2;resize:both;outline:none;field-sizing:content"
                        ></textarea>
                    </template>
                </div>
            </div>
        </div>
        @if ($panels !== '')
            {!! $panels !!}
        @endif
        </div>
    @else
        <p class="fi-fo-field-wrp-hint">{{ __('filament-image-annotations::filament-image-annotations.image_unavailable') }}</p>
    @endif
</x-dynamic-component>
