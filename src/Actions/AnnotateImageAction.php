<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Actions;

use Asignua\FilamentImageAnnotations\Concerns\InteractsWithImage;
use Asignua\FilamentImageAnnotations\Forms\Components\ImageAnnotator;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
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

        $this->schema(fn (): array => [
            ImageAnnotator::make('annotations')
                ->hiddenLabel()
                ->image(fn () => $this->getImageSource()),
        ]);

        $this->fillForm(function (): array {
            $source = $this->getImageSource();

            return ['annotations' => $source === null ? ['v' => 1, 'items' => []] : ImageAnnotations::store()->shapes($source, Filament::auth()->user())];
        });

        // Hidden for a record without an image and for a user who may not annotate it.
        $this->hidden(function (): bool {
            // An ActionGroup asks its children once WITHOUT a row to decide whether to draw itself; the per-row
            // check below hides the button where it matters.
            if ($this->hasTable() && $this->getRecord() === null) {
                return false;
            }

            $source = $this->getImageSource();

            return $source === null || !$source->canAnnotate(Filament::auth()->user());
        });

        $this->action(function (array $data): void {
            $source = $this->getImageSource();

            if ($source === null) {
                $this->failure();

                return;
            }

            try {
                ImageAnnotations::store()->save($source, Filament::auth()->user(), $data['annotations'] ?? null);
            } catch (AuthorizationException $exception) {
                Notification::make()->danger()->title($exception->getMessage())->send();

                return;
            }

            $this->success();
        });
    }
}
