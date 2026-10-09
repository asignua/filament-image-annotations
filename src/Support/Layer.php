<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Support;

/**
 * One drawing layer of an image: a named, optionally coloured list of sanitised items. The free package has a
 * single `default` layer; the pro package returns one per author. `meta` is free-form (JSON-safe) data for the host.
 */
final readonly class Layer
{
    public const string DEFAULT_KEY = 'default';

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed>       $meta
     */
    public function __construct(
        public string $key,
        public ?string $label,
        public ?string $color,
        public array $items,
        public bool $editable = false,
        public array $meta = [],
    ) {}

    /**
     * The shape handed to the browser: key, label, accent colour and items (plus `meta` when there is some).
     *
     * @return array{key: string, label: string|null, color: string|null, items: list<array<string, mixed>>, meta?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $data = ['key' => $this->key, 'label' => $this->label, 'color' => $this->color, 'items' => $this->items];

        if ($this->meta !== []) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}
