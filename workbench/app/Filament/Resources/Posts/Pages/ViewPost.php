<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts\Pages;

use Asignua\FilamentImageAnnotations\Actions\AnnotateImageAction;
use Filament\Resources\Pages\ViewRecord;
use Workbench\App\Filament\Resources\Posts\PostResource;
use Workbench\App\Models\Post;

class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AnnotateImageAction::make()->image(fn (Post $record) => $record->getFirstMedia('photos')),
        ];
    }
}
