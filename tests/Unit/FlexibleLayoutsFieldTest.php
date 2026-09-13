<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit;

use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\Text;
use Povly\FlexibleLayouts\Contracts\BlockContract;
use Povly\FlexibleLayouts\Fields\FlexibleLayouts;
use InvalidArgumentException;

final class FlexibleLayoutsFieldTest extends TestCase
{
    private static function titleOf(BlockContract $block): mixed
    {
        return $block
            ->fields()
            ->onlyFields()
            ->findByColumn('title')
            ?->toValue();
    }

    public function test_filled_blocks_do_not_leak_values_between_same_type_instances(): void
    {
        $field = FlexibleLayouts::make('Blocks', 'blocks')
            ->block('hero', 'Hero', [
                Text::make('Title', 'title'),
            ]);

        $field->setValue([
            ['_type' => 'hero', 'title' => 'Present'],
            ['_type' => 'hero'],
        ]);

        $blocks = $field->getFilledBlocks();

        self::assertCount(2, $blocks);
        self::assertSame('Present', self::titleOf($blocks[0]));
        self::assertNull(self::titleOf($blocks[1]));
    }

    public function test_filled_blocks_are_stable_across_repeated_calls(): void
    {
        $field = FlexibleLayouts::make('Blocks', 'blocks')
            ->block('hero', 'Hero', [
                Text::make('Title', 'title'),
            ]);

        $items = [
            ['_type' => 'hero', 'title' => 'First'],
            ['_type' => 'hero', 'title' => 'Second'],
        ];

        $field->setValue($items);
        $firstRun = $field
            ->getFilledBlocks()
            ->map(fn (BlockContract $block): mixed => self::titleOf($block))
            ->all();

        // Simulate an AJAX store() call with empty block data in between —
        // must not corrupt the prototypes for subsequent renders.
        $field->setValue([['_type' => 'hero']]);
        $field->getFilledBlocks();

        $field->setValue($items);
        $secondRun = $field
            ->getFilledBlocks()
            ->map(fn (BlockContract $block): mixed => self::titleOf($block))
            ->all();

        self::assertSame(['First', 'Second'], $firstRun);
        self::assertSame($firstRun, $secondRun);
    }

    public function test_nested_flexible_layouts_inside_containers_gets_full_fl_path(): void
    {
        $field = FlexibleLayouts::make('Content', 'content')
            ->block('section', 'Section', [
                Text::make('Title', 'title'),
                Flex::make([
                    Column::make([
                        FlexibleLayouts::make('Refs', 'refs')
                            ->block('reference', 'Reference', [
                                Text::make('Name', 'name'),
                            ]),
                    ])->columnSpan(12),
                ]),
            ]);

        $field->setValue([
            ['_type' => 'section', 'title' => 'T', 'refs' => []],
        ]);

        $nested = $field
            ->getFilledBlocks()
            ->first()
            ?->fields()
            ->onlyFields()
            ->findByColumn('refs');

        self::assertInstanceOf(FlexibleLayouts::class, $nested);
        self::assertSame('content.section.refs', $nested->getFlPath());
    }

    public function test_directly_nested_flexible_layouts_keeps_full_fl_path(): void
    {
        $field = FlexibleLayouts::make('Content', 'content')
            ->block('section', 'Section', [
                FlexibleLayouts::make('Blocks', 'items')
                    ->block('button', 'Button', [
                        Text::make('Text', 'text'),
                    ]),
            ]);

        $field->setValue([
            ['_type' => 'section', 'items' => []],
        ]);

        $nested = $field
            ->getFilledBlocks()
            ->first()
            ?->fields()
            ->onlyFields()
            ->findByColumn('items');

        self::assertInstanceOf(FlexibleLayouts::class, $nested);
        self::assertSame('content.section.items', $nested->getFlPath());
    }

    public function test_block_rejects_duplicate_normalized_names(): void
    {
        $field = FlexibleLayouts::make('Blocks', 'blocks')
            ->block('hero', 'Hero', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        $field->block('Hero', 'Another Hero', []);
    }

    public function test_block_rejects_limit_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be >= 1');

        FlexibleLayouts::make('Blocks', 'blocks')->block('hero', 'Hero', [], 0);
    }

    public function test_block_rejects_negative_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FlexibleLayouts::make('Blocks', 'blocks')->block('hero', 'Hero', [], -1);
    }

    public function test_block_accepts_positive_limit_and_unlimited(): void
    {
        $field = FlexibleLayouts::make('Blocks', 'blocks')
            ->block('hero', 'Hero', [], 1)
            ->block('text', 'Text Block', []);

        self::assertCount(2, $field->blocks());
        self::assertSame(1, $field->blocks()->findByName('hero')->limit());
        self::assertFalse($field->blocks()->findByName('text')->hasLimit());
    }
}
