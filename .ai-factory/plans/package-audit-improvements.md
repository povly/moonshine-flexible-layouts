# Implementation Plan: Аудит пакета — багфиксы, харднинг и улучшения

Branch: none (git.create_branches=false, работа на `main`)
Created: 2026-09-13
Mode: full

## Original Request

посмотри пакет, улучшение, фи4ксы и прочее. Ты сеньоре и sotware разработчик!

## Settings

- Testing: yes — новые тесты PHPUnit (tests/Unit), прогон `vendor/bin/phpunit` после каждой PHP-задачи
- Logging: verbose — PHP: диагностические `Log::*` с префиксом `[FlexibleLayouts]`, гейт `config('flexible-layouts.logging')` для warning-уровня, `Log::error` только для порчи данных; JS: `console.debug/warn` строго за `import.meta.env.DEV`
- Docs: yes — обязательный docs-checkpoint в конце (README + docs/)

## Результаты аудита

Полный обход пакета: `src/` (8 классов), `resources/{js,views,css}`, `routes/`, `config/`, `tests/`, `composer.json`, `vite.config.js`. Рабочее дерево чистое, база — `main` (a5b93b1).

### Баги (P0)

| # | Находка | Файлы | Эффект |
|---|---------|-------|--------|
| B1 | Атрибут `data-top-level` нигде не проставлен, но `field.js::add()/duplicate()` фильтруют `input.closest('[data-top-level]') === t.root` → `counts` всегда `{}` | `resources/views/field.blade.php`, `resources/js/field.js` | Лимиты блоков не работают для новых (несохранённых) записей: `resolveBlockCount()` fallback всегда получает 0. Плюс ложный warning «client-supplied counts» в логе |
| B2 | `fillClonedRecursively()` вызывает `resolveFill($data)` на **оригиналах** полей реестра и лишь потом клонирует (`clone $block` — shallow) | `src/Fields/FlexibleLayouts.php:299–320` | Прототипы блоков в `$this->blocks` мутируются данными последнего элемента: риск утечки значений между блоками одного типа и между рендерами (страница + AJAX `store()`) |
| B3 | `getFilledBlocks()` проставляет `flPath` только полям верхнего уровня блока (`foreach ($fields as $field)`), не рекурсивно через `Flex`/`Column`/`FieldsGroup` | `src/Fields/FlexibleLayouts.php:368–372` | Вложенный `FlexibleLayouts` внутри `Flex`/`Column` шлёт AJAX с `path=<column>` → `getField()` не находит поле → toast «Field not found». Серверный traversal (`onlyFields()` рекурсивен) при этом находит поле — ломается только клиентский путь |

### Нарушения инвариантов и харднинг (P1)

| # | Находка | Файлы |
|---|---------|-------|
| I1 | Глобальные CSS-селекторы `.sortable-ghost/.sortable-chosen/.sortable-drag` без скоупа `_fl-*` — нарушение собственного инварианта неймспейса (ARCHITECTURE.md «Ключевые принципы», п. 5) | `resources/css/field.css:353–363` |
| P1 | `block()` не валидирует вход: дубликаты нормализованных имён (тихий first-match в `findByName`), `limit < 1` (блок нельзя добавить никогда) | `src/Fields/FlexibleLayouts.php:70–82` |
| S2 | Нет throttle на AJAX-роут `flexible-layouts.store` (auth-gated, но по глобальным security rules желателен rate limit) | `routes/moonshine.php` |

### Полировка и покрытие (P2)

| # | Находка | Файлы |
|---|---------|-------|
| P2 | Мёртвый код: `$preservedUnknown` в `resolveOnApply()` собирается, но нигде не читается | `src/Fields/FlexibleLayouts.php:424,444` |
| P7 | Асимметрия DOM-событий: есть `block-added`/`block-duplicated`, нет `block-removed` | `resources/js/field.js::remove()` |
| S3 | `console.warn('[FL FIX] …')` в `restoreTypeValues()` не гейтирован `import.meta.env.DEV` — шум в проде | `resources/js/field.js:73` |
| B5 | Двойной обработчик клика на статичных табах: Alpine `@click` в blade + делегированный listener в `init()` — идемпотентно, но дублирующе | `resources/views/field.blade.php:13` |
| P3 | a11y: пикер без `role="dialog"`/`aria-modal`/focus-trap; табы без `tablist`/`tab`/`tabpanel`/`aria-selected` и навигации стрелками | `resources/views/field.blade.php`, `resources/js/field.js` |
| P4 | Пробелы в тест-покрытии (подтверждено codegraph): `resolveOnApply` (passthrough/drop/apply-пайплайн), `getFilledBlocks` (unknown `_type`, вложенность), `getBlockTitles`/`getBlockMeta`, `BlockController` | `tests/` |

Не включено (осознанно): мерж `data-top-level` в `$attributes` хостом — фиксим на стороне пакета; `_fl-block.hidden` дублирует Tailwind — оставляем как safety net; `resolveLabel()` через сравнение с ключом — работает, менять на `Lang::has()` нет выгоды.

## Commit Plan

- **Commit 1** (после задач 1–4): `fix: field fill isolation, recursive flPath, block registration validation`
- **Commit 2** (после задач 5–6): `fix: limit count fallback and css namespace, add block-removed event`
- **Commit 3** (после задач 7–8): `test: cover apply pipeline; harden store route with throttle`
- **Commit 4** (после задач 9–10): `feat: picker and tabs a11y; docs: audit changes`

Коммиты — только по явной просьбе пользователя (правило проекта).

## Tasks

### Phase 1: PHP-фиксы ядра

- [x] Task 1: Изоляция данных при заполнении блоков (B2)
  В `src/Fields/FlexibleLayouts.php::fillClonedRecursively()` клонировать поле ДО `resolveFill()`: для `Field` — `$item = (clone $item); $item->resolveFill($data);` вместо fill-оригинала-then-clone. Прототипы в `$this->blocks` больше не мутируются.
  Тест (`tests/Unit/FlexibleLayoutsFieldTest.php`, новый): поле с одним типом блока, `setValue([itemA, itemB])` с разными значениями → `getFilledBlocks()` дважды: значения каждого блока стабильны между вызовами и не «протекают» между itemA/itemB.
  Логирование: без новых логов (изменение механики без новых веток); при падении теста — стандартный вывод phpunit.
  Files: `src/Fields/FlexibleLayouts.php`, `tests/Unit/FlexibleLayoutsFieldTest.php`

- [x] Task 2: Рекурсивная установка flPath для вложенных FlexibleLayouts (B3)
  В `getFilledBlocks()` заменить плоский цикл (строки 368–372) на рекурсивный обход результата `fillClonedRecursively`: спускаться в `HasComponentsContract`/`HasFieldsContract` и проставлять `setFlPath($this->getFlPath().'.'.$block->name().'.'.$field->getColumn())` каждому найденному `FlexibleLayouts`. Реализовать приватным хелпером `applyFlPathRecursively(ComponentsContract|Collection $fields, string $basePath): void` — без новых публичных API.
  Тест: блок с `Flex::make([Column::make([FlexibleLayouts::make(...)])])` → `getFilledBlocks()` → у вложенного поля `getFlPath()` возвращает полный dot-path (`content.section.blocks`-вида).
  Логирование: `Log::warning('[FlexibleLayouts] flPath applied to nested FlexibleLayouts')` не нужен — молча; verbose-контекст даёт тест.
  Files: `src/Fields/FlexibleLayouts.php`, `tests/Unit/FlexibleLayoutsFieldTest.php`

- [x] Task 3: Валидация регистрации блоков (P1)
  В `block()` (`src/Fields/FlexibleLayouts.php:70–82`): (а) дубликат нормализованного `name()` среди уже зарегистрированных → `InvalidArgumentException` с сообщением, включающим оба исходных имени и колонку поля; (б) `isset($limit) && $limit < 1` → `InvalidArgumentException`. Нормализацию имени не менять (`str()->squish()->snake()`).
  Тесты: дубликат (`'hero'` + `'Hero'` после нормализации) бросает; `limit: 0` и `limit: -1` бросают; `limit: 1` и `null` проходят; уникальные имена регистрируются.
  Логирование: перед броском — `Log::warning('[FlexibleLayouts] block registration rejected', ['column', 'name', 'reason'])` только для случая дубликата имени, гейт `config('flexible-layouts.logging')` (исключение само по себе — сигнал разработчику; лог — для диагностики в проде).
  Files: `src/Fields/FlexibleLayouts.php`, `tests/Unit/FlexibleLayoutsFieldTest.php`

- [x] Task 4: Удалить мёртвый `$preservedUnknown` (P2)
  `src/Fields/FlexibleLayouts.php::resolveOnApply()` — убрать переменную и `use (&$preservedUnknown)`; поведение не меняется (passthrough остаётся, warning остаётся).
  Логирование: без изменений.
  Files: `src/Fields/FlexibleLayouts.php`

<!-- Commit checkpoint: задачи 1–4 -->

### Phase 2: Frontend-фиксы

- [x] Task 5: Починить подсчёт counts для лимитов новых записей + события и шум (B1, P7, S3, B5) (depends on 1)
  - `resources/views/field.blade.php`: корневому div добавить `data-top-level`: `$attributes->merge(['data-top-level' => 'fl-root'])->class('_fl-field')` — тогда `closest('[data-top-level]')` в JS находит корень именно этого инстанса (вложенные FL исключаются из родительского counts автоматически: их корень — их собственный `data-top-level`).
  - `resources/js/field.js`: в `remove()` диспатчить `flexible-layouts:block-removed` (bubbles, detail: `{ name, column }`) симметрично added/duplicated — после DOM-удаления, в `$nextTick`; `console.warn('[FL FIX] …')` в `restoreTypeValues()` обернуть в `if (import.meta.env.DEV)`.
  - `resources/views/field.blade.php`: убрать `@click="switchTab(...)"` со статичных табов (делегированный listener в `init()` уже покрывает и статичные, и динамические табы) — устраняет двойной вызов.
  - Пересборка: `bun run build` (обязательно после правок `resources/**`) + напоминание о републикации ассетов в хост-приложении.
  Логирование (JS, verbose в DEV): в `add()/duplicate()` при формировании counts добавить `console.debug('[FlexibleLayouts] limit counts', { column, counts })` за `import.meta.env.DEV`.
  Files: `resources/views/field.blade.php`, `resources/js/field.js`, `dist/` (пересборка)

- [x] Task 6: Скоупинг sortable-стилей под неймспейс _fl-* (I1) (depends on 5)
  `resources/css/field.css:353–363`: `.sortable-ghost`, `.sortable-chosen`, `.sortable-drag` → скоупить под корень поля: `._fl-field .sortable-ghost { … }` (и аналогично). Классы SortableJS не переопределять (их ставит ядро MoonShine) — скоупим только селекторы, глобального загрязнения больше нет. Пересборка `bun run build`.
  Логирование: не требуется (CSS).
  Files: `resources/css/field.css`, `dist/`

<!-- Commit checkpoint: задачи 5–6 -->

### Phase 3: Покрытие и харднинг

- [x] Task 7: Тесты passthrough и apply-пайплайна (P4) (depends on 1, 4)
  Расширить `tests/Unit/FlexibleLayoutsFieldTest.php` (или отдельный `ApplyPipelineTest.php`):
  - `resolveOnApply()`: запись с неизвестным `_type` возвращается verbatim; запись без `_type` отбрасывается; известный тип прогоняется через `apply()` вложенных полей (`Text` → значение, `Textarea` → значение); `_type` в результирующем элементе равен нормализованному имени блока; порядок элементов сохраняется.
  - `getFilledBlocks()`: неизвестный `_type` не попадает в выдачу (render-skip), известный — с заполненными полями; `getBlockTitles()`/`getBlockMeta()` возвращают полные карты по всем блокам.
  Инфраструктура: testbench уже подключён (`TestCase.php`); для apply-пайплайна замокать request-данные через `.setRequestValue()`/API поля MoonShine (как делает ядро в своих тестах).
  Логирование: тесты логов не требуют; падение — стандартный вывод phpunit.
  Files: `tests/Unit/`

- [x] Task 8: Throttle на store-роут (S2)
  `routes/moonshine.php`: `->middleware('throttle:60,1')` на POST-роут (внутри `Route::moonshine()` — auth/CSRF наследуются, throttle добавляется поверх; alias `throttle` стандартный для Laravel 12). Конфиг не расширять (число захардкожено в роуте, вынос в конфиг — по запросу).
  Логирование: без изменений (429 отдаёт Laravel).
  Files: `routes/moonshine.php`

<!-- Commit checkpoint: задачи 7–8 -->

### Phase 4: a11y и документация

- [x] Task 9: Доступность пикера и табов (P3) (depends on 5)
  - Пикер (`field.blade.php`): оверлею-карточке `role="dialog"` + `aria-modal="true"` + `aria-label` из лейбла поиска; заголовок/поиск связаны. Focus-trap: в `openPicker()` запоминать `document.activeElement`, фокус в поиск (уже есть), Tab зациклить внутри пикера (keydown-обработчик в JS: first/last focusable), в `closePicker()` возвращать фокус.
  - Табы: контейнеру `role="tablist"`, табам `role="tab"` + `aria-selected` (синхронизировать в `updateTabStyles()`), контейнеру блоков `role="tabpanel"`; навигация стрелками Left/Right (keydown на tabBar → switchTab(i±1), wrap-around).
  - Пересборка `bun run build`.
  Логирование (DEV): `console.debug('[FlexibleLayouts] focus trap engaged/released')` за `import.meta.env.DEV`.
  Files: `resources/views/field.blade.php`, `resources/js/field.js`, `resources/css/field.css` (focus outline при keyboard nav), `dist/`

- [x] Task 10: Docs checkpoint (depends on 1–9)
  Обязательный прогон `/aif-docs` с обновлением:
  - `README.md` (+ RU-секция): новое DOM-событие `flexible-layouts:block-removed`; поведение лимитов для несохранённых записей (теперь считается корректно, источник — клиентские counts, о чём пишет warning в лог); `InvalidArgumentException` при дубликатах имён и `limit < 1`.
  - `docs/blocks.md`: ошибки регистрации блоков; `docs/development.md`: инвариант скоупинга sortable-стилей; `docs/nested-layouts.md`: flPath теперь работает и внутри Flex/Column.
  Логирование: не требуется (документация).
  Files: `README.md`, `docs/`

<!-- Commit checkpoint: задачи 9–10 -->

## Верификация (критерии готовности)

1. `vendor/bin/phpunit` — зелёный, включая новые тесты (изоляция данных, flPath, валидация, passthrough/apply).
2. `vendor/bin/pint --test src/ tests/` (если настроен) / `php -l` на изменённых файлах.
3. `bun run build` без ошибок; `dist/` обновлён.
4. Ручная проверка в хост-приложении (по возможности): добавить блок на НОВОЙ записи с `limit: 1` — вторая попытка даёт toast «Limit count 1»; удалить блок — диспатчится `flexible-layouts:block-removed`; вложенный FL внутри Flex — «Добавить блок» работает.

## Замечания для /aif-implement

- Инварианты скилла `flexible-layouts-dev` обязательны: `_fl-*` неймспейс, passthrough `_type`, XSS-граница иконок (developer-trusted only), operation lock, пересборка+републикация после правок `resources/**`.
- Task 2 и Task 1 меняют соседние строки одного файла — выполнять последовательно, Task 2 после Task 1.
- Не рефакторить `resolveOnApply`/`fillClonedRecursively` сверх необходимого — конвейер MoonShine чувствителен к порядку операций (clone → fill → prepare).
