<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Fields;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\Core\HasComponentsContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\Collection\ComponentsContract;
use MoonShine\Contracts\UI\HasFieldsContract;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Dropdown;
use MoonShine\UI\Components\Link;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Fields\Hidden;
use Povly\FlexibleLayouts\Collections\BlockCollection;
use Povly\FlexibleLayouts\Contracts\BlockContract;
use Throwable;

final class FlexibleLayouts extends Field
{
    protected string $view = 'flexible-layouts::field';

    /** @var array<int, Block> */
    private array $blocks = [];

    private ?ActionButtonContract $addButton = null;

    private ?Dropdown $dropdown = null;

    private ?ActionButtonContract $removeButton = null;

    private bool $disableRemove = false;

    private bool $disableAdd = false;

    private bool $disableSort = false;

    protected function assets(): array
    {
        return [
            Js::make('/vendor/flexible-layouts/js/field.js'),
        ];
    }

    /**
     * Register a block type.
     *
     * @param  string  $name  Snake_case key stored in JSON as `_type`
     * @param  string  $title  Human-readable label shown in UI
     * @param  iterable<array-key, Field>  $fields  MoonShine fields (can include nested FlexibleLayouts)
     * @param  ?int  $limit  Max instances of this block type
     */
    public function block(string $name, string $title, iterable $fields, ?int $limit = null): self
    {
        $this->blocks[] = new Block($title, $name, $fields, $limit);

        return $this;
    }

    public function blocks(): BlockCollection
    {
        return BlockCollection::make($this->blocks);
    }

    public function addButton(ActionButtonContract $button): self
    {
        $this->addButton = $button;

        return $this;
    }

    public function disableAdd(): self
    {
        $this->disableAdd = true;

        return $this;
    }

    public function removeButton(ActionButtonContract $button): self
    {
        $this->removeButton = $button;

        return $this;
    }

    public function disableRemove(): self
    {
        $this->disableRemove = true;

        return $this;
    }

    public function disableSort(): self
    {
        $this->disableSort = true;

        return $this;
    }

    public function dropdown(Dropdown $dropdown): self
    {
        $this->dropdown = $dropdown;

        return $this;
    }

    public function getAddRoute(): string
    {
        return route('moonshine.flexible-layouts.store', [
            'resourceUri' => moonshineRequest()->getResourceUri(),
            'pageUri' => moonshineRequest()->getPageUri(),
        ]);
    }

    public function getAddButton(): ?ActionButtonContract
    {
        if ($this->disableAdd) {
            return null;
        }

        if (is_null($this->addButton)) {
            $this->addButton = ActionButton::make('Add block')
                ->secondary();
        }

        return $this->addButton;
    }

    public function getRemoveButton(): ?ActionButtonContract
    {
        if ($this->disableRemove) {
            return null;
        }

        if (is_null($this->removeButton)) {
            $this->removeButton = ActionButton::make('')
                ->icon('trash')
                ->style('margin-left: auto')
                ->error();
        }

        return $this->removeButton
            ->onClick(fn (): string => 'remove', 'stop');
    }

    /**
     * @return array<int, ActionButtonContract|Link>
     */
    public function getBlockButtons(): array
    {
        return $this
            ->blocks()
            ->map(
                fn (BlockContract $block) => Link::make('#', $block->title())
                    ->icon('plus')
                    ->customAttributes(['@click.prevent' => "add(`{$block->name()}`);closeDropdown()"]),
            )
            ->toArray();
    }

    public function getDropdown(): Dropdown
    {
        if (is_null($this->dropdown)) {
            $this->dropdown = Dropdown::make();
        }

        return $this->dropdown
            ->toggler(fn (): ?ActionButtonContract => $this->getAddButton())
            ->items($this->getBlockButtons());
    }

    /**
     * Fill cloned fields from stored data, recursing into nested containers.
     */
    private function fillClonedRecursively(ComponentsContract|Collection $collection, mixed $data): Collection
    {
        return $collection->map(function (mixed $item) use ($data) {
            if ($item instanceof HasComponentsContract) {
                $item = (clone $item)->setComponents(
                    $this->fillClonedRecursively($item->getComponents(), $data)->toArray(),
                );
            }

            if ($item instanceof HasFieldsContract) {
                $item = (clone $item)->fields(
                    $this->fillClonedRecursively($item->getFields(), $data)->toArray(),
                );
            }

            if ($item instanceof Field) {
                $item->resolveFill($data);
            }

            return clone $item;
        });
    }

    /**
     * Build filled Block objects from stored JSON values.
     *
     * @throws Throwable
     */
    public function getFilledBlocks(): BlockCollection
    {
        $blocks = $this->blocks();
        $values = $this->getValue();

        $stored = is_iterable($values) ? $values : [];

        $filled = collect($stored)->map(function (array $item) use ($blocks) {
            $block = $blocks->findByName($item['_type'] ?? '');

            if (! $block instanceof BlockContract) {
                return null;
            }

            $block = clone $block
                ->when(
                    $this->disableSort,
                    fn (Block $b): Block => $b->disableSort(),
                )
                ->when(
                    $this->isPreviewMode(),
                    fn (Block $b): Block => $b->forcePreview(),
                );

            $fields = $this->fillClonedRecursively(
                $block->fields(),
                $item,
            );

            $block
                ->setFields($fields)
                ->fields()
                ->prepend(
                    Hidden::make('_type')
                        ->customAttributes(['class' => '_type-value'])
                        ->setValue($block->name()),
                )
                ->prepareAttributes()
                ->prepareReindexNames($this);

            return $block->removeButton($this->getRemoveButton());
        })->filter();

        return BlockCollection::make($filled);
    }

    protected function resolvePreview(): View|string
    {
        return $this
            ->disableRemove()
            ->disableAdd()
            ->disableSort()
            ->previewMode()
            ->render();
    }

    protected function resolveOnApply(): ?Closure
    {
        return function ($item) {
            $requestValues = array_filter($this->getRequestValue() ?: []);

            $data = collect($requestValues)->map(function (array $value, $index): array {
                $block = $this->blocks()->findByName($value['_type'] ?? '');

                if (is_null($block)) {
                    return [];
                }

                unset($value['_type']);

                $applyValues = [];

                $block->fields()->onlyFields()->each(
                    function (Field $field) use ($value, $index, &$applyValues): void {
                        $field->appendRequestKeyPrefix(
                            "{$this->getColumn()}.$index",
                            $this->getRequestKeyPrefix(),
                        );

                        // If $field is itself a FlexibleLayouts, its apply() calls
                        // its own resolveOnApply() → recursion is FREE through delegation
                        $apply = $field->apply(
                            fn ($data): mixed => data_set($data, $field->getColumn(), $value[$field->getColumn()] ?? ''),
                            $value,
                        );

                        data_set(
                            $applyValues,
                            $field->getColumn(),
                            data_get($apply, $field->getColumn()),
                        );
                    },
                );

                return array_merge(['_type' => $block->name()], $applyValues);
            })->filter();

            data_set($item, $this->getColumn(), $data->values());

            return $item;
        };
    }

    /**
     * Iterate through each submitted row and call beforeApply on child fields.
     *
     * @throws Throwable
     */
    protected function resolveBeforeApply(mixed $data): mixed
    {
        return $this->resolveCallback($data, function (Field $field, mixed $value): void {
            $field->beforeApply($value);
        });
    }

    /**
     * Iterate through each submitted row and call afterApply on child fields.
     *
     * @throws Throwable
     */
    protected function resolveAfterApply(mixed $data): mixed
    {
        return $this->resolveCallback($data, function (Field $field, mixed $value): void {
            $field->afterApply($value);
        });
    }

    /**
     * Iterate through each stored row and call afterDestroy on child fields.
     *
     * @throws Throwable
     */
    protected function resolveAfterDestroy(mixed $data): mixed
    {
        return $this->resolveCallback($data, function (Field $field, mixed $value): void {
            $field->afterDestroy($value);
        }, fill: true);
    }

    /**
     * Shared callback loop for before/after apply and destroy pipelines.
     *
     * @param  Closure(Field, mixed): void  $callback
     *
     * @throws Throwable
     */
    private function resolveCallback(mixed $data, Closure $callback, bool $fill = false): mixed
    {
        $requestValues = array_filter($this->getRequestValue() ?: []);

        foreach ($requestValues as $index => $value) {
            $block = $this->blocks()->findByName($value['_type'] ?? '');

            if (is_null($block)) {
                continue;
            }

            $block
                ->fields()
                ->onlyFields()
                ->each(function (Field $field) use ($data, $index, $value, $callback, $fill): void {
                    $field->appendRequestKeyPrefix(
                        "{$this->getColumn()}.$index",
                        $this->getRequestKeyPrefix(),
                    );

                    $field->when($fill, fn (Field $f): Field => $f->resolveFill($data));

                    $callback($field, $value);
                });
        }

        return $data;
    }

    /**
     * @throws Throwable
     */
    protected function viewData(): array
    {
        return [
            'addRoute' => $this->getAddRoute(),
            'blocks' => $this->getFilledBlocks(),
            'dropdown' => $this->getDropdown(),
        ];
    }
}
