---
name: visual-editor-blade
description: "Adds Visual Editor click-to-edit annotations to Blade templates. Activates when adding visual_edit tags to Blade views, implementing click-to-edit in Blade partials or components, or when the user mentions visual_edit with Blade, annotating Blade templates, or live preview annotations in .blade.php files."
---

# Visual Editor — Blade Templates

## When to Apply

Activate this skill when:

- Adding `visual_edit` tags to Blade (`.blade.php`) templates
- Implementing click-to-edit for Replicator, Bard, or Grid sets in Blade
- Adding field targeting to Blade templates or components

## Key Principle

The Visual Editor tag outputs raw HTML data attributes when inside Live Preview and outputs **nothing** outside Live Preview. It has zero production footprint.

**Critical:** Always use `{!! !!}` (unescaped output) — never `{{ }}`. The tag returns raw HTML attributes that must not be escaped.

---

## Set Targeting (Replicator / Bard / Grid)

Use `Statamic::tag('visual_edit')->context($set->all())->fetch()` on the outermost element. You must pass the full set/row data via `->context()` so the tag can read `_visual_id` and `type`.

### Replicator Set

```blade
{{-- resources/views/page_builder/article.blade.php --}}
<section class="fluid-grid" {!! Statamic::tag('visual_edit')->context($set->all())->fetch() !!}>
    {{-- set content here --}}
</section>
```

### Bard Set

```blade
{{-- resources/views/components/text.blade.php --}}
<div class="prose" {!! Statamic::tag('visual_edit')->context($set->all())->fetch() !!}>
    {!! $set->text !!}
</div>
```

### Grid Rows

```blade
@foreach ($rows as $row)
    <li {!! Statamic::tag('visual_edit')->context($row->all())->fetch() !!}>
        <a href="{!! (string) ($row->link_url ?? '') !!}">{!! (string) ($row->label ?? '') !!}</a>
    </li>
@endforeach
```

### Rules

1. **Always call `->context($set->all())`** — without context the tag cannot find the UUID.
2. **Always use `{!! !!}`** — `{{ }}` will escape the HTML attributes and break them.
3. **Place on the outermost element** of the set partial.
4. **One tag per set** — don't add to multiple elements within the same set.

---

## Field Targeting (Fixed Fields)

For fields NOT inside a Replicator/Bard/Grid, use `->field('handle')`:

```blade
<h1 {!! Statamic::tag('visual_edit')->field('title')->fetch() !!}>
    {{ $title }}
</h1>
```

### Blueprint Resolution for Labels

The tag resolves human-readable field labels from the blueprint. In Blade, the entry object is often not available in context (especially in Blade components), so you have three options:

#### Option 1: Pass the blueprint handle (recommended)

Works everywhere, no entry object needed:

```blade
<h1 {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('hero_title')->fetch() !!}>
    {{ $hero_title }}
</h1>
```

The `blueprint` parameter accepts namespaced handles:
- `collections.{collection_handle}` — e.g., `collections.pages`, `collections.blog`
- `globals.{global_handle}` — e.g., `globals.seo`

#### Option 2: Pass the entry via context

If you have the entry object available:

```blade
<h1 {!! Statamic::tag('visual_edit')->context(['page' => $entry])->field('hero_title')->fetch() !!}>
    {{ $hero_title }}
</h1>
```

#### Option 3: Minimal (no label resolution)

CP navigation still works — the label is cosmetic only:

```blade
<h1 {!! Statamic::tag('visual_edit')->field('hero_title')->fetch() !!}>
    {{ $hero_title }}
</h1>
```

### Dot Notation for Grouped Fields

Target fields inside groups using dot-separated paths:

```blade
<p {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('page_info.author')->fetch() !!}>
    {{ $page_info_author }}
</p>
```

### Live Preview Conditional

Field-targeted elements that add extra wrapper markup should be wrapped in a Live Preview check:

```blade
@if(request()->isLivePreview())
    <div {!! Statamic::tag('visual_edit')->field('seo_title')->params(['outline-inside' => true])->fetch() !!}>
        {{ $seo_title }}
    </div>
@endif
```

---

## Outline Inside

For dense layouts where the default outline overlaps neighbouring elements:

```blade
<div {!! Statamic::tag('visual_edit')->context($set->all())->params(['outline-inside' => true])->fetch() !!}>
    {!! $set->text !!}
</div>
```

---

## Common Blade Scenarios

### Blade Components

In Blade components you typically don't have the entry object. Use `->blueprint()` for label resolution:

```blade
{{-- resources/views/components/hero.blade.php --}}
@props(['title', 'subtitle'])

<div class="hero">
    <h1 {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('hero_title')->fetch() !!}>
        {{ $title }}
    </h1>
    <p {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('hero_subtitle')->fetch() !!}>
        {{ $subtitle }}
    </p>
</div>
```

### Replicator Loop in a Blade Layout

```blade
@foreach ($page->page_builder as $set)
    <section {!! Statamic::tag('visual_edit')->context($set->all())->fetch() !!}>
        @include('page_builder.' . $set->type, ['set' => $set])
    </section>
@endforeach
```

### When Context Data is Unavailable

If you cannot access `$set->all()` (e.g., in a heavily abstracted component), you can pass the UUID directly using the `id` parameter:

```blade
<div {!! Statamic::tag('visual_edit')->params(['id' => $visualId])->fetch() !!}>
    {{-- content --}}
</div>
```

This is a fallback — prefer passing full context when possible.

---

## Antlers ↔ Blade Mapping

| Antlers | Blade |
|---------|-------|
| `{{ visual_edit }}` | `Statamic::tag('visual_edit')->context($set->all())->fetch()` |
| `{{ visual_edit field="title" }}` | `Statamic::tag('visual_edit')->field('title')->fetch()` |
| `{{ visual_edit field="title" blueprint="collections.pages" }}` | `Statamic::tag('visual_edit')->blueprint('collections.pages')->field('title')->fetch()` |
| `{{ visual_edit outline-inside="true" }}` | `Statamic::tag('visual_edit')->context($set->all())->params(['outline-inside' => true])->fetch()` |
| `{{ visual_edit id="custom-uuid" }}` | `Statamic::tag('visual_edit')->params(['id' => 'custom-uuid'])->fetch()` |

---

## Parameter Reference

| Parameter | Default | Description |
|---|---|---|
| `->context($data)` | — | Pass the set/row data array; tag reads `_visual_id` and `type` from it |
| `->field('handle')` | — | Target a fixed field by handle (dot notation for nested groups) |
| `->blueprint('ns.handle')` | — | Resolve field labels from a specific blueprint |
| `->params(['outline-inside' => true])` | `false` | Draw the highlight outline inside the element border |
| `->params(['id' => 'uuid'])` | — | Override: target a specific set by a known UUID |

---

## Checklist

When adding `visual_edit` to a Blade template:

- [ ] Using `{!! !!}` (unescaped), NOT `{{ }}`
- [ ] Calling `->context($set->all())` for set targeting (not `$set` alone — must be `->all()`)
- [ ] Tag is on the outermost element of the set partial
- [ ] Only one tag per set (not duplicated on child elements)
- [ ] For field targeting: `->field('handle')` has the correct blueprint field handle
- [ ] For label resolution: `->blueprint('collections.handle')` or `->context(['page' => $entry])` is passed
- [ ] Field-targeted elements wrapped in `@if(request()->isLivePreview())` if they add extra markup
- [ ] Tested in Live Preview to confirm clicking highlights the correct CP field
