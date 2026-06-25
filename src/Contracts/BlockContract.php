<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Contracts;

use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Laravel\Collections\Fields;
use Throwable;

interface BlockContract
{
    public function title(): string;

    public function name(): string;

    public function hasLimit(): bool;

    public function limit(): ?int;

    /**
     * @throws Throwable
     */
    public function fields(): Fields;

    public function getRemoveButton(): ?ActionButtonContract;

    public function removeButton(?ActionButtonContract $button): self;

    /**
     * @throws Throwable
     */
    public function renderTabContent(): string;
}
