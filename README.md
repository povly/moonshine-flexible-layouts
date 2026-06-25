# MoonShine Flexible Layouts

Flexible content blocks field for MoonShine 4. Build page builder-style layouts with unlimited nesting, drag-to-reorder, and AJAX-powered block management.

## Features

- **Tab-based UI** — blocks displayed as reorderable tabs
- **Unlimited nesting** — Flexible Layouts inside block fields just work
- **Drag to reorder** — powered by SortableJS via MoonShine's native `iterable` API
- **AJAX add/remove** — blocks are fetched on-demand from the server, no page reload
- **Limit per block type** — restrict how many instances of each block can be added
- **Native reindex** — reuses `MoonShine.iterable.reindex()` for correct form field naming at any depth
- **Searchable dropdown** — optional search for block type selection
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

Each block has a machine name, a human title, and a list of MoonShine fields:

```php
->block('cta', 'Call to Action', [
    Text::make('Button Text', 'label'),
    Text::make('Link', 'url'),
], limit: 1)
```

The `name` is stored in JSON as `_type`. The `limit` parameter restricts how many instances of this block can be added.

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

### Searchable Block Dropdown

```php
->searchable()     // adds search input to the block type dropdown
```

### Custom Buttons

```php
->addButton(ActionButton::make('Add')->primary())
->removeButton(ActionButton::make('Delete')->icon('trash')->error())
->dropdown(Dropdown::make()->searchable()->placement('bottom-start'))
```

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
npm install

# Build assets
npm run build

# Watch mode
npm run dev
```

Assets are built to `dist/` and published to `public/vendor/flexible-layouts/`.
