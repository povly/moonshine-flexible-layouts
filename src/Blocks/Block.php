<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Blocks;

use Illuminate\Support\Traits\Conditionable;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Collections\Fields;
use MoonShine\UI\Components\FieldsGroup;
use Povly\FlexibleLayouts\Contracts\BlockContract;
use Throwable;

/**
 * Domain value object representing a registered block type.
 *
 * Not a MoonShine Field (does not extend Field) — lives in src/Blocks/
 * because Fields/ is reserved for Presentation-layer field types.
 * See .ai-factory/ARCHITECTURE.md for the technical-layer contract.
 */
final class Block implements BlockContract
{
    use Conditionable;

    private ?ActionButtonContract $removeButton = null;

    private bool $isForcePreview = false;

    /**
     * @param  string  $title  Human-readable label shown in UI tabs and picker.
     * @param  string  $name  Snake_case key stored in JSON as `_type`. Normalised via str()->squish()->snake().
     * @param  iterable<array-key, FieldContract>  $fields  MoonShine fields (can include nested FlexibleLayouts).
     * @param  int|null  $limit  Max instances of this block type (null = unlimited).
     * @param  string|null  $category  Picker-modal grouping label.
     * @param  string|null  $description  Short description shown in picker card.
     * @param  string|null  $icon  TRUSTED developer-supplied icon spec — MoonShine icon name
     *                             (e.g. 'photo'), emoji (e.g. '📷'), or raw SVG markup (e.g. '<svg>...</svg>').
     *                             Rendered via Blade `{!! !!}` and Alpine `x-html` — NEVER pass user-controlled
     *                             data here (would be XSS). If block types ever become DB-driven, sanitise via
     *                             DOMPurify or `Element::setHTML()` before assignment.
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
            // Use `->each()` for iteration — `->map()` would discard the
            // return value. previewMode() mutates the field in place.
            $this->fields->onlyFields()
                ->each(fn (FieldContract $f): FieldContract => $f->previewMode());
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
