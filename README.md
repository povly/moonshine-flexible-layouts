# MoonShine Flexible Layouts

A flexible content field for [MoonShine](https://moonshine-laravel.com) admin panel — ACF-style repeater with **unlimited nesting support**.

Define block types, let editors compose pages by adding, reordering, and nesting blocks. All data stored as a single JSON column.

## Requirements

- PHP 8.2+
- MoonShine 4.0+

## Install

```shell
composer require povly/moonshine-flexible-layouts
```

Publish assets:

```shell
php artisan vendor:publish --tag=flexible-layouts
```

## Setup

Add the cast to your model:

```php
use Povly\FlexibleLayouts\Casts\FlexibleCast;

class Article extends Model
{
    protected function casts(): array
    {
        return [
            'content' => FlexibleCast::class,
        ];
    }
}
```

Add the field to your MoonShine resource:

```php
use Povly\FlexibleLayouts\Fields\FlexibleLayouts;

FlexibleLayouts::make('Content', 'content')
    ->block('hero', 'Hero Section', [
        Text::make('Title', 'title'),
        Image::make('Background', 'background'),
    ])
    ->block('text', 'Text Block', [
        Textarea::make('Body', 'body'),
    ])
    ->block('cta', 'Call to Action', [
        Text::make('Heading', 'heading'),
        Text::make('Button Text', 'button_text'),
        Url::make('Button Link', 'button_link'),
    ], limit: 1);
```

## Nested Layouts

Nesting works by placing another `FlexibleLayouts` field inside a block's fields. Recursion is handled automatically — no depth limit.

```php
FlexibleLayouts::make('Page Content', 'content')
    ->block('section', 'Section', [
        Text::make('Title', 'title'),
        FlexibleLayouts::make('Blocks', 'blocks')
            ->block('text', 'Text', [
                Textarea::make('Body', 'body'),
            ])
            ->block('quote', 'Quote', [
                Textarea::make('Quote', 'quote'),
                Text::make('Author', 'author'),
            ])
            ->block('image', 'Image', [
                Image::make('Photo', 'photo'),
                Text::make('Caption', 'caption'),
            ]),
    ])
    ->block('hero', 'Hero', [
        Text::make('Title', 'title'),
        Image::make('Background', 'background'),
    ]);
```

## JSON Structure

Data is stored as a flat JSON array. Each item has a `_type` key identifying the block type, followed by flat field values:

```json
[
    {"_type": "hero", "title": "Welcome", "background": "hero.jpg"},
    {"_type": "section", "title": "About", "blocks": [
        {"_type": "text", "body": "Lorem ipsum dolor sit amet."},
        {"_type": "quote", "quote": "Stay hungry, stay foolish.", "author": "Steve Jobs"}
    ]}
]
```

Nested layouts produce nested arrays under their column name (`"blocks"` in the example above).

## API Reference

### `FlexibleLayouts::make(string $label, string $column)`

Create the field.

### `->block(string $name, string $title, iterable $fields, ?int $limit = null)`

Register a block type.

| Parameter | Description |
|-----------|-------------|
| `$name` | Snake-case key stored in JSON as `_type` |
| `$title` | Human-readable label shown in the admin UI |
| `$fields` | Array of MoonShine fields (can include nested `FlexibleLayouts`) |
| `$limit` | Maximum instances of this block type (optional) |

### UI Controls

```php
FlexibleLayouts::make('Content', 'content')
    ->block('hero', 'Hero', [...])
    ->disableAdd()      // Hide the "Add block" button
    ->disableRemove()   // Hide the remove button on blocks
    ->disableSort()     // Disable drag-and-drop reordering
    ->addButton(        // Custom add button
        ActionButton::make('New block')->icon('plus')->primary()
    )
    ->removeButton(     // Custom remove button
        ActionButton::make('')->icon('trash')->error()
    )
    ->dropdown(         // Custom dropdown
        Dropdown::make()->searchable()
    );
```

## Rendering on Frontend

```blade
@foreach($article->content as $block)
    @switch($block['_type'])
        @case('hero')
            <x-hero :title="$block['title']" :background="$block['background']" />
            @break
        @case('text')
            <div class="prose">{!! $block['body'] !!}</div>
            @break
        @case('section')
            <section>
                <h2>{{ $block['title'] }}</h2>
                @foreach($block['blocks'] as $inner)
                    {{-- render nested blocks --}}
                @endforeach
            </section>
            @break
    @endswitch
@endforeach
```

## License

MIT
