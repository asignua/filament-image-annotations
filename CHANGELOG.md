# Changelog

All notable changes to `asignua/filament-image-annotations` are documented here.

## Unreleased

- Non-destructive annotation layer for images: arrows, rectangles, ellipses, lines, a pen, a highlighter and text, stored
  as sanitised JSON (normalised coordinates) in an own table; the original file is never modified.
- Editor (Alpine, SVG): select/move, eight colours, three stroke widths, undo/redo, keyboard shortcuts, touch and pen support.
- `AnnotateImageAction` (table / header / infolist action), `ImageAnnotator` field, `AnnotatedImageEntry` infolist entry and
  the `<x-image-annotations::image>` Blade component with a show/hide toggle.
- `AnnotatedUpload::make()`: an Annotate button per stored image of a `FileUpload` or `SpatieMediaLibraryFileUpload`;
  the layer of a file removed from a plain upload is forgotten on save.
- `ImageSource` contract with `MediaImageSource` and `ModelPathImageSource`, a registry (`ImageSources::register()`) for
  custom image classes, policy-based authorization with a global `ImageAnnotations::authorizeUsing()` override.
- `AnnotationStore` (rebindable), `ShapeSanitizer`, `AnnotationSaved` / `AnnotationDeleted` events, the `HasImageAnnotations`
  trait and automatic cleanup when a Media item is deleted.
- Ten interface languages.
