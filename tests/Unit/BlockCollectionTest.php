<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit;

use Povly\FlexibleLayouts\Blocks\Block;
use Povly\FlexibleLayouts\Collections\BlockCollection;

final class BlockCollectionTest extends TestCase
{
    public function test_find_by_name_returns_matching_block(): void
    {
        $hero = new Block('Hero', 'hero', []);
        $text = new Block('Text', 'text', []);
        $collection = BlockCollection::make([$hero, $text]);

        self::assertSame($hero, $collection->findByName('hero'));
        self::assertSame($text, $collection->findByName('text'));
    }

    public function test_find_by_name_returns_null_on_miss(): void
    {
        $collection = BlockCollection::make([new Block('Hero', 'hero', [])]);

        self::assertNull($collection->findByName('missing'));
    }

    public function test_find_by_name_uses_normalised_name(): void
    {
        $block = new Block('Call to Action', 'Call To Action', []);
        $collection = BlockCollection::make([$block]);

        self::assertSame($block, $collection->findByName('call_to_action'));
    }
}
