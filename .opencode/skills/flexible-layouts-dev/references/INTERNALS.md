# Internals — flexible-layouts-dev

Deep reference for `povly/moonshine-flexible-layouts`. Complements SKILL.md.

## Data Flow: Add Block (AJAX)

```
Picker card click (field.js add(name))
  ├─ _isMutating guard, 30s lockTimeout armed
  ├─ counts[] collected from top-level ._fl-type inputs
  ├─ MoonShine.request POST {prefix}/store/{pageUri}/{resourceUri?}
  │    body: { field, path (flPath), name, counts }
  │
  │  BlockController::store()
  │    ├─ getField($request)            # walks dot-path: column.blockName.fieldColumn...
  │    │    └─ buildNameFromPath()      # name="blocks[${index0}][content]..."
  │    ├─ $field->formName($resource->getUriKey())  # x-id scope fix for AJAX pipeline
  │    ├─ getFilledBlocks()->findByName()           # clone + fill fields
  │    ├─ resolveBlockCount()          # model attribute (authoritative) or request counts
  │    │    └─ limit check → toast error if exceeded
  │    └─ renderTabContent() → JsonResponse { blockHtml, blockTitle }
  │
  └─ afterResponse:
       ├─ wrapper div._fl-block (data-row-key, data-fl-uid, data-correct-type)
       ├─ tab button._fl-tab (grip + icon + label)
       ├─ switchTab + resolveReindex
       └─ dispatch CustomEvent 'flexible-layouts:block-added' { name, column }
```

## Data Flow: Save (apply pipeline)

```
Form submit
  ├─ resolveBeforeApply()  ─┐
  ├─ resolveOnApply()       │ each iterates request values by index,
  ├─ resolveAfterApply()    │ resolves block by _type, appends request key
  └─ resolveAfterDestroy() ─┘ prefix "{column}.{index}", runs per-field callbacks
       (unknown _type entries skipped in callbacks; preserved in resolveOnApply)
```

`resolveOnApply` per entry:
1. No string `_type` → drop (`[]`).
2. Unknown `_type` → preserve verbatim + warn (migration guard).
3. Known `_type` → strip `_type`, run each field's `apply()` with `appendRequestKeyPrefix()`, re-merge `['_type' => name] + applyValues`.

Result stored via `FlexibleCast` as flat JSON array (`array_values`, depth ≤ 64).

## `_type` Handling Matrix

| Entry state            | getFilledBlocks (render) | resolveOnApply (save) | resolveCallback (before/after/destroy) |
|------------------------|--------------------------|-----------------------|----------------------------------------|
| Known `_type`          | render filled block      | apply each field      | run callbacks                          |
| Unknown string `_type` | skip + warn (gated)      | preserve verbatim + warn (gated) | skip                                  |
| No `_type`             | skip                     | drop                  | skip                                   |
| Non-array entry        | skip                     | drop                  | skip                                   |

## Key Method Reference (Fields/FlexibleLayouts.php)

| Method | Purpose |
|---|---|
| `block(name, title, fields, limit?, category?, description?, icon?)` | Register block type → `Block` value object. |
| `blocks(): BlockCollection` | Registered (empty) blocks. |
| `getFilledBlocks(): BlockCollection` | Clone + `resolveFill` from stored value; prepends `Hidden _type`; propagates `formName`; `prepareReindexNames`. |
| `fillClonedRecursively()` | Recurses into `HasComponentsContract`/`HasFieldsContract` containers (Flex/Column) and fills nested fields. |
| `resolveLabel(key)` | `flexible-layouts::{transKey}.{key}` → fallback `flexible-layouts::messages.{key}`. |
| `resolveIconSvg(icon)` | `null`/`''` → null; starts `<` → raw; `view()->exists("moonshine::icons.{icon}")` → SVG view; else literal (emoji). |
| `getFlPath()/setFlPath()` | Dot-path identity for nested fields; defaults to column. |
| `resolvePreview()` | Renders itself with add/remove/sort disabled + previewMode. |

## Nested Path Addressing

Top-level field: `flPath = column` (e.g. `blocks`).
Nested field inside block `section`: `flPath = blocks.section.refs` — assigned in `getFilledBlocks()` as `{parent}.{blockName}.{childColumn}`.

`buildNameFromPath("a.b.c")` → `a[${index0}][c]`; every further pair adds `[${indexN}]`.
JS-side: `MoonShine.iterable.reindex()` rewrites `name` attributes and `data-row-key` per level.

## Translations

- Namespace `flexible-layouts::` → `lang/<locale>/`.
- `messages.php` — default labels: `add_block`, `search_blocks`, `no_blocks_found`, `all_categories`.
- `transKey('refs')` on a field instance switches label lookup to `flexible-layouts::refs.*` with fallback to `messages.*`. Any new UI label must be added to ALL shipped locales (en, ru) and to the README key table.

## Known Edges

- `restoreTypeValues()` (field.js init) repairs `_type` input vs `data-correct-type` mismatch after failed reindexes — logs `[FL FIX]`.
- AJAX-rendered fields need `formName` set manually (FormBuilder pipeline is bypassed) — done in `BlockController::store()`.
- SortableJS cleanup relies on full page loads (MoonShine default); Turbolinks-style navigation would need `Sortable.get().destroy()`.
- `Route::moonshine()` handles auth/web/CSRF middleware — the config deliberately has no `middleware` key.
