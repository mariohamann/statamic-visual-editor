# Statamic Visual Editor

Click any element in Statamic's Live Preview and the corresponding field in the Control Panel automatically expands, scrolls into view, and highlights. Zero production footprint — all annotations are stripped outside of Live Preview.

## Requirements

- Statamic 6
- PHP 8.4+

## Installation

```bash
composer require mariohamann/statamic-visual-editor
```

Publish assets (required on every addon update):

```bash
php artisan vendor:publish --provider="Mariohamann\StatamicVisualEditor\ServiceProvider" --force
```

A settings page is available at **CP → Tools → Visual Editor** to enable or disable the addon.

---

## Quick start: Replicator, Bard & Grid sets

The addon automatically assigns a stable UUID to every set — no blueprint changes required. Add `{{ visual_edit }}` to the outermost element of each set partial:

```antlers
{{# resources/views/page_builder/_text.antlers.html #}}
<div class="..." {{ visual_edit }}>
  {{ text }}
</div>
```

That's it. During Live Preview, clicking the element expands the matching set in the CP, scrolls to it, and highlights it. In production the tag outputs nothing.

The same `{{ visual_edit }}` tag works identically for Replicator, Bard, and Grid sets.

---

## Field targeting

For fixed fields — headings, SEO metadata — annotate any element with `field=` using the field's handle. The CP will jump directly to that field when clicked, switching tabs automatically if needed.

```antlers
{{# Top-level field #}}
<h1 {{ visual_edit field="hero_title" }}>{{ hero_title }}</h1>

{{# Nested field inside a group (dot notation) #}}
<p {{ visual_edit field="page_info.author" }}>{{ page_info:author }}</p>
```

The tooltip label is automatically resolved from the field's Display Name in the blueprint. Both mechanisms are bidirectional — hovering a field in the CP highlights the corresponding element in the preview, and vice versa.

> **Dot notation note:** Use dots for nested group fields (`group.field`). Avoid top-level field handles with underscores that could collide with group subfield paths, as both map to the same CP element ID.

---

## Reference

### `{{ visual_edit }}` parameters

| Parameter | Default | Description |
|---|---|---|
| _(none)_ | — | Auto-targets the current set by its UUID |
| `field="handle"` | — | Targets a fixed field by handle (dot notation for nested) |
| `outline-inside="true"` | `false` | Draws the highlight outline inside the element border (useful in dense layouts where a 2px outbound outline overlaps neighbours) |
| `id="uuid"` | — | Override: target a specific set by a known UUID |

### Blade helper

For Blade templates, use the `visual_edit()` PHP helper:

```blade
<div {!! visual_edit($uuid, $type) !!}>...</div>
```

Returns an empty string outside of Live Preview. The label is auto-derived as `Str::headline($type)` when `$type` is provided.

### Pair tag

When you have no single outermost element to annotate, the pair tag wraps its content in a `<div>`:

```antlers
{{ visual_edit }}
  <h1>{{ hero_title }}</h1>
  <p>{{ hero_text }}</p>
{{ /visual_edit }}
```

---

## Developer reference

### How it works

The addon has two targeting mechanisms:

- **UUID-based** (sets): `InjectVisualIdIntoBlueprint` adds a hidden `_visual_id` field to every Replicator/Bard/Grid set. `StampVisualIds` assigns stable UUIDs on save. `{{ visual_edit }}` outputs `data-sid="{uuid}"`. `bridge.js` (injected by middleware) sends `{ type: 'click', uid }` via `postMessage`; `cp.js` expands, scrolls, and highlights the set.
- **Field-based**: `{{ visual_edit field="path" }}` outputs `data-sid-field="path"`. `bridge.js` sends `{ type: 'click', field: 'path' }`; `cp.js` resolves `#field_{path}` in the DOM, switches tabs if needed, scrolls, and pulses the border.

Hover sync works in both directions for both mechanisms.

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

