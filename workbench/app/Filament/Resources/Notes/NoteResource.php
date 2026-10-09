<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Notes;

use Asignua\FilamentImageAnnotations\AnnotatedUpload;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Notes\Pages\CreateNote;
use Workbench\App\Filament\Resources\Notes\Pages\EditNote;
use Workbench\App\Filament\Resources\Notes\Pages\ListNotes;
use Workbench\App\Models\Note;

/**
 * Demo of the plain FileUpload path: the image is a path in the `image` column. No policy: any signed-in user.
 */
class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AnnotatedUpload::make(FileUpload::make('image')->image()->disk('public')->directory('notes')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('id')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotes::route('/'),
            'create' => CreateNote::route('/create'),
            'edit' => EditNote::route('/{record}/edit'),
        ];
    }
}
