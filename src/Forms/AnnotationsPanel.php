<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Forms;

use Asignua\FilamentImageAnnotations\Actions\AnnotateImageAction;
use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Asignua\FilamentImageAnnotations\Sources\ModelPathImageSource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The list next to an upload: one row per image with a thumbnail and the "Annotate" button. Built by
 * {@see \Asignua\FilamentImageAnnotations\AnnotatedUpload}; not meant to be used alone.
 *
 * It has no value of its own (never dehydrated). Its state only carries a signed snapshot of the stored paths of a
 * plain upload as the form was filled, so that a save can tell which files were removed and forget their layers.
 */
class AnnotationsPanel extends Field
{
    public const string ACTION = 'annotateFile';

    protected string $view = 'filament-image-annotations::forms.annotations-panel';

    /** @var array{0: string, 1: array<int|string, mixed>}|null */
    protected ?array $uploadedFilesCache = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();
        $this->dehydrated(false);

        $this->hidden(static fn (self $component): bool => $component->getUpload()->isHidden());

        $this->registerActions([
            fn (self $component): Action => $component->getAnnotateAction(),
        ]);

        $this->afterStateHydrated(static function (self $component): void {
            $component->rawState($component->snapshotState());
        });

        // Once the record is saved (and the upload stored its files): layers of removed files go away.
        $this->saveRelationshipsUsing(static function (self $component): void {
            $component->forgetRemoved();
        });
    }

    /**
     * The upload this panel belongs to: its sibling in the same container (resolved each time, Filament clones
     * components when it attaches them).
     */
    public function getUpload(): BaseFileUpload
    {
        foreach ($this->getContainer()->getComponents(withHidden: true) as $component) {
            if ($component instanceof BaseFileUpload) {
                return $component;
            }
        }

        throw new LogicException('The annotations panel must sit next to a file upload (use AnnotatedUpload::make()).');
    }

    protected function storesInMedia(): bool
    {
        return $this->getUpload() instanceof SpatieMediaLibraryFileUpload;
    }

    // ---------------------------------------------------------------- sources

    /**
     * The stored image behind an item key of the upload, or null when there is none to annotate (a temporary
     * upload, no record yet, a key the upload does not have, or a file that is not an image of this record).
     * The key comes from the browser, so it is checked against the upload's current files.
     */
    public function sourceFor(mixed $key): ?ImageSource
    {
        $files = $this->getUpload()->getRawState() ?? [];

        if (!is_string($key) && !is_int($key)) {
            return null;
        }

        $file = $files[$key] ?? null;
        $record = $this->getRecord();

        if (!is_string($file) || $file === '' || !$record instanceof Model || !$record->exists) {
            return null;
        }

        if ($this->storesInMedia()) {
            if (!$record instanceof HasMedia) {
                return null;
            }

            $media = $record->media()->where('uuid', $file)->first();

            return $media instanceof Media ? new MediaImageSource($media) : null;
        }

        // A path that is only in the state (not yet in the column) is not stored.
        if (!$this->isPersistedPath($record, $file)) {
            return null;
        }

        return new ModelPathImageSource($record, $file, $this->getUpload()->getDiskName());
    }

    /**
     * Whether the record's own column holds the path. A column that is not an attribute of the record (the
     * upload sits in a Repeater item, the field is mapped elsewhere) cannot be checked and is trusted.
     */
    protected function isPersistedPath(Model $record, string $path): bool
    {
        if (!array_key_exists($this->getUpload()->getName(), $record->getAttributes())) {
            return true;
        }

        return in_array($path, $this->persistedPaths($record), true);
    }

    /**
     * @return array<int, string>
     */
    protected function persistedPaths(Model $record): array
    {
        $value = $record->getAttribute($this->getUpload()->getName());

        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        return array_values(array_filter(is_array($value) ? $value : [$value], static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    // ---------------------------------------------------------------- removed files

    /**
     * @return array{json: string, sig: string}
     */
    public function snapshotState(): array
    {
        if ($this->storesInMedia()) {
            return ['json' => '[]', 'sig' => $this->sign('[]')];
        }

        $paths = array_values(array_filter($this->getUpload()->getRawState() ?? [], static fn (mixed $file): bool => is_string($file) && $file !== ''));
        $json = (string) json_encode($paths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['json' => $json, 'sig' => $this->sign($json)];
    }

    /**
     * Drops the layer of every file that was in the upload when the form was filled and is not any more.
     * (A file removed and uploaded again under the SAME path keeps the old layer: the save cannot tell them apart.)
     * A media item needs nothing here: its layer goes with the media item.
     */
    public function forgetRemoved(): void
    {
        $record = $this->getRecord();

        if ($this->storesInMedia() || !$record instanceof Model || !$record->exists) {
            return;
        }

        $state = $this->getRawState();
        $json = is_array($state) ? ($state['json'] ?? null) : null;
        $sig = is_array($state) ? ($state['sig'] ?? null) : null;

        // The state travels through the browser: only the snapshot signed on hydrate is trusted.
        if (!is_string($json) || !is_string($sig) || !hash_equals($this->sign($json), $sig)) {
            return;
        }

        $before = json_decode($json, true);
        $current = array_filter($this->getUpload()->getRawState() ?? [], is_string(...));

        foreach (is_array($before) ? $before : [] as $path) {
            if (is_string($path) && !in_array($path, $current, true)) {
                ImageAnnotations::store()->forget(new ModelPathImageSource($record, $path, $this->getUpload()->getDiskName()));
            }
        }

        $this->rawState($this->snapshotState());
    }

    protected function sign(string $json): string
    {
        return hash_hmac('sha256', $this->getName().'|'.$json, (string) config('app.key'));
    }

    // ---------------------------------------------------------------- rendering

    /**
     * @return array<int, array{key: string, name: string, url: ?string, stored: bool, can: bool, count: int}>
     */
    public function getRows(): array
    {
        $files = $this->uploadedFiles();
        $user = Filament::auth()->user();
        $rows = [];

        foreach ($this->getUpload()->getRawState() ?? [] as $key => $file) {
            $info = $this->describe($file, $files[$key] ?? null);

            if ($info === null || !$info['image']) {
                continue;
            }

            $source = $this->sourceFor($key);
            $can = $source !== null && $source->canAnnotate($user);

            $rows[] = [
                'key' => (string) $key,
                'name' => $info['name'],
                'url' => $info['url'],
                'stored' => $source !== null,
                'can' => $can,
                'count' => $can ? count(ImageAnnotations::store()->shapes($source, $user)['items']) : 0,
            ];
        }

        return $rows;
    }

    /**
     * What the upload reports about its files; read once per state (a stored file costs a disk request).
     *
     * @return array<int|string, mixed>
     */
    protected function uploadedFiles(): array
    {
        $upload = $this->getUpload();

        $signature = md5((string) json_encode(array_map(
            static fn (mixed $file): string => $file instanceof TemporaryUploadedFile ? 'tmp:'.$file->getFilename() : (string) json_encode($file),
            $upload->getRawState() ?? [],
        )));

        if ($this->uploadedFilesCache === null || $this->uploadedFilesCache[0] !== $signature) {
            $this->uploadedFilesCache = [$signature, $upload->getUploadedFiles() ?? []];
        }

        return $this->uploadedFilesCache[1];
    }

    /**
     * @param array{name?: string, size?: int, type?: ?string, url?: string}|null $uploaded
     *
     * @return array{name: string, url: ?string, image: bool}|null
     */
    protected function describe(mixed $file, ?array $uploaded): ?array
    {
        if ($file instanceof TemporaryUploadedFile) {
            return [
                'name' => $file->getClientOriginalName(),
                'url' => $file->isPreviewable() ? $file->temporaryUrl() : null,
                'image' => Str::startsWith((string) $file->getMimeType(), 'image/'),
            ];
        }

        if (!is_string($file) || $uploaded === null) {
            return null;
        }

        $type = $uploaded['type'] ?? null;
        $name = (string) ($uploaded['name'] ?? basename($file));

        $isImage = is_string($type) && $type !== ''
            ? Str::startsWith($type, 'image/')
            : in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'jpe', 'jfif', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp'], true);

        return ['name' => $name, 'url' => $uploaded['url'] ?? null, 'image' => $isImage];
    }

    // ---------------------------------------------------------------- the editor

    public function getAnnotateAction(): Action
    {
        return AnnotateImageAction::make(self::ACTION)
            ->image(fn (array $arguments, self $component): ?ImageSource => $component->sourceFor($arguments['key'] ?? null))
            ->link()
            ->size(Size::Small)
            ->color('gray');
    }
}
