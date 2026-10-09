<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations;

use Asignua\FilamentImageAnnotations\Forms\AnnotationsPanel;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Schemas\Components\Group;

/**
 * Adds an "Annotate" button per stored image to a core file upload.
 *
 *     AnnotatedUpload::make(FileUpload::make('image')->image()->disk('public'))
 *     AnnotatedUpload::make(SpatieMediaLibraryFileUpload::make('photos')->collection('photos')->multiple())
 *
 * Works with `FileUpload` (the layer is keyed by the file path on the record) and
 * `SpatieMediaLibraryFileUpload` (the layer belongs to the media item). The upload itself is not modified.
 *
 * It returns a plain, state-less `Group` holding the upload and the list as SIBLINGS, for the same reason as
 * `ImageMetaUpload`: a panel nested inside the upload would get a state path under the upload's own.
 *
 * Annotating is immediate: the button opens the editor and "Save annotations" writes the layer right away through
 * the AnnotationStore, it does NOT wait for the form's own Save. That is why a file that is not stored yet
 * (a fresh upload, or any file on a create page) cannot be annotated: there is nothing to hang the layer on.
 */
final class AnnotatedUpload
{
    public static function make(BaseFileUpload $upload): Group
    {
        $panel = AnnotationsPanel::make($upload->getName().'_annotations');

        return Group::make([$upload, $panel])->columnSpanFull();
    }
}
