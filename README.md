# MoonShine Flexible Layouts

Flexible content blocks field for MoonShine 4. Build page builder-style layouts with unlimited nesting, drag-to-reorder, and AJAX-powered block management.

## Features

- **Tab-based UI** — blocks displayed as reorderable tabs with optional icons
- **Unlimited nesting** — Flexible Layouts inside block fields just work
- **Drag to reorder** — powered by SortableJS via MoonShine's native `iterable` API
- **AJAX add/remove** — blocks are fetched on-demand from the server, no page reload
- **Limit per block type** — restrict how many instances of each block can be added
- **Native reindex** — reuses `MoonShine.iterable.reindex()` for correct form field naming at any depth
- **Block picker modal** — Gutenberg-style modal with search and category grouping
- **Layout components** — use MoonShine `Flex`, `Column`, and other layout components inside blocks for multi-column field layouts
- **Localization** — ships with English and Russian translations, extensible to any language

## Requirements

- PHP 8.2+
- Laravel 12+
- MoonShine 4+

## Installation

```bash
composer require povly/moonshine-flexible-layouts
```

Publish assets:

```bash
php artisan vendor:publish --tag=flexible-layouts
```

## Quick Start

```php
use Povly\FlexibleLayouts\Fields\FlexibleLayouts;

FlexibleLayouts::make('Content', 'content')
    ->block('hero', 'Hero', [
        Text::make('Title', 'title'),
        Image::make('Background', 'image'),
    ])
    ->block('text', 'Text Block', [
        Textarea::make('Body', 'body'),
    ])
    ->block('gallery', 'Gallery', [
        Json::make('Images', 'images'),
    ]);
```

## Usage

### Block Registration

The `block()` method accepts:

| Parameter | Type | Description |
|-----------|------|-------------|
| `$name` | `string` | Snake_case key stored in JSON as `_type` |
| `$title` | `string` | Human-readable label shown in tabs and picker |
| `$fields` | `iterable` | MoonShine fields (can include nested FlexibleLayouts) |
| `$limit` | `?int` | Max instances of this block type (default: unlimited) |
| `$category` | `?string` | Grouping label in the picker modal |
| `$description` | `?string` | Short description shown in the picker card |
| `$icon` | `?string` | MoonShine icon name, emoji, or SVG string |

Basic usage:

```php
->block('cta', 'Call to Action', [
    Text::make('Button Text', 'label'),
    Text::make('Link', 'url'),
], limit: 1)
```

With category, description, and icon (use named args):

```php
// MoonShine icon names (301+ Heroicons built-in)
->block('hero', 'Hero', [
    Text::make('Title', 'title'),
    Image::make('Background', 'image'),
], category: 'Header', description: 'Large banner with background', icon: 'photo')

->block('gallery', 'Gallery', [
    Json::make('Images', 'images'),
], category: 'Media', description: 'Image grid gallery', icon: 'rectangle-stack')

->block('wysiwyg', 'Text Editor', [
    Textarea::make('Body', 'body'),
], category: 'Content', description: 'Rich text content', icon: 'document-text')

// Emoji also works
->block('cta', 'Call to Action', [
    Text::make('Text', 'label'),
    Text::make('Link', 'url'),
], limit: 1, icon: '🔗')
```

#### Icons

The `icon` parameter accepts three types:

| Type | Example | Renders as |
|------|---------|------------|
| MoonShine icon name | `'photo'` | SVG from `moonshine::icons.photo` |
| Emoji | `'📷'` | Raw text |
| Raw SVG | `'<svg>...</svg>'` | Pass-through HTML |

MoonShine includes **301 Heroicons** (stroke-based, 24×24). Browse them in `vendor/moonshine/moonshine/src/UI/resources/views/icons/`. Common examples: `users`, `photo`, `document-text`, `rectangle-stack`, `bars-3`, `cog-6-tooth`, `star`, `bolt`, `globe-alt`, `bookmark`.

Icons appear in both the tab label and the picker card.

Blocks without a category are shown in an ungrouped section. When all blocks lack categories, the category pills row is hidden automatically.

### Block Picker Modal

Clicking **Add block** opens a Gutenberg-style modal with:

- **Search** — type to filter by title, description, or block name
- **Category tabs** — click to filter by category (only shown when 2+ categories exist)
- **Grid of cards** — icon + title + description per block; click to add

Press `Esc` or click outside the modal to close.

### Nested Flexible Layouts

You can put a `FlexibleLayouts` field inside any block. Nested layouts support the same features (drag, add, remove, reindex):

```php
->block('section', 'Section', [
    Text::make('Title', 'title'),
    FlexibleLayouts::make('Blocks', 'blocks')
        ->block('button', 'Button', [
            Text::make('Text', 'text'),
            Text::make('Link', 'link'),
        ])
        ->block('form', 'Form', [
            Select::make('Type', 'form_type')->options([
                'hotel' => 'Hotel',
                'tickets' => 'Tickets',
            ]),
            Text::make('Redirect URL', 'url'),
        ]),
])
```

### Disabling Features

```php
->disableAdd()     // hide the "Add block" button
->disableRemove()  // hide the remove button on blocks
->disableSort()    // disable drag-to-reorder
```

### Custom Buttons

```php
->addButton(ActionButton::make('Add')->primary())
->removeButton(ActionButton::make('Delete')->icon('trash')->error())
```

### Custom Labels per Field

By default, all Flexible Layouts fields share the same UI labels from `messages.php`. Use `->transKey()` to give a specific field its own set of labels — useful for nested layouts with different content types:

```php
// Top-level — default labels ("Add block", "Search blocks...")
FlexibleLayouts::make('Blocks', 'blocks')
    ->block('hero', 'Hero', [...])

// Nested — custom labels via separate translation file
->block('section', 'Section', [
    Text::make('Title', 'title'),

    FlexibleLayouts::make('Refs', 'refs')
        ->transKey('refs')   // uses flexible-layouts::refs.* translations
        ->block('reference', 'Справочник', [
            Text::make('Title', 'title'),
        ]),
])
```

Create `lang/vendor/flexible-layouts/ru/refs.php`:

```php
return [
    'add_block'       => 'Добавить справочник',
    'search_blocks'   => 'Поиск справочников...',
    'no_blocks_found' => 'Справочники не найдены',
    'all_categories'  => 'Все',
];
```

Labels resolve in order: `flexible-layouts::refs.{key}` -> if missing -> `flexible-layouts::messages.{key}`. An example file ships in `lang/en/refs.php` and `lang/ru/refs.php`.

### Multi-column Layouts

Use MoonShine's native `Flex` and `Column` components inside blocks for side-by-side fields:

```php
->block('hero', 'Hero', [
    Flex::make([
        Column::make([
            Text::make('Title', 'title'),
        ])->columnSpan(6),

        Column::make([
            Text::make('Subtitle', 'subtitle'),
        ])->columnSpan(6),
    ]),
])
```

`columnSpan(6)` = half width (out of 12-column grid). Use `columnSpan(4)` for three columns, `columnSpan(3)` for four, etc.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=flexible-layouts-config
```

```php
// config/flexible-layouts.php
return [
    'route_prefix' => 'flexible-layouts',
    'middleware' => ['web'],
];
```

## Translations

The package ships with English and Russian translations. The UI adapts to the app locale automatically.

Available keys:

| Key | EN | RU |
|-----|----|----|
| `add_block` | Add block | Добавить блок |
| `search_blocks` | Search blocks... | Поиск блоков... |
| `no_blocks_found` | No blocks found | Блоки не найдены |
| `all_categories` | All | Все |

Publish translations to customize or add new languages:

```bash
php artisan vendor:publish --tag=flexible-layouts-lang
```

This creates `lang/vendor/flexible-layouts/` with `en/` and `ru/` directories. To add a language, copy any file to a new locale folder:

```bash
# Example: add German
cp lang/vendor/flexible-layouts/en/messages.php lang/vendor/flexible-layouts/de/messages.php
```

```php
// lang/vendor/flexible-layouts/de/messages.php
return [
    'add_block' => 'Block hinzufügen',
    'search_blocks' => 'Blöcke suchen...',
    'no_blocks_found' => 'Keine Blöcke gefunden',
    'all_categories' => 'Alle',
];
```

## Data Format

The field stores data as a flat JSON array. Each entry has a `_type` key and the block's field values:

```json
[
  {
    "_type": "hero",
    "title": "Welcome",
    "image": "hero-bg.jpg"
  },
  {
    "_type": "section",
    "title": "About Us",
    "blocks": [
      {
        "_type": "button",
        "text": "Learn More",
        "link": "/about"
      }
    ]
  }
]
```

## Cast

Register the cast on your model:

```php
protected function casts(): array
{
    return [
        'content' => \Povly\FlexibleLayouts\Casts\FlexibleCast::class,
    ];
}
```

Or use `$casts` property if preferred.

## Development

```bash
# Install JS dependencies
bun install

# Build assets
bun run build

# Watch mode
bun run dev
```

Assets are built to `dist/` and published to `public/vendor/flexible-layouts/`.
