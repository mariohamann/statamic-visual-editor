---
name: visual-editor-antlers
description: "Adds Visual Editor click-to-edit annotations to Antlers templates. Activates when adding visual_edit tags to Antlers views, implementing click-to-edit in Antlers partials, or when the user mentions visual_edit with Antlers, annotating Antlers templates, or live preview annotations in .antlers.html files."
---

# Visual Editor — Antlers Templates

## When to Apply

Activate this skill when:

- Adding `{{ visual_edit }}` tags to Antlers (`.antlers.html`) templates
- Implementing click-to-edit for Replicator, Bard, or Grid sets in Antlers
- Adding field targeting to Antlers templates

## Key Principle

`{{ visual_edit }}` outputs raw HTML data attributes when inside Live Preview and outputs **nothing** outside Live Preview. It has zero production footprint.

---

## Set Targeting (Replicator / Bard / Grid)

Place `{{ visual_edit }}` on the **outermost HTML element** of each set partial. The tag automatically reads `_visual_id` and `type` from the current Antlers context — no parameters needed.

### Replicator Set Partial

```antlers
{{# resources/views/page_builder/_article.antlers.html #}}
<section class="fluid-grid" {{ visual_edit }}>
    {{# set content here #}}
</section>
```

### Bard Set Partial

```antlers
{{# resources/views/components/_text.antlers.html #}}
<div class="prose" {{ visual_edit }}>
    {{ text }}
</div>
```

### Grid Rows

For Grid fields, add `{{ visual_edit }}` inside the loop on each row's element:

```antlers
{{ links }}
    <li {{ visual_edit }}>
        <a href="{{ link_url }}">{{ label }}</a>
    </li>
{{ /links }}
```

### Rules

1. **Always place on the outermost element** of the set partial — the element that wraps all of the set's content.
2. **One tag per set** — don't add `{{ visual_edit }}` to multiple elements within the same set.
3. **No parameters needed** — `_visual_id` and `type` are read from context automatically.
4. **Works in nested sets** — Bard sets inside Replicator sets each get their own `{{ visual_edit }}`.

---

## Field Targeting (Fixed Fields)

For fields that are NOT inside a Replicator/Bard/Grid (e.g., `title`, SEO fields, group sub-fields), use the `field` parameter:

```antlers
<h1 {{ visual_edit field="title" }}>{{ title }}</h1>
```

### Dot Notation for Grouped Fields

Target fields inside groups using dot-separated paths:

```antlers
<p {{ visual_edit field="page_info.author" }}>{{ page_info:author }}</p>
<p {{ visual_edit field="page_info.notes" }}>{{ page_info:notes }}</p>
```

Note: In Antlers, accessing grouped field values uses colons (`page_info:author`), but the `visual_edit` field parameter uses dots (`page_info.author`).

### Live Preview Conditional

Field-targeted elements often add extra wrapper markup that shouldn't appear on the production frontend. Wrap them in a Live Preview conditional:

```antlers
{{ if live_preview }}
    <div {{ visual_edit field="seo_title" outline-inside="true" }}>
        {{ seo_title }}
    </div>
    <div {{ visual_edit field="seo_description" outline-inside="true" }}>
        {{ seo_description }}
    </div>
{{ /if }}
```

### Blueprint Parameter

In Antlers, the entry's blueprint is resolved automatically — you don't need to pass it. The `blueprint` parameter is only needed in Blade.

---

## Pair Tag

When a set has no single outermost HTML element, use `{{ visual_edit }}` as a pair tag. It wraps the content in a `<div>`:

```antlers
{{ visual_edit }}
    <h1>{{ hero_title }}</h1>
    <p>{{ hero_text }}</p>
{{ /visual_edit }}
```

Use this sparingly — prefer adding the tag to an existing wrapper element when one exists.

---

## Outline Inside

For dense layouts where the default 2px outbound outline overlaps neighbouring elements, draw the outline inside instead:

```antlers
<div {{ visual_edit outline-inside="true" }}>
    {{ text }}
</div>
```

---

## Complete Example

A typical page builder template rendering Replicator sets:

```antlers
{{# resources/views/default.antlers.html #}}
{{ page_builder }}
    {{ partial src="page_builder/_{type}" }}
{{ /page_builder }}
```

Each set partial (e.g., `_article.antlers.html`):

```antlers
<section class="fluid-grid {{ class }}" {{ visual_edit }}>
    <div class="span-content">
        {{ article }}
            {{ partial src="components/_{type}" }}
        {{ /article }}
    </div>
</section>
```

Each Bard set partial (e.g., `_pull_quote.antlers.html`):

```antlers
<figure class="span-md" {{ visual_edit }}>
    <blockquote>{{ quote }}</blockquote>
    {{ if author }}
        <figcaption>{{ author }}</figcaption>
    {{ /if }}
</figure>
```

---

## Parameter Reference

| Parameter | Default | Description |
|---|---|---|
| _(none)_ | — | Auto-targets the current set by its UUID (reads `_visual_id` from context) |
| `field` | — | Targets a fixed field by handle (dot notation for nested groups) |
| `outline-inside` | `false` | Draws the highlight outline inside the element border |
| `id` | — | Override: target a specific set by a known UUID |

---

## Checklist

When adding `{{ visual_edit }}` to an Antlers template:

- [ ] Tag is on the outermost element of the set partial
- [ ] Only one `{{ visual_edit }}` per set (not duplicated on child elements)
- [ ] For grid rows: tag is inside the loop on each row's element
- [ ] For field targeting: `field="handle"` parameter is set with the correct blueprint field handle
- [ ] For grouped fields: using dot notation (`field="group.subfield"`)
- [ ] Field-targeted elements wrapped in `{{ if live_preview }}` if they add extra markup
- [ ] Tested in Live Preview to confirm clicking highlights the correct CP field
