<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Forms\Components;

use Asignua\FilamentImageAnnotations\Concerns\InteractsWithImage;
use Asignua\FilamentImageAnnotations\Support\Layer;
use Asignua\FilamentImageAnnotations\Support\ShapeSanitizer;
use Closure;
use Filament\Forms\Components\Field;

/**
 * The editor: the image with a toolbar to draw on it. The state is the annotation layer
 * `{v: 1, items: [...]}`; whatever arrives from the browser is run through the ShapeSanitizer on save.
 */
class ImageAnnotator extends Field
{
    use InteractsWithImage;

    /** Every tool the toolbar knows, in toolbar order. `pin` is opt-in. */
    public const array TOOLS = ['select', 'arrow', 'rect', 'ellipse', 'line', 'pen', 'highlight', 'text', 'pin'];

    protected string $view = 'filament-image-annotations::forms.annotator';

    /** @var array<int, string>|Closure|null */
    protected array|Closure|null $tools = null;

    /** @var array<int, Layer>|Closure */
    protected array|Closure $underlays = [];

    protected string|Closure $layerKey = Layer::DEFAULT_KEY;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(static fn (mixed $state): array => ShapeSanitizer::sanitize($state));
    }

    public function getImageUrl(): ?string
    {
        $url = $this->getImageSource()?->url();

        return filled($url) ? $url : null;
    }

    /**
     * Which tools the toolbar offers (default: config `tools`). Unknown names are ignored.
     *
     * @param array<int, string>|Closure|null $tools
     */
    public function tools(array|Closure|null $tools): static
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTools(): array
    {
        $wanted = $this->evaluate($this->tools) ?? config('filament-image-annotations.tools', self::TOOLS);

        return array_values(array_filter(self::TOOLS, static fn (string $tool): bool => in_array($tool, (array) $wanted, true)));
    }

    /**
     * Read-only layers drawn below the editable one. They are not selectable and never become part of the state.
     *
     * @param array<int, Layer>|Closure $underlays
     */
    public function underlays(array|Closure $underlays): static
    {
        $this->underlays = $underlays;

        return $this;
    }

    /**
     * @return list<Layer>
     */
    public function getUnderlays(): array
    {
        return array_values(array_filter((array) $this->evaluate($this->underlays), static fn (mixed $layer): bool => $layer instanceof Layer));
    }

    /**
     * The key of the layer being edited; it travels in the `layer` detail of the JS events.
     */
    public function layerKey(string|Closure $key): static
    {
        $this->layerKey = $key;

        return $this;
    }

    public function getLayerKey(): string
    {
        return (string) $this->evaluate($this->layerKey);
    }
}
