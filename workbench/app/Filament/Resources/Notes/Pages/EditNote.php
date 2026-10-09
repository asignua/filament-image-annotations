<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Notes\Pages;

use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\Notes\NoteResource;

class EditNote extends EditRecord
{
    protected static string $resource = NoteResource::class;
}
