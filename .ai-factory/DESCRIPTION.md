# MoonShine Flexible Layouts

## Обзор

Laravel-пакет (`povly/moonshine-flexible-layouts`), добавляющий в MoonShine 4 поле конструктора страниц: гибкие контентные блоки с неограниченной вложенностью, drag-сортировкой и AJAX-управлением. Данные хранятся как плоский JSON-массив в атрибуте модели хост-приложения; каждый элемент содержит ключ `_type` (тип блока) и значения полей блока.

## Основные функции

- **Tab-based UI** — блоки отображаются как переупорядочиваемые табы с опциональными иконками
- **Неограниченная вложенность** — FlexibleLayouts внутри полей блоков работает рекурсивно
- **Drag-to-reorder** — SortableJS через нативный API `MoonShine.iterable`
- **AJAX add/remove** — блоки рендерятся по требованию через `BlockController::store()`, без перезагрузки
- **Лимит на тип блока** — ограничение числа инстансов каждого типа (authoritative-подсчёт по модели)
- **Модальный пикер блоков** — Gutenberg-стиль: поиск, категории, карточки с иконками
- **Passthrough неизвестных `_type`** — удалённые из кода типы блоков переживают save/load (защита от потери данных при миграциях)
- **Локализация** — en/ru из коробки, механизм `transKey()` для отдельных подписей поля
- **Мультиколоночность** — нативные `Flex`/`Column` внутри блоков

## Технологический стек

- **Язык:** PHP 8.2+ (`declare(strict_types=1)`, `final`-классы, typed properties)
- **Фреймворк:** Laravel 12+ (пакет), MoonShine 4.x (`moonshine/moonshine ^4.0`)
- **Frontend:** ванильный JavaScript (ES2017) + Alpine.js (через ядро MoonShine); без фреймворков
- **Сборка:** Vite 6 + lightningcss + autoprefixer, Bun как пакетный менеджер; IIFE-обёртка бандла
- **БД:** нет — пакет не имеет собственных таблиц; поле хранит JSON в модели хоста
- **Тесты:** автотестов нет — `php -l` + `bun run build` + ручная проверка в хост-приложении

## Архитектурные заметки

- PSR-4: `Povly\FlexibleLayouts\` → `src/`
- Слои: `Fields\FlexibleLayouts` (поле: презентация + apply-пайплайн), `Blocks\Block` (доменный value object, не Field), `Http\Controllers\BlockController` (AJAX), `Casts\FlexibleCast` (Eloquent-каст с depth limit 64), `resources/js/field.js` + `field.blade.php` (UI)
- Контракт `BlockContract` + типизированная `BlockCollection`
- Единый CSS/DOM неймспейс `_fl-*` — защита от коллизий с ядром MoonShine
- Ассеты пакета подключаются через `AssetManager` (`Js`/`Css`), публикуются в `public/vendor/flexible-layouts/`
- Роут добавления блока регистрируется внутри `Route::moonshine()` (наследует auth/web/CSRF)

## Архитектура

Подробные архитектурные правила — в `.ai-factory/ARCHITECTURE.md`.
**Паттерн:** Layered Architecture (слоистая): presentation (`Fields`, `Http/Controllers`, `resources/`) → домен (`Blocks`, `Contracts`, `Collections`), адаптеры (`Casts`, `routes/`, `config/`, `lang/`), composition root (`Providers`).

## Нефункциональные требования

- **Логирование:** диагностические `Log::warning` гейтятся `config('flexible-layouts.logging')` (env `FLEXIBLE_LAYOUTS_LOGGING`, по умолчанию `app.debug`); ошибки кодирования/декодирования JSON в `FlexibleCast` всегда пишутся как `Log::error`
- **Обработка ошибок:** без исключений для неизвестных `_type` (passthrough-семантика); `JSON_THROW_ON_ERROR` + `JSON_DEPTH_LIMIT = 64` против рекурсивного DoS
- **Безопасность:** иконки блоков — developer-trusted only (граница XSS: raw-рендер `{!! !!}`/`x-html`); лимиты блоков считаются по persisted-модели, клиентские `counts` — только fallback для несохранённых записей
