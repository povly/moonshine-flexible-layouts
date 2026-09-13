# Implementation Plan: Кнопка «Дублировать» для блока

Branch: none (git.create_branches = false, работа на текущей ветке `main`)
Created: 2026-09-13
Mode: full

## Original Request

сделай новую кнопку Дубликат, после кнопки удалить!

## Settings

- Testing: yes
- Logging: verbose
- Docs: yes

## Ключевые решения (технический дизайн)

1. **Подход к дублированию — через существующий AJAX-пайплайн**, а не через `cloneNode`. Кнопка «Дублировать» шлёт `POST {prefix}/store` (тот же эндпоинт, что «Добавить блок») с `_type` исходного блока → сервер возвращает **свежий** HTML с корректными `id`/`name` (`buildNameFromPath` с `${indexN}`-плейсхолдерами) → значения полей исходного блока копируются в новый клиентски. Это даёт: авторитетную серверную проверку лимитов (существующий `BlockController::store()`), уникальные `x-id` (нет дубликатов DOM id), переиспользование operation lock.
2. **Размещение кнопки — после кнопки «Удалить»** в `_fl-block-header` (flex, gap 8px — CSS-правки не нужны). Порядок в хедере: `[Удалить] [Дублировать]`.
3. **Паттерн API зеркалирует `removeButton`**: `duplicateButton()` / `disableDuplicate()` / `getDuplicateButton()` на поле; `Block::duplicateButton()` + контракт. Дефолт: icon-only `ActionButton` с иконкой `square-2-stack`, цвет `secondary`, `onClick('duplicate', 'stop')` — Alpine-метод `duplicate()` в `field.js` (механизм вызова идентичен `'remove'`).
4. **Вставка дубля сразу после исходного блока** (не в конец), таб — после исходного таба; активация переключается на дубль.
5. **Копирование значений** — попарный обход `input/select/textarea` исходного и нового блока в DOM-порядке (набор полей одного `_type` идентичен). `input[type=file]` пропускается (браузер запрещает программную установку файла) — поле файла в дубле остаётся пустым, DEV-warn.
6. **Локализация**: ключ `duplicate_block` (title-атрибут кнопки) в `lang/{en,ru}/messages.php`; фолбэк `resolveLabel()` уже работает.

## Commit Plan

- **Commit 1** (after task 1): `test: add PHPUnit harness with Block/Collection/Cast unit tests`
- **Commit 2** (after tasks 2-5): `feat: duplicate block button`
- **Commit 3** (after task 7): `docs: document duplicate block button`

## Tasks

### Phase 1: Тестовая инфраструктура

- [x] Task 1: Подключить PHPUnit + orchestra/testbench и написать базовые unit-тесты домена
  - `composer require --dev phpunit/phpunit orchestra/testbench` (внести в `composer.json` require-dev)
  - Создать `phpunit.xml.dist` (только suites Unit, БД не нужна)
  - `tests/Unit/BlockTest.php`: `name()` нормализация squish+snake; `hasLimit()/limit()`; `setFields()`/`fields()` ленивая конвертация в `Fields`; `forcePreview()`; `removeButton()`
  - `tests/Unit/BlockCollectionTest.php`: `findByName()` hit/miss
  - `tests/Unit/FlexibleCastTest.php`: `get()` — null/''→null, валидный JSON→array, уже массив→passthrough, битый JSON→null; `set()` — null/[]→`[key=>null]`, `Collection`→toArray, `array_values`, depth>64→null. Тесты наследуют `Orchestra\Testbench\TestCase` (чтобы `Log::` facade не падал)
  - Прогон: `vendor/bin/phpunit`
  - Логирование: не требуется (падения репортит сам phpunit); при фейле выводить diff в чат

### Phase 2: Реализация

- [x] Task 2: `BlockContract` + `Block` — API кнопки дублирования (depends on 1)
  - `src/Contracts/BlockContract.php`: добавить `duplicateButton(?ActionButtonContract $button): self` и `getDuplicateButton(): ?ActionButtonContract`
  - `src/Blocks/Block.php`: свойство `private ?ActionButtonContract $duplicateButton = null` + fluent-сеттер (возвращает `self`) + геттер
  - `renderTabContent()`: хедер `_fl-block-header` рендерит `[remove][duplicate]` — дубль ПОСЛЕ удаления; хедер рендерится, если есть хоть одна из кнопок (сейчас условие только по remove — расширить)
  - Тесты: расширить `tests/Unit/BlockTest.php` (сеттер/геттер duplicateButton, `renderTabContent()` содержит обе кнопки в правильном порядке — при необходимости через тестбенч-рендер, иначе строковую проверку убрать и проверить только API)
  - Логирование: новых серверных путей нет — `Log::*` не добавляется (конвенция пакета сохраняется)
  - BC-нота: расширение контракта — breaking change для внешних реализаций `BlockContract`; пакет pre-1.0, приемлемо

- [x] Task 3: Поле `FlexibleLayouts` — публичный API дублирования (depends on 2)
  - `src/Fields/FlexibleLayouts.php`:
    - `duplicateButton(ActionButtonContract $button): self` — кастомная кнопка
    - `disableDuplicate(): self` + приватный флаг
    - `getDuplicateButton(): ?ActionButtonContract` — null при `disableDuplicate()`; дефолт: `ActionButton::make('')->icon('square-2-stack')->secondary()->customAttributes(['title' => $this->resolveLabel('duplicate_block')])`; каждый вызов применять `->onClick(fn (): string => 'duplicate', 'stop')` (паттерн `getRemoveButton()`)
    - `getFilledBlocks()`: рядом с `$block->removeButton(...)` добавить `$block->duplicateButton($this->getDuplicateButton())`
    - `resolvePreview()`: добавить `->disableDuplicate()` к цепочке disable*
  - Иконка: проверить в хост-приложении `vendor/moonshine/moonshine/src/UI/resources/views/icons/square-2-stack.blade.php`; при отсутствии — `clipboard-document` (NB: `resolveIconSvg` рендерит несуществующее имя как текст, поэтому проверка обязательна)
  - Логирование: не добавляется — серверная логика не меняется (используется существующий `store()`)

- [x] Task 4: `duplicate()` в Alpine-компоненте + сборка (depends on 3)
  - `resources/js/field.js` — метод `duplicate()`:
    1. Guard `_isMutating` + DEV-warn `[FlexibleLayouts] duplicate() blocked by operation lock` (как в `add()`); armed `lockTimeout` 30 s
    2. Исходный блок: `this.$el.closest('._fl-block')`; его `_type` — из `._fl-type`; счётчики `counts` — как в `add()`
    3. `MoonShine.request(t, t.url, 'post', {field, path, name, counts})`
    4. `afterResponse`: wrapper `div._fl-block` (новый `data-fl-uid` через `_genUid()`, `data-correct-type`), вставить **после** исходного блока; таб `._fl-tab` (grip+icon+title) — **после** исходного таба
    5. Копирование значений: попарно `querySelectorAll('input:not([type=file]), select, textarea')` исходного и нового блока в DOM-порядке: `value`, `checked`; для `select` — `value` + `dispatchEvent(new Event('change', {bubbles: true}))`; при несовпадении числа полей — DEV-warn + копировать `min(n, m)`; пропущенные `input[type=file]` — DEV-warn
    6. `clearTimeout`, `_isMutating = false`, `resolveReindex()`, `switchTab(newIndex)`
    7. `CustomEvent('flexible-layouts:block-duplicated', {bubbles: true, detail: {name, column, sourceIndex}})`
    8. `errorCallback`: `clearTimeout`, разблокировка, `console.error('[FlexibleLayouts] duplicate() request failed', {...})`
  - Логирование (verbose): все DEV-warn через `import.meta.env.DEV` (вырезаются в prod); `console.error` — всегда
  - Сборка: `bun run build` (обязательно — правка resources/js); ассеты попадают в `dist/`
  - Логирование сборки: ноль WARN от IIFE-плагина

- [x] Task 5: Локализация — ключ `duplicate_block` (depends on 3)
  - `lang/en/messages.php`: `'duplicate_block' => 'Duplicate'`
  - `lang/ru/messages.php`: `'duplicate_block' => 'Дублировать'`
  - `transKey()`-фолбэк (`{transKey}.{key}` → `messages.{key}`) работает автоматически; в `lang/*/refs.php` ключ не нужен (пример-файлы)
  - Логирование: не требуется (статические файлы переводов)

### Phase 3: Верификация и документация

- [x] Task 6: Ручная верификация в хост-приложении (depends on 4, 5)
  - Републикация: `php artisan vendor:publish --tag=flexible-layouts`
  - Чеклист:
    1. Кнопка «Дублировать» видна после «Удалить» в хедере каждого блока; иконка рендерится SVG (не текст-фолбэк)
    2. Дубль вставляется сразу после исходного, таб — после исходного таба, активная вкладка — дубль
    3. Значения копируются: text, textarea, select (+change-биндинги), checkbox/radio, hidden `_type`, вложенный FlexibleLayout с блоками
    4. Поле файла в дубле пустое (ожидаемо)
    5. Лимиты: при достигнутом `limit` сервер отвечает тостом «Limit count N», дубль не создаётся
    6. `disableDuplicate()` скрывает кнопку; preview-режим без кнопки; `transKey('refs')`-поле дублируется с лейблами
    7. Lock: быстрый двойной клик создаёт ровно один дубль
    8. Save → reload: значения дубля сохраняются и восстанавливаются (round-trip)
  - Также: `php -l` на изменённых PHP-файлах, `vendor/bin/phpunit` зелёный
  - Логирование: при проблемах включить `FLEXIBLE_LAYOUTS_LOGGING=true` и собрать `[FlexibleLayouts]`-сообщения
  - Статус: автопроверки выполнены (php -l ×5, phpunit 22 OK, bun run build без предупреждений); браузерный чеклист 1–8 передан пользователю — в репозитории пакета нет хост-приложения

- [x] Task 7: Docs checkpoint (depends on 6)
  - `README.md` (обе половины EN/RU): features-буллет, секции Disabling Features (+`disableDuplicate()`), Custom Buttons (+`duplicateButton()`), таблица ключей переводов (+`duplicate_block`)
  - `docs/blocks.md`: «Управление полем» (+`disableDuplicate()`, `duplicateButton()`), «Лимиты» — нота о серверной проверке при дублировании
  - `docs/translations.md`: ключ `duplicate_block` в таблице (EN/RU)
  - `docs/development.md`: секция «Тесты» — PHPUnit появился, команда запуска `vendor/bin/phpunit`
  - `AGENTS.md`: «Тесты: отсутствуют» → PHPUnit (2 места: стек + правило верификации)
  - `.ai-factory/DESCRIPTION.md`: «Тесты: автотестов нет» → unit-тесты PHPUnit
  - Логирование: не требуется (документация)

## Риски и известные ограничения

- **Механизм `onClick('duplicate')`** — по аналогии с `'remove'` (Alpine вызывает метод x-data по имени). Если MoonShine не вызовет метод — план Б: навесить обработчик в `init()` через делегирование клика по кнопке с data-атрибутом. Проверяется в Task 6, п. 1.
- **Имя иконки** `square-2-stack` может отсутствовать в наборе MoonShine — проверка в Task 3, fallback `clipboard-document`.
- **JS-виджеты полей** (select2/TinyMCE и т.п.) в дубле могут требовать переинициализации — известное ограничение, зафиксировать в README/docs.
- Composer require-dev требует сети; при недоступности — зафиксировать в `composer.json` вручную и поставить в хост-окружении.
