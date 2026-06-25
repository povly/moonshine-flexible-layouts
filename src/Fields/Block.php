<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Fields;

use Illuminate\Support\Traits\Conditionable;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\UI\Components\FieldsGroup;
use Povly\FlexibleLayouts\Contracts\BlockContract;
use Throwable;

final class Block implements BlockContract
{
    use Conditionable;

    private ?ActionButtonContract $removeButton = null;

    private bool $isForcePreview = false;

    /**
     * @param  iterable<array-key, FieldContract>  $fields
     */
    public function __construct(
        private string $title,
        private string $name,
        private iterable $fields,
        private ?int $limit = null,
        private ?string $category = null,
        private ?string $description = null,
        private ?string $icon = null,
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

    public function category(): ?string
    {
        return $this->category;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function icon(): ?string
    {
        return $this->icon;
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
     * @param  iterable<array-key, FieldContract>  $fields
     */
    public function setFields(iterable $fields): self
    {
        $this->fields = $fields;

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
                ->map(fn (FieldContract $f): FieldContract => $f->previewMode());
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
    public function renderTabContent(): string
    {
        $html = '';

        if ($button = $this->getRemoveButton()) {
            $html .= '<div class="_fl-block-header">'.(string) $button.'</div>';
        }

        $html .= (string) FieldsGroup::make($this->fields());

        return $html;
    }
}
