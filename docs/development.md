[← Переводы](translations.md) · [К README](../README.md)

# Разработка

Сборка ассетов, публикация в хост-приложение и отладка пакета.

## Сборка

```bash
# JS-зависимости
bun install

# Сборка ассетов
bun run build

# Режим наблюдения
bun run dev
```

Сборка выполняется Vite 6: `resources/js/field.js` (единая точка входа, импортирует CSS) → `dist/field.js` + `dist/field.css`. Особенности:

- **IIFE-обёртка** — плагин `iifeWrapPlugin` в `vite.config.js` заворачивает бандл в `(() { ... })()`, чтобы top-level код (`Alpine.data`, слушатели) не утекал в глобальную область.
- **lightningcss + autoprefixer** — минификация и автопрефиксы по browserslist-таргетам.
- **ES2017-таргет** — без транспиляции в старые рантаймы.

После правок `resources/js/**` или `resources/css/**` обязательно пересоберите ассеты и опубликуйте их в хост-приложение:

```bash
bun run build
# в хост-приложении:
php artisan vendor:publish --tag=flexible-layouts
```

## Структура пакета

```
src/
  Fields/FlexibleLayouts.php      # Поле MoonShine: рендер + apply-пайплайн
  Blocks/Block.php                # Value object типа блока
  Casts/FlexibleCast.php          # Eloquent-каст JSON (depth limit 64)
  Http/Controllers/BlockController.php  # AJAX: рендер блока, лимиты
  Providers/...ServiceProvider.php
resources/
  js/field.js                     # Alpine-компонент: табы, пикер, AJAX
  css/field.css                   # Стили (_fl-*)
  views/field.blade.php           # Шаблон поля и пикера
```

Архитектурные правила — в `.ai-factory/ARCHITECTURE.md`; внутренние инварианты (`_fl-*` неймспейс, operation lock, passthrough `_type`) — в скилле `.opencode/skills/flexible-layouts-dev/`.

## Отладка

- **Diagnostic-логи** включаются через `FLEXIBLE_LAYOUTS_LOGGING=true` или `config('flexible-layouts.logging')` — см. [Конфигурация](configuration.md). Префиксы сообщений: `[FlexibleLayouts]`, `[FlexibleCast]`.
- **Ошибки JSON** в касте логируются уровнем `error` всегда.
- **Dev-предупреждения JS** (operation lock, `_type` mismatch) выводятся только в dev-сборке (`import.meta.env.DEV`).

## Тесты

Автотестов нет: изменения проверяются в хост-приложении (add/remove/reorder блоков, вложенность, лимиты, save/load round-trip с сохранением неизвестных `_type`).

## See Also

- [Конфигурация](configuration.md) — флаги логирования
- [Формат данных](data-format.md) — семантика passthrough
- [Начало работы](getting-started.md) — публикация ассетов
