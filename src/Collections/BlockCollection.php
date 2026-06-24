<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Collections;

use Illuminate\Support\Collection;
use Povly\FlexibleLayouts\Contracts\BlockContract;

/**
 * @extends Collection<int, BlockContract>
 */
final class BlockCollection extends Collection
{
    public function findByName(string $name, ?BlockContract $default = null): ?BlockContract
    {
        return $this->first(fn (BlockContract $block): bool => $block->name() === $name, $default);
    }
}
