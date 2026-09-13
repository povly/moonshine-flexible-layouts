---
name: flexible-layouts-dev
description: Development conventions and package internals for povly/moonshine-flexible-layouts — a MoonShine 4 flexible content blocks field (tab UI, unlimited nesting, AJAX block management). Use when modifying PHP field logic, Block/Cast/Controller code, Alpine.js field.js, _fl-* CSS, the Vite/Bun build pipeline, translations, or debugging block nesting, limits, and AJAX issues.
argument-hint: "[area or task]"
compatibility: Requires PHP 8.2+, Laravel 12+, MoonShine 4.x, Bun
metadata:
  author: povly
  version: "1.0"
  category: project-internals
---

# MoonShine Flexible Layouts — Development Skill

Internal conventions for this package. Read before touching PHP, JS, CSS, or build config.

## Package Map

```
src/
  Blocks/Block.php                    # Domain value object (NOT a Field). Snake-normalised name().
  Casts/FlexibleCast.php              # Eloquent cast: JSON <-> array, depth limit 64.
  Collections/BlockCollection.php     # Collection<int, BlockContract> + findByName().
  Contracts/BlockContract.php         # Interface implemented by Block.
  Fields/FlexibleLayouts.php          # The MoonShine field (presentation + apply pipeline).
  Http/Controllers/BlockController.php# AJAX store() — renders one block, enforces limits.
  Providers/FlexibleLayoutsServiceProvider.php
resources/
  js/field.js                         # Alpine.data('flexibleLayouts') — single JS entry.
  css/field.css                       # All styles, _fl-* namespace only.
  views/field.blade.php               # Field markup + picker modal (x-teleport).
routes/moonshine.php                  # POST {prefix}/store/{pageUri}/{resourceUri?} via Route::moonshine().
config/flexible-layouts.php           # route_prefix, logging (env FLEXIBLE_LAYOUTS_LOGGING, default app.debug).
lang/{en,ru}/{messages,refs}.php      # UI labels. refs.php = example transKey() file.
dist/                                 # Build output, published to public/vendor/flexible-layouts/.
```

## Build Pipeline

```bash
bun install          # JS deps
bun run build        # vite build -> dist/ (minified, IIFE-wrapped field.js)
bun run dev          # vite build --watch
```

- Vite wraps `dist/field.js` in an IIFE via the `iifeWrapPlugin` in `vite.config.js` — top-level code must never leak globals.
- `import.meta.env.DEV` gates dev-only `console.warn` calls; they are stripped in production builds.
- Host apps receive assets via `php artisan vendor:publish --tag=flexible-layouts` (config: `-config`, lang: `-lang`).
- After changing PHP/views you don't need a build; after changing resources/js or resources/css you MUST rebuild and republish.

## Critical Invariants — Do NOT Break

### 1. `_fl-*` namespace
Every CSS class and DOM hook uses the `_fl-` prefix (`_fl-field`, `_fl-tabs`, `_fl-tab`, `_fl-blocks`, `_fl-block`, `_fl-type`, `_fl-picker-*`). JS queries them with `:scope >` direct-child selectors. Never introduce unprefixed classes — they may collide with MoonShine core CSS.

### 2. Unknown `_type` passthrough semantics
Blocks removed from code but still present in stored JSON must survive round-trips:
- `getFilledBlocks()` **skips** unknown types in UI rendering (logs warning when logging enabled).
- `resolveOnApply()` **preserves** entries with a string `_type` as opaque dicts (data loss guard during block-type migrations).
- Entries **without** `_type` are corrupted data — dropped.
- `resolveCallback()` (before/after apply, destroy) **skips** unknown types — no field side-effects for missing field instances.

### 3. Icon XSS trust boundary
`Block::$icon` is DEVELOPER-supplied (icon name, emoji, or raw SVG) rendered raw via Blade `{!! !!}` and Alpine `x-html`. NEVER pass user-controlled data to `icon:`. If block types ever become DB-driven, sanitise first (DOMPurify / `Element::setHTML()`).

### 4. Operation lock in field.js
`_isMutating` guards `add()`/`remove()`/`duplicate()` against double-click and drag-during-pending-AJAX. Reset happens in `afterResponse`, `errorCallback`, AND a 30 s safety timeout. Any new mutating action must respect the lock.

### 5. reindex / flPath contract
- `MoonShine.iterable.reindex()` + `data-row-key` attributes keep form field names correct at any nesting depth — always reindex after DOM insert/remove/reorder.
- `flPath` (dot-path, e.g. `blocks.section.refs`) addresses nested fields; `BlockController::buildNameFromPath()` turns it into `blocks[${index0}][blocks][${index1}]...` templates. `${indexN}` placeholders are REQUIRED — do not replace with concrete numbers.
- Tab <-> block pairing uses `data-fl-uid` (generated `fl-<column>-<time>-<rand>`); `syncBlockOrder()` reorders blocks to match tab order via DocumentFragment.

### 6. Cast depth limit
`FlexibleCast::JSON_DEPTH_LIMIT = 64` — recursion-based DoS protection. Decode/encode failures ALWAYS log at `Log::error` level, not gated by the config flag.

### 7. Logging gating
Diagnostic warnings (`[FlexibleLayouts]`, `[FlexibleCast]` prefixes) are gated by `config('flexible-layouts.logging')`; data-corruption errors are not. Keep this distinction.

### 8. z-index layering
Picker overlay sits on the MoonShine core modal layer `var(--z-modal, 1100)`. Do not raise it above the core scale (`--z-menu: 1200`, `--z-toast: 1300`) — that reintroduces the historical 9999 bug.

## PHP Conventions

- `declare(strict_types=1)` in every file.
- `final` classes everywhere; `Block` is a value object, not a Field.
- Fluent setters return `self`; private promoted typed properties.
- Guard clauses and early returns; explicit `instanceof` checks over duck typing.
- No exceptions thrown for unknown block types — passthrough semantics above.
- Limit enforcement: `BlockController::resolveBlockCount()` counts from the persisted model (authoritative); falls back to request-supplied `counts` for unsaved records.

## JS Conventions

- Vanilla ES2017 + Alpine.js only (registered on `alpine:init` as `Alpine.data('flexibleLayouts', ...)`). No new frameworks/deps without strong justification.
- Use MoonShine globals (`MoonShine.request`, `MoonShine.iterable`) — do not reimplement AJAX or sorting.
- `const t = this` capture for callbacks (match the established file style).
- String concatenation (`+`) is the established pattern in field.js — do not mass-rewrite to template literals.

## Verification

Unit tests cover the domain layer (Block, BlockCollection, FlexibleCast): run `vendor/bin/phpunit` (PHPUnit + orchestra/testbench, MoonShine provider booted — see `tests/Unit/TestCase.php`). Before finishing any change:
1. `vendor/bin/phpunit` green.
2. `php -l` on changed PHP files.
3. `bun run build` must succeed with zero warnings from the IIFE plugin.
4. Functional check in a host Laravel app: add/remove/reorder/duplicate blocks, nested layouts, limit enforcement, save + reload round-trip (unknown `_type` preserved).

Deep internals (data flow, method reference, `_type` matrix): [references/INTERNALS.md](references/INTERNALS.md)
