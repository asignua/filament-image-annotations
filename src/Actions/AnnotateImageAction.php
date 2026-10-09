<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Actions;

use Asignua\FilamentImageAnnotations\Concerns\InteractsWithImage;
use Asignua\FilamentImageAnnotations\Forms\Components\ImageAnnotator;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Support\Layer;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Opens the editor in a modal. Works as a table record action, a header action or a schema action:
 * `->image()` takes the image (or a Closure that gets the usual `$record`).
 *
 *     AnnotateImageAction::make()->image(fn (Post $record) => $record->getFirstMedia('photos'))
 */
class AnnotateImageAction extends Action
{
    use InteractsWithImage;

    public static function getDefaultName(): ?string
    {
        return 'annotateImage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-image-annotations::filament-image-annotations.action.label'));
        $this->icon(Heroicon::OutlinedPencilSquare);
        $this->modalHeading(fn (): string => __('filament-image-annotations::filament-image-annotations.action.heading'));
        $this->modalSubmitActionLabel(fn (): string => __('filament-image-annotations::filament-image-annotations.action.save'));
        $this->modalWidth(Width::Screen);
        $this->slideOver(false);
        $this->successNotificationTitle(fn (): string => __('filament-image-annotations::filament-image-annotations.action.saved'));

        // Every closure takes the action as `$action` and NEVER uses `$this`: Filament clones the action when it binds
        // arguments (a per-file button of AnnotatedUpload), and `$this` inside a closure made here stays the ORIGINAL,
        // argument-less instance — the button then found no image and rendered hidden/disabled.
        $this->schema(static fn (self $action): array => [
            ImageAnnotator::make('annotations')
                ->hiddenLabel()
                ->image(static fn () => $action->getImageSource())
                ->layerKey(static fn (): string => $action->editableLayer()->key ?? Layer::DEFAULT_KEY)
                ->underlays(static fn (): array => $action->underlayLayers()),
        ]);

        $this->fillForm(static function (self $action): array {
            $source = $action->getImageSource();

            return ['annotations' => $source === null ? ['v' => 1, 'items' => []] : ImageAnnotations::store()->shapes($source, Filament::auth()->user())];
        });

        // Hidden for a record without an image and for a user who may not annotate it.
        $this->hidden(static function (self $action): bool {
            // An ActionGroup asks its children once WITHOUT a row to decide whether to draw itself; the per-row
            // check below hides the button where it matters.
            if ($action->hasTable() && $action->getRecord() === null) {
                return false;
            }

            $source = $action->getImageSource();

            return $source === null || !$source->canAnnotate(Filament::auth()->user());
        });

        $this->action(static function (self $action, array $data): void {
            $source = $action->getImageSource();

            if ($source === null) {
                $action->failure();

                return;
            }

            try {
                ImageAnnotations::store()->save($source, Filament::auth()->user(), $data['annotations'] ?? null);
            } catch (AuthorizationException $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();

                return;
            }

            $action->success();
        });
    }

    /**
     * @return list<Layer>
     */
    protected function layers(): array
    {
        $source = $this->getImageSource();

        return $source === null ? [] : ImageAnnotations::store()->layers($source, Filament::auth()->user());
    }

    public function editableLayer(): ?Layer
    {
        foreach ($this->layers() as $layer) {
            if ($layer->editable) {
                return $layer;
            }
        }

        return null;
    }

    /**
     * The layers shown below the one being edited (none in the free package).
     *
     * @return list<Layer>
     */
    public function underlayLayers(): array
    {
        return array_values(array_filter($this->layers(), static fn (Layer $layer): bool => !$layer->editable && $layer->items !== []));
    }
}
