[← Конфигурация](configuration.md) · [К README](../README.md) · [Разработка →](development.md)

# Переводы

Пакет поставляется с английской и русской локализацией. UI автоматически подстраивается под локаль приложения.

## Ключи

| Ключ | EN | RU |
|------|----|----|
| `add_block` | Add block | Добавить блок |
| `search_blocks` | Search blocks... | Поиск блоков... |
| `no_blocks_found` | No blocks found | Блоки не найдены |
| `all_categories` | All | Все |

## Публикация

```bash
php artisan vendor:publish --tag=flexible-layouts-lang
```

Создаст `lang/vendor/flexible-layouts/` с каталогами `en/` и `ru/` (файлы `messages.php` и `refs.php`).

## Добавление языка

Скопируйте любой файл в новый каталог локали и переведите значения:

```bash
# Пример: немецкий
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

## Персональные наборы лейблов

Метод `transKey()` переключает поле на другой файл переводов (`flexible-layouts::refs.*`) с фолбэком на `messages.*` — подробно во [Вложенных макетах](nested-layouts.md#персональные-подписи-transkey).

## See Also

- [Вложенные макеты](nested-layouts.md) — сценарий использования `transKey()`
- [Начало работы](getting-started.md) — публикация ассетов и конфига
- [Типы блоков](blocks.md) — подписи в пикере и табах
