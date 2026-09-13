# Architecture: Layered Architecture (Слоистая архитектура)

## Обзор

Пакет — единый модуль со слоистой организацией: presentation-слой (поле MoonShine, AJAX-контроллер, Blade/Alpine/CSS-ассеты) поверх доменного слоя (value object блока, контракт, коллекция), с тонкими адаптерами к внешнему миру (Eloquent-каст, роуты, конфиг, переводы) и composition root в сервис-провайдере. Паттерн выбран потому, что структура уже фактически сложилась в кодовой базе, доменная логика компактна (один агрегат — тип блока), а команда мала — формальная модульность (Structured Modules/Explicit) принесла бы церемонию без выгоды.

Это документация реальности, а не идеал, к которому надо рефакторить: расхождений между структурой кода и этим документом нет.

## Обоснование выбора

- **Тип проекта:** библиотека (Laravel-пакет), не приложение — один публичный API (`FlexibleLayouts::make()`) + один AJAX-эндпоинт
- **Стек:** PHP 8.2+ / Laravel 12+ / MoonShine 4.x; фронтенд без фреймворков
- **Ключевой фактор:** 8 PHP-классов, один доменный агрегат, слоистые каталоги `src/` уже существуют; docblock `Block.php` уже ссылается на этот контракт слоёв (`Fields/` зарезервирована под presentation)

## Структура каталогов

```
src/
├── Providers/              # COMPOSITION ROOT — регистрация пакета (config, views, lang, routes, publishes)
├── Fields/                 # PRESENTATION — поле MoonShine (FlexibleLayouts: рендер + apply-пайплайн)
├── Http/Controllers/       # PRESENTATION-адаптер — BlockController (AJAX store, лимиты)
├── Blocks/                 # DOMAIN — Block: value object типа блока (НЕ Field)
├── Contracts/              # DOMAIN — BlockContract
├── Collections/            # DOMAIN — BlockCollection (findByName)
├── Casts/                  # PERSISTENCE-адаптер — FlexibleCast (Eloquent, depth limit 64)
resources/
├── views/                  # PRESENTATION — field.blade.php (+ модальный пикер)
├── js/                     # PRESENTATION — field.js (Alpine-компонент, IIFE-бандл)
└── css/                    # PRESENTATION — field.css (неймспейс _fl-*)
routes/                     # ИНФРАСТРУКТУРА — POST {prefix}/store/{pageUri}/{resourceUri?}
config/                     # ИНФРАСТРУКТУРА — route_prefix, logging
lang/                       # ИНФРАСТРУКТУРА — переводы (en, ru)
```

## Правила зависимостей

```
Providers (composition root) — собирает всё вместе
        │
        ▼
PRESENTATION: Fields/, Http/Controllers/, resources/{views,js,css}
        │                     использует
        ▼
DOMAIN: Blocks/ + Contracts/ + Collections/
        ▲
        │ реализует/адаптирует (внешние границы)
ADAPTERS: Casts/ (Eloquent), routes/, config/, lang/
```

- ✅ `Fields` → `Blocks`/`Contracts`/`Collections` (presentation использует домен)
- ✅ `Http/Controllers` → `Fields` + `Blocks` (внешний адаптер вызывает presentation и домен)
- ✅ `Providers` знает все слои (composition root)
- ✅ Домен импортирует контракты MoonShine (`MoonShine\Contracts\UI\FieldContract`) — это внешний фреймворк-контракт, не наш слой
- ❌ `Blocks`/`Contracts`/`Collections` → `Povly\FlexibleLayouts\Fields\*` (домен не знает presentation)
- ❌ `Casts` → `Fields`/`Http` (каст работает только с массивом/JSON)
- ❌ Логика HTTP-запросов вне `Http/Controllers` (поле читает request только через API MoonShine `Field`)

## Взаимодействие слоёв

- **Поле → Blade/Alpine:** `Fields\FlexibleLayouts` готовит `viewData()` (заполненные блоки, метаданные пикера, лейблы), шаблон + `Alpine.data('flexibleLayouts')` рендерят UI.
- **JS → Контроллер:** Alpine-компонент шлёт `POST` через `MoonShine.request`; `BlockController` находит поле по dot-path (`getField()`), переиспользует тот же `getFilledBlocks()`-пайплайн и возвращает `{ blockHtml, blockTitle }`.
- **Данные между слоями:** плоский массив PHP (он же JSON в БД) с ключом `_type` — единый формат на всех уровнях; никаких DTO-мапперов между слоями не вводить.
- **Сохранение:** `resolveOnApply()` поля прогоняет значения через `apply()` вложенных полей MoonShine; `FlexibleCast` сериализует в JSON (`array_values`, depth ≤ 64).

## Ключевые принципы

1. **`Block` — value object, не Field.** `src/Blocks/` — домен; `src/Fields/` зарезервирована под presentation-типы полей MoonShine. Допустимый компромисс: `renderTabContent()` на `Block` — тонкий рендер-хелпер поверх `FieldsGroup` (архитектура MoonShine сама смешивает поле и компонент).
2. **Зависимости вниз.** Presentation знает домен, никогда наоборот. Единственное место, которому разрешено знать всё, — `Providers`.
3. **Истина живёт в форме.** UI (JS/Alpine) не хранит состояние — только DOM/`data-*` атрибуты, синхронизируемые `MoonShine.iterable.reindex()`; после сохранения источником является модель хоста.
4. **Passthrough `_type`.** Неизвестные типы блоков переживают save/load (data-loss guard) — семантика зафиксирована в скилле `flexible-layouts-dev`.
5. **Неймспейс `_fl-*`.** Всё, что пакет отдаёт в DOM/CSS, — с префиксом `_fl-`.

## Политика организации кода

- **Новый код:** следует этому документу там, где это применимо.
- **Существующий код:** документирован как есть; при модификации предпочитать эти конвенции, но не переписывать непохожий код ради структуры.
- **Взаимодействие:** новый код, вызывающий существующий, — через чистые интерфейсы (`BlockContract`, публичные методы поля), без рефакторинга ради выравнивания.

## Примеры кода

### Разрешённая зависимость: presentation → домен

```php
// src/Fields/FlexibleLayouts.php ✅ поле (presentation) использует домен
$block = $blocks->findByName($item['_type'] ?? '');

if (! $block instanceof BlockContract) {
    return null; // passthrough: неизвестный _type пропускается при рендере
}
```

### Запрещённая зависимость: домен → presentation

```php
// src/Blocks/Block.php ❌ никогда не импортировать наше presentation-ядро
use Povly\FlexibleLayouts\Fields\FlexibleLayouts; // ЗАПРЕЩЕНО

// ✅ домен оперирует только контрактами MoonShine и собственным BlockContract
use MoonShine\Contracts\UI\FieldContract;
```

### Тонкий адаптер к Eloquent

```php
// src/Casts/FlexibleCast.php — единственное место, знающее про JSON-сериализацию
return [$key => json_encode(array_values($value), JSON_THROW_ON_ERROR)];
```

## Антипаттерны

- ❌ Подклассы `Field` вне `src/Fields/`; `Block`, расширяющий MoonShine `Field`
- ❌ Импорт `Povly\FlexibleLayouts\Fields\*` из `Blocks/`, `Contracts/`, `Collections/`, `Casts/`
- ❌ Чтение `Request`/сессий вне `Http/Controllers` (поле — только через API MoonShine `Field`)
- ❌ Состояние/истина в JS; хранение данных вне JSON-атрибута модели хоста
- ❌ CSS-классы/DOM-хуки без префикса `_fl-`; подъём z-index пикера выше `--z-modal`
- ❌ Правки `resources/{js,css}` без пересборки (`bun run build`) и републикации ассетов
