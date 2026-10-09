@php
    $rows = $getRows();
    $annotateAction = $getAction(\Asignua\FilamentImageAnnotations\Forms\AnnotationsPanel::ACTION);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
@if (count($rows) > 0)
    <ul
        class="fi-image-annotations-panel"
        role="list"
        style="display:flex;flex-direction:column;gap:0.5rem;margin:0;padding:0;list-style:none"
    >
        @foreach ($rows as $row)
            <li
                wire:key="{{ $getLivewireKey() }}.row.{{ $row['key'] }}"
                class="fi-section"
                style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.75rem"
            >
                @if ($row['url'])
                    <img
                        src="{{ $row['url'] }}"
                        alt=""
                        loading="lazy"
                        style="width:3rem;height:3rem;flex:none;object-fit:cover;border-radius:0.375rem"
                    />
                @endif

                <span style="min-width:0;flex:1 1 auto">
                    <span
                        style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500"
                    >
                        {{ $row['name'] }}
                    </span>

                    @if (! $row['stored'])
                        <span class="fi-fo-field-wrp-hint" style="display:block">
                            {{ __('filament-image-annotations::filament-image-annotations.upload.save_first') }}
                        </span>
                    @endif
                </span>

                @if ($row['count'] > 0)
                    <x-filament::badge color="gray" :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare">
                        {{ __('filament-image-annotations::filament-image-annotations.upload.shapes', ['count' => $row['count']]) }}
                    </x-filament::badge>
                @endif

                @if (! $row['stored'])
                    <x-filament::button
                        size="sm"
                        color="gray"
                        :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare"
                        disabled
                    >
                        {{ __('filament-image-annotations::filament-image-annotations.action.label') }}
                    </x-filament::button>
                @elseif ($row['can'])
                    {{ $annotateAction(['key' => $row['key']]) }}
                @endif
            </li>
        @endforeach
    </ul>
@endif
</x-dynamic-component>
