<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Collections;

use Illuminate\Support\Collection;
use Povly\FlexibleLayouts\Contracts\BlockContract;

/**
 * @extends Collection<int, BlockContract>
 */
class BlockCollection extends Collection
{
    public function findByName(string $name): ?BlockContract
    {
        return $this->first(fn (BlockContract $block): bool => $block->name() === $name);
    }
}
