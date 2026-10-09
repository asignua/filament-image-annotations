<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Notes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Notes\NoteResource;

class CreateNote extends CreateRecord
{
    protected static string $resource = NoteResource::class;
}
