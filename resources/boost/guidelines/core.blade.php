## Statamic Visual Editor

Click-to-edit for Statamic's Live Preview. Authors click any annotated element in the preview iframe — the corresponding CP field expands, scrolls into view, and highlights. Zero production footprint; all annotations are stripped outside of Live Preview.

@verbatim
- **Set targeting**: Links Replicator, Bard, and Grid items to their CP sets via a hidden `_visual_id` UUID field (auto-injected into blueprints). Use `{{ visual_edit }}` (Antlers) or `Statamic::tag('visual_edit')->context($set->all())->fetch()` (Blade) on the outermost element of each set partial.
- **Field targeting**: Links any element to a specific CP field by handle. Use `{{ visual_edit field="handle" }}` (Antlers) or `Statamic::tag('visual_edit')->field('handle')->fetch()` (Blade). Supports dot notation for grouped fields (e.g., `page_info.author`).
@endverbatim
