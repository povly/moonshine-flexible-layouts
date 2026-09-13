# AGENTS.md

> Карта проекта для AI-агентов. Обновляйте при существенных изменениях структуры. Подробный стек — в `.ai-factory/DESCRIPTION.md`, архитектурные правила — в `.ai-factory/ARCHITECTURE.md`.

## Обзор проекта

Laravel-пакет, добавляющий в MoonShine 4 поле конструктора страниц: контентные блоки с неограниченной вложенностью, табами, drag-сортировкой и AJAX-управлением. Данные — плоский JSON с ключом `_type` в каждом элементе.

## Технологический стек

- **Язык:** PHP 8.2+ (`strict_types`, `final`-классы)
- **Фреймворк:** Laravel 12+ / MoonShine 4.x
- **Frontend:** ванильный JS (ES2017) + Alpine.js, без фреймворков
- **Сборка:** Vite 6 + Bun (IIFE-обёртка, lightningcss)
- **БД:** нет (JSON в атрибуте модели хост-приложения)
- **Тесты:** PHPUnit 13 + orchestra/testbench (unit-тесты домена)

## Структура проекта

```
├── src/                              # PHP-код пакета (PSR-4: Povly\FlexibleLayouts\)
│   ├── Blocks/Block.php              #   Доменный value object типа блока (не Field)
│   ├── Casts/FlexibleCast.php        #   Eloquent-каст: JSON <-> array (depth limit 64)
│   ├── Collections/BlockCollection.php
│   ├── Contracts/BlockContract.php   #   Контракт блока
│   ├── Fields/FlexibleLayouts.php    #   Главное поле MoonShine (apply-пайплайн)
│   ├── Http/Controllers/BlockController.php  # AJAX-рендер блока + лимиты
│   └── Providers/FlexibleLayoutsServiceProvider.php
├── tests/                            # Unit-тесты (PHPUnit + testbench)
├── resources/
│   ├── js/field.js                   # Alpine-компонент: табы, пикер, AJAX, operation lock
│   ├── css/field.css                 # Стили, только неймспейс _fl-*
│   └── views/field.blade.php         # Разметка поля + модальный пикер
├── routes/moonshine.php              # POST {prefix}/store/{pageUri}/{resourceUri?}
├── config/flexible-layouts.php       # route_prefix, logging
├── lang/{en,ru}/                     # Переводы (messages, refs)
├── dist/                             # Собранные ассеты → public/vendor/flexible-layouts/
├── docs/                             # Документация (создаётся /aif-docs)
└── .ai-factory/                      # Контекст AI Factory (планы, правила, архитектура)
```

## Ключевые точки входа

| Файл | Назначение |
|------|-----------|
| `src/Fields/FlexibleLayouts.php` | Ядро поля: регистрация блоков, fill/apply-пайплайн, passthrough неизвестных `_type` |
| `src/Providers/FlexibleLayoutsServiceProvider.php` | Регистрация пакета: config, views, lang, routes, publishes |
| `src/Http/Controllers/BlockController.php` | AJAX `store()`: dot-path `getField()`, authoritative-подсчёт лимитов |
| `resources/js/field.js` | Alpine-компонент `flexibleLayouts`: табы, сортировка, пикер, `_isMutating` lock |
| `resources/views/field.blade.php` | Blade-шаблон поля и пикера (x-teleport, `{!! $icon !!}` — trust boundary) |
| `routes/moonshine.php` | Роут добавления блока (внутри `Route::moonshine()` — auth/CSRF) |
| `vite.config.js` | Сборка: IIFE-обёртка `field.js`, lightningcss-таргеты |
| `config/flexible-layouts.php` | `route_prefix`, `logging` (env `FLEXIBLE_LAYOUTS_LOGGING`) |

## Документация

| Документ | Путь | Описание |
|----------|------|----------|
| README | `README.md` | Полная документация (EN — по умолчанию, + RU-секция): установка, API поля, иконки, локализация, формат данных |
| Начало работы | `docs/getting-started.md` | Установка, публикация, каст модели, проверка |
| Типы блоков | `docs/blocks.md` | `block()`, иконки, лимиты, категории, пикер, z-index |
| Вложенные макеты | `docs/nested-layouts.md` | Вложенность, Flex/Column, `transKey()` |
| Формат данных | `docs/data-format.md` | JSON, `_type`, каст, passthrough неизвестных типов |
| Конфигурация | `docs/configuration.md` | `route_prefix`, `logging`, env-переменные |
| Переводы | `docs/translations.md` | Ключи, локали, добавление языков |
| Разработка | `docs/development.md` | Сборка Bun/Vite, отладка, инварианты |

## Файлы контекста для AI

| Файл | Назначение |
|------|-----------|
| `AGENTS.md` | Этот файл — карта проекта |
| `.ai-factory/DESCRIPTION.md` | Спецификация: стек, функции, нефункциональные требования |
| `.ai-factory/ARCHITECTURE.md` | Архитектурные правила и слои |
| `.ai-factory/rules/base.md` | Конвенции кодовой базы (нейминг, ошибки, логирование) |
| `.opencode/skills/flexible-layouts-dev/` | Скилл внутренних инвариантов пакета (критично к соблюдению) |

## Правила для агентов

- Разделяйте составные shell-команды на отдельные шаги:
  - Неправильно: `git checkout main && git pull`
  - Правильно: сначала `git checkout main`, затем `git pull origin main`
- После правок в `resources/js/**` или `resources/css/**` обязательно: `bun run build` (+ републикация ассетов в хост-приложении)
- Коммитить и пушить только по явной просьбе пользователя
- Перед изменением PHP/JS читайте скилл `flexible-layouts-dev` — там критические инварианты (`_fl-*` неймспейс, passthrough `_type`, XSS-граница иконок, operation lock)
