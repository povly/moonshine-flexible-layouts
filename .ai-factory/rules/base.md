# Базовые правила проекта — конвенции кодовой базы

> Автоматически определено анализом кодовой базы. Правьте по необходимости.

## Конвенции именования

- PHP-классы и файлы: `PascalCase` (`FlexibleLayouts.php`, `BlockCollection.php`)
- Методы/функции: `camelCase`; fluent-сеттеры возвращают `self`
- Свойства: `camelCase`, `private` promoted typed properties
- Интерфейсы: суффикс `Contract` (`BlockContract`)
- Blade-views: lowercase (`field.blade.php`)
- CSS-классы и DOM-хуки: строго префикс `_fl-*` (неймспейс пакета)
- Lang-файлы: `snake_case` (`messages.php`, `refs.php`); ключи переводов `snake_case`; каждый новый UI-лейбл добавляется во все локали (en, ru)
- JS: `camelCase` для методов/свойств Alpine-компонента; «приватные» члены с префиксом `_` (`_isMutating`, `_genUid`, `_directBlocks`)

## Структура модулей

- `src/Blocks/` — доменные объекты блока (`Block` — value object, НЕ MoonShine Field)
- `src/Fields/` — поля MoonShine (presentation-слой)
- `src/Casts/` — Eloquent-касты
- `src/Collections/` — типизированные коллекции
- `src/Contracts/` — интерфейсы
- `src/Http/Controllers/` — AJAX-контроллеры
- `src/Providers/` — сервис-провайдер пакета
- `resources/{js,css,views}/` — ассеты и Blade-шаблон поля
- `routes/`, `config/`, `lang/` — инфраструктура пакета

## Обработка ошибок

- Без исключений для неизвестных `_type` — passthrough-семантика: `getFilledBlocks()` пропускает при рендере, `resolveOnApply()` сохраняет verbatim, записи без `_type` отбрасываются (подробности: скилл `flexible-layouts-dev`)
- `try/catch` с `JSON_THROW_ON_ERROR` в `FlexibleCast`; ошибки кодирования — `Log::error` без гейта конфигом
- Guard clauses и ранние `return`/`continue`; явные `instanceof`-проверки вместо duck typing

## Контроль потока

- Плоский читаемый поток: guard clauses, ранние `return`/`continue`, маленькие именованные хелперы (`resolveBlockCount()`, `resolveCallback()`, `buildNameFromPath()`, `fillClonedRecursively()`). Граничные случаи обрабатываются рано, основной путь остаётся видимым.

## Логирование

- `Log::warning` — диагностика, гейтится `config('flexible-layouts.logging')`
- `Log::error` — только реальная порча данных (`FlexibleCast`), пишется всегда
- Формат сообщений: префикс `[FlexibleLayouts]` / `[FlexibleCast]` + контекст (`column`, `index`, `_type`, `model`, `key`)
- JS: `console.warn`/`console.error` с префиксом `[FlexibleLayouts]`; dev-only предупреждения — только через `import.meta.env.DEV`

## Тестирование

- Автотестов нет. Минимальная проверка изменений: `php -l` на изменённых PHP-файлах, `bun run build`, ручная проверка в хост-приложении (add/remove/reorder, вложенность, лимиты, save/load round-trip)
