<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit;

use MoonShine\Laravel\Collections\Fields;
use MoonShine\UI\Components\ActionButton;
use Povly\FlexibleLayouts\Blocks\Block;

final class BlockTest extends TestCase
{
    public function test_name_is_normalised_to_snake_case(): void
    {
        $block = new Block('Hero', '  My   Cool Type ', []);

        self::assertSame('my_cool_type', $block->name());
    }

    public function test_metadata_getters(): void
    {
        $block = new Block('Hero', 'hero', [], 3, 'Header', 'Big banner', 'photo');

        self::assertSame('Hero', $block->title());
        self::assertSame('hero', $block->name());
        self::assertSame('Header', $block->category());
        self::assertSame('Big banner', $block->description());
        self::assertSame('photo', $block->icon());
        self::assertTrue($block->hasLimit());
        self::assertSame(3, $block->limit());
    }

    public function test_no_limit_by_default(): void
    {
        $block = new Block('Hero', 'hero', []);

        self::assertFalse($block->hasLimit());
        self::assertNull($block->limit());
    }

    public function test_fields_converts_iterable_to_fields_collection(): void
    {
        $block = new Block('Hero', 'hero', []);

        self::assertInstanceOf(Fields::class, $block->fields());
    }

    public function test_set_fields_replaces_fields(): void
    {
        $block = new Block('Hero', 'hero', []);
        $block->setFields(['first' => []]);

        self::assertInstanceOf(Fields::class, $block->fields());
    }

    public function test_force_preview_is_fluent(): void
    {
        $block = new Block('Hero', 'hero', []);

        self::assertSame($block, $block->forcePreview());
    }

    public function test_remove_button_getter_and_fluent_setter(): void
    {
        $block = new Block('Hero', 'hero', []);
        $button = ActionButton::make('Delete');

        self::assertNull($block->getRemoveButton());
        self::assertSame($block, $block->removeButton($button));
        self::assertSame($button, $block->getRemoveButton());
        self::assertSame($block, $block->removeButton(null));
        self::assertNull($block->getRemoveButton());
    }

    public function test_duplicate_button_getter_and_fluent_setter(): void
    {
        $block = new Block('Hero', 'hero', []);
        $button = ActionButton::make('Duplicate');

        self::assertNull($block->getDuplicateButton());
        self::assertSame($block, $block->duplicateButton($button));
        self::assertSame($button, $block->getDuplicateButton());
        self::assertSame($block, $block->duplicateButton(null));
        self::assertNull($block->getDuplicateButton());
    }

    public function test_render_tab_content_without_buttons_has_no_header(): void
    {
        $block = new Block('Hero', 'hero', []);

        self::assertStringNotContainsString('_fl-block-header', $block->renderTabContent());
    }

    public function test_render_tab_content_renders_duplicate_after_remove(): void
    {
        $block = new Block('Hero', 'hero', []);
        $block->removeButton(ActionButton::make('Delete'));
        $block->duplicateButton(ActionButton::make('Duplicate'));

        $html = $block->renderTabContent();

        self::assertStringContainsString('_fl-block-header', $html);
        self::assertLessThan(
            strpos($html, 'Duplicate'),
            strpos($html, 'Delete'),
            'Duplicate button must render after the Delete button',
        );
    }
}
