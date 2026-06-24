<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Fields;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Traits\Conditionable;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\UI\Components\FieldsGroup;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\Icon;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\Field;
use MoonShine\UI\Fields\Position;
use MoonShine\UI\Fields\Preview;
use Povly\FlexibleLayouts\Contracts\BlockContract;
use Throwable;

final class Block implements BlockContract
{
    use Conditionable;

    private ?ActionButtonContract $removeButton = null;

    private bool $disableSort = false;

    private bool $isForcePreview = false;

    /**
     * @param  iterable<array-key, Field>  $fields
     */
    public function __construct(
        private string $title,
        private string $name,
        private iterable $fields,
        private ?int $limit = null,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function name(): string
    {
        return str($this->name)
            ->squish()
            ->snake()
            ->value();
    }

    public function hasLimit(): bool
    {
        return ! is_null($this->limit);
    }

    public function limit(): ?int
    {
        return $this->limit;
    }

    /**
     * @param  iterable<array-key, Field>  $fields
     */
    public function setFields(iterable $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function disableSort(): self
    {
        $this->disableSort = true;

        return $this;
    }

    public function forcePreview(): self
    {
        $this->isForcePreview = true;

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function fields(): Fields
    {
        if (! $this->fields instanceof Fields) {
            $this->fields = Fields::make($this->fields);
        }

        if ($this->isForcePreview) {
            $this->fields->onlyFields()
                ->map(fn (Field $f): Field => $f->previewMode());
        }

        return $this->fields;
    }

    public function removeButton(?ActionButtonContract $button): self
    {
        $this->removeButton = $button;

        return $this;
    }

    public function getRemoveButton(): ?ActionButtonContract
    {
        return $this->removeButton;
    }

    /**
     * @throws Throwable
     */
    public function headingFields(): Fields
    {
        return Fields::make([
            Flex::make(array_filter([
                $this->disableSort ? null : Preview::make(
                    formatted: static fn () => Icon::make('bars-4')
                )
                    ->withoutWrapper()
                    ->customAttributes(['class' => 'handle', 'style' => 'cursor: move']),

                Position::make()
                    ->withoutWrapper()
                    ->iterableAttributes(),

                FlexibleRender::make($this->title()),
            ]))
                ->customAttributes(['class' => 'w-full'])
                ->itemsAlign('center')
                ->justifyAlign('start'),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function render(): View
    {
        return view('flexible-layouts::block', [
            'heading' => FieldsGroup::make($this->headingFields()),
            'button' => $this->getRemoveButton(),
            'fields' => FieldsGroup::make($this->fields()),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function toHtml(): string
    {
        return (string) $this->render();
    }

    /**
     * @throws Throwable
     */
    public function __toString(): string
    {
        return (string) $this->render();
    }
}
