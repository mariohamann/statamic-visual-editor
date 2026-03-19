---
name: visual-editor-research
description: "Audits a Statamic project to find where Visual Editor annotations should be added. Activates when setting up click-to-edit, auditing templates for visual_edit tags, planning Visual Editor integration, or when the user mentions visual editor setup, annotate templates, click-to-edit audit, or live preview annotations."
---

# Visual Editor Research

## When to Apply

Activate this skill when:

- Setting up the Statamic Visual Editor addon in a project for the first time
- Auditing which templates need `visual_edit` annotations
- Planning where click-to-edit tags should be added
- Checking coverage of existing annotations

## Goal

Produce a structured checklist of all templates that should have `visual_edit` annotations, mapping blueprint fields → fieldset imports → view partials. This tells the developer exactly where to add tags and which targeting mode to use.

## Step 1: Scan Blueprints and Fieldsets

Search `resources/blueprints/` and `resources/fieldsets/` for YAML files containing Replicator, Bard, or Grid fields. These are the fields whose rendered sets need **set targeting** (`{{ visual_edit }}`).

```
# Find all fields with replicator, bard, or grid types
grep -rn "type: replicator\|type: bard\|type: grid" resources/blueprints/ resources/fieldsets/
```

For each match, note:
- **Blueprint/fieldset path** (e.g., `resources/fieldsets/page_builder.yaml`)
- **Field handle** (e.g., `page_builder`, `article`, `links`)
- **Field type** (`replicator`, `bard`, or `grid`)
- **Set handles** within the field (e.g., `article`, `form`, `cards`, `button`)

Follow `import` references to resolve the full set hierarchy. Statamic fieldsets use `import: fieldset_name` to include other fieldsets. Trace the chain:

```
Blueprint → Replicator field → set handle → imported fieldset → nested Bard/Replicator → deeper sets
```

Example hierarchy from a typical Statamic Peak project:
```
page.yaml (blueprint)
  └── page_builder (replicator, imports page_builder fieldset)
        ├── article (set, imports article fieldset)
        │     └── article (bard field)
        │           ├── buttons (set, imports buttons fieldset)
        │           │     └── button (replicator → button set)
        │           ├── pull_quote (set)
        │           ├── table (set)
        │           ├── image (set)
        │           └── video (set)
        ├── form (set, imports form fieldset)
        └── cards (set, imports cards fieldset)
              └── cards (replicator → card set)
```

## Step 2: Map Sets to View Partials

Statamic uses naming conventions to map set handles to view partials. Identify the correct view directory by checking how the Replicator/Bard field is rendered in the parent template.

Common patterns:
- **Replicator sets**: `resources/views/{directory}/_{set_handle}.antlers.html` or `resources/views/{directory}/{set_handle}.blade.php`
- **Bard sets**: Often rendered inside a parent partial, with each set type having its own partial in a components directory
- **Grid rows**: Rendered inline within the parent template's loop

Search for existing view partials:
```
# Find all Antlers partials
find resources/views -name "*.antlers.html" | sort

# Find all Blade partials
find resources/views -name "*.blade.php" | sort
```

Map each set handle from Step 1 to its corresponding view partial. The set handle typically matches the partial filename (e.g., set `article` → `_article.antlers.html`).

## Step 3: Check Existing Annotations

Search for templates that already have `visual_edit` tags:

```
# Antlers
grep -rn "visual_edit" resources/views/ --include="*.antlers.html"

# Blade
grep -rn "visual_edit" resources/views/ --include="*.blade.php"
```

## Step 4: Identify Field Targeting Candidates

Field targeting (`{{ visual_edit field="handle" }}`) is for **fixed blueprint fields** that are NOT inside a Replicator/Bard/Grid — things like:
- `title`, `slug` — always present
- SEO fields (`seo_title`, `seo_description`)
- Fields inside groups (use dot notation: `page_info.author`)
- Global set fields

Scan blueprints for top-level fields and group fields:
```
# Look for top-level text, textarea, and group fields in blueprints
grep -A2 "handle:" resources/blueprints/collections/**/*.yaml resources/blueprints/globals/*.yaml
```

Field targeting is optional and cosmetic — it improves the editing experience for authors but isn't required for the addon to work. Prioritize set targeting first.

**Important**: Field-targeted elements should typically be wrapped in a Live Preview conditional so they don't render empty wrappers on the frontend:
```antlers
{{ if live_preview }}
  <div {{ visual_edit field="title" outline-inside="true" }}>{{ title }}</div>
{{ /if }}
```

## Step 5: Output the Checklist

Present findings as a structured table:

| Template Path | Field Type | Set Handle | Tag Status | Recommended Action |
|---|---|---|---|---|
| `views/page_builder/_article.antlers.html` | replicator set | `article` | Missing | Add `{{ visual_edit }}` to outermost element |
| `views/components/_text.antlers.html` | bard set | `text` | Present | No action needed |
| `views/layout/_meta.antlers.html` | field | `title` | Missing | Add `{{ visual_edit field="title" }}` |

## Step 6: Remind About Post-Setup Tasks

After annotations are added:
1. **Re-save all existing entries** so the addon stamps `_visual_id` UUIDs on every Replicator/Bard/Grid item. New entries get UUIDs automatically.
2. **Publish addon assets** if not done already: `php artisan vendor:publish --provider="MarioHamann\StatamicVisualEditor\ServiceProvider" --force`
3. **Verify in Live Preview** — open an entry's Live Preview and click annotated elements to confirm the CP scrolls to the correct field.

## Notes

- The addon has zero production footprint — `{{ visual_edit }}` outputs nothing outside Live Preview.
- Set targeting requires no blueprint changes — the addon auto-injects a hidden `_visual_id` field.
- For Antlers templates, activate the `visual-editor-antlers` skill for implementation details.
- For Blade templates, activate the `visual-editor-blade` skill for implementation details.
