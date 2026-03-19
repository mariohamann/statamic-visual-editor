---
name: visual-editor-blade
description: "Adds Visual Editor click-to-edit annotations to Blade templates. Activates when adding visual_edit tags to Blade views, implementing click-to-edit in Blade partials or components, or when the user mentions visual_edit with Blade, annotating Blade templates, or live preview annotations in .blade.php files."
---

# Visual Editor — Blade Templates

## Key Principle

The tag outputs raw HTML data attributes in Live Preview and **nothing** outside it. Zero production footprint — annotations are always safe to add. Even sets rendered inline in a larger template (without a dedicated partial) can be annotated directly on the element in the loop.

**Critical:** Always use `{!! !!}` (unescaped) — never `{{ }}`. The tag returns raw HTML attributes.

---

## Set Targeting (Replicator / Bard / Grid)

Pass the full set/row data via `->context()` so the tag can read `_visual_id` and `type`. Place on the outermost element.

```blade
{{-- Replicator / Bard set partial --}}
<section class="fluid-grid" {!! Statamic::tag('visual_edit')->context($set->all())->fetch() !!}>
    {{-- content --}}
</section>
```

```blade
{{-- Grid rows --}}
@foreach ($rows as $row)
    <li {!! Statamic::tag('visual_edit')->context($row->all())->fetch() !!}>
        {{ $row->label }}
    </li>
@endforeach
```

Each item in an iterated loop gets its own tag. A single template will often have multiple tags when rendering multiple set items — that's expected. Avoid tagging both a parent element and a child element for the *same* set item, since only one is needed.

---

## Field Targeting (Fixed Fields)

For fields outside Replicator/Bard/Grid, use `->field('handle')`. The label shown in the CP tooltip is resolved from the blueprint — pass a blueprint handle when the entry object isn't available (common in Blade components).

The most common use is annotating elements that already exist in the markup:

```blade
{{-- Simple field on an existing element --}}
<h1 {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('hero_title')->fetch() !!}>
    {{ $hero_title }}
</h1>

{{-- Dot notation for grouped fields --}}
<p {!! Statamic::tag('visual_edit')->blueprint('collections.pages')->field('page_info.author')->fetch() !!}>
    {{ $author }}
</p>

{{-- If the entry is available, pass it via context instead --}}
<h1 {!! Statamic::tag('visual_edit')->context(['page' => $entry])->field('hero_title')->fetch() !!}>
    {{ $hero_title }}
</h1>

{{-- Minimal — label is cosmetic only, CP navigation still works without it --}}
<h1 {!! Statamic::tag('visual_edit')->field('hero_title')->fetch() !!}>
    {{ $hero_title }}
</h1>
```

The `blueprint` parameter accepts `collections.{handle}` or `globals.{handle}`.

### Surfacing hidden fields in Live Preview

Some fields (like SEO metadata) don't have a visible element on the page but you still want authors to click to edit them in Live Preview. Wrap annotated placeholder elements in `@if(request()->isLivePreview())` so they appear only during preview.

A Blade component is a natural home for this — it receives props rather than the full entry, so use `->blueprint()` for label resolution:

```blade
{{-- resources/views/components/seo-meta.blade.php --}}
@props(['seoTitle', 'seoDescription'])

@if(request()->isLivePreview())
    <div>
        <p {!! Statamic::tag('visual_edit')
            ->blueprint('collections.pages')
            ->field('seo_title')
            ->params(['outline-inside' => true])
            ->fetch() !!}>
            {{ $seoTitle }}
        </p>
        <p {!! Statamic::tag('visual_edit')
            ->blueprint('collections.pages')
            ->field('seo_description')
            ->params(['outline-inside' => true])
            ->fetch() !!}>
            {{ $seoDescription }}
        </p>
    </div>
@endif
```

This is optional and a progressive enhancement — the typical case is simply annotating elements that are already in the markup.

---

## Outline Inside

For dense layouts where the outbound outline overlaps neighbours:

```blade
<div {!! Statamic::tag('visual_edit')->context($set->all())->params(['outline-inside' => true])->fetch() !!}>
    {!! $set->text !!}
</div>
```

---

## Antlers ↔ Blade Mapping

| Antlers | Blade |
|---------|-------|
| `{{ visual_edit }}` | `Statamic::tag('visual_edit')->context($set->all())->fetch()` |
| `{{ visual_edit field="title" }}` | `Statamic::tag('visual_edit')->field('title')->fetch()` |
| `{{ visual_edit field="title" blueprint="collections.pages" }}` | `Statamic::tag('visual_edit')->blueprint('collections.pages')->field('title')->fetch()` |
| `{{ visual_edit outline-inside="true" }}` | `Statamic::tag('visual_edit')->context($set->all())->params(['outline-inside' => true])->fetch()` |

---

## Parameter Reference

| Parameter | Default | Description |
|---|---|---|
| `->context($data)` | — | Pass the set/row data array; tag reads `_visual_id` and `type` from it |
| `->field('handle')` | — | Target a fixed field by handle (dot notation for nested groups) |
| `->blueprint('ns.handle')` | — | Resolve field labels from a specific blueprint |
| `->params(['outline-inside' => true])` | `false` | Draw the highlight outline inside the element border |
| `->params(['id' => 'uuid'])` | — | Override: target a specific set by a known UUID |
