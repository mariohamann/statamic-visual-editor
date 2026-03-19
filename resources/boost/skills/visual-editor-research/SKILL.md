---
name: visual-editor-research
description: "Audits a Statamic project to find where Visual Editor annotations should be added. Activates when setting up click-to-edit, auditing templates for visual_edit tags, planning Visual Editor integration, or when the user mentions visual editor setup, annotate templates, click-to-edit audit, or live preview annotations."
---

# Visual Editor Research

## When to Apply

Activate this skill when setting up click-to-edit annotations in a Statamic project for the first time, auditing coverage, or planning where tags should be added.

## Approach

Annotations are cheap — the UUID is already stamped on every set, so adding the tag is always worthwhile. Aim for complete coverage across all rendered Replicator, Bard, and Grid items. Even sets rendered inline in a larger template (without a dedicated partial) can and should be annotated by placing the tag on the appropriate element in the loop.

## Step 1: Find Replicator, Bard, and Grid Fields

Scan blueprints and fieldsets for the field types that need **set targeting**:

```
grep -rn "type: replicator\|type: bard\|type: grid" resources/blueprints/ resources/fieldsets/
```

For each match, note the field handle and the set handles defined within it. Follow `import:` references to trace the full hierarchy — Statamic fieldsets nest via `import: fieldset_name`.

## Step 2: Map Sets to View Partials

Check how the Replicator/Bard field is rendered in its parent template to find the corresponding partials.

Common conventions:
- **Replicator sets**: `resources/views/{dir}/_{set_handle}.antlers.html` or `.blade.php`
- **Bard sets**: Often a partial per set type in a components directory
- **Grid rows**: Rendered inline in the loop — add the tag directly there

```
find resources/views -name "*.antlers.html" -o -name "*.blade.php" | sort
```

## Step 3: Check Existing Annotations

```
grep -rn "visual_edit" resources/views/
```

## Step 4: Identify Field Targeting Candidates

Field targeting is for **fixed blueprint fields** outside Replicator/Bard/Grid (e.g., `title`, `slug`, SEO fields, group sub-fields). It's optional and purely cosmetic — improves author experience but isn't required.

The most common case is annotating an element that already exists in the markup. For fields that have no visible element on the page (e.g., SEO metadata), you can optionally surface them as Live Preview-only placeholders so authors can click to edit — but this is a progressive enhancement, not a requirement.

## Step 5: Summarize Findings

Present a prioritized list of annotation points: template path, set/field handle, and whether the tag is already present. Note which sets are rendered inline vs. in a dedicated partial — both should be annotated, inline ones just need the tag placed on the right element in the loop.
