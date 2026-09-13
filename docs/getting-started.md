[К README](../README.md) · [Типы блоков →](blocks.md)

# Начало работы

Установка и первый запуск поля `FlexibleLayouts` в Laravel-приложении с MoonShine 4.

## Требования

- PHP 8.2+
- Laravel 12+
- MoonShine 4+

## Установка

```bash
composer require povly/moonshine-flexible-layouts
```

Опубликуйте собранные ассеты (JS/CSS поля):

```bash
php artisan vendor:publish --tag=flexible-layouts
```

Ассеты публикуются в `public/vendor/flexible-layouts/`. Поле подключает их само через `AssetManager` MoonShine — ничего дополнительно регистрировать не нужно.

Опционально можно опубликовать конфиг и переводы:

```bash
php artisan vendor:publish --tag=flexible-layouts-config
php artisan vendor:publish --tag=flexible-layouts-lang
```

## Каст модели

Поле хранит данные как JSON-массив. Зарегистрируйте каст на атрибуте модели:

```php
use Povly\FlexibleLayouts\Casts\FlexibleCast;

protected function casts(): array
{
    return [
        'content' => FlexibleCast::class,
    ];
}
```

(Свойство `$casts` тоже работает.) Каст декодирует JSON при чтении и кодирует при сохранении, ограничивая глубину вложенности 64 уровнями.

## Первый макет

Добавьте поле в форму ресурса MoonShine:

```php
use Povly\FlexibleLayouts\Fields\FlexibleLayouts;

FlexibleLayouts::make('Контент', 'content')
    ->block('hero', 'Hero', [
        Text::make('Заголовок', 'title'),
        Image::make('Фон', 'image'),
    ])
    ->block('text', 'Текстовый блок', [
        Textarea::make('Текст', 'body'),
    ]);
```

## Проверка

1. Откройте форму ресурса — поле отрисуется как панель табов с кнопкой «Добавить блок».
2. Нажмите «Добавить блок» — откроется пикер; выберите блок — вкладка появится без перезагрузки (AJAX).
3. Сохраните запись — в атрибуте модели окажется JSON вида `[{"_type": "hero", ...}]`.
4. Откройте форму заново — блоки восстановятся из сохранённых данных.

## See Also

- [Типы блоков](blocks.md) — параметры `block()`, иконки, лимиты, пикер
- [Формат данных](data-format.md) — JSON, `_type`, каст
- [Конфигурация](configuration.md) — префикс роута, логирование
