[← Формат данных](data-format.md) · [К README](../README.md) · [Переводы →](translations.md)

# Конфигурация

Опубликуйте конфиг, чтобы переопределить значения по умолчанию:

```bash
php artisan vendor:publish --tag=flexible-layouts-config
```

## `config/flexible-layouts.php`

```php
return [
    'route_prefix' => 'flexible-layouts',
    'logging' => env('FLEXIBLE_LAYOUTS_LOGGING', config('app.debug')),
];
```

### `route_prefix`

Префикс URL AJAX-роута добавления блока: `POST {prefix}/store/{pageUri}/{resourceUri?}`.

### `logging`

Управляет diagnostic-логами пакета (по умолчанию — значение `app.debug`):

- пропуск/сохранение неизвестных `_type`;
- подсчёт лимитов по клиентским данным для несохранённых записей.

Для прод-отладки включайте точечно через env:

```bash
FLEXIBLE_LAYOUTS_LOGGING=true
```

Исключение: ошибки кодирования/декодирования JSON в `FlexibleCast` пишутся уровнем `error` **всегда** — они сигнализируют о реальной порче данных.

## Middleware

Роут пакета регистрируется внутри `Route::moonshine()` и наследует auth/web/CSRF middleware ядра MoonShine. Поверх них на роут добавлен rate limit `throttle:60,1` (60 запросов в минуту на пользователя). Ключ `middleware` в конфиге не предусмотрен и не нужен — он был бы проигнорирован.

## See Also

- [Формат данных](data-format.md) — когда именно срабатывают diagnostic-логи
- [Разработка](development.md) — сборка и публикация ассетов
- [Переводы](translations.md) — локализация
