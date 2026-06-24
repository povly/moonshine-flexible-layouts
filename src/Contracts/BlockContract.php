<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Collections\Fields;
use Stringable;

interface BlockContract extends Htmlable, Renderable, Stringable
{
    public function title(): string;

    public function name(): string;

    public function fields(): Fields;

    public function hasLimit(): bool;

    public function limit(): ?int;

    public function removeButton(?ActionButtonContract $button): self;

    public function getRemoveButton(): ?ActionButtonContract;
}
