# Statamic Visual Editor

Click any element in Statamic's Live Preview and the corresponding field in the Control Panel automatically expands, scrolls into view, and highlights. Zero production footprint — all annotations are stripped outside of Live Preview.

## Requirements

- Statamic 6
- PHP 8.4+

## Installation

```bash
composer require mariohamann/statamic-visual-editor
```

After installing, publish assets (required on every addon update):

```bash
php artisan vendor:publish --provider="Mariohamann\StatamicVisualEditor\ServiceProvider" --force
```

## How it works

The addon provides two complementary targeting mechanisms:

| Mechanism | Use case | CP interaction |
|---|---|---|
| **UUID-based** (auto) | Replicator, Bard, Grid sets — repeating content | Expands collapsed set, scrolls, pulses |
| **Field-handle-based** (manual) | Any fixed/flat field — headings, SEO fields, grouped fields | Switches tab if needed, scrolls, pulses border |

Both are bidirectional: clicking in the preview focuses the field in the CP, and hovering sets in the CP highlights the corresponding element in the preview.

---

## Auto-injection: Replicator, Bard and Grid

The addon automatically assigns a stable UUID (`_visual_id`) to every set in Replicator, Bard, and Grid fields. No blueprint changes are required.

### Template usage

Use `{{ visual_edit:attr }}` inside the loop that renders your sets. The tag outputs `data-sid="..."` attributes during Live Preview and is a complete no-op in production.

**Replicator / page builder:**

```antlers
{{ page_builder }}
  {{ partial:page_builder/{{ type }} }}
{{ /page_builder }}
```

Inside each partial, annotate the outermost element:

```antlers
{{# resources/views/page_builder/_text.antlers.html #}}
<div class="..." {{ visual_edit:attr }}>
  {{ text }}
</div>
```

**Bard (article sets):**

```antlers
{{ article }}
  {{ if type == "set" }}
    {{ partial:components/{{ values:type }} }}
  {{ /if }}
{{ /article }}
```

Inside each component partial:

```antlers
{{# resources/views/components/_pull_quote.antlers.html #}}
<figure {{ visual_edit:attr }}>
  <blockquote>{{ quote }}</blockquote>
</figure>
```

**Grid:**

```antlers
{{ team_members }}
  <div class="card" {{ visual_edit:attr }}>
    <h2>{{ name }}</h2>
  </div>
{{ /team_members }}
```

### Adding a custom label

```antlers
<div {{ visual_edit:attr label="Team Member" }}>...</div>
```

### Overriding the UUID

In rare cases you may want to target a specific set by its UUID directly:

```antlers
<div {{ visual_edit:attr id="custom-uuid-here" }}>...</div>
```

### Pair tag (wraps content in a `<div>`)

```antlers
{{ visual_edit label="Hero Block" }}
  <h1>{{ hero_title }}</h1>
  <p>{{ hero_text }}</p>
{{ /visual_edit }}
```

---

## Manual field targeting

For pages without page builders — long landing pages, SEO metadata, global settings — you can annotate any element with the `field=` parameter. The CP will jump directly to that field when clicked.

### Template usage

```antlers
{{# Top-level field #}}
<h1 {{ visual_edit:attr field="hero_title" label="Hero Title" }}>
  {{ hero_title }}
</h1>

{{# Field in a group (dot notation) #}}
<p {{ visual_edit:attr field="page_info.author" label="Author" }}>
  {{ page_info:author }}
</p>

{{# SEO fields are in the SEO tab — tab switching is handled automatically #}}
<meta name="description" content="{{ seo_description }}">
{{# In the preview panel: #}}
<div {{ visual_edit:attr field="seo_description" label="SEO Description" }}>
  {{ seo_description }}
</div>
```

No blueprint changes are required. The `field=` value must match Statamic's field handle path using **dot notation** for nested fields inside groups (e.g., `group_handle.field_handle`).

### How field paths map to CP IDs

Statamic renders every field wrapper with `id="field_{path.replaceAll('.', '_')}"`. This addon uses that stable convention to locate the field:

| Template annotation | Field `id` in CP DOM |
|---|---|
| `field="hero_title"` | `#field_hero_title` |
| `field="page_info.author"` | `#field_page_info_author` |
| `field="seo.meta.description"` | `#field_seo_meta_description` |

> **Ambiguity note:** A top-level field named `page_info_author` and a nested field `page_info.author` both map to `#field_page_info_author`. This is a Statamic naming-convention limitation. Avoid naming top-level fields with underscores that collide with group subfield paths.

### Hover sync

Field targeting is fully bidirectional:

- Hovering a `[data-sid-field]` element in the preview highlights the corresponding CP field.
- Hovering a CP field wrapper highlights the corresponding element in the preview.

---

## PHP helper

For Blade templates, a global `visual_edit()` helper is available:

```blade
<div {!! visual_edit($uuid, $label, $type) !!}>...</div>
```

The function returns an empty string when not in Live Preview.

---

## Settings

After publishing, a settings page is available at **CP → Tools → Visual Editor** (or via the `statamic-visual-editor` config file):

| Setting | Default | Description |
|---|---|---|
| `enabled` | `true` | Enable or disable the entire addon |

---

## Developer reference

### Data flow (UUID-based)

1. **Blueprint loading** — `InjectVisualIdIntoBlueprint` listener adds a hidden `_visual_id` (`auto_uuid`) field to every Replicator, Bard, and Grid set definition.
2. **Entry saving** — `StampVisualIds` listener assigns a `Str::uuid()` to each set that doesn't already have one. Existing UUIDs are never changed.
3. **Live Preview rendering** — `{{ visual_edit:attr }}` outputs `data-sid="{uuid}"` on annotated elements. `InjectBridgeScript` middleware injects `bridge.js` into the iframe.
4. **Click in preview** — `bridge.js` sends `{ type: 'click', uid }` via `postMessage` to the CP.
5. **CP receives message** — `cp.js` finds the set by its `[data-visual-id]` input, switches tabs if needed, expands collapsed ancestors, scrolls to the set, and plays a highlight animation.
6. **CP → preview hover sync** — Hovering a set in the CP sends `{ type: 'hover', uid }` to the preview, which adds `data-sid-hover` styling to the matching element.

### Data flow (field-handle-based)

1. **Template** — `{{ visual_edit:attr field="path" }}` outputs `data-sid-field="path"` (and optionally `data-sid-label`). No blueprint changes, no UUID required.
2. **Click in preview** — `bridge.js` sends `{ type: 'click', field: 'path' }` to the CP.
3. **CP receives message** — `cp.js` calls `document.getElementById('field_' + path.replaceAll('.', '_'))`, switches tabs if needed, scrolls to the field, and plays a border-pulse animation.
4. **CP → preview hover sync** — Hovering a CP field wrapper (any element whose `id` starts with `field_`) sends `{ type: 'hover', field: key }` to the preview. The preview matches by normalizing the underscore form back to the registered `data-sid-field` values.

### Building assets (development)

```bash
# 1. PHP formatting
vendor/bin/pint addons/mariohamann/statamic-visual-editor/src addons/mariohamann/statamic-visual-editor/tests --format agent

# 2. Build JS
cd addons/mariohamann/statamic-visual-editor && npm run build

# 3. Publish to public/
php artisan vendor:publish --provider="Mariohamann\StatamicVisualEditor\ServiceProvider" --force

# 4. PHP tests
cd addons/mariohamann/statamic-visual-editor && vendor/bin/phpunit

# 5. E2E tests (requires live site at https://live-editor.test)
npx playwright test
```

