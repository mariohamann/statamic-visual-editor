# Statamic Visual Editor

Click any element in Statamic's Live Preview and the corresponding field in the Control Panel automatically expands, scrolls into view, and highlights. Zero production footprint — all annotations are stripped outside of Live Preview.

## Demo

<video src="https://github.com/user-attachments/assets/97ec557d-2642-4e74-87df-fb365a03154b"></video>

## Requirements

- Statamic 6
- PHP 8.4+

## Installation

```bash
composer require mariohamann/statamic-visual-editor
```

Publish assets (required on every addon update):

```bash
php artisan vendor:publish --provider="MarioHamann\StatamicVisualEditor\ServiceProvider" --force
```

A settings page is available at **CP → Tools → Visual Editor** to enable or disable the addon.

---

## Concepts

The addon provides a single tag — `{{ visual_edit }}` — that you place on HTML elements in your templates. During Live Preview it outputs data attributes that power bidirectional click-and-hover sync between the preview and the CP. Outside Live Preview it outputs nothing.

There are two targeting modes:

| Mode | What it targets | How it works |
|------|----------------|--------------|
| **Set targeting** | Replicator, Bard & Grid items | Links each rendered item to its CP set via an auto-generated UUID |
| **Field targeting** | Fixed blueprint fields (title, SEO, etc.) | Links any element to a CP field by its handle |

Both modes are fully bidirectional: clicking or hovering in the preview highlights the CP field, and vice versa.

---

## Set targeting

Targets individual Replicator, Bard, or Grid items. The addon automatically adds a hidden `_visual_id` field to every set in your blueprints and stamps a stable UUID during preview and on save — **no blueprint changes required**.

### Antlers

Add `{{ visual_edit }}` to the outermost element of each set partial. The tag reads `_visual_id` and `type` from the current context automatically:

```antlers
{{# Replicator / Bard set partial #}}
<div class="..." {{ visual_edit }}>
  {{ text }}
</div>
```

```antlers
{{# Grid rows #}}
{{ links }}
  <li {{ visual_edit }}>
    <a href="{{ link_url }}">{{ label }}</a>
  </li>
{{ /links }}
```

### Blade

Use `Statamic::tag('visual_edit')` with `->context($item->all())` to pass the set/row data. The tag reads `_visual_id` and `type` from the context, just like in Antlers:

```blade
{{-- Replicator / Bard set --}}
<div {!! Statamic::tag('visual_edit')->context($set->all())->fetch() !!}>
    {!! $set->text !!}
</div>
```

```blade
{{-- Grid rows --}}
@foreach ($rows as $row)
    <li {!! Statamic::tag('visual_edit')->context($row->all())->fetch() !!}>
        {!! (string) ($row->rule ?? '') !!}
    </li>
@endforeach
```

> **Important:** Always use `{!! !!}` (unescaped output), not `{{ }}`. The tag returns raw HTML attributes.

---

## Field targeting

Targets fixed blueprint fields — titles, SEO metadata, or any field that isn't inside a Replicator/Bard/Grid. The CP jumps directly to the field when clicked, switching tabs automatically if needed.

### Antlers

```antlers
{{# Top-level field #}}
<h1 {{ visual_edit field="hero_title" }}>{{ hero_title }}</h1>

{{# Nested field inside a group (dot notation) #}}
<p {{ visual_edit field="page_info.author" }}>{{ page_info:author }}</p>
```

The tooltip label is resolved from the field's Display Name in the current entry's blueprint automatically.

### Blade

```blade
{{-- Recommended: pass the blueprint handle (works without an entry object) --}}
<h1 {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('hero_title')->fetch() !!}>

{{-- Alternative: pass the entry for blueprint resolution --}}
<h1 {!! Statamic::tag('visual_edit')->context(['page' => $entry])->field('hero_title')->fetch() !!}>

{{-- Minimal: no label resolution (CP navigation still works; label is cosmetic) --}}
<h1 {!! Statamic::tag('visual_edit')->field('hero_title')->fetch() !!}>
```

The `blueprint` parameter accepts a namespaced handle: `collections.{handle}`, `globals.{handle}`.

> **Tip:** In Blade components you often don't have the entry object — use `->blueprint()` instead of threading `$entry` through props.

### Dot notation

Use dots to target nested fields inside groups: `page_info.author`. Avoid top-level field handles containing underscores that could collide with group subfield paths — both `page_info.author` and `page_info_author` resolve to the same CP element ID.

---

## Additional features

### Pair tag

When there's no single outermost element to annotate, use the pair tag to wrap content in a `<div>`:

```antlers
{{ visual_edit }}
  <h1>{{ hero_title }}</h1>
  <p>{{ hero_text }}</p>
{{ /visual_edit }}
```

### Outline inside

For dense layouts where a 2 px outbound outline overlaps neighbouring elements, draw the outline inside instead:

```antlers
<div {{ visual_edit outline-inside="true" }}>
```

```blade
<div {!! Statamic::tag('visual_edit')->context($set->all())->params(['outline-inside' => true])->fetch() !!}>
```

---

## Parameter reference

All parameters work in both Antlers and Blade (via the fluent API).

| Parameter | Default | Description |
|---|---|---|
| _(none)_ | — | Auto-targets the current set by its UUID |
| `field` | — | Targets a fixed field by handle (dot notation for nested groups) |
| `blueprint` | — | Resolve field labels from a specific blueprint (e.g. `collections.pages`). In Antlers the entry's blueprint is used automatically. |
| `outline-inside` | `false` | Draws the outline inside the element border |
| `id` | — | Override: target a specific set by a known UUID |

### Antlers ↔ Blade mapping

| Antlers | Blade |
|---------|-------|
| `{{ visual_edit }}` | `Statamic::tag('visual_edit')->context($set->all())->fetch()` |
| `{{ visual_edit field="title" }}` | `Statamic::tag('visual_edit')->field('title')->fetch()` |
| `{{ visual_edit field="title" blueprint="collections.pages" }}` | `Statamic::tag('visual_edit')->blueprint('collections.pages')->field('title')->fetch()` |
| `{{ visual_edit outline-inside="true" }}` | `Statamic::tag('visual_edit')->context($set->all())->params(['outline-inside' => true])->fetch()` |

---

## Developer reference

### How it works

1. **Blueprint injection** — `InjectVisualIdIntoBlueprint` adds a hidden `_visual_id` field to every Replicator, Bard, and Grid set when a blueprint is loaded.
2. **UUID stamping** — `StampVisualIds` generates stable UUIDs on save for any item missing a `_visual_id`.
3. **Template annotation** — `{{ visual_edit }}` outputs `data-sid="{uuid}"` (set targeting) or `data-sid-field="{path}"` (field targeting) plus optional label/type attributes.
4. **Bridge script** — `InjectBridgeScript` middleware injects `bridge.js` into the Live Preview iframe. It handles click/hover events and communicates with the CP via `postMessage`.
5. **CP script** — `addon.js` (loaded via Vite) listens for messages from the iframe, expands collapsed sets, switches tabs, scrolls, and highlights the target field.

Hover sync works in both directions for both mechanisms.

### Building assets (development)

```bash
# 1. PHP formatting
vendor/bin/pint addons/mariohamann/statamic-visual-editor/src addons/mariohamann/statamic-visual-editor/tests --format agent

# 2. Build JS
cd addons/mariohamann/statamic-visual-editor && npm run build

# 3. Publish to public/
php artisan vendor:publish --provider="MarioHamann\StatamicVisualEditor\ServiceProvider" --force

# 4. PHP tests
cd addons/mariohamann/statamic-visual-editor && vendor/bin/phpunit

# 5. E2E tests (requires site running)
npx playwright test
```

