---
name: visual-editor-antlers
description: "Adds Visual Editor click-to-edit annotations to Antlers templates. Activates when adding visual_edit tags to Antlers views, implementing click-to-edit in Antlers partials, or when the user mentions visual_edit with Antlers, annotating Antlers templates, or live preview annotations in .antlers.html files."
---

# Visual Editor — Antlers Templates

## Key Principle

`{{ visual_edit }}` outputs raw HTML data attributes in Live Preview and **nothing** outside it. Zero production footprint — annotations are always safe to add. Even sets rendered inline in a larger template (without a dedicated partial) can be annotated directly on the element in the loop.

---

## Set Targeting (Replicator / Bard / Grid)

Place `{{ visual_edit }}` on the outermost HTML element of each set partial. The tag reads `_visual_id` and `type` from the Antlers context automatically — no parameters needed.

```antlers
{{# Replicator / Bard set partial #}}
<section class="fluid-grid" {{ visual_edit }}>
    {{# content #}}
</section>
```

```antlers
{{# Grid rows — add inside the loop #}}
{{ links }}
    <li {{ visual_edit }}>
        <a href="{{ link_url }}">{{ label }}</a>
    </li>
{{ /links }}
```

Each item in an iterated loop gets its own tag — including Bard sets nested inside Replicator sets. A single template will often have multiple `{{ visual_edit }}` tags when it renders multiple set items, which is totally fine. Just avoid tagging both a parent element and a child element for the *same* set item, since only one is needed.

### Pair Tag

When there's no single outermost element, use the pair form to wrap content in a `<div>`:

```antlers
{{ visual_edit }}
    <h1>{{ hero_title }}</h1>
    <p>{{ hero_text }}</p>
{{ /visual_edit }}
```

---

## Field Targeting (Fixed Fields)

For fields outside Replicator/Bard/Grid (e.g., `title`, SEO fields, group sub-fields), use the `field` parameter. The entry's blueprint is resolved automatically in Antlers.

The most common use is annotating elements that already exist in the markup:

```antlers
<h1 {{ visual_edit field="title" }}>{{ title }}</h1>

{{# Dot notation for grouped fields #}}
<p {{ visual_edit field="page_info.author" }}>{{ page_info:author }}</p>
```

Note: Antlers accesses group values with colons (`page_info:author`) but `visual_edit` uses dots (`page_info.author`).

### Surfacing hidden fields in Live Preview

Some fields (like SEO metadata) don't have a visible element on the page but you still want authors to be able to click to edit them in Live Preview. Wrap a representative element in `{{ if live_preview }}` to make it appear only during preview:

```antlers
{{ if live_preview }}
    <div {{ visual_edit field="seo_title" outline-inside="true" }}>{{ seo_title }}</div>
    <div {{ visual_edit field="seo_description" outline-inside="true" }}>{{ seo_description }}</div>
{{ /if }}
```

This is optional. The typical case is simply annotating elements that are already there.

---

## Outline Inside

For dense layouts where the outbound outline overlaps neighbours:

```antlers
<div {{ visual_edit outline-inside="true" }}>{{ text }}</div>
```

---

## Parameter Reference

| Parameter | Default | Description |
|---|---|---|
| _(none)_ | — | Auto-targets the current set by UUID (reads `_visual_id` from context) |
| `field` | — | Targets a fixed field by handle (dot notation for nested groups) |
| `outline-inside` | `false` | Draws the highlight outline inside the element border |
| `id` | — | Override: target a specific set by a known UUID |
