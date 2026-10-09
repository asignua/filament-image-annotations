<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts;

use Asignua\FilamentImageAnnotations\Actions\AnnotateImageAction;
use Asignua\FilamentImageAnnotations\AnnotatedUpload;
use Asignua\FilamentImageAnnotations\Infolists\Components\AnnotatedImageEntry;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Filament\Resources\Posts\Pages\ViewPost;
use Workbench\App\Models\Post;

/**
 * Demo resource: a table record action opens the editor, the view page shows the annotated picture.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            AnnotatedUpload::make(
                SpatieMediaLibraryFileUpload::make('photos')->collection('photos')->image()->multiple(),
            ),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            AnnotatedImageEntry::make('photo')
                ->label('Photo')
                ->image(fn (Post $record) => $record->getFirstMedia('photos'))
                ->alt('Demo photo'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('user_id'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    AnnotateImageAction::make()->image(fn (Post $record) => $record->getFirstMedia('photos')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'edit' => EditPost::route('/{record}/edit'),
            'view' => ViewPost::route('/{record}'),
        ];
    }
}
