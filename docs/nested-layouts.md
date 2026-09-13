[← Типы блоков](blocks.md) · [К README](../README.md) · [Формат данных →](data-format.md)

# Вложенные макеты

Неограниченная вложенность, мультиколоночные раскладки и персональные подписи поля.

## Вложенные FlexibleLayouts

`FlexibleLayouts` можно положить внутрь полей любого блока. Вложенные макеты поддерживают те же возможности (drag, add, remove, reindex):

```php
->block('section', 'Секция', [
    Text::make('Заголовок', 'title'),
    FlexibleLayouts::make('Блоки', 'blocks')
        ->block('button', 'Кнопка', [
            Text::make('Текст', 'text'),
            Text::make('Ссылка', 'link'),
        ])
        ->block('form', 'Форма', [
            Select::make('Тип', 'form_type')->options([
                'hotel' => 'Отель',
                'tickets' => 'Билеты',
            ]),
            Text::make('URL перенаправления', 'url'),
        ]),
])
```

Корректное именование полей формы на любой глубине обеспечивает нативный `MoonShine.iterable.reindex()` — поле лишь проставляет `data-row-key` и dot-path (`flPath`) для адресации вложенных макетов в AJAX-запросах.

## Мультиколоночность

Внутри блоков работают нативные компоненты `Flex` и `Column`:

```php
->block('hero', 'Hero', [
    Flex::make([
        Column::make([
            Text::make('Заголовок', 'title'),
        ])->columnSpan(6),

        Column::make([
            Text::make('Подзаголовок', 'subtitle'),
        ])->columnSpan(6),
    ]),
])
```

`columnSpan(6)` — половина ширины (сетка из 12 колонок). `columnSpan(4)` — три колонки, `columnSpan(3)` — четыре, и т.д.

## Персональные подписи: `transKey()`

По умолчанию все поля `FlexibleLayouts` берут подписи UI из `messages.php`. Метод `transKey()` даёт конкретному полю свой набор лейблов — полезно для вложенных макетов с другим типом контента:

```php
// Верхний уровень — подписи по умолчанию («Добавить блок», «Поиск блоков...»)
FlexibleLayouts::make('Блоки', 'blocks')
    ->block('hero', 'Hero', [...])

// Вложенный — свои подписи из отдельного файла переводов
->block('section', 'Секция', [
    Text::make('Заголовок', 'title'),

    FlexibleLayouts::make('Справочники', 'refs')
        ->transKey('refs')   // берёт flexible-layouts::refs.*
        ->block('reference', 'Справочник', [
            Text::make('Название', 'title'),
        ]),
])
```

Файл `lang/vendor/flexible-layouts/ru/refs.php`:

```php
return [
    'add_block'       => 'Добавить справочник',
    'search_blocks'   => 'Поиск справочников...',
    'no_blocks_found' => 'Справочники не найдены',
    'all_categories'  => 'Все',
];
```

Порядок разрешения: `flexible-layouts::refs.{key}` → при отсутствии → `flexible-layouts::messages.{key}`. Пример файла идёт в комплекте: `lang/en/refs.php` и `lang/ru/refs.php`.

## See Also

- [Типы блоков](blocks.md) — параметры `block()`, пикер, лимиты
- [Переводы](translations.md) — ключи и добавление языков
- [Конфигурация](configuration.md) — префикс роута, логирование
