# Changelog

All notable changes to `asignua/filament-image-annotations` are documented here.

## Unreleased

- New: the **Polygon** tool (key `G`, on by default after the ellipse). Click to add vertices; double click, `Enter` or a click on the first vertex closes it; `Esc` cancels, `Backspace` removes the last vertex, `Shift` snaps a segment to 45 degrees. Select / move, recolour, stroke width, undo / redo and the viewer all handle polygons. Stored as `type: "polygon"` with 3 to `limits.polygon_points` (new, default `100`) points in `pts`; `ShapeSanitizer` drops a polygon outside that range. Add `polygon` to a published `tools` list to get the button.

## v1.2.0 - 2026-10-13

- New: the viewer as a bare overlay over a picture drawn by someone else (no `x-ref="image"`): `data-aspect` (height / width) sizes the marks, `data-fit="cover"` crops them like an `object-fit: cover` picture, `data-interactive="false"` keeps the pins from catching clicks. Used by the Pro add-on to draw marks over chat thumbnails.
- Editor: the toolbar is framed and the picture sits on a tinted, framed stage with a shadow, so a light screenshot no longer melts into a light modal (light and dark panels).

## v1.1.2 - 2026-10-12

- Docs: screenshots, cover and a workbench demo.

## v1.1.1 - 2026-10-10

- Fix: the migration no longer adds an index over `key` (512 characters) next to the morph columns — on MySQL / MariaDB it exceeded the 3072-byte key limit and `migrate` failed with error 1071, leaving a half-created table. Hosts that already published the migration should remove that `$table->index(...)` line from their copy before migrating.

## v1.1.0 - 2026-10-09

- First public release (versioned 1.1.0: the Pro add-on and early adopters already require ^1.1).
- `AnnotateImageAction` dispatches the Livewire event `image-annotations:saved` (payload `image` = `ImageAnnotations::imageId()`) after a save; the viewer keeps its width next to a side panel; workbench builds the `public/storage` link.
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
- Extension points for the Pro package: `Support\Layer` and `AnnotationStore::layers()`; read-only underlays in the editor
  (`ImageAnnotator::underlays()`, filled by `AnnotateImageAction`) and several layers in the viewer, each with a toggle chip
  (the viewer attribute is now `data-layers`); a `pin` shape (numbered marker, sanitised and rendered) with an opt-in `pin`
  tool; configurable toolbar (`tools` config and `->tools()`); `image-annotations:pin-click` / `change` / `pin-added` DOM events.
- `ImageAnnotations::addPanel()` side panels next to the editor and the viewer (`data-ia-scope` container); layers with `meta.hidden` start hidden.
